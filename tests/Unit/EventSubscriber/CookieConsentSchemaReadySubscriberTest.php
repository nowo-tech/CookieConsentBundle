<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\EventSubscriber;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Nowo\CookieConsentBundle\EventSubscriber\CookieConsentSchemaReadySubscriber;
use Nowo\CookieConsentBundle\Http\ColdStartRequestAttributes;
use Nowo\CookieConsentBundle\Tests\Unit\Support\MutableClock;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

use function is_bool;

final class CookieConsentSchemaReadySubscriberTest extends TestCase
{
    public function testMarksNotReadyWhenConfigTableMissing(): void
    {
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->expects(self::once())->method('tablesExist')->willReturn(false);

        $connection = $this->createStub(Connection::class);
        $connection->method('createSchemaManager')->willReturn($schemaManager);

        $subscriber = new CookieConsentSchemaReadySubscriber($connection);
        $request    = Request::create('/');
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
        $request    = Request::create('/');
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
        $request    = Request::create('/');
        $request->attributes->set(ColdStartRequestAttributes::SITE_BACKUP_SCHEMA_EXISTS, false);
        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        self::assertFalse($request->attributes->has(ColdStartRequestAttributes::COOKIE_CONSENT_SCHEMA_READY));
    }

    public function testPositiveProbeIsMemoizedAcrossRequestsUntilTtlExpires(): void
    {
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->expects(self::exactly(2))->method('tablesExist')->willReturn(true);
        $connection = $this->createStub(Connection::class);
        $connection->method('createSchemaManager')->willReturn($schemaManager);

        $clock      = $this->clock();
        $subscriber = new CookieConsentSchemaReadySubscriber($connection, $clock, 60);

        self::assertNull($this->dispatch($subscriber));
        $clock->advance(59);
        self::assertNull($this->dispatch($subscriber));
        $clock->advance(1);
        self::assertNull($this->dispatch($subscriber));
        $clock->advance(10);
        self::assertNull($this->dispatch($subscriber));
    }

    public function testMissingTableIsNeverMemoized(): void
    {
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->expects(self::exactly(3))->method('tablesExist')->willReturnOnConsecutiveCalls(false, false, true);
        $connection = $this->createStub(Connection::class);
        $connection->method('createSchemaManager')->willReturn($schemaManager);

        $subscriber = new CookieConsentSchemaReadySubscriber($connection, $this->clock());

        self::assertFalse($this->dispatch($subscriber));
        self::assertFalse($this->dispatch($subscriber));
        self::assertNull($this->dispatch($subscriber));
        self::assertNull($this->dispatch($subscriber));
    }

    public function testZeroTtlProbesOnEveryRequest(): void
    {
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->expects(self::exactly(2))->method('tablesExist')->willReturn(true);
        $connection = $this->createStub(Connection::class);
        $connection->method('createSchemaManager')->willReturn($schemaManager);

        $subscriber = new CookieConsentSchemaReadySubscriber($connection, $this->clock(), 0);

        self::assertNull($this->dispatch($subscriber));
        self::assertNull($this->dispatch($subscriber));
    }

    public function testClearSchemaReadyMemoForcesNewProbe(): void
    {
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->expects(self::exactly(2))->method('tablesExist')->willReturnOnConsecutiveCalls(true, false);
        $connection = $this->createStub(Connection::class);
        $connection->method('createSchemaManager')->willReturn($schemaManager);

        $subscriber = new CookieConsentSchemaReadySubscriber($connection);

        self::assertNull($this->dispatch($subscriber));
        $subscriber->clearSchemaReadyMemo();
        self::assertFalse($this->dispatch($subscriber));
    }

    public function testFallbackQueryWhenSchemaManagerFails(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('createSchemaManager')->willThrowException(new RuntimeException('no schema manager'));
        $connection->expects(self::once())->method('executeQuery');

        $subscriber = new CookieConsentSchemaReadySubscriber($connection, $this->clock());

        self::assertNull($this->dispatch($subscriber));
        self::assertNull($this->dispatch($subscriber));
    }

    public function testFallbackQueryFailureMarksNotReady(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('createSchemaManager')->willThrowException(new RuntimeException('no schema manager'));
        $connection->method('executeQuery')->willThrowException(new RuntimeException('no table'));

        self::assertFalse($this->dispatch(new CookieConsentSchemaReadySubscriber($connection, $this->clock())));
    }

    public function testSkipsSubRequestsAndPresetAttribute(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::never())->method('createSchemaManager');
        $subscriber = new CookieConsentSchemaReadySubscriber($connection, $this->clock());

        self::assertNull($this->dispatch($subscriber, HttpKernelInterface::SUB_REQUEST));
        self::assertTrue($this->dispatch($subscriber, attributes: [ColdStartRequestAttributes::COOKIE_CONSENT_SCHEMA_READY => true]));
    }

    /**
     * Returns the schema-ready attribute after dispatching (null when absent).
     *
     * @param array<string, mixed> $attributes
     */
    private function dispatch(
        CookieConsentSchemaReadySubscriber $subscriber,
        int $type = HttpKernelInterface::MAIN_REQUEST,
        array $attributes = [ColdStartRequestAttributes::SITE_BACKUP_SCHEMA_EXISTS => true],
    ): ?bool {
        $request = Request::create('/');
        foreach ($attributes as $name => $value) {
            $request->attributes->set($name, $value);
        }
        $subscriber->onKernelRequest(new RequestEvent($this->createStub(HttpKernelInterface::class), $request, $type));

        $value = $request->attributes->get(ColdStartRequestAttributes::COOKIE_CONSENT_SCHEMA_READY);

        return is_bool($value) ? $value : null;
    }

    private function clock(): MutableClock
    {
        return new MutableClock();
    }
}
