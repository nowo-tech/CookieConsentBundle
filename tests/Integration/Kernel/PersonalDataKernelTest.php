<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Integration\Kernel;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Nowo\CookieConsentBundle\PersonalData\CookieConsentPersonalDataErasedEvent;
use Nowo\CookieConsentBundle\PersonalData\CookieConsentPersonalDataEraserInterface;
use Nowo\CookieConsentBundle\PersonalData\CookieConsentPersonalDataExporterInterface;
use Nowo\CookieConsentBundle\PersonalData\CookieConsentPersonalDataSubject;
use Nowo\CookieConsentBundle\PersonalData\CookieConsentPersonalDataSubjectResolverInterface;
use Nowo\CookieConsentBundle\PersonalData\IpWindowSubjectResolver;
use Nowo\CookieConsentBundle\PersonalData\UserKit\CookieConsentLogsPersonalDataEraser;
use Nowo\CookieConsentBundle\PersonalData\UserKit\CookieConsentsPersonalDataExportProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * The GDPR ports and the UserKit bridge compiled by a real kernel (standard host: DoctrineBundle, UserKit installed).
 */
final class PersonalDataKernelTest extends TestCase
{
    private const PUBLIC_IDS = [
        CookieConsentPersonalDataEraserInterface::class,
        CookieConsentPersonalDataExporterInterface::class,
        CookieConsentPersonalDataSubjectResolverInterface::class,
        CookieConsentLogsPersonalDataEraser::class,
        CookieConsentsPersonalDataExportProvider::class,
    ];

    private ?CookieConsentTestKernel $kernel = null;

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
    }

    public function testThePortsEraseAndExportThroughTheKernelServices(): void
    {
        $container = $this->boot([]);

        $em = $container->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());
        $em->getConnection()->insert('dashboard_cookie_log', [
            'ip_address'   => '198.51.100.xx', 'cookie_consent_key' => 'kernel-key', 'cookie_name' => 'analytics',
            'cookie_value' => true, 'timestamp' => new DateTimeImmutable('2026-01-01 10:00:00', new DateTimeZone('UTC')),
        ], ['cookie_value' => Types::BOOLEAN, 'timestamp' => Types::DATETIME_IMMUTABLE]);

        $dispatcher = $container->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
        $events = [];
        $dispatcher->addListener(CookieConsentPersonalDataErasedEvent::class, static function (CookieConsentPersonalDataErasedEvent $event) use (&$events): void {
            $events[] = $event;
        });

        $exporter = $container->get(CookieConsentPersonalDataExporterInterface::class);
        $eraser   = $container->get(CookieConsentPersonalDataEraserInterface::class);
        self::assertInstanceOf(CookieConsentPersonalDataExporterInterface::class, $exporter);
        self::assertInstanceOf(CookieConsentPersonalDataEraserInterface::class, $eraser);
        $subject = CookieConsentPersonalDataSubject::fromConsentKey('kernel-key');

        self::assertCount(1, $exporter->export($subject)['decisions']);
        self::assertSame(1, $eraser->erase($subject)->consentLogs);
        self::assertCount(1, $events);
        self::assertSame(['consent_logs' => 1], $events[0]->counts);

        self::assertInstanceOf(IpWindowSubjectResolver::class, $container->get(CookieConsentPersonalDataSubjectResolverInterface::class));
        self::assertSame([], $container->get(CookieConsentPersonalDataSubjectResolverInterface::class)->subjectsFor(new stdClass(), 'alice'));
        self::assertInstanceOf(CookieConsentLogsPersonalDataEraser::class, $container->get(CookieConsentLogsPersonalDataEraser::class));
        self::assertInstanceOf(CookieConsentsPersonalDataExportProvider::class, $container->get(CookieConsentsPersonalDataExportProvider::class));
    }

    public function testDisabledBridgeIsNotInTheContainer(): void
    {
        $container = $this->boot(['personal_data' => ['enabled' => false]]);

        self::assertFalse($container->has(CookieConsentLogsPersonalDataEraser::class));
        self::assertFalse($container->has(CookieConsentsPersonalDataExportProvider::class));
        self::assertTrue($container->has(CookieConsentPersonalDataEraserInterface::class), 'the ports stay');
    }

    /**
     * @param array<string, mixed> $personalData
     */
    private function boot(array $personalData): ContainerInterface
    {
        $this->kernel = new CookieConsentTestKernel(false, ['security' => ['allow_unauthenticated' => true]] + $personalData, publicIds: self::PUBLIC_IDS);
        $this->kernel->boot();
        $container = $this->kernel->getContainer();

        return $container;
    }
}
