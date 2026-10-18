<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Integration\Kernel;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieDefinition;
use Nowo\CookieConsentBundle\Entity\CookieDefinitionTranslation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function array_filter;
use function array_merge;
use function array_unique;
use function array_values;
use function bin2hex;
use function random_bytes;
use function sprintf;
use function sys_get_temp_dir;

/**
 * The admin pages through a real kernel (FrameworkBundle, Twig, Doctrine on SQLite, UiKit, FormKit): UiKit layout by
 * default (no CDN asset, canonical page markers), the web_ui.layout_template / web_ui.templates overrides and the
 * deprecated web_ui.chrome map bridged into the UiKit theme (REQ-UI-003).
 */
final class AdminPagesKernelTest extends TestCase
{
    private ?CookieConsentTestKernel $kernel = null;

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
        (new Filesystem())->remove(sys_get_temp_dir() . '/nowo_cookie_consent_kernel');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function pages(): iterable
    {
        yield 'cookie definitions' => ['/cookie-consent-config/%d/cookies', 'list'];
        yield 'new cookie definition' => ['/cookie-consent-config/%d/cookies/new', 'form'];
        yield 'edit cookie definition' => ['/cookie-consent-config/%d/cookies/%d/edit', 'form'];
        yield 'settings (profile)' => ['/cookie-consent-config/%d/settings/profile', 'settings'];
        yield 'settings (appearance)' => ['/cookie-consent-config/%d/settings/appearance', 'settings'];
    }

    #[DataProvider('pages')]
    public function testPagesRenderThroughTheUiKitLayout(string $path, string $pageType): void
    {
        [$configId, $definitionId] = $this->boot([]);

        $response = $this->get(sprintf($path, $configId, $definitionId));
        $html     = (string) $response->getContent();

        self::assertSame(200, $response->getStatusCode(), $html);
        self::assertStringContainsString('<html', $html, 'UiKit document layout');
        self::assertStringContainsString('data-nowo-ui-page="' . $pageType . '"', $html);
        self::assertStringContainsString('data-nowo-ui-bundle="NowoCookieConsentBundle"', $html);
        self::assertMatchesRegularExpression('#<div class="nowo-ui-page-header__nav">\s*<nav [^>]*data-turbo="false"[^>]*data-nowo-ui-region="area-nav"#', $html, 'area nav inside the UiKit page header');
        self::assertStringNotContainsString('cdn.jsdelivr.net', $html);
        self::assertDoesNotMatchRegularExpression('/\son[a-z]+="/i', $html);
        self::assertDoesNotMatchRegularExpression('/\b[a-z_]+(\.[a-z_]+)+\.(label|help|placeholder|title)\b/', $html);
    }

    public function testListShowsTheProfileInventory(): void
    {
        [$configId] = $this->boot([]);

        $html = (string) $this->get(sprintf('/cookie-consent-config/%d/cookies', $configId))->getContent();

        self::assertStringContainsString('<code>_ga</code>', $html);
        self::assertStringContainsString('Google', $html);
        self::assertStringContainsString('Cookies declared for this consent profile and shown in the preferences modal.', $html);
        self::assertStringContainsString('data-nowo-ui-confirm', $html);
        self::assertMatchesRegularExpression('/name="delete_cookie_definition_\d+\[_token\]"/', $html);
    }

    /** Invalid (here: no CSRF token) submissions re-render the form page with 422. */
    public function testInvalidSubmitReturns422(): void
    {
        [$configId] = $this->boot([]);

        $response = $this->kernel()->handle(Request::create(sprintf('/cookie-consent-config/%d/cookies/new', $configId), 'POST', ['cookie_definition' => ['name' => '_gid', 'duration' => '1 day', 'category' => 'analytics', 'type' => 'first_party', 'sortOrder' => '0']]));

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode(), (string) $response->getContent());
        self::assertStringContainsString('data-nowo-ui-page="form"', (string) $response->getContent());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function emptyRequiredFields(): iterable
    {
        yield 'empty name' => [['name' => ''], 'cookie_definition[name]'];
        yield 'missing name' => [['name' => null], 'cookie_definition[name]'];
        yield 'empty category' => [['category' => ''], 'cookie_definition[category]'];
        yield 'empty party' => [['type' => ''], 'cookie_definition[type]'];
        yield 'empty translation locale' => [['translations' => [['locale' => '', 'provider' => '', 'purpose' => '']]], 'cookie_definition[translations][0][locale]'];
    }

