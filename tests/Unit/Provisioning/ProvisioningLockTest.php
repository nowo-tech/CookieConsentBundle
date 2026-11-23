<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Provisioning;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use LogicException;
use Nowo\CookieConsentBundle\Provisioning\ProvisioningLock;
use Nowo\TransactionContract\TransientStorageFailure;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function array_shift;
use function str_starts_with;
use function strlen;

final class ProvisioningLockTest extends TestCase
{
    public function testMySqlTakesAPerDatabaseSessionLockAndReleasesIt(): void
    {
        $connection = $this->connection(new MySQL80Platform());
        $connection->method('getDatabase')->willReturn('app');
        $calls = [];
        $connection->method('fetchOne')->willReturnCallback(static function (string $sql, array $params = []) use (&$calls): int {
            $calls[] = [$sql, $params];

            return 1;
        });

        $name = ProvisioningLock::acquireSession($connection);
        self::assertIsString($name);
        self::assertTrue(str_starts_with($name, ProvisioningLock::NAME . ':'));
        self::assertLessThanOrEqual(64, strlen($name), 'MySQL lock names are 64 characters at most');
        ProvisioningLock::acquireTransaction($connection, $name);
        ProvisioningLock::release($connection, $name);

        self::assertSame([
            ['SELECT GET_LOCK(?, ?)', [$name, (float) ProvisioningLock::WAIT_SECONDS]],
            ['SELECT RELEASE_LOCK(?)', [$name]],
        ], $calls);
    }

    public function testMariaDbLockTimeoutIsATransientFailure(): void
    {
        $connection = $this->connection(new MariaDBPlatform());
        $connection->method('fetchOne')->willReturn(0);

        $this->expectException(TransientStorageFailure::class);
        ProvisioningLock::acquireSession($connection);
    }

    public function testMySqlRefusesToRunInsideATransactionOfTheCaller(): void
    {
        $connection = $this->connection(new MySQL80Platform(), 1);
        $connection->expects(self::never())->method('fetchOne');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('outside your transaction');
        ProvisioningLock::acquireSession($connection);
    }

    public function testAFailedReleaseIsIgnored(): void
    {
        $connection = $this->connection(new MySQL80Platform());
        $connection->method('fetchOne')->willThrowException(new RuntimeException('gone away'));

        ProvisioningLock::release($connection, 'name');
        ProvisioningLock::release($connection, null);
        $this->addToAssertionCount(1);
    }

    /**
     * 1.36.1 (audit ZAH-11): no session lock on PostgreSQL. The call's own transaction is set to READ COMMITTED before
     * any query, then the transaction-level lock is polled (released by the COMMIT / ROLLBACK, pooler-safe).
     */
    public function testPostgreSqlPollsATransactionLockInTheCallsOwnReadCommittedTransaction(): void
    {
        $connection = $this->connection(new PostgreSQLPlatform(), 1);
        $statements = [];
        $connection->method('executeStatement')->willReturnCallback(static function (string $sql) use (&$statements): int {
            $statements[] = $sql;

            return 0;
        });
        $answers = [false, 'f', true];
        $calls   = [];
        $connection->method('fetchOne')->willReturnCallback(static function (string $sql) use (&$calls, &$answers): mixed {
            $calls[] = $sql;

            return array_shift($answers);
        });

        self::assertNull(ProvisioningLock::acquireSession($connection));
        ProvisioningLock::acquireTransaction($connection, null);
        ProvisioningLock::release($connection, null);

        self::assertSame(['SET TRANSACTION ISOLATION LEVEL READ COMMITTED'], $statements);
        self::assertSame(['SELECT pg_try_advisory_xact_lock(?)', 'SELECT pg_try_advisory_xact_lock(?)', 'SELECT pg_try_advisory_xact_lock(?)'], $calls);
        self::assertGreaterThan(0, ProvisioningLock::postgresKey());
    }

    public function testPostgreSqlInsideAReadCommittedTransactionTakesATransactionLock(): void
    {
        $connection = $this->connection(new PostgreSQLPlatform(), 2);
        $connection->expects(self::never())->method('executeStatement');
        $answers = ['read committed', 1];
        $connection->method('fetchOne')->willReturnCallback(static function () use (&$answers): mixed {
            return array_shift($answers);
        });

        self::assertNull(ProvisioningLock::acquireSession($connection));
        ProvisioningLock::acquireTransaction($connection, null);
        self::assertSame([], $answers);
    }

    public function testPostgreSqlInsideARepeatableReadTransactionIsRefused(): void
    {
        $connection = $this->connection(new PostgreSQLPlatform(), 2);
        $connection->method('fetchOne')->willReturn('repeatable read');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('READ COMMITTED');
        ProvisioningLock::acquireTransaction($connection, null);
    }

    public function testPostgreSqlGivesUpAfterTheWait(): void
    {
        $connection = $this->connection(new PostgreSQLPlatform(), 1);
        $connection->method('fetchOne')->willReturn('0');

        $this->expectException(TransientStorageFailure::class);
        ProvisioningLock::acquireTransaction($connection, null, 0.25);
    }

    public function testSqliteTakesNoLock(): void
    {
        $connection = $this->connection(new SQLitePlatform());
        $connection->expects(self::never())->method('fetchOne');

        self::assertNull(ProvisioningLock::acquireSession($connection));
        ProvisioningLock::acquireTransaction($connection, null);
    }

    /**
     * @return Connection&MockObject
     */
    private function connection(AbstractPlatform $platform, int $level = 0): Connection
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform);
        $connection->method('getTransactionNestingLevel')->willReturn($level);

        return $connection;
    }
}
