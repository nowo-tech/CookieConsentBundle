<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Integration\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Nowo\CookieConsentBundle\Concurrency\StaleVersionException;
use Nowo\CookieConsentBundle\Cookie\CookieHandler;
use Nowo\CookieConsentBundle\Cookie\CookieLogger;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieDefinition;
use Nowo\CookieConsentBundle\Enum\CookieNameEnum;
use Nowo\CookieConsentBundle\EventListener\CookieConsentConflictListener;
use Nowo\CookieConsentBundle\EventSubscriber\CookieConsentFormSubscriber;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;
use Nowo\CookieConsentBundle\Repository\CookieDefinitionRepository;
use Nowo\CookieConsentBundle\Tests\Support\PersonalData\ConsentLogDatabase;
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\TransactionDoctrine\ConflictCode;
use Nowo\TransactionDoctrine\Symfony\Conflict\ConflictResponsePolicy;
use Nowo\TransactionDoctrine\WriteUnit\CommitLedger;
use Nowo\TransactionDoctrine\WriteUnit\Dbal\CommitTrackingMiddleware;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

use function array_column;
use function array_map;
use function array_unique;
use function dirname;
use function fgets;
use function getenv;
use function hrtime;
use function is_resource;
use function method_exists;
use function proc_close;
use function proc_open;
use function sort;
use function sprintf;
use function stream_get_contents;
use function stream_set_timeout;
use function trim;

use const PHP_BINARY;

/**
 * REQ-RUNTIME-003: the bundle's write paths against real servers, two sessions at once (MySQL 8.4, MariaDB 11.4,
 * PostgreSQL 16 in CI, job "Real databases"). Every session sets a short lock timeout, so a hang fails fast.
 *
 * - The consent log is append-only: two visitors' consents never wait for each other and both are stored whole.
 * - A consent POST whose log meets a lock answers 409 + Retry-After + CONFLICT_001, stores no row and sets no cookie;
 *   the connection stays usable and the retry stores exactly one set of rows.
 * - After a commit earlier in the request (counted by transaction-doctrine's real middleware) it answers CONFLICT_002.
 * - Inside an open host transaction the failed log rolls back to its savepoint only: the host's work commits.
 * - "Only one default profile": two admins making two profiles the default at once leave exactly one default; a save
 *   that meets the other's lock past its timeout answers 409 + CONFLICT_001 and saves nothing.
 */
#[Group('database')]
final class CookieConsentConcurrencyDatabaseTest extends TestCase
{
    private const KEY = '0123456789abcdef0123456789abcdef';

    /** Categories of the posted consent (`required` is not logged): two rows per consent. */
    private const CATEGORIES = ['required' => true, 'analytics' => 'true', 'marketing' => false];

    private ConsentLogDatabase $db;

    /** @var list<ConsentLogDatabase> */
    private array $sessions = [];

    private RequestStack $stack;

    protected function setUp(): void
    {
        if (ConsentLogDatabase::url() === null) {
            self::markTestSkipped(sprintf('Set %s to a MySQL, MariaDB or PostgreSQL database (group "database").', ConsentLogDatabase::URL_ENV));
        }

        $this->stack = new RequestStack();
        $this->db    = ConsentLogDatabase::create();
        self::setLockTimeout($this->db->entityManager->getConnection(), 2);
    }

    protected function tearDown(): void
    {
        foreach ($this->sessions as $session) {
            $session->close();
        }
        $this->db->close();
    }

    /**
     * Session lock timeout in seconds (row locks and, on MySQL / MariaDB, metadata locks).
     */
    public static function setLockTimeout(Connection $connection, int $seconds): void
    {
        $platform = $connection->getDatabasePlatform();
        if ($platform instanceof PostgreSQLPlatform) {
            $connection->executeStatement(sprintf("SET lock_timeout = '%ds'", $seconds));

            return;
        }

        if ($platform instanceof AbstractMySQLPlatform) {
            $connection->executeStatement(sprintf('SET SESSION innodb_lock_wait_timeout = %d', $seconds));
            $connection->executeStatement(sprintf('SET SESSION lock_wait_timeout = %d', $seconds));
        }
    }

