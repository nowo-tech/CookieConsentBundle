<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Integration\Database;

use LogicException;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieDefinition;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;
use Nowo\CookieConsentBundle\Repository\CookieDefinitionRepository;
use Nowo\CookieConsentBundle\Tests\Support\PersonalData\ConsentLogDatabase;
use Nowo\TransactionDoctrine\PrivateManager\PrivateEntityManager;
use Nowo\TransactionDoctrine\PrivateManager\PrivateManagerRegistry;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function array_map;
use function dirname;
use function fgets;
use function getenv;
use function hrtime;
use function intval;
use function is_resource;
use function proc_close;
use function proc_open;
use function sprintf;
use function stream_get_contents;
use function stream_set_timeout;
use function trim;

use const PHP_BINARY;

/**
 * The private entity manager (1.34 opt-in, the only mode on the default manager since 2.0) against real servers (REQ-ARCH-001 rule 6 with REQ-RUNTIME-003; CI job
 * "Real databases", group "database"): the private manager shares the application's connection, so.
 *
 * - a profile save flushes only the private manager: the application's pending change is not written;
 * - a save inside an open application transaction is part of it (savepoint): the application's rollback undoes it;
 * - the "only one default" lock still works: a private save that meets another admin's save waits for it, and exactly
 *   one default profile is left;
 * - 2.0 (W1-2): a save given the application's manager, or a profile the application's manager loaded, fails before
 *   anything is written, and the application's pending change stays its own.
 */
#[Group('database')]
final class PrivateEntityManagerDatabaseTest extends TestCase
{
    private ConsentLogDatabase $db;

    protected function setUp(): void
    {
        if (ConsentLogDatabase::url() === null) {
            self::markTestSkipped(sprintf('Set %s to a MySQL, MariaDB or PostgreSQL database (group "database").', ConsentLogDatabase::URL_ENV));
        }

        $this->db = ConsentLogDatabase::create();
        CookieConsentConcurrencyDatabaseTest::setLockTimeout($this->db->entityManager->getConnection(), 2);
    }

    protected function tearDown(): void
    {
        $this->db->close();
    }

    public function testAPrivateSaveNeverWritesTheApplicationsPendingChange(): void
    {
        [$first, $second]       = $this->profiles();
        [$private, $repository] = $this->privateSide($this->db);

        // The application changed profile "second" in its own manager and has not flushed yet.
        $hostCopy = $this->db->entityManager->find(CookieConsentConfig::class, $second);
        self::assertInstanceOf(CookieConsentConfig::class, $hostCopy);
        $hostCopy->setName('host pending');

        $profile = $repository->find($first);
        self::assertInstanceOf(CookieConsentConfig::class, $profile);
        self::assertTrue($private->contains($profile));
        $profile->setName('saved privately');
        $repository->saveSettings($profile, $private);

        self::assertSame(['saved privately', 'second'], $this->names([$first, $second]));
        self::assertTrue($this->db->entityManager->contains($hostCopy), 'still the application\'s to commit');

        $this->db->entityManager->flush();
        self::assertSame(['saved privately', 'host pending'], $this->names([$first, $second]));
    }

    public function testASaveOutsideTheBundlesManagerFailsAndWritesNothing(): void
    {
        [$first, $second]       = $this->profiles();
        [$private, $repository] = $this->privateSide($this->db);
        $host                   = $this->db->entityManager;

        $hostPending = $host->find(CookieConsentConfig::class, $second);
        self::assertInstanceOf(CookieConsentConfig::class, $hostPending);
        $hostPending->setName('host pending');
        $hostLoaded = $host->find(CookieConsentConfig::class, $first);
        self::assertInstanceOf(CookieConsentConfig::class, $hostLoaded);
        $hostLoaded->setName('through the application');

        foreach ([$host, $private] as $manager) {
            try {
                $repository->saveSettings($hostLoaded, $manager);
                self::fail('A save outside the bundle\'s manager must fail.');
            } catch (LogicException $e) {
                self::assertStringContainsString('Nothing was written', $e->getMessage());
            }
        }

        self::assertSame(['first', 'second'], $this->names([$first, $second]), 'nothing written, the application\'s manager not flushed');
        self::assertSame(0, $host->getConnection()->getTransactionNestingLevel());
        self::assertTrue($host->contains($hostPending));

        $host->flush();
        self::assertSame(['through the application', 'host pending'], $this->names([$first, $second]), 'the application commits its own changes');
    }

