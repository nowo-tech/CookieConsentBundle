<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Doctrine;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use Nowo\CookieConsentBundle\Doctrine\CookieConsentManagerRegistry;
use Nowo\CookieConsentBundle\Entity\CookieConsentLog;
use PHPUnit\Framework\TestCase;
use stdClass;

final class CookieConsentManagerRegistryTest extends TestCase
{
    public function testBundleEntitiesAndTheirProxiesResolveToTheConfiguredManager(): void
    {
        $consent = $this->createMock(ObjectManager::class);
        $inner   = $this->createMock(ManagerRegistry::class);
        $inner->method('getManager')->with('cookie_consent')->willReturn($consent);
        $inner->expects(self::never())->method('getManagerForClass');

        $registry = new CookieConsentManagerRegistry($inner, 'cookie_consent');

        self::assertSame($consent, $registry->getManagerForClass(CookieConsentLog::class));
        /** @var class-string $proxy */
        $proxy = implode('\\', ['Proxies', '__CG__', CookieConsentLog::class]);
        self::assertSame($consent, $registry->getManagerForClass($proxy));
    }

    public function testOtherClassesAndCallsAreDelegated(): void
    {
        $host       = $this->createMock(ObjectManager::class);
        $repository = $this->createMock(ObjectRepository::class);
        $connection = new stdClass();
        $inner      = $this->createMock(ManagerRegistry::class);
        $inner->method('getManagerForClass')->with(stdClass::class)->willReturn($host);
        $inner->method('getRepository')->willReturnMap([
            [CookieConsentLog::class, 'cookie_consent', $repository],
            [CookieConsentLog::class, 'other', $repository],
        ]);
        $inner->method('getDefaultConnectionName')->willReturn('default');
        $inner->method('getConnection')->with('consent')->willReturn($connection);
        $inner->method('getConnections')->willReturn(['default' => $connection]);
        $inner->method('getConnectionNames')->willReturn(['default' => 'doctrine.dbal.default_connection']);
        $inner->method('getDefaultManagerName')->willReturn('default');
        $inner->method('getManager')->with(null)->willReturn($host);
        $inner->method('getManagers')->willReturn(['default' => $host]);
        $inner->method('resetManager')->with('default')->willReturn($host);
        $inner->method('getManagerNames')->willReturn(['default' => 'doctrine.orm.default_entity_manager']);

        $registry = new CookieConsentManagerRegistry($inner, 'cookie_consent');

        self::assertSame($host, $registry->getManagerForClass(stdClass::class));
        self::assertSame($repository, $registry->getRepository(CookieConsentLog::class));
        self::assertSame($repository, $registry->getRepository(CookieConsentLog::class, 'other'));
        self::assertSame('default', $registry->getDefaultConnectionName());
        self::assertSame($connection, $registry->getConnection('consent'));
        self::assertSame(['default' => $connection], $registry->getConnections());
        self::assertSame(['default' => 'doctrine.dbal.default_connection'], $registry->getConnectionNames());
        self::assertSame('default', $registry->getDefaultManagerName());
        self::assertSame($host, $registry->getManager());
        self::assertSame(['default' => $host], $registry->getManagers());
        self::assertSame($host, $registry->resetManager('default'));
        self::assertSame(['default' => 'doctrine.orm.default_entity_manager'], $registry->getManagerNames());
    }
}
