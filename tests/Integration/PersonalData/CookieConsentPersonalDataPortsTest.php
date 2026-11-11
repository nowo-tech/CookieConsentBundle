<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Integration\PersonalData;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Nowo\CookieConsentBundle\PersonalData\CookieConsentIpWindow;
use Nowo\CookieConsentBundle\PersonalData\CookieConsentPersonalDataErasedEvent;
use Nowo\CookieConsentBundle\PersonalData\CookieConsentPersonalDataEraser;
use Nowo\CookieConsentBundle\PersonalData\CookieConsentPersonalDataExporter;
use Nowo\CookieConsentBundle\PersonalData\CookieConsentPersonalDataSubject;
use Nowo\CookieConsentBundle\Tests\Support\PersonalData\ConsentLogDatabase;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

use function count;
use function gc_collect_cycles;
use function memory_get_usage;

/**
 * The GDPR ports against a real database: in-memory SQLite in every run, MySQL / PostgreSQL when
 * `COOKIE_CONSENT_TEST_DATABASE_URL` is set (CI `database` job).
 */
final class CookieConsentPersonalDataPortsTest extends TestCase
{
    private ConsentLogDatabase $db;

    private EventDispatcher $dispatcher;

    /** @var list<array{event: CookieConsentPersonalDataErasedEvent, inTransaction: bool, remaining: list<int>}> */
    private array $events = [];

    private CookieConsentPersonalDataEraser $eraser;

    private CookieConsentPersonalDataExporter $exporter;

