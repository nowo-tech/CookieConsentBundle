<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Cookie;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\CookieConsentBundle\Cookie\CookieLogger;
use Nowo\CookieConsentBundle\Entity\CookieConsentLog;
use PHPUnit\Framework\TestCase;
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

        $logger = new CookieLogger($entityManager, $stack);
        $logger->log([
            'required'  => true,
            'analytics' => true,
            'marketing' => 'true',
        ], 'consent-key');

        self::assertCount(2, $persisted);
        self::assertSame('203.0.113.xx', $persisted[0]->getIpAddress());
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

        $logger = new CookieLogger($entityManager, $stack);
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

        $logger = new CookieLogger($entityManager, $stack);
        $logger->log(['analytics' => true], 'key');

        self::assertSame('2001:db8::1', $persisted[0]->getIpAddress());
    }
}
