<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Integration\Kernel;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

use function libxml_use_internal_errors;

/**
 * Audit JH-M1 / JH-I2 (1.24): `allowed_by_default` (and its alias `allowedByDefault`) are accepted in the YAML
 * `cookie_inventory` of the bundle configuration, drive the rendered pre-ticked state, and a value that is not a
 * boolean fails the container build instead of silently becoming false.
 */
final class YamlInventoryAllowedByDefaultKernelTest extends TestCase
{
    private ?CookieConsentTestKernel $kernel = null;

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
    }

    public function testBothSpellingsAreAcceptedAndDriveTheRenderedState(): void
    {
        $this->boot([
            ['name' => 'PHPSESSID', 'category' => 'required'],
            ['name' => '_ga', 'category' => 'analytics', 'allowed_by_default' => true],
            ['name' => '_gid', 'category' => 'analytics', 'allowedByDefault' => 'yes'],
            ['name' => '_fbp', 'category' => 'marketing'],
            ['name' => '_gcl', 'category' => 'marketing', 'allowedByDefault' => false],
            ['name' => 'lang', 'category' => 'preferences', 'allowed_by_default' => 'off'],
        ]);

        $checked = $this->renderCheckedState();

        self::assertTrue($checked['cookie_consent[required]']);
        self::assertTrue($checked['cookie_consent[cookies][_ga]']);
        self::assertTrue($checked['cookie_consent[cookies][_gid]']);
        self::assertTrue($checked['cookie_consent[analytics]']);
        self::assertFalse($checked['cookie_consent[cookies][_fbp]']);
        self::assertFalse($checked['cookie_consent[cookies][_gcl]']);
        self::assertFalse($checked['cookie_consent[marketing]']);
        self::assertFalse($checked['cookie_consent[cookies][lang]']);
        self::assertFalse($checked['cookie_consent[preferences]']);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function badValues(): iterable
    {
        yield 'typo' => ['ture'];
        yield 'integer 2' => [2];
        yield 'spanish' => ['si'];
    }

    #[DataProvider('badValues')]
    public function testANonBooleanValueFailsTheBuild(mixed $value): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('allowed_by_default must be a boolean');

        $this->boot([['name' => '_ga', 'category' => 'analytics', 'allowed_by_default' => $value]]);
    }

    public function testABadAliasValueFailsTheBuildToo(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->boot([['name' => '_ga', 'category' => 'analytics', 'allowedByDefault' => 'enabled']]);
    }

    /**
     * @param list<array<string, mixed>> $inventory
     */
    private function boot(array $inventory): void
    {
        $this->kernel = new CookieConsentTestKernel(false, [
            'use_database_config'  => true,
            'use_cookie_inventory' => true,
            'csrf_protection'      => false,
            'categories'           => ['analytics', 'marketing', 'preferences'],
            'web_ui'               => ['enabled' => false],
            'cookie_inventory'     => $inventory,
        ]);
        $this->kernel->boot();

        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);
        $em = $container->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());

        // A profile without definition rows: the YAML inventory applies.
        $em->persist((new CookieConsentConfig())->setName('Site')->setEnabled(true)->setDefault(true)->setGranularCookieSelection(true));
        $em->flush();
        $em->clear();
    }

    /**
     * @return array<string, bool>
     */
    private function renderCheckedState(): array
    {
        $response = $this->kernel?->handle(Request::create('/cookie_consent'));
        self::assertNotNull($response);
        self::assertSame(200, $response->getStatusCode());

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>' . $response->getContent());
        libxml_use_internal_errors(false);

        $checked = [];
        foreach ((new DOMXPath($dom))->query('//input[@type="checkbox"][starts-with(@name, "cookie_consent[")]') ?: [] as $input) {
            self::assertInstanceOf(DOMElement::class, $input);
            $checked[$input->getAttribute('name')] = $input->hasAttribute('checked');
        }

        return $checked;
    }
}
