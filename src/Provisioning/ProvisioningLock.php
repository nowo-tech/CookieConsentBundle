<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Provisioning;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use LogicException;
use Nowo\TransactionContract\TransientStorageFailure;
use Throwable;

use function hash;
use function hexdec;
use function hrtime;
use function in_array;
use function strtolower;
use function substr;
use function trim;
use function usleep;

/**
 * The bundle's provisioning lock (1.36, REQ-RUNTIME-003): serializes provisioning calls across processes, so two
 * seeders never both find "no default profile" and both create one. Stateless. Every wait is bounded by
 * {@see WAIT_SECONDS}, then {@see TransientStorageFailure} (retried by the provisioner).
 *
 * Outside a transaction (the normal case: a console seeder) the lock is a SESSION lock taken before the call's
 * transaction begins and released after its commit or rollback, so the transaction's snapshot is always taken after
 * the lock (correct under READ COMMITTED, REPEATABLE READ and SERIALIZABLE):
 *
 * - MySQL / MariaDB: `GET_LOCK()` (per database);
 * - PostgreSQL: `pg_try_advisory_lock()`, polled (never an unbounded wait, whatever the server's `lock_timeout`).
 *
 * Inside a transaction of the caller:
 *
 * - PostgreSQL: a transaction-level lock (`pg_try_advisory_xact_lock()`, polled), held until the caller's commit;
 *   only under READ COMMITTED (a REPEATABLE READ / SERIALIZABLE snapshot may predate the other seeder's commit), any
 *   other isolation level is refused with {@see LogicException};
 * - MySQL / MariaDB: refused with {@see LogicException} (a session lock would be released before the caller's commit);
 * - other platforms (SQLite: one writer at a time): no lock.
 *
 * @internal
 */
final class ProvisioningLock
{
    /** Name of the lock (MySQL lock names are per server: the database name is appended as a hash). */
    public const NAME = 'nowo_cookie_consent.provisioning';

    /** Longest wait for the lock, in seconds (every platform). */
    public const WAIT_SECONDS = 10;

    /** Pause between two PostgreSQL attempts, in microseconds. */
    private const POLL_MICROSECONDS = 100_000;

    /**
     * Takes the session lock before the call's transaction (outside a transaction of the caller).
     *
     * @param float $waitSeconds Longest wait (tests shorten it)
     *
     * @throws TransientStorageFailure When another provisioning call held the lock for the whole wait
     * @throws LogicException On MySQL / MariaDB inside a transaction of the caller
     *
     * @return string|null What {@see release()} needs, or null when no session lock was taken
     */
    public static function acquireSession(Connection $connection, float $waitSeconds = self::WAIT_SECONDS): ?string
    {
        $platform = $connection->getDatabasePlatform();
        if ($connection->getTransactionNestingLevel() > 0) {
            if ($platform instanceof AbstractMySQLPlatform) {
                throw new LogicException('Cookie consent provisioning cannot run inside a transaction on MySQL / MariaDB: its lock would be released before your commit, so two seeders could both create the default profile. Call it outside your transaction.');
            }

            return null;
        }

        if ($platform instanceof AbstractMySQLPlatform) {
            $name = self::NAME . ':' . substr(hash('sha256', (string) $connection->getDatabase()), 0, 16);
            if ((int) $connection->fetchOne('SELECT GET_LOCK(?, ?)', [$name, $waitSeconds]) !== 1) {
                throw self::busy();
            }

            return $name;
        }

        if ($platform instanceof PostgreSQLPlatform) {
            self::poll($connection, 'SELECT pg_try_advisory_lock(?)', $waitSeconds);

            return 'pgsql';
        }

        return null;
    }

    /**
     * Inside a transaction of the caller on PostgreSQL: the transaction-level lock (the first statement of the call's
     * savepoint). Nothing in every other case (the session lock is held, or the platform needs none).
     *
     * @param string|null $sessionLock What {@see acquireSession()} answered
     * @param float $waitSeconds Longest wait (tests shorten it)
     *
     * @throws TransientStorageFailure When another provisioning call held the lock for the whole wait
     * @throws LogicException Under REPEATABLE READ or SERIALIZABLE
     */
    public static function acquireTransaction(Connection $connection, ?string $sessionLock, float $waitSeconds = self::WAIT_SECONDS): void
    {
        if ($sessionLock !== null || !$connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            return;
        }

        $isolation = strtolower(trim((string) $connection->fetchOne('SHOW transaction_isolation')));
        if ($isolation !== 'read committed') {
            throw new LogicException('Cookie consent provisioning inside a transaction on PostgreSQL needs READ COMMITTED (got "' . $isolation . '"): an older snapshot cannot see another seeder\'s default profile. Call it outside your transaction.');
        }

        self::poll($connection, 'SELECT pg_try_advisory_xact_lock(?)', $waitSeconds);
    }

    /**
     * Releases the session lock. A failure is ignored: a lost connection released it already.
     */
    public static function release(Connection $connection, ?string $sessionLock): void
    {
        if ($sessionLock === null) {
            return;
        }

        try {
            if ($sessionLock === 'pgsql') {
                $connection->fetchOne('SELECT pg_advisory_unlock(?)', [self::postgresKey()]);

                return;
            }

            $connection->fetchOne('SELECT RELEASE_LOCK(?)', [$sessionLock]);
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

    /**
     * Tries the PostgreSQL lock until it is granted or `$waitSeconds` have passed.
     */
    private static function poll(Connection $connection, string $sql, float $waitSeconds): void
    {
        $deadline = hrtime(true) + (int) ($waitSeconds * 1_000_000_000);
        while (!self::granted($connection->fetchOne($sql, [self::postgresKey()]))) {
            if (hrtime(true) >= $deadline) {
                throw self::busy();
            }

            usleep(self::POLL_MICROSECONDS);
        }
    }

    private static function granted(mixed $answer): bool
    {
        return in_array($answer, [true, 1, 't', '1'], true);
    }

    private static function busy(): TransientStorageFailure
    {
        return new TransientStorageFailure(1, 'Another cookie consent provisioning call holds the lock: retry later.');
    }
}
