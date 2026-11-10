<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Cookie;

use DateTimeImmutable;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Id\AbstractIdGenerator;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\CookieConsentBundle\Clock\SystemClock;
use Nowo\CookieConsentBundle\Cookie\CookieLogger;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieConsentLog;
use Nowo\CookieConsentBundle\Tests\Support\SqliteEntityManagerFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Throwable;

final class CookieLoggerTest extends TestCase
{
    private EntityManager $entityManager;

    protected function setUp(): void
    {
        $this->entityManager = SqliteEntityManagerFactory::create();
    }

    public function testThrowsWhenNoRequestAvailable(): void
    {
        $logger = new CookieLogger($this->entityManager, new RequestStack(), new SystemClock());

        $this->expectException(RuntimeException::class);
        $logger->log(['analytics' => true], 'key-1');
    }

    public function testInsertsNonRequiredCategories(): void
    {
        $psrLogger = $this->createMock(LoggerInterface::class);
        $psrLogger->expects(self::once())->method('info')->with(
            'Cookie consent choices persisted.',
            self::callback(static fn (array $context): bool => $context['consent_key'] === 'consent-key'
                && $context['categories'] === ['analytics', 'marketing', 'other']
                && !isset($context['ip'])),
        );

        $logger = new CookieLogger($this->entityManager, $this->stack('203.0.113.10'), new SystemClock(), $psrLogger);
        $logger->log([
            'required'  => true,
            'analytics' => true,
            'marketing' => 'true',
            'other'     => 'false',
        ], 'consent-key');

        $rows = $this->rows();
        self::assertCount(3, $rows);
        self::assertSame(['analytics', 'marketing', 'other'], array_column($rows, 'cookie_name'));
        self::assertSame([1, 1, 0], array_map(intval(...), array_column($rows, 'cookie_value')));
        self::assertSame('203.0.113.xx', $rows[0]['ip_address']);
        self::assertSame('consent-key', $rows[0]['cookie_consent_key']);
        self::assertNull($rows[0]['consent_revision']);
        self::assertNotNull($rows[0]['id']);
    }

    public function testOnlyRequiredCategoryWritesNothing(): void
    {
        (new CookieLogger($this->entityManager, $this->stack('203.0.113.10'), new SystemClock()))
            ->log(['required' => true], 'key');

        self::assertSame([], $this->rows());
    }

    public function testUsesClockForTimestamp(): void
    {
        $clock = $this->createMock(ClockInterface::class);
        $clock->method('now')->willReturn(new DateTimeImmutable('2026-03-01 10:00:00'));

        (new CookieLogger($this->entityManager, $this->stack('203.0.113.10'), $clock))->log(['analytics' => true], 'key');

        self::assertSame('2026-03-01 10:00:00', $this->rows()[0]['timestamp']);
    }

