<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\EventSubscriber;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Nowo\CookieConsentBundle\EventSubscriber\CookieConsentSchemaReadySubscriber;
use Nowo\CookieConsentBundle\Http\ColdStartRequestAttributes;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class CookieConsentSchemaReadySubscriberTest extends TestCase
{
    public function testMarksNotReadyWhenConfigTableMissing(): void
    {
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->expects(self::once())->method('tablesExist')->willReturn(false);

        $connection = $this->createStub(Connection::class);
        $connection->method('createSchemaManager')->willReturn($schemaManager);

        $subscriber = new CookieConsentSchemaReadySubscriber($connection);
        $request = Request::create('/');
        $request->attributes->set(ColdStartRequestAttributes::SITE_BACKUP_SCHEMA_EXISTS, true);
        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        self::assertFalse($request->attributes->get(ColdStartRequestAttributes::COOKIE_CONSENT_SCHEMA_READY));
    }

    public function testLeavesReadyWhenConfigTableExists(): void
    {
        $schemaManager = $this->createStub(AbstractSchemaManager::class);
        $schemaManager->method('tablesExist')->willReturn(true);

        $connection = $this->createStub(Connection::class);
        $connection->method('createSchemaManager')->willReturn($schemaManager);

        $subscriber = new CookieConsentSchemaReadySubscriber($connection);
        $request = Request::create('/');
        $request->attributes->set(ColdStartRequestAttributes::SITE_BACKUP_SCHEMA_EXISTS, true);
        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        self::assertFalse($request->attributes->has(ColdStartRequestAttributes::COOKIE_CONSENT_SCHEMA_READY));
    }

    public function testSkipsWhenSiteBackupAlreadyCold(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::never())->method('createSchemaManager');

        $subscriber = new CookieConsentSchemaReadySubscriber($connection);
        $request = Request::create('/');
        $request->attributes->set(ColdStartRequestAttributes::SITE_BACKUP_SCHEMA_EXISTS, false);
        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        self::assertFalse($request->attributes->has(ColdStartRequestAttributes::COOKIE_CONSENT_SCHEMA_READY));
    }
}