    /**
     * ZAO-4: an entity the private manager holds, passed with the application's manager, fails for both saves; the
     * application's pending change stays unwritten.
     */
    public function testABundleHeldEntityWithTheApplicationsManagerFailsForBothSaves(): void
    {
        [$first, $second]       = $this->profiles();
        [$private, $repository] = $this->privateSide($this->db);
        $host                   = $this->db->entityManager;

        $definition = (new CookieDefinition())->setName('_ga')->setCategory('analytics')->setDuration('2 years');
        $owner      = $host->find(CookieConsentConfig::class, $first);
        self::assertInstanceOf(CookieConsentConfig::class, $owner);
        $owner->addCookieDefinition($definition);
        $host->persist($definition);
        $host->flush();
        $definitionId = (int) $definition->getId();
        $host->clear();

        $hostPending = $host->find(CookieConsentConfig::class, $second);
        self::assertInstanceOf(CookieConsentConfig::class, $hostPending);
        $hostPending->setName('host pending');

        $definitions = new CookieDefinitionRepository(new PrivateManagerRegistry($this->db, $private, ['Nowo\\CookieConsentBundle\\Entity'], 'default'));
        $config      = $repository->find($first);
        $held        = $definitions->find($definitionId);
        self::assertInstanceOf(CookieConsentConfig::class, $config);
        self::assertInstanceOf(CookieDefinition::class, $held);
        self::assertTrue($private->contains($config));
        self::assertTrue($private->contains($held));
        $config->setName('through the application');
        $held->setDuration('forever');

        foreach ([
            'saveSettings' => static fn () => $repository->saveSettings($config, $host),
            'saveEdit'     => static fn () => $definitions->saveEdit($held, $host, 1),
        ] as $method => $save) {
            try {
                $save();
                self::fail($method . ' with the application\'s manager must fail.');
            } catch (LogicException $e) {
                self::assertStringContainsString('Nothing was written', $e->getMessage());
            }
        }

        $connection = $host->getConnection();
        self::assertSame(['first', 'second'], $this->names([$first, $second]), 'nothing written, the application\'s change not flushed');
        self::assertSame('2 years', $connection->fetchOne('SELECT duration FROM dashboard_cookie_definition WHERE id = ?', [$definitionId]));
        self::assertSame(0, $connection->getTransactionNestingLevel());
        self::assertTrue($host->contains($hostPending));
    }

    public function testASaveInsideAnOpenApplicationTransactionIsRolledBackWithIt(): void
    {
        [$first]                = $this->profiles();
        [$private, $repository] = $this->privateSide($this->db);
        $connection             = $this->db->entityManager->getConnection();

        $connection->beginTransaction();
        $profile = $repository->find($first);
        self::assertInstanceOf(CookieConsentConfig::class, $profile);
        $profile->setName('inside the host transaction');
        $repository->saveSettings($profile, $private);
        self::assertSame(1, $connection->getTransactionNestingLevel(), 'the bundle closed only its own level');
        $connection->rollBack();

        self::assertSame(['first'], $this->names([$first]));
        $private->reset();
        self::assertSame('first', $repository->find($first)?->getName(), 'a fresh private manager reads the committed row');
    }

    public function testAPrivateDefaultSaveWaitsForTheOtherAdminAndLeavesExactlyOneDefault(): void
    {
        [$first, $second] = $this->profiles();
        $session          = ConsentLogDatabase::connect();
        CookieConsentConcurrencyDatabaseTest::setLockTimeout($session->entityManager->getConnection(), 10);
        [$private, $repository] = $this->privateSide($session);

        try {
            $child   = $this->startSaveDefault($first, 1500);
            $started = hrtime(true);
            $profile = $repository->find($second);
            self::assertInstanceOf(CookieConsentConfig::class, $profile);
            $profile->setDefault(true);
            $repository->saveSettings($profile, $private);
            $waited = (hrtime(true) - $started) / 1e9;
            self::assertSame("DONE\n", $this->finish($child));
        } finally {
            $session->close();
        }

        self::assertGreaterThan(0.5, $waited, 'the private save waited for the other admin\'s locks');
        self::assertSame([$second], $this->defaults());
    }

    /**
     * The bundle side of `private_entity_manager: true`, as the extension wires it: a PrivateEntityManager on the
     * session's manager and the repository on a PrivateManagerRegistry.
     *
     * @return array{PrivateEntityManager, CookieConsentConfigRepository}
     */
    private function privateSide(ConsentLogDatabase $session): array
    {
        $private  = new PrivateEntityManager($session, 'default', CookieConsentConfig::class, 'NowoCookieConsentBundle');
        $registry = new PrivateManagerRegistry($session, $private, ['Nowo\\CookieConsentBundle\\Entity'], 'default');

        return [$private, new CookieConsentConfigRepository($registry)];
    }

    /**
     * @return array{int, int, int} ids of "first", "second" and the "initial" default
     */
    private function profiles(): array
    {
        $em  = $this->db->entityManager;
        $ids = [];
        foreach (['first' => false, 'second' => false, 'initial' => true] as $name => $default) {
            $profile = (new CookieConsentConfig())->setName($name)->setEnabled(true)->setDefault($default);
            $em->persist($profile);
            $em->flush();
            $ids[] = (int) $profile->getId();
        }
        $em->clear();

        return [$ids[0], $ids[1], $ids[2]];
    }

    /**
     * @param list<int> $ids
     *
     * @return list<string>
     */
    private function names(array $ids): array
    {
        $connection = $this->db->entityManager->getConnection();

        return array_map(static fn (int $id): string => (string) $connection->fetchOne('SELECT name FROM dashboard_cookie_config WHERE id = ?', [$id]), $ids);
    }

    /**
     * @return list<int>
     */
    private function defaults(): array
    {
        $ids = $this->db->entityManager->getConnection()->fetchFirstColumn('SELECT id FROM dashboard_cookie_config WHERE is_default = ? AND enabled = ? ORDER BY id', [true, true], ['boolean', 'boolean']);

        return array_map(intval(...), $ids);
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
        $line = fgets($pipes[1]);
        if ($line !== "LOCKED\n") {
            throw new RuntimeException('The second process did not take its locks: ' . trim((string) $line) . ' ' . stream_get_contents($pipes[2]));
        }

        return [$process, $pipes];
    }

    /**
     * @param array{0: resource, 1: array<int, resource>} $child
     */
    private function finish(array $child): string
    {
        [$process, $pipes] = $child;
        $out               = (string) stream_get_contents($pipes[1]);
        $err               = (string) stream_get_contents($pipes[2]);
        $code              = proc_close($process);
        self::assertSame(0, $code, $err);

        return $out;
    }
}