    public function testAnonymizeIpHandlesMissingClientIp(): void
    {
        $request = Request::create('/');
        $request->server->remove('REMOTE_ADDR');
        $stack = new RequestStack();
        $stack->push($request);

        (new CookieLogger($this->entityManager, $stack, new SystemClock()))->log(['analytics' => false], 'key');

        self::assertSame('unknown', $this->rows()[0]['ip_address']);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function ipProvider(): iterable
    {
        yield 'ipv6 is truncated' => ['2001:db8:85a3:1234:5678:8a2e:370:7334', '2001:db8:85a3::'];
        yield 'ipv6 short form' => ['2001:db8::1', '2001:db8::'];
        yield 'ipv4-mapped ipv6' => ['::ffff:192.0.2.128', '::ffff:192.0.2.xxx'];
        yield 'ipv4' => ['198.51.100.7', '198.51.100.x'];
        yield 'invalid' => ['not-an-ip', 'unknown'];
    }

    #[DataProvider('ipProvider')]
    public function testAnonymizesIp(string $clientIp, string $expected): void
    {
        (new CookieLogger($this->entityManager, $this->stack($clientIp), new SystemClock()))->log(['analytics' => true], 'key');

        self::assertSame($expected, $this->rows()[0]['ip_address']);
    }

    public function testConsecutiveRequestsOnSameInstanceRecordTheirOwnIp(): void
    {
        $stack  = new RequestStack();
        $logger = new CookieLogger($this->entityManager, $stack, new SystemClock());

        $stack->push(Request::create('/', 'POST', [], [], [], ['REMOTE_ADDR' => '203.0.113.10']));
        $logger->log(['analytics' => true], 'visitor-1');
        $stack->pop();

        $stack->push(Request::create('/', 'POST', [], [], [], ['REMOTE_ADDR' => '198.51.100.20']));
        $logger->log(['analytics' => false], 'visitor-2');

        $rows = $this->rows();
        self::assertCount(2, $rows);
        self::assertSame(['203.0.113.xx', 'visitor-1'], [$rows[0]['ip_address'], $rows[0]['cookie_consent_key']]);
        self::assertSame(['198.51.100.xx', 'visitor-2'], [$rows[1]['ip_address'], $rows[1]['cookie_consent_key']]);
    }

    public function testDoesNotFlushPendingHostChangesNorManageTheLogRows(): void
    {
        $managed = (new CookieConsentConfig())->setName('Managed');
        $this->entityManager->persist($managed);
        $this->entityManager->flush();

        $managed->setName('Changed by the host, not flushed yet');
        $pending = (new CookieConsentConfig())->setName('Pending host insert');
        $this->entityManager->persist($pending);

        (new CookieLogger($this->entityManager, $this->stack('203.0.113.10'), new SystemClock()))->log(['analytics' => true], 'key');

        self::assertCount(1, $this->rows());
        self::assertSame(
            ['Managed'],
            $this->entityManager->getConnection()->fetchFirstColumn('SELECT name FROM dashboard_cookie_config'),
            'A consent POST must not commit pending host changes.',
        );

        $unitOfWork = $this->entityManager->getUnitOfWork();
        self::assertTrue($unitOfWork->isScheduledForInsert($pending));
        self::assertArrayNotHasKey(CookieConsentLog::class, $unitOfWork->getIdentityMap(), 'Log rows are not managed.');

        $this->entityManager->flush();

        self::assertCount(2, $this->entityManager->getConnection()->fetchFirstColumn('SELECT name FROM dashboard_cookie_config'));
    }

    public function testUsesTheManagerOfTheLogEntityFromTheRegistry(): void
    {
        $fallback = $this->createMock(EntityManagerInterface::class);
        $fallback->expects(self::never())->method('getConnection');

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->with(CookieConsentLog::class)->willReturn($this->entityManager);
        $registry->expects(self::never())->method('resetManager');

        (new CookieLogger($fallback, $this->stack('203.0.113.10'), new SystemClock(), null, $registry))->log(['analytics' => true], 'key');

        self::assertCount(1, $this->rows());
    }

    public function testFallsBackToInjectedManagerWhenRegistryHasNoOrmManager(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn(null);

        (new CookieLogger($this->entityManager, $this->stack('203.0.113.10'), new SystemClock(), null, $registry))
            ->log(['analytics' => true], 'key');

        self::assertCount(1, $this->rows());
    }

    public function testClosedManagerIsNeitherNeededNorReset(): void
    {
        $this->entityManager->close();

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->entityManager);
        $registry->expects(self::never())->method('resetManager');

        (new CookieLogger($this->createMock(EntityManagerInterface::class), $this->stack('203.0.113.10'), new SystemClock(), null, $registry))
            ->log(['analytics' => true], 'key');

        self::assertCount(1, $this->rows());
    }