    /**
     * Regression: empty required fields reached the non-nullable entity setters (setName(null) TypeError, 500).
     * They are validation errors now: 422, the form re-rendered with the error, nothing persisted.
     *
     * @param array<string, mixed> $override
     */
    #[DataProvider('emptyRequiredFields')]
    public function testEmptyRequiredFieldIsAValidationError(array $override, string $field): void
    {
        [$configId] = $this->boot([]);
        $browser    = $this->browser();
        $crawler    = $browser->request('GET', sprintf('/cookie-consent-config/%d/cookies/new', $configId));
        $token      = (string) $crawler->filter('input[name="cookie_definition[_token]"]')->attr('value');

        $data = array_merge([
            'name'         => '_gid',
            'duration'     => '1 day',
            'category'     => 'analytics',
            'type'         => 'first_party',
            'sortOrder'    => '',
            'translations' => [['locale' => 'en', 'provider' => '', 'purpose' => '']],
            '_token'       => $token,
        ], $override);
        $data = array_filter($data, static fn (mixed $value): bool => $value !== null);

        $crawler = $browser->request('POST', sprintf('/cookie-consent-config/%d/cookies/new', $configId), ['cookie_definition' => $data]);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        self::assertGreaterThan(0, $crawler->filter('[name="' . $field . '"]')->closest('div')?->filter('ul li, .invalid-feedback, [id$="_errors"]')->count() ?? 0, 'error rendered next to ' . $field);
        self::assertSame(1, $this->definitionCount(), 'nothing persisted');
    }

