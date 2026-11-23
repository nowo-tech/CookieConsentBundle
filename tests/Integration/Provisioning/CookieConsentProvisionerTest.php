<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Integration\Provisioning;

use Closure;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use LogicException;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieDefinition;
use Nowo\CookieConsentBundle\Provisioning\CookieConsentProvisioner;
use Nowo\CookieConsentBundle\Provisioning\CookieConsentProvisioningException;
use Nowo\CookieConsentBundle\Provisioning\CookieDefinitionSpec;
use Nowo\CookieConsentBundle\Provisioning\CookieDefinitionSpecs;
use Nowo\CookieConsentBundle\Provisioning\CookieDefinitionTranslationSpec;
use Nowo\CookieConsentBundle\Provisioning\DefaultProfileSpec;
use Nowo\CookieConsentBundle\Provisioning\ProfileTranslationSpec;
use Nowo\CookieConsentBundle\Tests\Support\PersonalData\ConsentLogDatabase;
use Nowo\CookieConsentBundle\Tests\Support\Provisioning\FlushProbe;
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\TransactionDoctrine\PrivateManager\PrivateEntityManager;
use PHPUnit\Framework\TestCase;
use Throwable;

use function array_map;
use function is_bool;
use function json_decode;
use function sort;

use const PHP_INT_MAX;
use const SORT_STRING;

/**
 * 1.36 provisioning port on a real Doctrine stack (in-memory SQLite, or the database of
 * COOKIE_CONSENT_TEST_DATABASE_URL): creation, idempotency (a second run reports unchanged and writes nothing),
 * updates that advance the row version, exact-key matching, retries, and the application's manager left alone.
 */
final class CookieConsentProvisionerTest extends TestCase
{
    private ConsentLogDatabase $db;

    private PrivateEntityManager $private;

    private CookieConsentProvisioner $provisioner;

    protected function setUp(): void
    {
        $this->db          = ConsentLogDatabase::create();
        $this->private     = new PrivateEntityManager($this->db, 'default', CookieConsentConfig::class, 'test');
        $this->provisioner = new CookieConsentProvisioner($this->private);
    }

    protected function tearDown(): void
    {
        $this->db->close();
    }