    public function testFailedInsertIsRethrownAndNothingStaysQueued(): void
    {
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement('ALTER TABLE dashboard_cookie_log RENAME TO dashboard_cookie_log_off');

        $logger = new CookieLogger($this->entityManager, $this->stack('203.0.113.10'), new SystemClock());

        try {
            $logger->log(['analytics' => true, 'marketing' => true], 'key-1');
            self::fail('Expected the insert exception to be rethrown.');
        } catch (Throwable $exception) {
            self::assertInstanceOf(DbalException::class, $exception);
        }

        self::assertFalse($connection->isTransactionActive());
        $connection->executeStatement('ALTER TABLE dashboard_cookie_log_off RENAME TO dashboard_cookie_log');

        $logger->log(['analytics' => false], 'key-2');

        self::assertSame(['key-2'], array_column($this->rows(), 'cookie_consent_key'));
    }

    public function testJoinsAnOpenHostTransaction(): void
    {
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        (new CookieLogger($this->entityManager, $this->stack('203.0.113.10'), new SystemClock()))->log(['analytics' => true], 'key');

        self::assertTrue($connection->isTransactionActive());
        $connection->rollBack();

        self::assertSame([], $this->rows());
    }

    public function testPassesTheIdOfAPreInsertGenerator(): void
    {
        $metadata              = $this->entityManager->getClassMetadata(CookieConsentLog::class);
        $metadata->idGenerator = new class extends AbstractIdGenerator {
            public function generateId(EntityManagerInterface $em, ?object $entity): int
            {
                return 4242;
            }
        };

        (new CookieLogger($this->entityManager, $this->stack('203.0.113.10'), new SystemClock()))->log(['analytics' => true], 'key');

        self::assertSame(4242, (int) $this->rows()[0]['id']);
    }

    /**
     * Worker mode without kernel reset: one logger instance serves many requests of two visitors; memory stays flat,
     * nothing is kept in the unit of work and no row carries the other visitor's data.
     */
    public function testManyRequestsOfTwoVisitorsKeepMemoryFlatAndDoNotLeakState(): void
    {
        $stack  = new RequestStack();
        $logger = new CookieLogger($this->entityManager, $stack, new SystemClock());
        $visit  = static function (int $i) use ($stack, $logger): void {
            $stack->push(Request::create('/', 'POST', [], [], [], ['REMOTE_ADDR' => $i % 2 === 0 ? '203.0.113.10' : '198.51.100.20']));
            $logger->log(['analytics' => $i % 2 === 0, 'marketing' => true], $i % 2 === 0 ? 'visitor-a' : 'visitor-b');
            $stack->pop();
        };

        for ($i = 0; $i < 200; ++$i) {
            $visit($i);
        }
        gc_collect_cycles();
        $baseline = memory_get_usage();

        for ($i = 200; $i < 2200; ++$i) {
            $visit($i);
        }
        gc_collect_cycles();

        self::assertLessThan(256 * 1024, memory_get_usage() - $baseline, 'memory grows with the number of requests');
        self::assertSame([], $this->entityManager->getUnitOfWork()->getIdentityMap());

        $mixed = $this->entityManager->getConnection()->fetchOne(
            "SELECT COUNT(*) FROM dashboard_cookie_log WHERE (cookie_consent_key = 'visitor-a' AND ip_address <> '203.0.113.xx')"
            . " OR (cookie_consent_key = 'visitor-b' AND ip_address <> '198.51.100.xx')"
            . " OR (cookie_consent_key = 'visitor-b' AND cookie_name = 'analytics' AND cookie_value = 1)",
        );
        self::assertSame(0, (int) $mixed);
        self::assertSame(4400, (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM dashboard_cookie_log'));
    }

    private function stack(string $clientIp): RequestStack
    {
        $stack = new RequestStack();
        $stack->push(Request::create('/', 'POST', [], [], [], ['REMOTE_ADDR' => $clientIp]));

        return $stack;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->entityManager->getConnection()->fetchAllAssociative('SELECT * FROM dashboard_cookie_log ORDER BY id');

        return $rows;
    }
}
