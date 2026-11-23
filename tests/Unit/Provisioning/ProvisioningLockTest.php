<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Provisioning;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Nowo\CookieConsentBundle\Provisioning\ProvisioningLock;
use Nowo\TransactionContract\TransientStorageFailure;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function str_starts_with;
use function strlen;

final class ProvisioningLockTest extends TestCase
{
    public function testMySqlTakesAPerDatabaseSessionLockAndReleasesIt(): void
    {
        $connection = $this->connection(new MySQL80Platform());
        $connection->method('getDatabase')->willReturn('app');
        $calls = [];
        $connection->method('fetchOne')->willReturnCallback(static function (string $sql, array $params) use (&$calls): int {
            $calls[] = [$sql, $params];

            return 1;
        });

        $name = ProvisioningLock::acquireSession($connection);
        self::assertIsString($name);
        self::assertTrue(str_starts_with($name, ProvisioningLock::NAME . ':'));
        self::assertLessThanOrEqual(64, strlen($name), 'MySQL lock names are 64 characters at most');
        ProvisioningLock::acquireTransaction($connection);
        ProvisioningLock::release($connection, $name);

        self::assertSame([
            ['SELECT GET_LOCK(?, ?)', [$name, ProvisioningLock::MYSQL_WAIT_SECONDS]],
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

    public function testAFailedReleaseIsIgnored(): void
    {
        $connection = $this->connection(new MySQL80Platform());
        $connection->method('fetchOne')->willThrowException(new RuntimeException('gone away'));

        ProvisioningLock::release($connection, 'name');
        ProvisioningLock::release($connection, null);
        $this->addToAssertionCount(1);
    }

    public function testPostgreSqlTakesATransactionLock(): void
    {
        $connection = $this->connection(new PostgreSQLPlatform());
        $connection->expects(self::once())->method('fetchOne')->with('SELECT pg_advisory_xact_lock(?)', [ProvisioningLock::postgresKey()]);

        self::assertNull(ProvisioningLock::acquireSession($connection));
        ProvisioningLock::acquireTransaction($connection);
        self::assertGreaterThan(0, ProvisioningLock::postgresKey());
    }

    public function testSqliteTakesNoLock(): void
    {
        $connection = $this->connection(new SQLitePlatform());
        $connection->expects(self::never())->method('fetchOne');

        self::assertNull(ProvisioningLock::acquireSession($connection));
        ProvisioningLock::acquireTransaction($connection);
    }

    /**
     * @return Connection&MockObject
     */
    private function connection(AbstractPlatform $platform): Connection
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform);

        return $connection;
    }
}