    public function testTwoConsentsAtOnceNeverWaitAndAreBothStoredWhole(): void
    {
        $a = $this->db->entityManager->getConnection();
        $b = $this->session();

        $a->beginTransaction();
        $this->post($this->subscriber($this->db), $this->consentRequest());
        $started = hrtime(true);
        $this->post($this->subscriber($b), $this->consentRequest('fedcba9876543210fedcba9876543210'));
        $elapsed = (hrtime(true) - $started) / 1e9;
        $a->commit();

        self::assertLessThan(1.5, $elapsed, 'an open consent of another visitor never blocks this one');
        $rows = $a->fetchAllAssociative('SELECT id, cookie_consent_key, cookie_name FROM dashboard_cookie_log ORDER BY id');
        self::assertCount(4, $rows);
        self::assertCount(4, array_unique(array_column($rows, 'id')));
        self::assertSame([self::KEY, self::KEY, 'fedcba9876543210fedcba9876543210', 'fedcba9876543210fedcba9876543210'], $this->keysSorted($rows), 'two rows per consent, none lost or duplicated');
    }

    public function testALockedLogAnswers409Conflict001StoresNothingAndTheRetryStoresOneConsent(): void
    {
        $b        = $this->session();
        $request  = $this->consentRequest();
        $response = new Response('host page');

        $this->lockTheLog();
        $answer = $this->expectAnswer($this->subscriber($b), $request, $response);
        $this->db->entityManager->getConnection()->rollBack();

        self::assertSame(409, $answer->getStatusCode());
        self::assertSame(['X-Conflict-Code' => ConflictCode::RETRY_SAFE, 'Retry-After' => '1'], $answer->getHeaders());
        self::assertInstanceOf(TransientStorageFailure::class, $answer->getPrevious());
        self::assertSame([], $response->headers->getCookies(), 'no consent cookie without its proof of consent');
        self::assertSame(0, $this->logRows(), 'no half-stored consent');
        $connection = $b->entityManager->getConnection();
        self::assertSame(0, $connection->getTransactionNestingLevel());
        self::assertSame(1, (int) $connection->fetchOne('SELECT 1'), 'the connection is still usable');

        $retry = new Response();
        $this->post($this->subscriber($b), $this->consentRequest(), $retry);
        self::assertSame(2, $this->logRows(), 'the retry stores exactly one consent');
        self::assertNotSame([], $retry->headers->getCookies());
    }

    public function testTheHostCanAnswer503(): void
    {
        $this->lockTheLog();
        $answer = $this->expectAnswer($this->subscriber($this->session(), new CookieConsentConflictListener(new ConflictResponsePolicy(503, 2))), $this->consentRequest(), new Response());
        $this->db->entityManager->getConnection()->rollBack();

        self::assertSame(503, $answer->getStatusCode());
        self::assertSame(['X-Conflict-Code' => ConflictCode::RETRY_SAFE, 'Retry-After' => '2'], $answer->getHeaders());
    }

    public function testACommitEarlierInTheRequestAnswersConflict002WithoutRetryAfter(): void
    {
        $ledger    = new CommitLedger();
        $b         = $this->session($ledger);
        $conflicts = new CookieConsentConflictListener(null, $ledger);
        $request   = $this->consentRequest();
        $conflicts->onRequest(new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));

        // The host's controller commits its own work on the same connection first.
        $connection = $b->entityManager->getConnection();
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS cw_host_note (id INT NOT NULL PRIMARY KEY)');
        $connection->executeStatement('DELETE FROM cw_host_note');
        $connection->executeStatement('INSERT INTO cw_host_note (id) VALUES (1)');
        $this->lockTheLog();
        $answer = $this->expectAnswer($this->subscriber($b, $conflicts), $request, new Response());
        $this->db->entityManager->getConnection()->rollBack();

