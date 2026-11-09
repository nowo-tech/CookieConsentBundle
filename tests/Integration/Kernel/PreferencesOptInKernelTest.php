<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Integration\Kernel;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieDefinition;
use Nowo\CookieConsentBundle\Enum\CookieNameEnum;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

use function json_decode;
use function libxml_use_internal_errors;

use const JSON_THROW_ON_ERROR;

/**
 * Owner decision JD-L6 (1.24): with a cookie inventory the preferences step must not open with optional categories or
 * cookies pre-ticked (CJEU Planet49). Through a real kernel: the definitions are stored without touching
 * `allowed_by_default` (entity default, and one row inserted without the column so the DB default applies), the
 * modal is rendered, and the form is posted back exactly as a browser would send it when the visitor presses Save
 * without touching anything. Only the required category may come back allowed.
 */
final class PreferencesOptInKernelTest extends TestCase
{
    private ?CookieConsentTestKernel $kernel = null;

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
    }

    public function testRenderedPreferencesStepHasNoOptionalToggleCheckedAndSaveRecordsOnlyNecessary(): void
    {
        $this->bootWithInventory(explicitTrue: false);

        $inputs = $this->renderConsentInputs();

        self::assertArrayHasKey('cookie_consent[required]', $inputs);
        self::assertTrue($inputs['cookie_consent[required]']['checked'], 'required stays on');
        self::assertTrue($inputs['cookie_consent[required]']['disabled'], 'required stays disabled');

        foreach (['cookie_consent[analytics]', 'cookie_consent[marketing]', 'cookie_consent[cookies][_ga]', 'cookie_consent[cookies][_fbp]', 'cookie_consent[cookies][_raw]'] as $name) {
            self::assertArrayHasKey($name, $inputs);
            self::assertFalse($inputs[$name]['checked'], $name . ' must not be pre-checked');
        }

        $cookies = $this->saveAsBrowser($inputs);

        self::assertSame('false', $cookies[CookieNameEnum::getCookieCategoryName('analytics')] ?? null);
        self::assertSame('false', $cookies[CookieNameEnum::getCookieCategoryName('marketing')] ?? null);
        $granular = json_decode($cookies[CookieNameEnum::COOKIE_CONSENT_GRANULAR_NAME] ?? '{}', true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($granular);
        self::assertNotContains(true, $granular, 'no optional cookie recorded as allowed');
    }

    public function testExplicitTrueIsStillHonoured(): void
    {
        $this->bootWithInventory(explicitTrue: true);

        $inputs = $this->renderConsentInputs();

        self::assertTrue($inputs['cookie_consent[cookies][_ga]']['checked']);
        self::assertTrue($inputs['cookie_consent[analytics]']['checked']);
        self::assertFalse($inputs['cookie_consent[cookies][_fbp]']['checked']);
        self::assertFalse($inputs['cookie_consent[marketing]']['checked']);
    }

    private function bootWithInventory(bool $explicitTrue): void
    {
        $this->kernel = new CookieConsentTestKernel(false, [
            'use_database_config'  => true,
            'use_cookie_inventory' => true,
            'csrf_protection'      => false,
            'categories'           => ['analytics', 'marketing'],
            'web_ui'               => ['enabled' => false],
        ]);
        $this->kernel->boot();

        $em = $this->container()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());

        $config = (new CookieConsentConfig())->setName('Site')->setEnabled(true)->setDefault(true)->setGranularCookieSelection(true);
        $em->persist($config);

        $definitions = [
            (new CookieDefinition())->setName('PHPSESSID')->setCategory('required')->setDuration('Session')->setSortOrder(0),
            (new CookieDefinition())->setName('_ga')->setCategory('analytics')->setDuration('2 years')->setSortOrder(1),
            (new CookieDefinition())->setName('_fbp')->setCategory('marketing')->setDuration('3 months')->setSortOrder(2),
        ];
        if ($explicitTrue) {
            $definitions[1]->setAllowedByDefault(true);
        }
        foreach ($definitions as $definition) {
            $config->addCookieDefinition($definition);
            $em->persist($definition);
        }
        $em->flush();

        if (!$explicitTrue) {
            // A row written without the column (e.g. by a host SQL seed): the column default applies.
            $table = $em->getClassMetadata(CookieDefinition::class)->getTableName();
            $em->getConnection()->executeStatement(
                "INSERT INTO {$table} (name, duration, category, type, sort_order, config_id) VALUES ('_raw', '1 day', 'marketing', 'first_party', 3, ?)",
                [$config->getId()],
            );
        }
        $em->clear();
    }

    /**
     * @return array<string, array{type: string, value: string, checked: bool, disabled: bool}>
     */
    private function renderConsentInputs(): array
    {
        $response = $this->kernel?->handle(Request::create('/cookie_consent'));
        self::assertNotNull($response);
        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>' . $response->getContent());
        libxml_use_internal_errors(false);

        $inputs = [];
        foreach ((new DOMXPath($dom))->query('//input[starts-with(@name, "cookie_consent[")]') ?: [] as $input) {
            self::assertInstanceOf(DOMElement::class, $input);
            $inputs[$input->getAttribute('name')] = [
                'type'     => $input->getAttribute('type'),
                'value'    => $input->getAttribute('value'),
                'checked'  => $input->hasAttribute('checked'),
                'disabled' => $input->hasAttribute('disabled'),
            ];
        }

        return $inputs;
    }

    /**
     * Posts what a browser submits for Save: checked, enabled checkboxes, hidden fields and the Save button.
     *
     * @param array<string, array{type: string, value: string, checked: bool, disabled: bool}> $inputs
     *
     * @return array<string, string> Response cookies by name
     */
    private function saveAsBrowser(array $inputs): array
    {
        $body = '';
        foreach ($inputs as $name => $input) {
            if ($input['disabled'] || ($input['type'] === 'checkbox' && !$input['checked'])) {
                continue;
            }
            $body .= rawurlencode($name) . '=' . rawurlencode($input['value']) . '&';
        }
        $body .= rawurlencode('cookie_consent[save]') . '=';
        parse_str($body, $post);

        $response = $this->kernel?->handle(Request::create('/cookie_consent', 'POST', $post));
        self::assertNotNull($response);

        $cookies = [];
        foreach ($response->headers->getCookies() as $cookie) {
            $cookies[$cookie->getName()] = (string) $cookie->getValue();
        }
        self::assertArrayHasKey(CookieNameEnum::COOKIE_CONSENT_NAME, $cookies, 'consent was saved');

        return $cookies;
    }

    private function container(): ContainerInterface
    {
        $container = $this->kernel?->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        return $container;
    }
}
