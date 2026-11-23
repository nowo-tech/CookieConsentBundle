<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Provisioning;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Nowo\TransactionContract\TransientStorageFailure;
use Throwable;

use function hash;
use function hexdec;
use function substr;

/**
 * The bundle's provisioning lock (1.36, REQ-RUNTIME-003): serializes provisioning calls across processes, so two
 * seeders never both find "no default profile" and both create one. Stateless.
 *
 * - MySQL / MariaDB: a named session lock (`GET_LOCK()`, per database), taken before the call's transaction and
 *   released after its commit. Waits {@see MYSQL_WAIT_SECONDS} at most, then {@see TransientStorageFailure}.
 * - PostgreSQL: a transaction-level advisory lock (`pg_advisory_xact_lock()`), the first statement of the call's
 *   transaction, released by its outermost commit or rollback (inside a transaction of yours: by yours). The wait is
 *   bounded by `lock_timeout`.
 * - Other platforms (SQLite: one writer at a time): no lock.
 *
 * @internal
 */
final class ProvisioningLock
{
    /** Name of the lock (MySQL lock names are per server: the database name is appended as a hash). */
    public const NAME = 'nowo_cookie_consent.provisioning';

    /** Longest wait for the MySQL / MariaDB lock, in seconds. */
    public const MYSQL_WAIT_SECONDS = 10;

    /**
     * Takes the MySQL / MariaDB session lock (before the transaction); null on other platforms.
     *
     * @throws TransientStorageFailure When another provisioning call held the lock for the whole wait
     *
     * @return string|null The lock name to release, or null when no session lock was taken
     */
    public static function acquireSession(Connection $connection): ?string
    {
        if (!$connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            return null;
        }

        $name = self::NAME . ':' . substr(hash('sha256', (string) $connection->getDatabase()), 0, 16);
        if ((int) $connection->fetchOne('SELECT GET_LOCK(?, ?)', [$name, self::MYSQL_WAIT_SECONDS]) !== 1) {
            throw new TransientStorageFailure(1, 'Another cookie consent provisioning call holds the lock: retry later.');
        }

        return $name;
    }

    /**
     * Takes the PostgreSQL transaction-level lock (inside the transaction); nothing on other platforms.
     */
    public static function acquireTransaction(Connection $connection): void
    {
        if ($connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $connection->fetchOne('SELECT pg_advisory_xact_lock(?)', [self::postgresKey()]);
        }
    }

    /**
     * Releases the MySQL / MariaDB session lock. A failure is ignored: a lost connection released it already.
     */
    public static function release(Connection $connection, ?string $name): void
    {
        if ($name === null) {
            return;
        }

        try {
            $connection->fetchOne('SELECT RELEASE_LOCK(?)', [$name]);
        } catch (Throwable) {
            // The server releases a session lock when the session ends.
        }
    }

    /**
     * The 60-bit key of the PostgreSQL advisory lock (advisory locks are per database already).
     */
    public static function postgresKey(): int
    {
        return (int) hexdec(substr(hash('sha256', self::NAME), 0, 15));
    }
}