        self::assertSame(409, $answer->getStatusCode());
        self::assertSame(['X-Conflict-Code' => ConflictCode::COMMITTED], $answer->getHeaders());
        self::assertSame(0, $this->logRows(), 'no consent row');
        self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM cw_host_note'), 'the host work stays committed');
    }

    public function testInsideAnOpenHostTransactionOnlyTheLogSavepointRollsBack(): void
    {
        $b          = $this->session();
        $connection = $b->entityManager->getConnection();
        // DBAL 4 always nests with savepoints; on DBAL 3 the host enables them (DoctrineBundle use_savepoints).
        // @phpstan-ignore function.alreadyNarrowedType (DBAL 3 only)
        if (method_exists($connection, 'setNestTransactionsWithSavepoints')) {
            $connection->setNestTransactionsWithSavepoints(true);
        }
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS cw_host_note (id INT NOT NULL PRIMARY KEY)');
        $connection->executeStatement('DELETE FROM cw_host_note');
        $connection->beginTransaction(); // the host's own unit, still open at kernel.response
        $connection->executeStatement('INSERT INTO cw_host_note (id) VALUES (1)');

        $this->lockTheLog();
        $this->expectAnswer($this->subscriber($b), $this->consentRequest(), new Response());
        $this->db->entityManager->getConnection()->rollBack();

        if ($connection->isConnected() && $connection->getTransactionNestingLevel() === 1) {
            $connection->commit();
            self::assertSame(1, (int) $this->db->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM cw_host_note'), 'the host work survives the failed log');
        } else {
            // The server dropped the savepoint: the runner closed the connection instead of leaving it poisoned.
            self::assertFalse($connection->isConnected());
        }
        self::assertSame(0, $this->logRows());
        self::assertSame(1, (int) $connection->fetchOne('SELECT 1'));
    }

    public function testTwoAdminsMakingTwoProfilesTheDefaultAtOnceLeaveExactlyOne(): void
    {
        [$first, $second, $initial] = $this->profiles();
        $b                          = $this->session();
        self::setLockTimeout($b->entityManager->getConnection(), 10);

        $child   = $this->startSaveDefault($first, 1500);
        $started = hrtime(true);
        $profile = $b->entityManager->find(CookieConsentConfig::class, $second);
        self::assertInstanceOf(CookieConsentConfig::class, $profile);
        $profile->setDefault(true);
        (new CookieConsentConfigRepository($b))->saveSettings($profile, $b->entityManager);
        $waited = (hrtime(true) - $started) / 1e9;
        self::assertSame("DONE\n", $this->finish($child));

        self::assertGreaterThan(0.5, $waited, 'the second save waited for the first one\'s locks');
        self::assertSame([$second], $this->defaults(), sprintf('exactly one default (profile %d was the default before)', $initial));
    }

    public function testASaveThatMeetsTheOtherAdminsLockAnswers409Conflict001AndSavesNothing(): void
    {
        [$first, $second] = $this->profiles();
        $b                = $this->session();
        self::setLockTimeout($b->entityManager->getConnection(), 1);

        $child   = $this->startSaveDefault($first, 3000);
        $profile = $b->entityManager->find(CookieConsentConfig::class, $second);
        self::assertInstanceOf(CookieConsentConfig::class, $profile);
        $profile->setDefault(true)->setName('second (renamed)');

        $failure = null;

        try {
            (new CookieConsentConfigRepository($b))->saveSettings($profile, $b->entityManager);
        } catch (TransientStorageFailure $caught) {
            $failure = $caught;
        }
        self::assertSame("DONE\n", $this->finish($child));
        self::assertInstanceOf(TransientStorageFailure::class, $failure);

        $request = Request::create('/admin/cookie-consent-config/2/settings/profile', 'POST');
        $request->attributes->set('_controller', 'Nowo\\CookieConsentBundle\\Controller\\CookieConsentConfigSettingsAdminController::section');
        $event = new ExceptionEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $failure);
        (new CookieConsentConflictListener())->onException($event);
        $answer = $event->getThrowable();
        self::assertInstanceOf(HttpException::class, $answer);
        self::assertSame(409, $answer->getStatusCode());
        self::assertSame(['X-Conflict-Code' => ConflictCode::RETRY_SAFE, 'Retry-After' => '1'], $answer->getHeaders());

        self::assertSame([$first], $this->defaults(), 'the other admin\'s save is whole');
        self::assertSame(0, (int) $this->db->entityManager->getConnection()->fetchOne("SELECT COUNT(*) FROM dashboard_cookie_config WHERE name = 'second (renamed)'"), 'nothing of the failed save');
        self::assertSame(0, $b->entityManager->getConnection()->getTransactionNestingLevel());
        self::assertTrue($b->entityManager->isOpen(), 'the lock failed before the flush: the manager stays open');
    }

    public function testTwoAdminsOpenTheSameProfileTheSecondSaveIsStaleAndTheFirstStaysWhole(): void
    {
        [$first] = $this->profiles();
        $b       = $this->session();
        $pageA   = $this->db->entityManager->find(CookieConsentConfig::class, $first);
        $pageB   = $b->entityManager->find(CookieConsentConfig::class, $first);
        self::assertInstanceOf(CookieConsentConfig::class, $pageA);
        self::assertInstanceOf(CookieConsentConfig::class, $pageB);
        self::assertSame($pageA->getRowVersion(), $pageB->getRowVersion(), 'both admins opened the same version');

        $pageA->setName('first (admin A)');
        (new CookieConsentConfigRepository($this->db))->saveSettings($pageA, $this->db->entityManager, $pageA->getRowVersion());

        $pageB->setName('first (admin B)')->setPriority(9);
        $stale = null;

        try {
            (new CookieConsentConfigRepository($b))->saveSettings($pageB, $b->entityManager, 1);
        } catch (StaleVersionException $caught) {
            $stale = $caught;
        }

        self::assertInstanceOf(StaleVersionException::class, $stale, 'a stale save is a domain answer, never a silent overwrite');
        self::assertSame(['first (admin A)', 0, 2], $this->profileRow($first), 'the first admin\'s save is whole: name, priority, version');
        $connection = $b->entityManager->getConnection();
        self::assertSame(0, $connection->getTransactionNestingLevel());
        self::assertSame(1, (int) $connection->fetchOne('SELECT 1'), 'the connection is still usable');
        self::assertTrue($b->entityManager->isOpen(), 'the manager was not closed by the refused save');

        // The second admin reloads and applies the change again on the current page.
        $b->entityManager->refresh($pageB);
        $pageB->setName('first (admin B)');
        (new CookieConsentConfigRepository($b))->saveSettings($pageB, $b->entityManager, $pageB->getRowVersion());
        self::assertSame(['first (admin B)', 0, 3], $this->profileRow($first));
    }

    public function testASimultaneousProfileSaveWaitsForTheWinnerAndThenIsStale(): void
    {
        [$first] = $this->profiles();
        $b       = $this->session();
        self::setLockTimeout($b->entityManager->getConnection(), 10);
        $page = $b->entityManager->find(CookieConsentConfig::class, $first);
        self::assertInstanceOf(CookieConsentConfig::class, $page);

        // Both pages were opened at version 1; the other process saves first and keeps the row locked for 1.5 s.
        $child   = $this->startSaveEdit('profile', $first, 1, 1500, 'first (winner)');
        $started = hrtime(true);
        $page->setName('first (loser)');
        $stale = null;

        try {
            (new CookieConsentConfigRepository($b))->saveSettings($page, $b->entityManager, 1);
        } catch (StaleVersionException $caught) {
            $stale = $caught;
        }
        $waited = (hrtime(true) - $started) / 1e9;
        self::assertSame("DONE\n", $this->finish($child));

        self::assertGreaterThan(0.5, $waited, 'the loser waited for the winner\'s row lock, then found the version moved');
        self::assertInstanceOf(StaleVersionException::class, $stale);
        self::assertSame(['first (winner)', 0, 2], $this->profileRow($first), 'no lost update: the winner\'s save is the stored row');
        self::assertSame(0, $b->entityManager->getConnection()->getTransactionNestingLevel());
        self::assertTrue($b->entityManager->isOpen());
    }

    public function testASimultaneousDefinitionEditWaitsForTheWinnerAndThenIsStale(): void
    {
        $definition = $this->definition('_ga');
        $b          = $this->session();
        self::setLockTimeout($b->entityManager->getConnection(), 10);
        $page = $b->entityManager->find(CookieDefinition::class, $definition);
        self::assertInstanceOf(CookieDefinition::class, $page);

        $child   = $this->startSaveEdit('definition', $definition, 1, 1500, '_ga_winner');
        $started = hrtime(true);
        $page->setName('_ga_loser');
        $stale = null;

        try {
            (new CookieDefinitionRepository($b))->saveEdit($page, $b->entityManager, 1);
        } catch (StaleVersionException $caught) {
            $stale = $caught;
        }
        $waited = (hrtime(true) - $started) / 1e9;
        self::assertSame("DONE\n", $this->finish($child));

        self::assertGreaterThan(0.5, $waited, 'the loser waited for the winner\'s row lock');
        self::assertInstanceOf(StaleVersionException::class, $stale);
        $row = $this->db->entityManager->getConnection()->fetchAssociative('SELECT name, row_version FROM dashboard_cookie_definition WHERE id = ?', [$definition]);
        self::assertSame(['name' => '_ga_winner', 'row_version' => 2], ['name' => $row['name'] ?? null, 'row_version' => (int) ($row['row_version'] ?? 0)], 'no lost update');
        self::assertSame(0, $b->entityManager->getConnection()->getTransactionNestingLevel());
        self::assertTrue($b->entityManager->isOpen());
    }

    public function testADefinitionEditThatMeetsTheWinnersLockPastItsTimeoutIsAConflictNotAStaleAnswer(): void
    {
        $definition = $this->definition('_ga');
        $b          = $this->session();
        self::setLockTimeout($b->entityManager->getConnection(), 1);
        $page = $b->entityManager->find(CookieDefinition::class, $definition);
        self::assertInstanceOf(CookieDefinition::class, $page);

        $child = $this->startSaveEdit('definition', $definition, 1, 3000, '_ga_winner');
        $page->setName('_ga_loser');
        $failure = null;

        try {
            (new CookieDefinitionRepository($b))->saveEdit($page, $b->entityManager, 1);
        } catch (TransientStorageFailure $caught) {
            $failure = $caught;
        }
        self::assertSame("DONE\n", $this->finish($child));

        self::assertInstanceOf(TransientStorageFailure::class, $failure, 'a lock wait timeout stays CONFLICT_001 material: retry is safe');
        $row = $this->db->entityManager->getConnection()->fetchAssociative('SELECT name, row_version FROM dashboard_cookie_definition WHERE id = ?', [$definition]);
        self::assertSame('_ga_winner', $row['name'] ?? null);
        self::assertSame(0, $b->entityManager->getConnection()->getTransactionNestingLevel());
    }

    public function testMakingAProfileTheDefaultMakesAnOpenPageOfTheDemotedProfileStale(): void
    {
        [$first, , $initial] = $this->profiles();
        $b                   = $this->session();
        $oldDefault          = $b->entityManager->find(CookieConsentConfig::class, $initial);
        self::assertInstanceOf(CookieConsentConfig::class, $oldDefault);
        self::assertTrue($oldDefault->isDefault());

        $page = $this->db->entityManager->find(CookieConsentConfig::class, $first);
        self::assertInstanceOf(CookieConsentConfig::class, $page);
        $page->setDefault(true);
        (new CookieConsentConfigRepository($this->db))->saveSettings($page, $this->db->entityManager, 1);

        // The admin who opened the old default's page before that save cannot write the old flag back.
        $oldDefault->setDefault(true)->setName('initial (stale page)');
        $this->expectException(StaleVersionException::class);
        (new CookieConsentConfigRepository($b))->saveSettings($oldDefault, $b->entityManager, 1);
    }

    public function testTwoDefaultProfilesNeverResultFromAStalePage(): void
    {
        [$first, , $initial] = $this->profiles();
        $b                   = $this->session();
        $page                = $b->entityManager->find(CookieConsentConfig::class, $initial);
        self::assertInstanceOf(CookieConsentConfig::class, $page);

        $other = $this->db->entityManager->find(CookieConsentConfig::class, $first);
        self::assertInstanceOf(CookieConsentConfig::class, $other);
        $other->setDefault(true);
        (new CookieConsentConfigRepository($this->db))->saveSettings($other, $this->db->entityManager, 1);

        try {
            (new CookieConsentConfigRepository($b))->saveSettings($page->setName('initial (stale page)'), $b->entityManager, 1);
            self::fail('The demoted profile\'s page is stale.');
        } catch (StaleVersionException) {
        }

        self::assertSame([$first], $this->defaults(), 'exactly one default');
    }

    private function session(?CommitLedger $ledger = null): ConsentLogDatabase
    {
        $session = ConsentLogDatabase::connect($ledger instanceof CommitLedger ? [new CommitTrackingMiddleware($ledger)] : []);
        self::setLockTimeout($session->entityManager->getConnection(), 2);
        $this->sessions[] = $session;

        return $session;
    }

    /**
     * Session A holds a lock that blocks every new log row: InnoDB next-key locks up to the supremum, a PostgreSQL
     * table lock. The caller rolls A back.
     */
    private function lockTheLog(): void
    {
        $a = $this->db->entityManager->getConnection();
        $a->beginTransaction();
        if ($a->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $a->executeStatement('LOCK TABLE dashboard_cookie_log IN EXCLUSIVE MODE');

            return;
        }

        $a->fetchAllAssociative('SELECT id FROM dashboard_cookie_log WHERE id >= 0 FOR UPDATE');
    }

    private function subscriber(ConsentLogDatabase $session, ?CookieConsentConflictListener $conflicts = null): CookieConsentFormSubscriber
    {
        $logger = new CookieLogger($session->entityManager, $this->stack, new MockClock(), null, $session);

        return new CookieConsentFormSubscriber($this->formFactory(), $logger, new CookieHandler(false, new MockClock()), true, null, null, null, null, null, $conflicts);
    }

    private function formFactory(): FormFactoryInterface
    {
        $form = $this->createStub(FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn(self::CATEGORIES);
        $form->method('has')->willReturn(false);
        $factory = $this->createStub(FormFactoryInterface::class);
        $factory->method('create')->willReturn($form);

        return $factory;
    }

    private function consentRequest(string $key = self::KEY): Request
    {
        $request = Request::create('/host/page', 'POST', server: ['REMOTE_ADDR' => '198.51.100.7']);
        $request->cookies->set(CookieNameEnum::COOKIE_CONSENT_KEY_NAME, $key);

        return $request;
    }

    private function post(CookieConsentFormSubscriber $subscriber, Request $request, ?Response $response = null): void
    {
        $this->stack->push($request);

        try {
            $subscriber->onResponse(new ResponseEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response ?? new Response()));
        } finally {
            $this->stack->pop();
        }
    }

    private function expectAnswer(CookieConsentFormSubscriber $subscriber, Request $request, Response $response): HttpException
    {
        try {
            $this->post($subscriber, $request, $response);
        } catch (HttpException $answer) {
            return $answer;
        }

        self::fail('A lock conflict of the consent log must answer a conflict.');
    }

    private function logRows(): int
    {
        return (int) $this->db->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM dashboard_cookie_log');
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<string>
     */
    private function keysSorted(array $rows): array
    {
        $keys = array_map(static fn (array $row): string => (string) $row['cookie_consent_key'], $rows);
        sort($keys);

        return $keys;
    }

    /**
     * Three enabled profiles, the third one the default: [first, second, initial default] ids.
     *
     * @return array{int, int, int}
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
     * @return array{string, int, int} name, priority and row version of a profile
     */
    private function profileRow(int $id): array
    {
        $row = $this->db->entityManager->getConnection()->fetchAssociative('SELECT name, priority, row_version FROM dashboard_cookie_config WHERE id = ?', [$id]);
        self::assertIsArray($row);

        return [(string) $row['name'], (int) $row['priority'], (int) $row['row_version']];
    }

    private function definition(string $name): int
    {
        [$profile] = $this->profiles();
        $em        = $this->db->entityManager;
        $owner     = $em->find(CookieConsentConfig::class, $profile);
        self::assertInstanceOf(CookieConsentConfig::class, $owner);
        $definition = (new CookieDefinition())->setName($name)->setConfig($owner);
        $em->persist($definition);
        $em->flush();
        $id = (int) $definition->getId();
        $em->clear();

        return $id;
    }

    /**
     * @return array{0: resource, 1: array<int, resource>}
     */
    private function startSaveEdit(string $kind, int $id, int $token, int $holdMs, string $name): array
    {
        // @phpstan-ignore frankenphp.classic.noUnlimitedIoTimeout (test-only child process; pipes read with stream_set_timeout)
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__, 2) . '/Fixtures/concurrency/save-edit.php', $kind, (string) $id, (string) $token, (string) $holdMs, $name],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            [ConsentLogDatabase::URL_ENV => (string) getenv(ConsentLogDatabase::URL_ENV), 'PATH' => (string) getenv('PATH')],
        );
        self::assertTrue(is_resource($process));
        // Bounded: the child holds its locks for $holdMs and its own session has a lock timeout.
        stream_set_timeout($pipes[1], 30);
        stream_set_timeout($pipes[2], 30);
        $line = fgets($pipes[1]);
        if ($line !== "LOCKED\n") {
            throw new RuntimeException('The second process did not take its locks: ' . trim((string) $line) . ' ' . stream_get_contents($pipes[2]));
        }

        return [$process, $pipes];
    }

    /**
     * @return list<int> ids of the enabled default profiles
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
        // Bounded: the child holds its locks for $holdMs and its own session has a lock timeout.
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
