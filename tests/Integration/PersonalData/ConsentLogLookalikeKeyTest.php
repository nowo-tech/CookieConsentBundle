<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Integration\PersonalData;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\ToolEvents;
use Nowo\CookieConsentBundle\Doctrine\ConsentLogCollationListener;
use Nowo\CookieConsentBundle\PersonalData\CookieConsentIpWindow;
use Nowo\CookieConsentBundle\PersonalData\CookieConsentPersonalDataEraser;
use Nowo\CookieConsentBundle\PersonalData\CookieConsentPersonalDataExporter;
use Nowo\CookieConsentBundle\PersonalData\CookieConsentPersonalDataSubject;
use Nowo\CookieConsentBundle\Tests\Support\PersonalData\ConsentLogDatabase;
use Nowo\CookieConsentBundle\Tests\Support\PersonalData\LookalikeCollation;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

use function array_keys;
use function array_map;
use function count;
use function sort;

/**
 * REQ-SEC-006 (look-alike keys) for the consent log: `Key-a`, `kéy-a`, `key-a `, "key-a\n", NBSP and ZWSP variants of
 * `key-a` (and of an anonymised IP) never reach the export or the erasure of `key-a`.
 *
 * On SQLite the key and IP columns get a collation that folds like the default MySQL / MariaDB ones
 * ({@see LookalikeCollation}). On MySQL / MariaDB (`COOKIE_CONSENT_TEST_DATABASE_URL`, CI `database` job) the schema is
 * generated with {@see ConsentLogCollationListener}, and SQL itself must then match the key byte for byte.
 */
final class ConsentLogLookalikeKeyTest extends TestCase
{
    private const LOOKALIKES = ['Key-a', "k\u{00E9}y-a", 'key-a ', "key-a\n", "key-a\u{00A0}", "key-\u{200B}a"];

    private ConsentLogDatabase $db;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->db         = ConsentLogDatabase::create(false);
        $em               = $this->db->entityManager;
        $this->connection = $em->getConnection();
        $em->getEventManager()->addEventListener(ToolEvents::postGenerateSchemaTable, new ConsentLogCollationListener($this->db));
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());

        if ($this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            LookalikeCollation::register($this->connection);
            LookalikeCollation::recreate($this->connection, 'dashboard_cookie_log', ['cookie_consent_key', 'ip_address']);
        }
    }

    protected function tearDown(): void
    {
        $this->db->close();
    }

    public function testTheKeyAndIpColumnsAreExactOnMysqlAndMariaDb(): void
    {
        $platform = $this->connection->getDatabasePlatform();
        if (!$platform instanceof AbstractMySQLPlatform) {
            self::assertNull(ConsentLogCollationListener::collationFor($platform));

            return;
        }

        $collations = $this->connection->fetchAllKeyValue(
            "SELECT COLUMN_NAME, COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dashboard_cookie_log' AND COLUMN_NAME IN ('cookie_consent_key', 'ip_address')",
        );
        self::assertSame(['cookie_consent_key', 'ip_address'], $this->sorted(array_map('strval', array_keys($collations))));
        foreach ($collations as $collation) {
            self::assertContains($collation, ['utf8mb4_0900_bin', 'utf8mb4_nopad_bin']);
        }

        $this->seed();
        self::assertSame(1, (int) $this->connection->fetchOne("SELECT COUNT(*) FROM dashboard_cookie_log WHERE cookie_consent_key = 'key-a'"));
        self::assertSame(1, (int) $this->connection->fetchOne("SELECT COUNT(*) FROM dashboard_cookie_log WHERE ip_address = '203.0.113.xx'"));
    }

    public function testSqliteFoldsLikeMysqlWithoutTheCollation(): void
    {
        if (!$this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->seed();
        // Guard: SQL alone matches every look-alike, so the tests below exercise the PHP re-check.
        self::assertSame(1 + count(self::LOOKALIKES), (int) $this->connection->fetchOne("SELECT COUNT(*) FROM dashboard_cookie_log WHERE cookie_consent_key = 'key-a'"));
    }

    public function testExportAndErasureOfAKeyTakeTheExactRowsOnly(): void
    {
        $ids = $this->seed();

        $export = (new CookieConsentPersonalDataExporter($this->db))->export(CookieConsentPersonalDataSubject::fromConsentKey('key-a'));
        self::assertCount(1, $export['decisions']);

        $eraser = new CookieConsentPersonalDataEraser($this->db, new EventDispatcher(), $this->clock());
        self::assertSame(1, $eraser->erase(CookieConsentPersonalDataSubject::fromConsentKey('key-a'))->consentLogs);
        self::assertNotContains($ids['key-a'], $this->db->ids());
        self::assertCount(count(self::LOOKALIKES), $this->db->ids());

        self::assertSame(1, $eraser->erase(CookieConsentPersonalDataSubject::fromConsentKey('Key-a'))->consentLogs);
        self::assertCount(count(self::LOOKALIKES) - 1, $this->db->ids());
    }

    public function testErasureByIpWindowTakesTheExactIpOnly(): void
    {
        $this->seed();
        $eraser = new CookieConsentPersonalDataEraser($this->db, new EventDispatcher(), $this->clock(), eraseByIpWindow: true);
        $window = CookieConsentIpWindow::of('203.0.113.42', $this->at('2026-03-01 00:00:00'), $this->at('2026-03-31 00:00:00'));

        self::assertSame(1, $eraser->erase(CookieConsentPersonalDataSubject::of([$window]))->consentLogs);
    }

    /**
     * One row per key: `key-a` on IP `203.0.113.xx`, each look-alike key on a look-alike of that IP.
     *
     * @return array<string, int>
     */
    private function seed(): array
    {
        $ids = ['key-a' => $this->db->insert('key-a', '203.0.113.xx', 'analytics', true, $this->at('2026-03-10 10:00:00'))];
        foreach (self::LOOKALIKES as $i => $key) {
            $ip        = ['203.0.113.XX', '203.0.113.xx ', "203.0.113.xx\n", "203.0.113.xx\u{00A0}", "203.0.113.\u{200B}xx", '203.0.113.Xx'][$i];
            $ids[$key] = $this->db->insert($key, $ip, 'analytics', true, $this->at('2026-03-10 10:00:00'));
        }

        return $ids;
    }

    /**
     * @param list<string> $values
     *
     * @return list<string>
     */
    private function sorted(array $values): array
    {
        sort($values);

        return $values;
    }

    private function at(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable($time, new DateTimeZone('UTC'));
    }

    private function clock(): ClockInterface
    {
        return new class implements ClockInterface {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-11-11 12:00:00', new DateTimeZone('UTC'));
            }
        };
    }
}
