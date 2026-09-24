<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Cookie;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\CookieConsentBundle\Clock\SystemClock;
use Nowo\CookieConsentBundle\Cookie\CookieLogger;
use Nowo\CookieConsentBundle\Entity\CookieConsentLog;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class CookieLoggerTest extends TestCase
{
    public function testThrowsWhenNoRequestAvailable(): void
    {
        $logger = new CookieLogger(
            $this->createMock(EntityManagerInterface::class),
            new RequestStack(),
            new SystemClock(),
        );

        $this->expectException(RuntimeException::class);
        $logger->log(['analytics' => true], 'key-1');
    }

    public function testPersistsNonRequiredCategoriesAndFlushes(): void
    {
        $request = Request::create('/', 'POST', [], [], [], ['REMOTE_ADDR' => '203.0.113.10']);
        $stack   = new RequestStack();
        $stack->push($request);

        $persisted     = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(static function (CookieConsentLog $log) use (&$persisted): void {
            $persisted[] = $log;
        });
        $entityManager->expects(self::once())->method('flush');

        $psrLogger = $this->createMock(LoggerInterface::class);
        $psrLogger->expects(self::once())->method('info')->with(
            'Cookie consent choices persisted.',
            self::callback(static fn (array $context): bool => $context['consent_key'] === 'consent-key'
                && $context['categories'] === ['analytics', 'marketing']
                && !isset($context['ip'])),
        );

        $logger = new CookieLogger($entityManager, $stack, new SystemClock(), $psrLogger);
        $logger->log([
            'required'  => true,
            'analytics' => true,
            'marketing' => 'true',
        ], 'consent-key');

        self::assertCount(2, $persisted);
        self::assertSame('203.0.113.xx', $persisted[0]->getIpAddress());
    }

    public function testUsesClockForTimestamp(): void
    {
        $now   = new DateTimeImmutable('2026-03-01 10:00:00');
        $clock = $this->createMock(ClockInterface::class);
        $clock->method('now')->willReturn($now);

        $request = Request::create('/', 'POST', [], [], [], ['REMOTE_ADDR' => '203.0.113.10']);
        $stack   = new RequestStack();
        $stack->push($request);

        $persisted     = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(static function (CookieConsentLog $log) use (&$persisted): void {
            $persisted[] = $log;
        });
        $entityManager->expects(self::once())->method('flush');

        $logger = new CookieLogger($entityManager, $stack, $clock);
        $logger->log(['analytics' => true], 'key');

        self::assertSame($now, $persisted[0]->getTimestamp());
    }

    public function testAnonymizeIpHandlesMissingClientIp(): void
    {
        $request = Request::create('/');
        $request->server->remove('REMOTE_ADDR');
        $stack = new RequestStack();
        $stack->push($request);

        $persisted     = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(static function (CookieConsentLog $log) use (&$persisted): void {
            $persisted[] = $log;
        });
        $entityManager->expects(self::once())->method('flush');

        $logger = new CookieLogger($entityManager, $stack, new SystemClock());
        $logger->log(['analytics' => false], 'key');

        self::assertSame('unknown', $persisted[0]->getIpAddress());
    }

    public function testAnonymizeIpReturnsUnchangedWhenNoDot(): void
    {
        $request = Request::create('/', 'POST', [], [], [], ['REMOTE_ADDR' => '2001:db8::1']);
        $stack   = new RequestStack();
        $stack->push($request);

        $persisted     = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(static function (CookieConsentLog $log) use (&$persisted): void {
            $persisted[] = $log;
        });
        $entityManager->expects(self::once())->method('flush');

        $logger = new CookieLogger($entityManager, $stack, new SystemClock());
        $logger->log(['analytics' => true], 'key');

        self::assertSame('2001:db8::1', $persisted[0]->getIpAddress());
    }

    public function testConsecutiveRequestsOnSameInstanceRecordTheirOwnIp(): void
    {
        $stack         = new RequestStack();
        $persisted     = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(static function (CookieConsentLog $log) use (&$persisted): void {
            $persisted[] = $log;
        });

        $logger = new CookieLogger($entityManager, $stack, new SystemClock());

        $stack->push(Request::create('/', 'POST', [], [], [], ['REMOTE_ADDR' => '203.0.113.10']));
        $logger->log(['analytics' => true], 'visitor-1');
        $stack->pop();

        $stack->push(Request::create('/', 'POST', [], [], [], ['REMOTE_ADDR' => '198.51.100.20']));
        $logger->log(['analytics' => false], 'visitor-2');

        self::assertCount(2, $persisted);
        self::assertSame('203.0.113.xx', $persisted[0]->getIpAddress());
        self::assertSame('visitor-1', $persisted[0]->getCookieConsentKey());
        self::assertSame('198.51.100.xx', $persisted[1]->getIpAddress());
        self::assertSame('visitor-2', $persisted[1]->getCookieConsentKey());
    }

    public function testInstanceBuiltBeforeAnyRequestLogsLaterRequests(): void
    {
        $stack         = new RequestStack();
        $persisted     = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(static function (CookieConsentLog $log) use (&$persisted): void {
            $persisted[] = $log;
        });

        $logger = new CookieLogger($entityManager, $stack, new SystemClock());

        $stack->push(Request::create('/', 'POST', [], [], [], ['REMOTE_ADDR' => '203.0.113.10']));
        $logger->log(['analytics' => true], 'key');

        self::assertCount(1, $persisted);
    }

    public function testDetachesOnlyTheEntitiesOfEachCallAfterFlush(): void
    {
        $stack = new RequestStack();
        $stack->push(Request::create('/', 'POST', [], [], [], ['REMOTE_ADDR' => '203.0.113.10']));

        $persisted     = [];
        $detached      = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(static function (CookieConsentLog $log) use (&$persisted): void {
            $persisted[] = $log;
        });
        $entityManager->method('detach')->willReturnCallback(static function (object $log) use (&$detached): void {
            $detached[] = $log;
        });

        $logger = new CookieLogger($entityManager, $stack, new SystemClock());
        $logger->log(['analytics' => true, 'marketing' => false], 'key-1');

        self::assertSame($persisted, $detached);

        $logger->log(['analytics' => true], 'key-2');

        self::assertCount(3, $detached);
        self::assertSame($persisted[2], $detached[2]);
    }

    public function testUsesOpenManagerFromRegistry(): void
    {
        $stack = new RequestStack();
        $stack->push(Request::create('/', 'POST', [], [], [], ['REMOTE_ADDR' => '203.0.113.10']));

        $fallback = $this->createMock(EntityManagerInterface::class);
        $fallback->expects(self::never())->method('persist');

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('isOpen')->willReturn(true);
        $manager->expects(self::once())->method('persist');
        $manager->expects(self::once())->method('flush');

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->with(CookieConsentLog::class)->willReturn($manager);
        $registry->expects(self::never())->method('resetManager');

        (new CookieLogger($fallback, $stack, new SystemClock(), null, $registry))->log(['analytics' => true], 'key');
    }

    public function testResetsClosedManagerBeforeLogging(): void
    {
        $stack = new RequestStack();
        $stack->push(Request::create('/', 'POST', [], [], [], ['REMOTE_ADDR' => '203.0.113.10']));

        $closed = $this->createMock(EntityManagerInterface::class);
        $closed->method('isOpen')->willReturn(false);
        $closed->expects(self::never())->method('flush');

        $fresh = $this->createMock(EntityManagerInterface::class);
        $fresh->method('isOpen')->willReturn(true);
        $fresh->expects(self::once())->method('persist');
        $fresh->expects(self::once())->method('flush');

        $other = $this->createMock(EntityManagerInterface::class);

        $managerForClass = $closed;
        $registry        = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturnCallback(static function () use (&$managerForClass): EntityManagerInterface {
            return $managerForClass;
        });
        $registry->method('getManagerNames')->willReturn(['other' => 'doctrine.orm.other_entity_manager', 'default' => 'doctrine.orm.default_entity_manager']);
        $registry->method('getManager')->willReturnCallback(static fn (?string $name): EntityManagerInterface => $name === 'default' ? $closed : $other);
        $registry->expects(self::once())->method('resetManager')->with('default')->willReturnCallback(
            static function () use (&$managerForClass, $fresh): EntityManagerInterface {
                $managerForClass = $fresh;

                return $fresh;
            },
        );

        (new CookieLogger($this->createMock(EntityManagerInterface::class), $stack, new SystemClock(), null, $registry))
            ->log(['analytics' => true], 'key');
    }

    public function testFallsBackToInjectedManagerWhenRegistryHasNoOrmManager(): void
    {
        $stack = new RequestStack();
        $stack->push(Request::create('/', 'POST', [], [], [], ['REMOTE_ADDR' => '203.0.113.10']));

        $fallback = $this->createMock(EntityManagerInterface::class);
        $fallback->expects(self::once())->method('flush');

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn(null);

        (new CookieLogger($fallback, $stack, new SystemClock(), null, $registry))->log(['analytics' => true], 'key');
    }

    public function testFallsBackToInjectedManagerWhenResetYieldsNoOrmManager(): void
    {
        $stack = new RequestStack();
        $stack->push(Request::create('/', 'POST', [], [], [], ['REMOTE_ADDR' => '203.0.113.10']));

        $fallback = $this->createMock(EntityManagerInterface::class);
        $fallback->expects(self::once())->method('flush');

        $closed = $this->createMock(EntityManagerInterface::class);
        $closed->method('isOpen')->willReturn(false);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturnOnConsecutiveCalls($closed, null, $closed, null, $closed, null);
        $registry->method('getManagerNames')->willReturn([]);
        $registry->expects(self::never())->method('resetManager');

        (new CookieLogger($fallback, $stack, new SystemClock(), null, $registry))->log(['analytics' => true], 'key');
    }

    public function testFailedFlushResetsClosedManagerAndRethrows(): void
    {
        $stack = new RequestStack();
        $stack->push(Request::create('/', 'POST', [], [], [], ['REMOTE_ADDR' => '203.0.113.10']));

        $isOpen  = true;
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('isOpen')->willReturnCallback(static function () use (&$isOpen): bool {
            return $isOpen;
        });
        $manager->method('flush')->willReturnCallback(static function () use (&$isOpen): never {
            $isOpen = false;

            throw new RuntimeException('Flush failed');
        });
        $manager->expects(self::never())->method('detach');

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($manager);
        $registry->method('getManagerNames')->willReturn(['default' => 'doctrine.orm.default_entity_manager']);
        $registry->method('getManager')->with('default')->willReturn($manager);
        $registry->expects(self::once())->method('resetManager')->with('default')->willReturnCallback(
            static function () use (&$isOpen, $manager): EntityManagerInterface {
                $isOpen = true;

                return $manager;
            },
        );

        $logger = new CookieLogger($this->createMock(EntityManagerInterface::class), $stack, new SystemClock(), null, $registry);

        try {
            $logger->log(['analytics' => true], 'key');
            self::fail('Expected the flush exception to be rethrown.');
        } catch (RuntimeException $exception) {
            self::assertSame('Flush failed', $exception->getMessage());
        }

        self::assertTrue($isOpen);
    }
}
