<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Integration\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieDefinition;
use Nowo\CookieConsentBundle\Provisioning\CookieConsentProvisioner;
use Nowo\CookieConsentBundle\Provisioning\CookieDefinitionSpec;
use Nowo\CookieConsentBundle\Provisioning\CookieDefinitionSpecs;
use Nowo\CookieConsentBundle\Provisioning\CookieDefinitionTranslationSpec;
use Nowo\CookieConsentBundle\Provisioning\DefaultProfileSpec;
use Nowo\CookieConsentBundle\Provisioning\ProfileTranslationSpec;
use Nowo\CookieConsentBundle\Provisioning\ProvisioningLock;
use Nowo\CookieConsentBundle\Tests\Support\PersonalData\ConsentLogDatabase;
use Nowo\TransactionDoctrine\PrivateManager\PrivateEntityManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function array_map;
use function array_values;
use function dirname;
use function fgets;
use function getenv;
use function hrtime;
use function is_array;
use function is_resource;
use function json_decode;
use function microtime;
use function proc_close;
use function proc_open;
use function sort;
use function sprintf;
use function stream_get_contents;
use function stream_set_timeout;
use function usleep;

use const PHP_BINARY;
use const SORT_STRING;

/**
 * REQ-RUNTIME-003 for the 1.36 provisioning port against real servers (MySQL 8.4, MariaDB 11.4, PostgreSQL 16 in CI,
 * job "Real databases"), two connections at once:
 *
 * - two seeders started together on an empty database leave exactly one default profile, one row per cookie name and
 *   one copy per locale; exactly one of them reports the profile and the cookies created, the other unchanged;
 * - a seeder that finds the provisioning lock held waits for it, then sees the holder's committed default profile
 *   instead of creating a second one.
 */
#[Group('database')]
final class ProvisionerConcurrencyDatabaseTest extends TestCase
{
    private ConsentLogDatabase $db;

    protected function setUp(): void
    {
        if (ConsentLogDatabase::url() === null) {
            self::markTestSkipped(sprintf('Set %s to a MySQL, MariaDB or PostgreSQL database (group "database").', ConsentLogDatabase::URL_ENV));
        }

        $this->db = ConsentLogDatabase::create();
        CookieConsentConcurrencyDatabaseTest::setLockTimeout($this->connection(), 15);
    }

    protected function tearDown(): void
    {
        $this->db->close();
    }

    /**
     * The seeding both processes run: the default profile with two copies, then three cookies.
     *
     * @return array{profile: list<int>, cookies: list<int>}
     */
    public static function seed(CookieConsentProvisioner $provisioner): array
    {
        $profile = $provisioner->ensureDefaultProfile(DefaultProfileSpec::create()
            ->withName('Race')
            ->withColorTheme('dark')
            ->withTranslation(new ProfileTranslationSpec('en', 'Cookies', 'Text', 'All', 'Necessary'))
            ->withTranslation(new ProfileTranslationSpec('es', 'Cookies', 'Texto', 'Todo', 'Necesarias')));
        $cookies = $provisioner->upsertCookieDefinitions(new CookieDefinitionSpecs(
            (new CookieDefinitionSpec('_ga', 'analytics', '2 years', CookieDefinition::TYPE_THIRD_PARTY))->withTranslation(new CookieDefinitionTranslationSpec('en', 'Google', 'Statistics')),
            new CookieDefinitionSpec('_gid', 'analytics', '1 day', CookieDefinition::TYPE_THIRD_PARTY),
            new CookieDefinitionSpec('PHPSESSID', 'required', 'Session'),
        ));

        return [
            'profile' => [$profile->created, $profile->updated, $profile->unchanged],
            'cookies' => [$cookies->created, $cookies->updated, $cookies->unchanged],
        ];
    }

    /**
     * @return iterable<string, array{0: string|null}>
     */
    public static function isolationLevels(): iterable
    {
        yield 'server default' => [null];
        yield 'REPEATABLE READ (audit ZAH-4)' => ['REPEATABLE READ'];
        yield 'SERIALIZABLE' => ['SERIALIZABLE'];
    }

