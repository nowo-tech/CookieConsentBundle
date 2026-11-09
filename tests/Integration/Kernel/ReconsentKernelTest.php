<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Integration\Kernel;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Nowo\CookieConsentBundle\Cookie\CookieChecker;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieConsentLog;
use Nowo\CookieConsentBundle\Enum\CookieNameEnum;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

use function json_decode;
use function libxml_use_internal_errors;
use function sprintf;
use function strlen;
use function substr;

use const JSON_THROW_ON_ERROR;

/**
 * Owner decision JH-L1 (1.25) through a real kernel (Doctrine on SQLite, Twig, the bundle admin with CSRF): the admin
 * changes the revision and answers the re-consent question; visitors whose consent predates a "yes" get the banner
 * again with only necessary cookies applying, answering again stores a fresh consent, and legacy cookies are kept.
 */
final class ReconsentKernelTest extends TestCase
{
    /** A consent saved by 1.24 (ISO UTC) long before any re-consent request. */
    private const OLD_CONSENT = '2024-05-01T08:00:00Z';

    private ?CookieConsentTestKernel $kernel = null;

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
    }

    public function testLegacyConsentIsNotAskedAgainAfterUpgrade(): void
    {
        $this->boot();

        [$html, $request, $response] = $this->visit($this->oldCookies());

        self::assertSame('false', $this->modalAttribute($html, 'data-nowo-open'));
        self::assertNull($this->modalAttribute($html, 'data-nowo-reconsent-required-at'));
        self::assertTrue($this->checker($request)->isCategoryAllowedByUser('analytics'));
        self::assertSame([], $response->headers->getCookies(), 'nothing expired');
    }

    public function testRevisionChangeAnsweredNoKeepsConsentsAndUpdatesTheLabel(): void
    {
        $configId = $this->boot();

        $this->saveBehavior($configId, '2', 'no');

        $profile = $this->profile();
        self::assertSame(2, $profile->getRevision());
        self::assertNull($profile->getReconsentRequiredAt());

        [$html, $request] = $this->visit($this->oldCookies());
        self::assertSame('false', $this->modalAttribute($html, 'data-nowo-open'));
        self::assertSame('2', $this->modalAttribute($html, 'data-nowo-revision'));
        self::assertTrue($this->checker($request)->isCategoryAllowedByUser('analytics'));
    }

    public function testRevisionChangeWithoutAnswerIsRefused(): void
    {
        $configId = $this->boot();

        $browser = $this->saveBehavior($configId, '2', null);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $browser->getResponse()->getStatusCode());
        self::assertStringContainsString('You changed the revision: choose whether visitors must consent again.', (string) $browser->getResponse()->getContent());
        self::assertSame(1, $this->profile()->getRevision(), 'nothing saved');
        self::assertNull($this->profile()->getReconsentRequiredAt());
    }

    public function testCsrfIsStillRequired(): void
    {
        $configId = $this->boot();

        $browser = $this->saveBehavior($configId, '2', 'yes', 'forged-token');

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $browser->getResponse()->getStatusCode());
        self::assertNull($this->profile()->getReconsentRequiredAt());
        self::assertSame(1, $this->profile()->getRevision());
    }

    public function testAdminPermissionsStillApply(): void
    {
        $configId = $this->boot(secured: true);

        $response = $this->kernel()->handle(Request::create(sprintf('/cookie-consent-config/%d/settings/behavior', $configId), 'POST', [
            'cookie_consent_config_behavior_settings' => ['revision' => '2', 'reconsent' => 'yes'],
        ]));

        self::assertContains($response->getStatusCode(), [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN]);
        self::assertNull($this->profile()->getReconsentRequiredAt());
        self::assertSame(1, $this->profile()->getRevision());
    }

    public function testRevisionChangeAnsweredYesAsksOldConsentsAgainUntilTheVisitorAnswers(): void
    {
        $configId = $this->boot(logger: true);

        $this->saveBehavior($configId, '2', 'yes');

        $profile = $this->profile();
        self::assertSame(2, $profile->getRevision());
        self::assertNotNull($profile->getReconsentRequiredAt());

        // 1. Old consent: banner again, nothing optional applies or is pre-ticked, browser cookies expired.
        [$html, $request, $response] = $this->visit($this->oldCookies());
        self::assertSame('true', $this->modalAttribute($html, 'data-nowo-open'));
        self::assertNotNull($this->modalAttribute($html, 'data-nowo-reconsent-required-at'));
        $checker = $this->checker($request);
        self::assertTrue($checker->isConsentOutdated());
        self::assertFalse($checker->isCookieConsentSavedByUser());
        self::assertFalse($checker->isCategoryAllowedByUser('analytics'));
        self::assertFalse($checker->isCategoryAllowedByUser('marketing'));
        self::assertTrue($checker->isCookieAllowedByUser('PHPSESSID', 'required'));
        self::assertFalse($this->checkbox($html, 'cookie_consent[analytics]'), 'previous choice is not pre-ticked');

        $expired = [];
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->isCleared()) {
                $expired[] = $cookie->getName();
            }
        }
        self::assertContains(CookieNameEnum::COOKIE_CONSENT_NAME, $expired);
        self::assertContains(CookieNameEnum::getCookieCategoryName('analytics'), $expired);
        self::assertNotContains(CookieNameEnum::COOKIE_CONSENT_KEY_NAME, $expired, 'key kept for the consent log');

        // 2. The visitor answers (reject optional): a fresh consent is stored, nothing is expired.
        $answer = $this->kernel()->handle(Request::create('/cookie_consent', 'POST', [
            'cookie_consent' => ['use_only_functional_cookies' => ''],
        ], $this->oldCookies()));
        $stored = [];
        foreach ($answer->headers->getCookies() as $cookie) {
            self::assertFalse($cookie->isCleared(), $cookie->getName() . ' not expired on the answer');
            $stored[$cookie->getName()] = (string) $cookie->getValue();
        }
        self::assertArrayHasKey(CookieNameEnum::COOKIE_CONSENT_NAME, $stored);
        self::assertSame('false', $stored[CookieNameEnum::getCookieCategoryName('analytics')]);
        self::assertGreaterThanOrEqual($profile->getReconsentRequiredAt()->getTimestamp(), (new DateTimeImmutable($stored[CookieNameEnum::COOKIE_CONSENT_NAME]))->getTimestamp());

        // 3. With the new cookies the banner stays closed and the new choice applies.
        $fresh = [
            CookieNameEnum::COOKIE_CONSENT_NAME                => $stored[CookieNameEnum::COOKIE_CONSENT_NAME],
            CookieNameEnum::COOKIE_CONSENT_KEY_NAME            => 'visitor-key',
            CookieNameEnum::getCookieCategoryName('analytics') => $stored[CookieNameEnum::getCookieCategoryName('analytics')],
        ];
        [$html, $request, $response] = $this->visit($fresh);
        self::assertSame('false', $this->modalAttribute($html, 'data-nowo-open'));
        self::assertTrue($this->checker($request)->isCookieConsentSavedByUser());
        self::assertSame([], $response->headers->getCookies());

        // 4. The consent log records the profile revision the visitor answered.
        $em = $this->em();
        $em->clear();
        $logs = $em->getRepository(CookieConsentLog::class)->findBy(['cookieConsentKey' => 'visitor-key']);
        self::assertNotEmpty($logs);
        foreach ($logs as $log) {
            self::assertSame(2, $log->getConsentRevision());
        }
    }

    public function testRemoteConfigApiExposesTheRequest(): void
    {
        $configId = $this->boot();
        $this->saveBehavior($configId, '2', 'yes');

        $response = $this->kernel()->handle(Request::create('/cookie-consent/config'));
        $payload  = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($payload);
        self::assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ$/', (string) $payload['data']['reconsentRequiredAt']);
        self::assertSame([], $response->headers->getCookies(), 'public API response never carries Set-Cookie');
    }

    private function boot(bool $logger = false, bool $secured = false): int
    {
        $this->kernel = new CookieConsentTestKernel($secured, [
            'use_database_config'  => true,
            'fetch_config_via_api' => true,
            'csrf_protection'      => false,
            'use_logger'           => $logger,
            'categories'           => ['analytics', 'marketing'],
            'web_ui'               => ['enabled' => true],
            'security'             => $secured ? ['access_roles' => ['ROLE_ADMIN']] : ['allow_unauthenticated' => true],
        ]);
        $this->kernel->boot();

        $em = $this->em();
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());

        $config = (new CookieConsentConfig())->setName('Site')->setEnabled(true)->setDefault(true)->setRevision(1);
        $em->persist($config);
        $em->flush();

        return (int) $config->getId();
    }

    /**
     * Submits the behavior settings section as a browser would (CSRF token from the rendered form).
     */
    private function saveBehavior(int $configId, string $revision, ?string $reconsent, ?string $csrfToken = null): KernelBrowser
    {
        $browser = $this->browser();
        $crawler = $browser->request('GET', sprintf('/cookie-consent-config/%d/settings/behavior', $configId));
        self::assertSame(200, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());

        $revisionInput = $crawler->filter('input[name$="[revision]"]');
        self::assertCount(1, $revisionInput);
        $name = (string) $revisionInput->attr('name');
        $base = substr($name, 0, -strlen('[revision]'));

        $radios = $crawler->filter(sprintf('input[type="radio"][name="%s[reconsent]"]', $base));
        self::assertCount(2, $radios, 'yes/no question rendered');
        foreach ($radios as $radio) {
            self::assertInstanceOf(DOMElement::class, $radio);
            self::assertFalse($radio->hasAttribute('checked'), 'no pre-selected answer');
        }

        $form = $revisionInput->closest('form')?->form();
        self::assertNotNull($form);
        $form[$name] = $revision;
        if ($reconsent !== null) {
            $field = $form[$base . '[reconsent]'];
            self::assertInstanceOf(ChoiceFormField::class, $field);
            $field->select($reconsent);
        }
        if ($csrfToken !== null) {
            $form[$base . '[_token]'] = $csrfToken;
        }
        $browser->submit($form);

        if ($reconsent !== null && $csrfToken === null) {
            self::assertSame(302, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        }

        $this->em()->clear();

        return $browser;
    }

    /**
     * @param array<string, string> $cookies
     *
     * @return array{string, Request, Response}
     */
    private function visit(array $cookies): array
    {
        $request  = Request::create('/cookie_consent', 'GET', [], $cookies);
        $response = $this->kernel()->handle($request);
        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        return [(string) $response->getContent(), $request, $response];
    }

    /**
     * @return array<string, string>
     */
    private function oldCookies(): array
    {
        return [
            CookieNameEnum::COOKIE_CONSENT_NAME                => self::OLD_CONSENT,
            CookieNameEnum::COOKIE_CONSENT_KEY_NAME            => 'visitor-key',
            CookieNameEnum::getCookieCategoryName('analytics') => 'true',
            CookieNameEnum::getCookieCategoryName('marketing') => 'true',
        ];
    }

    /** Checker state for a handled request (its resolved profile is on the request attributes). */
    private function checker(Request $request): CookieChecker
    {
        $stack = new RequestStack();
        $stack->push($request);

        return new CookieChecker($stack);
    }

    private function modalAttribute(string $html, string $attribute): ?string
    {
        $nodes = (new DOMXPath($this->dom($html)))->query('//*[@id="cookieconsent"]');
        $modal = $nodes === false ? null : $nodes->item(0);
        self::assertInstanceOf(DOMElement::class, $modal);

        return $modal->hasAttribute($attribute) ? $modal->getAttribute($attribute) : null;
    }

    private function checkbox(string $html, string $name): bool
    {
        $nodes = (new DOMXPath($this->dom($html)))->query(sprintf('//input[@name="%s"]', $name));
        $input = $nodes === false ? null : $nodes->item(0);
        self::assertInstanceOf(DOMElement::class, $input);

        return $input->hasAttribute('checked');
    }

    private function dom(string $html): DOMDocument
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>' . $html);
        libxml_use_internal_errors(false);

        return $dom;
    }

    private function profile(): CookieConsentConfig
    {
        $em = $this->em();
        $em->clear();
        $profile = $em->getRepository(CookieConsentConfig::class)->findOneBy([]);
        self::assertInstanceOf(CookieConsentConfig::class, $profile);

        return $profile;
    }

    private function browser(): KernelBrowser
    {
        $browser = new KernelBrowser($this->kernel());
        $browser->disableReboot();

        return $browser;
    }

    private function em(): EntityManagerInterface
    {
        $container = $this->kernel()->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);
        $em = $container->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        return $em;
    }

    private function kernel(): CookieConsentTestKernel
    {
        self::assertNotNull($this->kernel);

        return $this->kernel;
    }
}