    protected function setUp(): void
    {
        $this->db         = ConsentLogDatabase::create();
        $this->dispatcher = new EventDispatcher();
        $connection       = $this->db->entityManager->getConnection();
        $this->dispatcher->addListener(CookieConsentPersonalDataErasedEvent::class, function (CookieConsentPersonalDataErasedEvent $event) use ($connection): void {
            $this->events[] = ['event' => $event, 'inTransaction' => $connection->isTransactionActive(), 'remaining' => $this->db->ids()];
        });
        // Erasure by IP window opted in (`personal_data.ip_windows: erase`); the default is covered separately.
        $this->eraser = new CookieConsentPersonalDataEraser($this->db, $this->dispatcher, new class implements ClockInterface {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-11-11 12:00:00', new DateTimeZone('UTC'));
            }
        }, eraseByIpWindow: true);
        $this->exporter = new CookieConsentPersonalDataExporter($this->db);
    }

    protected function tearDown(): void
    {
        $this->db->close();
    }

    public function testErasesTheKeyRowsAndTheWindowRowsOnly(): void
    {
        $byKey     = $this->db->insert('key-a', '198.51.100.xx', 'analytics', true, $this->at('2026-01-01 10:00:00'));
        $inWindow  = $this->db->insert('key-x', '203.0.113.xx', 'marketing', false, $this->at('2026-03-10 08:00:00'));
        $edge      = $this->db->insert('key-y', '203.0.113.xx', 'analytics', true, $this->at('2026-03-11 10:00:00'));
        $natLater  = $this->db->insert('key-n', '203.0.113.xx', 'analytics', true, $this->at('2026-03-11 10:00:01'));
        $natBefore = $this->db->insert('key-m', '203.0.113.xx', 'analytics', true, $this->at('2026-03-09 23:59:59'));
        $otherIp   = $this->db->insert('key-o', '203.0.114.xx', 'analytics', true, $this->at('2026-03-10 08:00:00'));
        $otherKey  = $this->db->insert('key-b', '198.51.100.xx', 'analytics', true, $this->at('2026-01-01 10:00:00'));
        $subject   = CookieConsentPersonalDataSubject::of(
            [CookieConsentIpWindow::of('203.0.113.42', $this->at('2026-03-10 00:00:00'), $this->at('2026-03-11 10:00:00'))],
            ['key-a'],
        );

        self::assertSame(3, $this->eraser->erase($subject)->consentLogs);
        self::assertSame([$natLater, $natBefore, $otherIp, $otherKey], $this->db->ids());
        self::assertNotContains($byKey, $this->db->ids());
        self::assertNotContains($inWindow, $this->db->ids());
        self::assertNotContains($edge, $this->db->ids());
    }

    public function testByDefaultIpWindowsAreIgnoredAndOnlyConsentKeysAreErased(): void
    {
        $byKey    = $this->db->insert('key-a', '198.51.100.xx', 'analytics', true, $this->at('2026-01-01 10:00:00'));
        $inWindow = $this->db->insert('key-x', '203.0.113.xx', 'marketing', false, $this->at('2026-03-10 08:00:00'));
        $default  = new CookieConsentPersonalDataEraser($this->db, $this->dispatcher);
        $window   = CookieConsentIpWindow::of('203.0.113.42', $this->at('2026-03-10 00:00:00'), $this->at('2026-03-11 00:00:00'));

        self::assertSame(0, $default->erase(CookieConsentPersonalDataSubject::of([$window]))->total());
        self::assertSame(0, $this->eventCount(), 'a subject reduced to nothing dispatches no event');
        self::assertSame(1, $default->erase(CookieConsentPersonalDataSubject::of([$window], ['key-a']))->consentLogs);
        self::assertSame([$inWindow], $this->db->ids());
        self::assertNotContains($byKey, $this->db->ids());
        self::assertSame(1, $this->eventCount());
        self::assertSame([], $this->events[0]['event']->ipWindows, 'the event reports the subject actually used');
    }

    public function testTheExportIgnoresIpWindows(): void
    {
        $this->db->insert('key-x', '203.0.113.xx', 'marketing', false, $this->at('2026-03-10 08:00:00'));
        $window = CookieConsentIpWindow::of('203.0.113.42', $this->at('2026-03-10 00:00:00'), $this->at('2026-03-11 00:00:00'));

        self::assertSame(['decisions' => []], $this->exporter->export(CookieConsentPersonalDataSubject::of([$window])));
        self::assertSame(['decisions' => []], $this->exporter->export(CookieConsentPersonalDataSubject::of([$window], ['key-other'])));
        self::assertCount(1, $this->db->ids());
    }

    public function testASecondRunErasesNothingAndDoesNotFail(): void
    {
        $this->db->insert('key-a', '198.51.100.xx', 'analytics', true, $this->at('2026-01-01 10:00:00'));
        $subject = CookieConsentPersonalDataSubject::fromConsentKey('key-a');

        self::assertSame(1, $this->eraser->erase($subject)->total());
        self::assertSame(0, $this->eraser->erase($subject)->total());
        self::assertSame(['decisions' => []], $this->exporter->export($subject));
    }

    public function testCaseAndAccentVariantsAreNeitherErasedNorExported(): void
    {
        $this->db->insert('UserA', '198.51.100.xx', 'analytics', true, $this->at('2026-01-01 10:00:00'));
        $this->db->insert('josé', '198.51.100.xx', 'analytics', true, $this->at('2026-01-01 10:00:00'));
        $this->db->insert('k-1', '203.0.113.XX', 'analytics', true, $this->at('2026-03-10 08:00:00'));
        $subject = CookieConsentPersonalDataSubject::of(
            [CookieConsentIpWindow::of('203.0.113.xx', $this->at('2026-03-10 00:00:00'), $this->at('2026-03-11 00:00:00'))],
            ['usera', 'jose'],
        );

        self::assertSame(['decisions' => []], $this->exporter->export($subject));
        self::assertSame(0, $this->eraser->erase($subject)->consentLogs);
        self::assertCount(3, $this->db->ids());
    }

    public function testExportsTheKeyDecisionsOldestFirstWithoutIdentifiers(): void
    {
        $this->db->insert('key-a', '198.51.100.xx', 'marketing', false, $this->at('2026-02-01 10:00:00'), 3);
        $this->db->insert('key-a', '198.51.100.xx', 'analytics', true, $this->at('2026-01-01 10:00:00'));
        // In the window but another key: never exported (IP windows are not used for the export).
        $this->db->insert('key-z', '203.0.113.xx', 'preferences', true, $this->at('2026-03-10 08:00:00'), 4);
        $this->db->insert('key-b', '198.51.100.xx', 'analytics', true, $this->at('2026-01-01 10:00:00'));
        $this->db->insert('key-a', '203.0.113.xx', 'analytics', false, $this->at('2026-03-10 09:00:00'));

        $export = $this->exporter->export(CookieConsentPersonalDataSubject::of(
            [CookieConsentIpWindow::of('203.0.113.42', $this->at('2026-03-10 00:00:00'), $this->at('2026-03-11 00:00:00'))],
            ['key-a'],
        ));

        self::assertSame(['decisions' => [
            ['cookie_name' => 'analytics', 'allowed' => true, 'consent_revision' => null, 'recorded_at' => '2026-01-01T10:00:00+00:00'],
            ['cookie_name' => 'marketing', 'allowed' => false, 'consent_revision' => 3, 'recorded_at' => '2026-02-01T10:00:00+00:00'],
            ['cookie_name' => 'analytics', 'allowed' => false, 'consent_revision' => null, 'recorded_at' => '2026-03-10T09:00:00+00:00'],
        ]], $export);
    }

    public function testDeletesLargeSetsInBatches(): void
    {
        $connection = $this->db->entityManager->getConnection();
        $connection->beginTransaction();
        for ($i = 0; $i < CookieConsentPersonalDataEraser::BATCH_SIZE * 2 + 3; ++$i) {
            $this->db->insert('key-a', '198.51.100.xx', 'c' . $i, true, $this->at('2026-01-01 10:00:00'));
        }
        $this->db->insert('key-b', '198.51.100.xx', 'other', true, $this->at('2026-01-01 10:00:00'));
        $connection->commit();

        self::assertSame(CookieConsentPersonalDataEraser::BATCH_SIZE * 2 + 3, $this->eraser->erase(CookieConsentPersonalDataSubject::fromConsentKey('key-a'))->consentLogs);
        self::assertCount(1, $this->db->ids());
        self::assertCount(1, $this->events);
    }

    public function testOwnTransactionDispatchesTheEventAfterCommit(): void
    {
        $this->db->insert('key-a', '198.51.100.xx', 'analytics', true, $this->at('2026-01-01 10:00:00'));
        $subject = CookieConsentPersonalDataSubject::of(
            [CookieConsentIpWindow::of('203.0.113.42', $this->at('2026-03-10 00:00:00'), $this->at('2026-03-11 00:00:00'))],
            ['key-a'],
        );

        $this->eraser->erase($subject);

        self::assertCount(1, $this->events);
        self::assertFalse($this->events[0]['inTransaction']);
        self::assertSame([], $this->events[0]['remaining'], 'the rows are gone when the event is dispatched');
        $event = $this->events[0]['event'];
        self::assertSame(['key-a'], $event->consentKeys);
        self::assertSame([['ip' => '203.0.113.xx', 'from' => '2026-03-10T00:00:00+00:00', 'to' => '2026-03-11T00:00:00+00:00']], $event->ipWindows);
        self::assertSame(['consent_logs' => 1], $event->counts);
        self::assertSame('2026-11-11T12:00:00+00:00', $event->occurredAt->format(DateTimeInterface::ATOM));
    }

    public function testInsideTheCallerTransactionJoinsItAndDispatchesNothing(): void
    {
        $this->db->insert('key-a', '198.51.100.xx', 'analytics', true, $this->at('2026-01-01 10:00:00'));
        $connection = $this->db->entityManager->getConnection();

        $result = $connection->transactional(function (Connection $connection): int {
            $deleted = $this->eraser->erase(CookieConsentPersonalDataSubject::fromConsentKey('key-a'))->consentLogs;
            self::assertTrue($connection->isTransactionActive());
            self::assertSame([], $this->db->ids());

            return $deleted;
        });

        self::assertSame(1, $result);
        self::assertSame([], $this->events, 'no event inside the caller transaction');

        // Joined: rolling back the caller's transaction brings the rows back.
        $this->db->insert('key-c', '198.51.100.xx', 'analytics', true, $this->at('2026-01-01 10:00:00'));
        $connection->beginTransaction();
        self::assertSame(1, $this->eraser->erase(CookieConsentPersonalDataSubject::fromConsentKey('key-c'))->consentLogs);
        $connection->rollBack();
        self::assertCount(1, $this->db->ids());
    }

    public function testAKeptAliveServiceKeepsTwoSubjectsApartWithFlatMemory(): void
    {
        $runs = ConsentLogDatabase::url() === null ? 1000 : 50;
        $a    = CookieConsentPersonalDataSubject::fromConsentKey('worker-a');
        $bKey = CookieConsentPersonalDataSubject::fromConsentKey('worker-b');
        $b    = CookieConsentPersonalDataSubject::fromIpWindow('192.0.2.10', $this->at('2026-05-01 00:00:00'), $this->at('2026-05-02 00:00:00'));
        $base = 0;

        for ($i = 0; $i < $runs; ++$i) {
            $this->db->insert('worker-a', '198.51.100.xx', 'a-cat', true, $this->at('2026-01-01 10:00:00'));
            $this->db->insert('worker-b', '192.0.2.xx', 'b-cat', false, $this->at('2026-05-01 12:00:00'));

            self::assertSame('a-cat', $this->exporter->export($a)['decisions'][0]['cookie_name'] ?? null);
            self::assertCount(1, $this->exporter->export($a)['decisions']);
            self::assertSame(1, $this->eraser->erase($a)->consentLogs);
            self::assertSame(['decisions' => []], $this->exporter->export($b), 'IP windows are never exported');
            self::assertSame(['decisions' => [['cookie_name' => 'b-cat', 'allowed' => false, 'consent_revision' => null, 'recorded_at' => '2026-05-01T12:00:00+00:00']]], $this->exporter->export($bKey));
            self::assertSame(1, $this->eraser->erase($b)->consentLogs);
            self::assertSame([], $this->db->ids());

            if ($i === 9) {
                gc_collect_cycles();
                $this->events = [];
                $base         = memory_get_usage();
            }
        }

        $this->events = [];
        gc_collect_cycles();
        self::assertLessThan(1024 * 1024, memory_get_usage() - $base, 'memory stays flat');
    }

    private function eventCount(): int
    {
        return count($this->events);
    }

    private function at(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable($time, new DateTimeZone('UTC'));
    }
}