    public function testCreatesTheDefaultProfileThenReportsItUnchanged(): void
    {
        $spec = $this->profileSpec();

        $first = $this->provisioner->ensureDefaultProfile($spec);
        self::assertSame([1, 0, 0], [$first->created, $first->updated, $first->unchanged]);
        self::assertTrue($first->hasChanges());
        self::assertFalse($this->private->isCreated(), 'the provisioning manager is dropped after the call');

        $row = $this->fetch('SELECT * FROM dashboard_cookie_config');
        self::assertSame($first->profileId, (int) $row['id']);
        self::assertSame('Demo', $row['name']);
        self::assertSame('dark', $row['color_theme']);
        self::assertSame('bar', $row['consent_modal_layout']);
        self::assertNull($row['consent_modal_position_x']);
        self::assertTrue((bool) $row['is_default']);
        self::assertTrue((bool) $row['enabled']);
        self::assertSame(['admin_*'], json_decode((string) $row['route_patterns'], true));
        self::assertSame(1, (int) $row['row_version']);
        self::assertSame(['en', 'es'], $this->column('SELECT locale FROM dashboard_cookie_config_translation ORDER BY locale'));
        self::assertSame('Cookies', $this->connection()->fetchOne('SELECT consent_modal_title FROM dashboard_cookie_config_translation WHERE locale = ?', ['en']));

        $second = $this->provisioner->ensureDefaultProfile($spec);
        self::assertSame([0, 0, 1], [$second->created, $second->updated, $second->unchanged]);
        self::assertFalse($second->hasChanges());
        self::assertSame($first->profileId, $second->profileId);
        self::assertSame(1, (int) $this->connection()->fetchOne('SELECT row_version FROM dashboard_cookie_config'), 'nothing written');
        self::assertSame(1, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM dashboard_cookie_config'));
    }

    public function testUpdatesOnlyTheGivenSettingsAndAdvancesTheRowVersion(): void
    {
        $profileId = $this->seedProfile();
        $this->connection()->executeStatement('UPDATE dashboard_cookie_config SET hide_from_bots = ?, priority = ? WHERE id = ?', [false, 7, $profileId], ['boolean', 'integer', 'integer']);

        $result = $this->provisioner->ensureDefaultProfile(DefaultProfileSpec::create()
            ->withColorTheme('elegant-black')
            ->withTranslation(new ProfileTranslationSpec('en', 'New title', 'Text', 'All', 'Necessary')));

        self::assertSame([0, 1, 0], [$result->created, $result->updated, $result->unchanged]);
        self::assertSame($profileId, $result->profileId);
        $row = $this->fetch('SELECT * FROM dashboard_cookie_config');
        self::assertSame('elegant-black', $row['color_theme']);
        self::assertFalse((bool) $row['hide_from_bots'], 'a setting the specification does not give is kept');
        self::assertSame(7, (int) $row['priority']);
        self::assertSame(2, (int) $row['row_version'], 'an admin page opened before is now stale');
        self::assertSame('New title', $this->connection()->fetchOne('SELECT consent_modal_title FROM dashboard_cookie_config_translation WHERE locale = ?', ['en']));
        self::assertNull($this->connection()->fetchOne('SELECT consent_modal_label FROM dashboard_cookie_config_translation WHERE locale = ?', ['en']), 'a complete copy: optional texts not given are cleared');
        self::assertSame('Cookies ES', $this->connection()->fetchOne('SELECT consent_modal_title FROM dashboard_cookie_config_translation WHERE locale = ?', ['es']), 'other locales are kept');
    }

    public function testATranslationOnlyChangeCountsAsAnUpdate(): void
    {
        $this->seedProfile();

        $result = $this->provisioner->ensureDefaultProfile(DefaultProfileSpec::create()->withTranslation(new ProfileTranslationSpec('fr', 'Témoins', 'Texte', 'Tout', 'Nécessaires')));

        self::assertSame([0, 1, 0], [$result->created, $result->updated, $result->unchanged]);
        self::assertSame(['en', 'es', 'fr'], $this->column('SELECT locale FROM dashboard_cookie_config_translation ORDER BY locale'));
    }

    public function testEntityNormalizationIsNotAChange(): void
    {
        $this->provisioner->ensureDefaultProfile(DefaultProfileSpec::create()->withName('  Demo  ')->withRoutePatterns(' a ', 'a', '', 'b'));

        $again = $this->provisioner->ensureDefaultProfile(DefaultProfileSpec::create()->withName('  Demo  ')->withRoutePatterns(' a ', 'a', '', 'b'));

        self::assertSame(1, $again->unchanged);
        self::assertSame('Demo', $this->connection()->fetchOne('SELECT name FROM dashboard_cookie_config'));
    }

    public function testADisabledOrNonDefaultProfileIsNotTheDefaultProfile(): void
    {
        $em = $this->db->entityManager;
        $em->persist((new CookieConsentConfig())->setName('Disabled default')->setEnabled(false)->setDefault(true));
        $em->persist((new CookieConsentConfig())->setName('Other')->setEnabled(true)->setDefault(false));
        $em->flush();
        $em->clear();

        $result = $this->provisioner->ensureDefaultProfile(DefaultProfileSpec::create()->withName('Seeded'));

        self::assertSame(1, $result->created);
        self::assertSame(['Seeded'], $this->column('SELECT name FROM dashboard_cookie_config WHERE enabled = ? AND is_default = ?', [true, true]));
    }

    public function testUpsertsCookieDefinitionsByExactName(): void
    {
        $profileId = $this->seedProfile();
        $em        = $this->db->entityManager;
        $config    = $em->find(CookieConsentConfig::class, $profileId);
        self::assertInstanceOf(CookieConsentConfig::class, $config);
        $em->persist((new CookieDefinition())->setName('NID')->setCategory('marketing')->setDuration('6 months')->setType(CookieDefinition::TYPE_THIRD_PARTY)->setConfig($config));
        $em->persist((new CookieDefinition())->setName('_kept')->setCategory('required')->setConfig($config));
        $em->flush();
        $em->clear();

        $specs = new CookieDefinitionSpecs(
            (new CookieDefinitionSpec('_ga', 'analytics', '2 years', CookieDefinition::TYPE_THIRD_PARTY, 1))
                ->withTranslation(new CookieDefinitionTranslationSpec('en', 'Google', 'Statistics'))
                ->withTranslation(new CookieDefinitionTranslationSpec('es', 'Google', 'Estadísticas')),
            new CookieDefinitionSpec('NID', 'marketing', '6 months', CookieDefinition::TYPE_THIRD_PARTY, 0, false),
            new CookieDefinitionSpec('session', 'required', 'Session', sortOrder: 2),
        );

        $first = $this->provisioner->upsertCookieDefinitions($specs);
        self::assertSame([2, 0, 1], [$first->created, $first->updated, $first->unchanged]);
        self::assertSame($profileId, $first->profileId);
        self::assertSame(['NID', '_ga', '_kept', 'session'], $this->column('SELECT name FROM dashboard_cookie_definition ORDER BY name'), 'nothing is deleted');
        self::assertTrue((bool) $this->connection()->fetchOne('SELECT allowed_by_default FROM dashboard_cookie_definition WHERE name = ?', ['session']), 'required: pre-ticked by default');
        self::assertSame(['en', 'es'], $this->column('SELECT t.locale FROM dashboard_cookie_definition_translation t JOIN dashboard_cookie_definition d ON d.id = t.definition_id WHERE d.name = ? ORDER BY t.locale', ['_ga']));

        $second = $this->provisioner->upsertCookieDefinitions($specs);
        self::assertSame([0, 0, 3], [$second->created, $second->updated, $second->unchanged]);

        // "nid" is another cookie than "NID" (exact key); a changed purpose updates "_ga" and advances its version.
        $third = $this->provisioner->upsertCookieDefinitions(new CookieDefinitionSpecs(
            (new CookieDefinitionSpec('_ga', 'analytics', '2 years', CookieDefinition::TYPE_THIRD_PARTY, 1))->withTranslation(new CookieDefinitionTranslationSpec('en', 'Google', 'Audience statistics')),
        ));
        self::assertSame([0, 1, 0], [$third->created, $third->updated, $third->unchanged]);
        self::assertSame(2, (int) $this->connection()->fetchOne('SELECT row_version FROM dashboard_cookie_definition WHERE name = ?', ['_ga']));
        self::assertSame('Estadísticas', $this->connection()->fetchOne('SELECT t.purpose FROM dashboard_cookie_definition_translation t JOIN dashboard_cookie_definition d ON d.id = t.definition_id WHERE d.name = ? AND t.locale = ?', ['_ga', 'es']), 'other locales kept');
    }

    /**
     * REQ-SEC-006: "nid" is never matched to the stored "NID". Where the server compares exactly (SQLite, PostgreSQL)
     * it is another cookie; where the default MySQL / MariaDB collation folds case, the unique key refuses it and the
     * call fails with a provisioning exception instead of updating "NID".
     */
    public function testALookAlikeNameIsNeverMatchedToTheStoredOne(): void
    {
        $this->seedProfile();
        $this->provisioner->upsertCookieDefinitions(new CookieDefinitionSpecs(new CookieDefinitionSpec('NID', 'marketing', '6 months')));
        $lookAlike = new CookieDefinitionSpecs(new CookieDefinitionSpec('nid', 'marketing', '1 day'));

        if ($this->connection()->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            try {
                $this->provisioner->upsertCookieDefinitions($lookAlike);
                self::fail('Expected the unique key to refuse the look-alike name.');
            } catch (CookieConsentProvisioningException $exception) {
                self::assertInstanceOf(UniqueConstraintViolationException::class, $exception->getPrevious());
            }

            self::assertSame(['NID'], $this->column('SELECT name FROM dashboard_cookie_definition'));
            self::assertSame('6 months', $this->connection()->fetchOne('SELECT duration FROM dashboard_cookie_definition'), 'the stored cookie was not updated');

            return;
        }

        $result = $this->provisioner->upsertCookieDefinitions($lookAlike);

        self::assertSame(1, $result->created);
        self::assertSame(['NID', 'nid'], $this->column('SELECT name FROM dashboard_cookie_definition'));
    }

    public function testDefinitionsNeedTheDefaultProfile(): void
    {
        $this->expectException(CookieConsentProvisioningException::class);
        $this->expectExceptionMessage('ensureDefaultProfile()');

        try {
            $this->provisioner->upsertCookieDefinitions(new CookieDefinitionSpecs(new CookieDefinitionSpec('_ga')));
        } finally {
            self::assertSame(0, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM dashboard_cookie_definition'));
            self::assertFalse($this->private->isCreated());
        }
    }

    public function testTheApplicationsManagerIsNeverFlushedClearedOrReset(): void
    {
        $profileId = $this->seedProfile();
        $host      = $this->db->entityManager;
        $loaded    = $host->find(CookieConsentConfig::class, $profileId);
        self::assertInstanceOf(CookieConsentConfig::class, $loaded);
        $loaded->setName('Pending host change');
        $pending = (new CookieConsentConfig())->setName('Pending host insert')->setEnabled(false);
        $host->persist($pending);

        $this->provisioner->ensureDefaultProfile(DefaultProfileSpec::create()->withColorTheme('dark'));
        $this->provisioner->upsertCookieDefinitions(new CookieDefinitionSpecs(new CookieDefinitionSpec('_ga', 'analytics')));

        self::assertSame('Demo', $this->connection()->fetchOne('SELECT name FROM dashboard_cookie_config WHERE id = ?', [$profileId]), 'the host change was not flushed');
        self::assertSame(1, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM dashboard_cookie_config'), 'nor its insert');
        self::assertTrue($host->contains($loaded), 'the host manager was not cleared');
        self::assertTrue($host->getUnitOfWork()->isScheduledForInsert($pending));
        self::assertTrue($host->isOpen());
    }

    public function testAUniqueKeyConflictIsRetriedWithAFreshUnitOfWork(): void
    {
        $this->seedProfile();
        $rogue = $this->rogueInsertOnFlush(1);

        $result = $this->provisioner->upsertCookieDefinitions(new CookieDefinitionSpecs(new CookieDefinitionSpec('_ga', 'analytics')));

        self::assertSame(1, $result->created);
        self::assertSame(2, $rogue->flushes, 'one failed attempt (rolled back with the rogue row), one that committed');
        self::assertSame(['_ga'], $this->column('SELECT name FROM dashboard_cookie_definition'));
    }

    public function testAUniqueKeyConflictOnEveryAttemptIsAProvisioningException(): void
    {
        $this->seedProfile();
        $rogue = $this->rogueInsertOnFlush(PHP_INT_MAX);

        try {
            $this->provisioner->upsertCookieDefinitions(new CookieDefinitionSpecs(new CookieDefinitionSpec('_ga', 'analytics')));
            self::fail('Expected a provisioning exception.');
        } catch (CookieConsentProvisioningException $exception) {
            self::assertInstanceOf(UniqueConstraintViolationException::class, $exception->getPrevious());
        }

        self::assertSame(CookieConsentProvisioner::MAX_ATTEMPTS, $rogue->flushes);
        self::assertSame(0, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM dashboard_cookie_definition'));
        self::assertFalse($this->private->isCreated());
    }

    public function testInsideATransactionOfTheCallerThereIsOneAttempt(): void
    {
        $this->seedProfile();
        $rogue      = $this->rogueInsertOnFlush(1);
        $connection = $this->connection();
        $connection->beginTransaction();

        try {
            $this->provisioner->upsertCookieDefinitions(new CookieDefinitionSpecs(new CookieDefinitionSpec('_ga', 'analytics')));
            self::fail('Expected a provisioning exception.');
        } catch (CookieConsentProvisioningException) {
            self::assertSame(1, $rogue->flushes);
            self::assertSame(1, $connection->getTransactionNestingLevel(), 'only the savepoint was rolled back');
        } finally {
            $connection->rollBack();
        }
    }

    public function testInsideATransactionOfTheCallerItsCommitDecides(): void
    {
        $connection = $this->connection();
        $connection->beginTransaction();
        $result = $this->provisioner->ensureDefaultProfile(DefaultProfileSpec::create()->withName('In the caller'));
        self::assertSame(1, $result->created);
        $connection->rollBack();

        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM dashboard_cookie_config'));
    }

    public function testALockConflictIsRetriedThenRethrown(): void
    {
        $this->seedProfile();
        $listener = $this->failOnFlush(static fn (): TransientStorageFailure => new TransientStorageFailure(1), 1);
        $result   = $this->provisioner->ensureDefaultProfile(DefaultProfileSpec::create()->withName('After a conflict'));
        self::assertSame(1, $result->updated);
        self::assertSame(2, $listener->flushes);

        $listener = $this->failOnFlush(static fn (): TransientStorageFailure => new TransientStorageFailure(1), PHP_INT_MAX);
        try {
            $this->provisioner->ensureDefaultProfile(DefaultProfileSpec::create()->withName('Never'));
            self::fail('Expected the lock conflict.');
        } catch (TransientStorageFailure) {
            self::assertSame(CookieConsentProvisioner::MAX_ATTEMPTS, $listener->flushes);
        }
    }

    public function testOtherFailuresAreNotRetried(): void
    {
        $this->seedProfile();
        $listener = $this->failOnFlush(static fn (): LogicException => new LogicException('Host listener failure'), PHP_INT_MAX);

        try {
            $this->provisioner->ensureDefaultProfile(DefaultProfileSpec::create()->withName('Never'));
            self::fail('Expected the host listener failure.');
        } catch (LogicException $exception) {
            self::assertSame('Host listener failure', $exception->getMessage());
        }

        self::assertSame(1, $listener->flushes);
        self::assertSame('Demo', $this->connection()->fetchOne('SELECT name FROM dashboard_cookie_config'));
    }

    public function testAPlainManagerIsClearedAfterEveryCall(): void
    {
        $db          = ConsentLogDatabase::create();
        $manager     = $db->entityManager;
        $provisioner = new CookieConsentProvisioner($manager);

        $provisioner->ensureDefaultProfile(DefaultProfileSpec::create()->withName('Plain'));

        self::assertSame(0, $manager->getUnitOfWork()->size());
        $db->close();
    }

    public function testAClosedPlainManagerIsLeftClosed(): void
    {
        $db          = ConsentLogDatabase::create();
        $manager     = $db->entityManager;
        $provisioner = new CookieConsentProvisioner($manager);
        $manager->getEventManager()->addEventListener([Events::onFlush], new class {
            public function onFlush(OnFlushEventArgs $args): never
            {
                $args->getObjectManager()->close();

                throw new LogicException('closed');
            }
        });

        try {
            $provisioner->ensureDefaultProfile(DefaultProfileSpec::create()->withName('Plain'));
            self::fail('Expected the failure.');
        } catch (LogicException) {
            self::assertFalse($manager->isOpen());
        } finally {
            $db->close();
        }
    }

    private function profileSpec(): DefaultProfileSpec
    {
        return DefaultProfileSpec::create()
            ->withName('Demo')
            ->withColorTheme('dark')
            ->withConsentModalLayout('bar')
            ->withConsentModalVariant('inline')
            ->withConsentModalPositionY('bottom')
            ->withConsentModalPositionX(null)
            ->withRoutePatterns('admin_*')
            ->withGranularCookieSelection(true)
            ->withPreferencesBubbleEnabled(true)
            ->withPreferencesBubbleBorderColor('#30363c')
            ->withTranslation(new ProfileTranslationSpec('en', 'Cookies', 'We use cookies.', 'Accept all', 'Only necessary', consentModalLabel: 'Cookie banner'))
            ->withTranslation(new ProfileTranslationSpec('es', 'Cookies ES', 'Usamos cookies.', 'Aceptar todo', 'Solo necesarias'));
    }

    private function seedProfile(): int
    {
        return $this->provisioner->ensureDefaultProfile($this->profileSpec())->profileId;
    }

    /**
     * A writer that inserts the same cookie name in the provisioning transaction, on the first `$times` flushes.
     */
    private function rogueInsertOnFlush(int $times): FlushProbe
    {
        return $this->probe(static function (OnFlushEventArgs $args, int $flush) use ($times): void {
            $em = $args->getObjectManager();
            foreach ($em->getUnitOfWork()->getScheduledEntityInsertions() as $entity) {
                if ($entity instanceof CookieDefinition && $flush <= $times) {
                    $em->getConnection()->insert('dashboard_cookie_definition', [
                        'name'               => $entity->getName(),
                        'duration'           => '',
                        'category'           => 'required',
                        'type'               => 'first_party',
                        'sort_order'         => 0,
                        'allowed_by_default' => 0,
                        'row_version'        => 1,
                        'config_id'          => $entity->getConfig()?->getId(),
                    ]);
                }
            }
        });
    }

    /**
     * @param Closure(): Throwable $failure
     */
    private function failOnFlush(Closure $failure, int $times): FlushProbe
    {
        return $this->probe(static function (OnFlushEventArgs $args, int $flush) use ($failure, $times): void {
            if ($flush <= $times) {
                throw $failure();
            }
        });
    }

    /**
     * @param Closure(OnFlushEventArgs, int): void $onFlush
     */
    private function probe(Closure $onFlush): FlushProbe
    {
        $probe = new FlushProbe($onFlush);
        // The application's event manager, shared by the provisioning manager.
        $this->db->entityManager->getEventManager()->addEventListener([Events::onFlush], $probe);

        return $probe;
    }

    private function connection(): Connection
    {
        return $this->db->entityManager->getConnection();
    }

    /**
     * @return array<string, mixed>
     */
    private function fetch(string $sql): array
    {
        $row = $this->connection()->fetchAssociative($sql);
        self::assertIsArray($row);

        return $row;
    }

    /**
     * @param list<mixed> $params
     *
     * @return list<string>
     */
    private function column(string $sql, array $params = []): array
    {
        $types = array_map(static fn (mixed $param): string => is_bool($param) ? 'boolean' : 'string', $params);

        // Sorted in PHP (byte order): ORDER BY follows the server's collation.
        $values = array_map(strval(...), $this->connection()->fetchFirstColumn($sql, $params, $types));
        sort($values, SORT_STRING);

        return $values;
    }
}
