<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Cookie;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
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
            self::callback(static function (array $context): bool {
                return $context['consent_key'] === 'consent-key'
                    && $context['categories'] === ['analytics', 'marketing']
                    && !isset($context['ip']);
            }),
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
}
