<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\DependencyInjection;

use Nowo\CookieConsentBundle\DependencyInjection\Compiler\CookieConsentWithoutDoctrinePass;
use Nowo\CookieConsentBundle\DependencyInjection\NowoCookieConsentExtension;
use Nowo\CookieConsentBundle\Doctrine\CookieConsentManagerRegistry;
use Nowo\CookieConsentBundle\PersonalData\CookieConsentIpWindowProviderInterface;
use Nowo\CookieConsentBundle\PersonalData\CookieConsentPersonalDataEraser;
use Nowo\CookieConsentBundle\PersonalData\CookieConsentPersonalDataEraserInterface;
use Nowo\CookieConsentBundle\PersonalData\CookieConsentPersonalDataExporter;
use Nowo\CookieConsentBundle\PersonalData\CookieConsentPersonalDataExporterInterface;
use Nowo\CookieConsentBundle\PersonalData\CookieConsentPersonalDataSubjectResolverInterface;
use Nowo\CookieConsentBundle\PersonalData\IpWindowSubjectResolver;
use Nowo\CookieConsentBundle\PersonalData\UserKit\CookieConsentLogsPersonalDataEraser;
use Nowo\CookieConsentBundle\PersonalData\UserKit\CookieConsentsPersonalDataExportProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class PersonalDataExtensionTest extends TestCase
{
    public function testPortsAndDefaultsAreRegistered(): void
    {
        $container = $this->load([]);

        self::assertSame(CookieConsentPersonalDataEraser::class, (string) $container->getAlias(CookieConsentPersonalDataEraserInterface::class));
        self::assertSame(CookieConsentPersonalDataExporter::class, (string) $container->getAlias(CookieConsentPersonalDataExporterInterface::class));
        self::assertSame(IpWindowSubjectResolver::class, (string) $container->getAlias(CookieConsentPersonalDataSubjectResolverInterface::class));
        self::assertTrue($container->getParameter('nowo_cookie_consent.personal_data.enabled'));
        self::assertSame('PT0S', $container->getParameter('nowo_cookie_consent.personal_data.ip_window_margin'));
        self::assertSame('off', $container->getParameter('nowo_cookie_consent.personal_data.ip_windows'));
        self::assertFalse($container->getDefinition(CookieConsentPersonalDataEraser::class)->getArgument('$eraseByIpWindow'));
        self::assertFalse($container->getDefinition(IpWindowSubjectResolver::class)->getArgument('$enabled'));
        self::assertSame(
            [NowoCookieConsentExtension::IP_WINDOW_PROVIDER_TAG => [[]]],
            $container->getAutoconfiguredInstanceof()[CookieConsentIpWindowProviderInterface::class]->getTags(),
        );
    }

    public function testUserKitBridgeIsRegisteredWithDoctrineAndUserKit(): void
    {
        $container = $this->load([], doctrine: true);

        self::assertSame([['priority' => 110]], $container->getDefinition(CookieConsentLogsPersonalDataEraser::class)->getTag('nowo_user_kit.personal_data.eraser'));
        self::assertFalse($container->getDefinition(CookieConsentLogsPersonalDataEraser::class)->getArgument('$eraseByIpWindow'));
        self::assertSame([['priority' => -50]], $container->getDefinition(CookieConsentsPersonalDataExportProvider::class)->getTag('nowo_user_kit.personal_data.export_provider'));
    }

    public function testUserKitBridgeIsNotRegisteredWhenDisabledOrWithoutDoctrine(): void
    {
        $disabled = $this->load(['personal_data' => ['enabled' => false]], doctrine: true);
        self::assertFalse($disabled->hasDefinition(CookieConsentLogsPersonalDataEraser::class));
        self::assertFalse($disabled->hasDefinition(CookieConsentsPersonalDataExportProvider::class));
        self::assertFalse($disabled->getParameter('nowo_cookie_consent.personal_data.enabled'));

        $withoutDoctrine = $this->load([]);
        self::assertFalse($withoutDoctrine->hasDefinition(CookieConsentLogsPersonalDataEraser::class));
    }

    public function testErasureByIpWindowIsOptIn(): void
    {
        $container = $this->load(['personal_data' => ['ip_windows' => 'erase']], doctrine: true);

        self::assertSame('erase', $container->getParameter('nowo_cookie_consent.personal_data.ip_windows'));
        self::assertTrue($container->getDefinition(CookieConsentPersonalDataEraser::class)->getArgument('$eraseByIpWindow'));
        self::assertTrue($container->getDefinition(IpWindowSubjectResolver::class)->getArgument('$enabled'));
        self::assertTrue($container->getDefinition(CookieConsentLogsPersonalDataEraser::class)->getArgument('$eraseByIpWindow'));
    }

    public function testRejectsAnUnknownIpWindowsMode(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->load(['personal_data' => ['ip_windows' => 'export']]);
    }

    public function testCustomSubjectResolverAndMargin(): void
    {
        $container = $this->load(['personal_data' => ['subject_resolver' => 'app.consent_subjects', 'ip_window_margin' => 'PT6H']]);

        self::assertSame('app.consent_subjects', (string) $container->getAlias(CookieConsentPersonalDataSubjectResolverInterface::class));
        self::assertSame('PT6H', $container->getParameter('nowo_cookie_consent.personal_data.ip_window_margin'));
    }

    public function testRejectsAnInvalidMargin(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('ip_window_margin');
        $this->load(['personal_data' => ['ip_window_margin' => 'one day']]);
    }

    public function testDedicatedEntityManagerWiresThePorts(): void
    {
        $container = $this->load(['doctrine' => ['entity_manager' => 'consent']]);

        foreach ([CookieConsentPersonalDataEraser::class, CookieConsentPersonalDataExporter::class] as $port) {
            $registry = $container->getDefinition($port)->getArgument('$managerRegistry');
            self::assertInstanceOf(Reference::class, $registry);
            self::assertSame(NowoCookieConsentExtension::MANAGER_REGISTRY_SERVICE, (string) $registry);
        }
        self::assertSame(CookieConsentManagerRegistry::class, $container->getDefinition(NowoCookieConsentExtension::MANAGER_REGISTRY_SERVICE)->getClass());
    }

    public function testWithoutDoctrineTheDefaultPortsAreRemovedButAHostPortStays(): void
    {
        $container = $this->load(['use_logger' => false]);
        (new CookieConsentWithoutDoctrinePass())->process($container);

        self::assertFalse($container->hasDefinition(CookieConsentPersonalDataEraser::class));
        self::assertFalse($container->hasAlias(CookieConsentPersonalDataEraserInterface::class));
        self::assertFalse($container->hasAlias(CookieConsentPersonalDataExporterInterface::class));

        $host = $this->load(['use_logger' => false]);
        $host->setAlias(CookieConsentPersonalDataEraserInterface::class, 'app.consent_eraser');
        (new CookieConsentWithoutDoctrinePass())->process($host);
        self::assertSame('app.consent_eraser', (string) $host->getAlias(CookieConsentPersonalDataEraserInterface::class));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config, bool $doctrine = false): ContainerBuilder
    {
        $container = new ContainerBuilder();
        if ($doctrine) {
            $container->setParameter('kernel.bundles', ['DoctrineBundle' => 'Doctrine\\Bundle\\DoctrineBundle\\DoctrineBundle']);
        }
        (new NowoCookieConsentExtension())->load([$config], $container);

        return $container;
    }
}