    public function testValidSubmissionWithOptionalFieldsEmptyIsSaved(): void
    {
        [$configId] = $this->boot([]);
        $browser    = $this->browser();
        $crawler    = $browser->request('GET', sprintf('/cookie-consent-config/%d/cookies/new', $configId));

        $browser->submit($crawler->filter('form[name="cookie_definition"]')->form(), [
            'cookie_definition[name]'                      => '_gid',
            'cookie_definition[duration]'                  => '',
            'cookie_definition[sortOrder]'                 => '',
            'cookie_definition[translations][0][provider]' => '',
            'cookie_definition[translations][0][purpose]'  => '',
        ]);

        self::assertSame(Response::HTTP_FOUND, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        self::assertSame(2, $this->definitionCount());
    }

    /**
     * Regression: every row delete form was named delete_cookie_definition, so the hidden _token inputs shared one
     * DOM id. Each row form has its own name now (UiKit 2.1 prints the hidden fields of action forms without an id),
     * and posting it still passes the CSRF check.
     */
    public function testRowDeleteFormsHaveUniqueIdsAndStillDelete(): void
    {
        [$configId] = $this->boot([]);
        $this->addDefinition('_gid');
        $browser = $this->browser();
        $crawler = $browser->request('GET', sprintf('/cookie-consent-config/%d/cookies', $configId));

        $names = $crawler->filter('dialog form input[type="hidden"]')->each(static fn ($input): string => (string) $input->attr('name'));
        self::assertCount(2, $names);
        self::assertSame($names, array_values(array_unique($names)));
        $ids = array_values(array_filter($crawler->filter('[id]')->each(static fn ($node): string => (string) $node->attr('id'))));
        self::assertSame($ids, array_values(array_unique($ids)), 'no duplicate DOM ids');
        self::assertCount(0, $crawler->filter('form[name="delete_cookie_definition"]'), 'one form name per row');

        $browser->submit($crawler->filter('dialog form')->first()->form());
        self::assertSame(Response::HTTP_FOUND, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        self::assertSame(1, $this->definitionCount());
    }

    public function testLayoutAndPageTemplateOverrides(): void
    {
        [$configId] = $this->boot([
            'layout_template' => '@NowoUiKit/layout/guest.html.twig',
            'templates'       => ['settings' => '@NowoCookieConsentBundle/admin/_admin_area_nav.html.twig'],
        ]);

        $list = (string) $this->get(sprintf('/cookie-consent-config/%d/cookies', $configId))->getContent();
        self::assertStringContainsString('nowo-ui-page--guest', $list);

        // web_ui.templates.settings renders the settings page (here: just the deprecated area navigation partial,
        // fed by the UiKit area-nav registry through CookieConsentAreaNavProvider).
        $settings = (string) $this->get(sprintf('/cookie-consent-config/%d/settings/profile', $configId))->getContent();
        self::assertStringContainsString('data-nowo-ui-region="area-nav"', $settings);
        self::assertStringContainsString('data-turbo="false"', $settings);
        self::assertMatchesRegularExpression(sprintf('#href="/cookie-consent-config/%d/settings"[^>]*aria-current="page"#', $configId), $settings);
        self::assertStringNotContainsString('data-nowo-ui-page="settings"', $settings);
    }

    public function testDeprecatedChromeMapIsBridgedIntoTheUiKitTheme(): void
    {
        [$configId] = $this->boot(['chrome' => [
            'classes'    => ['table' => 'host-table', 'button_primary' => 'host-btn'],
            'attributes' => ['table_panel' => ['data-testid' => 'cookie-consent-definitions-table']],
        ]]);

        $html = (string) $this->get(sprintf('/cookie-consent-config/%d/cookies', $configId))->getContent();

        self::assertMatchesRegularExpression('/<table class="nowo-ui-table nowo-ui-table--striped host-table[ "]/', $html, 'host classes replace the framework defaults');
        self::assertStringContainsString('data-testid="cookie-consent-definitions-table"', $html);
        self::assertStringContainsString('host-btn', $html);
    }

    /**
     * @param array<string, mixed> $webUi
     *
     * @return array{int, int}
     */
    private function boot(array $webUi): array
    {
        $this->kernel = new CookieConsentTestKernel(false, [
            'web_ui'   => ['enabled' => true] + $webUi,
            'security' => ['allow_unauthenticated' => true],
        ], bin2hex(random_bytes(6)));
        $this->kernel->boot();

        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);
        $em = $container->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());

        $config     = (new CookieConsentConfig())->setName('Public site');
        $definition = (new CookieDefinition())->setName('_ga')->setCategory('analytics')->setDuration('2 years')
            ->addTranslation((new CookieDefinitionTranslation())->setLocale('en')->setProvider('Google')->setPurpose('Analytics'));
        $config->addCookieDefinition($definition);
        $em->persist($config);
        $em->persist($definition);
        $em->flush();

        return [(int) $config->getId(), (int) $definition->getId()];
    }

    /** Browser on the booted kernel; no reboot between requests (the SQLite database is in memory). */
    private function browser(): KernelBrowser
    {
        $browser = new KernelBrowser($this->kernel());
        $browser->disableReboot();

        return $browser;
    }

    private function definitionCount(): int
    {
        $container = $this->kernel()->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);
        $em = $container->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $em->clear();

        return $em->getRepository(CookieDefinition::class)->count([]);
    }

    private function addDefinition(string $name): void
    {
        $container = $this->kernel()->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);
        $em = $container->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $config = $em->getRepository(CookieConsentConfig::class)->findOneBy([]);
        self::assertInstanceOf(CookieConsentConfig::class, $config);
        $definition = (new CookieDefinition())->setName($name)->setCategory('analytics')->setConfig($config);
        $em->persist($definition);
        $em->flush();
    }

    private function get(string $path): Response
    {
        return $this->kernel()->handle(Request::create($path));
    }

    private function kernel(): CookieConsentTestKernel
    {
        self::assertNotNull($this->kernel);

        return $this->kernel;
    }
}