    #[DataProvider('isolationLevels')]
    public function testTwoSeedersAtOnceLeaveOneDefaultProfileAndNoDuplicateCookie(?string $isolation): void
    {
        if ($isolation !== null) {
            self::setIsolation($this->connection(), $isolation);
        }

        for ($round = 0; $round < 3; ++$round) {
            if ($round > 0) {
                $this->connection()->executeStatement('DELETE FROM dashboard_cookie_config');
            }

            $startAt = microtime(true) + 1.0;
            $child   = $this->start($startAt, $isolation);
            $wait    = $startAt - microtime(true);
            if ($wait > 0) {
                usleep((int) ($wait * 1e6));
            }

            $mine   = self::seed($this->provisioner());
            $theirs = $this->finish($child);

            self::assertSame(1, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM dashboard_cookie_config'), 'round ' . $round . ': exactly one profile');
            self::assertSame(['PHPSESSID', '_ga', '_gid'], $this->column('SELECT name FROM dashboard_cookie_definition ORDER BY name'), 'round ' . $round . ': one row per cookie');
            self::assertSame(['en', 'es'], $this->column('SELECT locale FROM dashboard_cookie_config_translation ORDER BY locale'));
            self::assertSame(['en'], $this->column('SELECT locale FROM dashboard_cookie_definition_translation'));

            $profiles = [$mine['profile'], $theirs['profile']];
            $cookies  = [$mine['cookies'], $theirs['cookies']];
            sort($profiles);
            sort($cookies);
            self::assertSame([[0, 0, 1], [1, 0, 0]], $profiles, 'round ' . $round . ': one seeder created the profile, the other found it');
            self::assertSame([[0, 0, 3], [3, 0, 0]], $cookies, 'round ' . $round . ': one seeder created the cookies, the other found them');
        }
    }

    public function testASeederWaitsForTheLockAndThenFindsTheHoldersDefaultProfile(): void
    {
        $connection = $this->connection();
        $lock       = ProvisioningLock::acquireSession($connection);
        $connection->beginTransaction();
        ProvisioningLock::acquireTransaction($connection, $lock);
        // The holder has not created the default profile yet when the other seeder starts.
        $child   = $this->start(microtime(true));
        $started = hrtime(true);
        usleep(1_500_000);

        $config = (new CookieConsentConfig())->setName('Holder')->setEnabled(true)->setDefault(true);
        $this->db->entityManager->persist($config);
        $this->db->entityManager->flush();
        $connection->commit();
        ProvisioningLock::release($connection, $lock);

        $theirs = $this->finish($child);
        $waited = (hrtime(true) - $started) / 1e9;

        self::assertGreaterThan(1.0, $waited, 'the other seeder waited for the lock');
        self::assertSame([0, 1, 0], $theirs['profile'], 'it updated the holder\'s profile instead of creating a second default');
        self::assertSame([3, 0, 0], $theirs['cookies']);
        self::assertSame(['Race'], $this->column('SELECT name FROM dashboard_cookie_config WHERE is_default = ? AND enabled = ?', [true, true], ['boolean', 'boolean']));
    }

    /**
     * Audit ZAH-4: an admin making a profile the default (the repository's save, a second PHP process holding its row
     * locks) while no default exists. The seeder's locking read waits for the admin's commit, then finds that default
     * and updates it: never a second enabled default.
     */
    public function testASeederAndAnAdminMakingAProfileTheDefaultLeaveOneDefault(): void
    {
        $em      = $this->db->entityManager;
        $profile = (new CookieConsentConfig())->setName('Admin profile')->setEnabled(true)->setDefault(false);
        $em->persist($profile);
        $em->flush();
        $id = (int) $profile->getId();

        $child   = $this->startSaveDefault($id, 1500);
        $started = hrtime(true);
        $result  = self::seed($this->provisioner());
        $waited  = (hrtime(true) - $started) / 1e9;
        $this->finishSaveDefault($child);

        self::assertGreaterThan(0.5, $waited, 'the seeder waited for the admin\'s row locks');
        self::assertSame([0, 1, 0], $result['profile'], 'it updated the admin\'s default');
        self::assertSame([(string) $id], $this->column('SELECT id FROM dashboard_cookie_config WHERE is_default = ? AND enabled = ?', [true, true], ['boolean', 'boolean']));
    }

    public static function setIsolation(Connection $connection, string $isolation): void
    {
        $connection->executeStatement($connection->getDatabasePlatform() instanceof PostgreSQLPlatform
            ? 'SET SESSION CHARACTERISTICS AS TRANSACTION ISOLATION LEVEL ' . $isolation
            : 'SET SESSION TRANSACTION ISOLATION LEVEL ' . $isolation);
    }

    /**
     * @return array{0: resource, 1: array<int, resource>}
     */
    private function startSaveDefault(int $id, int $holdMs): array
    {
        // @phpstan-ignore frankenphp.classic.noUnlimitedIoTimeout (test-only child process; pipes read with stream_set_timeout)
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__, 2) . '/Fixtures/concurrency/save-default.php', (string) $id, (string) $holdMs],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            [ConsentLogDatabase::URL_ENV => (string) getenv(ConsentLogDatabase::URL_ENV), 'PATH' => (string) getenv('PATH')],
        );
        self::assertTrue(is_resource($process));
        stream_set_timeout($pipes[1], 30);
        stream_set_timeout($pipes[2], 30);
        if (fgets($pipes[1]) !== "LOCKED\n") {
            throw new RuntimeException('The admin process did not take its locks: ' . stream_get_contents($pipes[2]));
        }

        return [$process, $pipes];
    }

    /**
     * @param array{0: resource, 1: array<int, resource>} $child
     */
    private function finishSaveDefault(array $child): void
    {
        [$process, $pipes] = $child;
        $out               = (string) stream_get_contents($pipes[1]);
        $err               = (string) stream_get_contents($pipes[2]);
        self::assertSame(0, proc_close($process), $err);
        self::assertSame("DONE\n", $out);
    }

    private function provisioner(): CookieConsentProvisioner
    {
        return new CookieConsentProvisioner(new PrivateEntityManager($this->db, 'default', CookieConsentConfig::class, 'parent'));
    }

    private function connection(): Connection
    {
        return $this->db->entityManager->getConnection();
    }

    /**
     * @param list<mixed> $params
     * @param list<string> $types
     *
     * @return list<string>
     */
    private function column(string $sql, array $params = [], array $types = []): array
    {
        // Sorted in PHP (byte order): ORDER BY follows the server's collation.
        $values = array_map(strval(...), $this->connection()->fetchFirstColumn($sql, $params, $types));
        sort($values, SORT_STRING);

        return $values;
    }

    /**
     * @return array{0: resource, 1: array<int, resource>}
     */
    private function start(float $startAt, ?string $isolation = null): array
    {
        // @phpstan-ignore frankenphp.classic.noUnlimitedIoTimeout (test-only child process; pipes read with stream_set_timeout)
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__, 2) . '/Fixtures/concurrency/provision.php', sprintf('%.6F', $startAt), $isolation ?? ''],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            [ConsentLogDatabase::URL_ENV => (string) getenv(ConsentLogDatabase::URL_ENV), 'PATH' => (string) getenv('PATH')],
        );
        self::assertTrue(is_resource($process));
        stream_set_timeout($pipes[1], 60);
        stream_set_timeout($pipes[2], 60);

        return [$process, $pipes];
    }

    /**
     * @param array{0: resource, 1: array<int, resource>} $child
     *
     * @return array{profile: list<int>, cookies: list<int>}
     */
    private function finish(array $child): array
    {
        [$process, $pipes] = $child;
        $line              = (string) fgets($pipes[1]);
        $err               = (string) stream_get_contents($pipes[2]);
        $code              = proc_close($process);
        self::assertSame(0, $code, $err);
        $counts = json_decode($line, true);
        if (!is_array($counts)) {
            throw new RuntimeException('The other seeder printed no counts: ' . $line . ' ' . $err);
        }

        $profile = $counts['profile'] ?? null;
        $cookies = $counts['cookies'] ?? null;
        self::assertIsArray($profile);
        self::assertIsArray($cookies);

        return ['profile' => array_map(intval(...), array_values($profile)), 'cookies' => array_map(intval(...), array_values($cookies))];
    }
}
