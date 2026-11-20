<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Integration\Database;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\CookieConsentBundle\Concurrency\RowVersionGuard;
use Nowo\CookieConsentBundle\Concurrency\StaleVersionException;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieDefinition;
use Nowo\CookieConsentBundle\Entity\CookieDefinitionTranslation;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;
use Nowo\CookieConsentBundle\Repository\CookieDefinitionRepository;
use Nowo\CookieConsentBundle\Tests\Support\PersonalData\ConsentLogDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function sprintf;

/**
 * REQ-RUNTIME-003 rule 4 (1.33): the admin edits of a profile and of a cookie definition are checked against the row
 * version of the page they were made on. In-memory SQLite, or the database of COOKIE_CONSENT_TEST_DATABASE_URL (MySQL,
 * MariaDB and PostgreSQL in the "Real databases" job).
 */
#[Group('database')]
final class RowVersionDatabaseTest extends TestCase
{
    private ConsentLogDatabase $db;

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->db = ConsentLogDatabase::create();
        $this->em = $this->db->entityManager;
    }

    protected function tearDown(): void
    {
        $this->db->close();
    }

    public function testANewRowStartsAtVersionOne(): void
    {
        $config = $this->profile('A', true);

        self::assertSame(1, $config->getRowVersion());
        self::assertSame(1, $this->storedVersion('dashboard_cookie_config', (int) $config->getId()));
    }

    public function testTheGuardAdvancesTheRowOnceAndRefusesTheSameTokenTwice(): void
    {
        $config = $this->profile('A', true);
        $id     = (int) $config->getId();

        RowVersionGuard::advance($this->em, $config, 1);
        self::assertSame(2, $config->getRowVersion());
        self::assertSame(2, $this->storedVersion('dashboard_cookie_config', $id));

        try {
            RowVersionGuard::advance($this->em, $config, 1);
            self::fail('The version 1 page is stale.');
        } catch (StaleVersionException $stale) {
            self::assertSame(CookieConsentConfig::class, $stale->entityClass);
            self::assertSame($id, $stale->id);
            self::assertSame(1, $stale->expected);
        }
        self::assertSame(2, $this->storedVersion('dashboard_cookie_config', $id), 'a refused token advances nothing');
        self::assertSame(2, $config->getRowVersion());
    }

    public function testAMissingTokenIsStaleAndADeletedRowIsStale(): void
    {
        $config = $this->profile('A', true);
        $id     = (int) $config->getId();

        try {
            RowVersionGuard::advance($this->em, $config, 0);
            self::fail('Token 0 matches no row.');
        } catch (StaleVersionException) {
            self::assertSame(1, $this->storedVersion('dashboard_cookie_config', $id));
        }

        $this->em->getConnection()->executeStatement('DELETE FROM dashboard_cookie_config WHERE id = ?', [$id]);
        $this->expectException(StaleVersionException::class);
        RowVersionGuard::advance($this->em, $config, 1);
    }

    public function testASettingsSaveWithTheCurrentTokenAdvancesTheVersion(): void
    {
        $config     = $this->profile('A', false);
        $repository = new CookieConsentConfigRepository($this->db);

        $config->setName('A (saved)');
        $repository->saveSettings($config, $this->em, 1);

        self::assertSame('A (saved)', $this->em->getConnection()->fetchOne('SELECT name FROM dashboard_cookie_config WHERE id = ?', [$config->getId()]));
        self::assertSame(2, $this->storedVersion('dashboard_cookie_config', (int) $config->getId()));
        self::assertSame(0, $this->em->getConnection()->getTransactionNestingLevel());
    }

    public function testAStaleSettingsSaveWritesNothingAndLeavesTheManagerOpen(): void
    {
        $config     = $this->profile('A', false);
        $repository = new CookieConsentConfigRepository($this->db);
        // Another admin saved the profile after this page was opened (version 1).
        $this->em->getConnection()->executeStatement('UPDATE dashboard_cookie_config SET name = ?, row_version = 2 WHERE id = ?', ['Other admin', $config->getId()]);

        $config->setName('A (stale page)');

        try {
            $repository->saveSettings($config, $this->em, 1);
            self::fail('A stale save must be refused.');
        } catch (StaleVersionException) {
        }

        self::assertSame('Other admin', $this->em->getConnection()->fetchOne('SELECT name FROM dashboard_cookie_config WHERE id = ?', [$config->getId()]), 'the other admin\'s save is whole');
        self::assertSame(2, $this->storedVersion('dashboard_cookie_config', (int) $config->getId()));
        self::assertSame(0, $this->em->getConnection()->getTransactionNestingLevel());
        self::assertTrue($this->em->isOpen());
    }

    public function testASettingsSaveWithoutATokenIsNotChecked(): void
    {
        $config     = $this->profile('A', false);
        $repository = new CookieConsentConfigRepository($this->db);
        $this->em->getConnection()->executeStatement('UPDATE dashboard_cookie_config SET row_version = 5 WHERE id = ?', [$config->getId()]);

        $config->setName('Host code');
        $repository->saveSettings($config, $this->em);

        self::assertSame('Host code', $this->em->getConnection()->fetchOne('SELECT name FROM dashboard_cookie_config WHERE id = ?', [$config->getId()]));
    }

    public function testMakingAProfileTheDefaultAdvancesTheVersionOfTheProfilesItDemotes(): void
    {
        $old        = $this->profile('Old default', true);
        $new        = $this->profile('New default', false);
        $repository = new CookieConsentConfigRepository($this->db);

        $new->setDefault(true);
        $repository->saveSettings($new, $this->em, 1);

        self::assertSame(2, $this->storedVersion('dashboard_cookie_config', (int) $old->getId()), 'a page of the demoted profile opened before is now stale');
        self::assertSame(2, $old->getRowVersion(), 'the loaded profile was refreshed');
        self::assertFalse($old->isDefault());

        // The admin who still has the demoted profile's old page open cannot write the old flag back.
        $this->expectException(StaleVersionException::class);
        $old->setDefault(true);
        $repository->saveSettings($old, $this->em, 1);
    }

    public function testADefinitionEditAdvancesTheVersionAndSavesTheTranslations(): void
    {
        $definition = $this->definition('_ga');
        $repository = new CookieDefinitionRepository($this->db);

        // Only the translation changes: the definition row itself is untouched, and still advances.
        $definition->addTranslation((new CookieDefinitionTranslation())->setLocale('en')->setProvider('Google')->setPurpose('Statistics'));
        $repository->saveEdit($definition, $this->em, 1);

        self::assertSame(2, $this->storedVersion('dashboard_cookie_definition', (int) $definition->getId()));
        self::assertSame(['Google'], $this->em->getConnection()->fetchFirstColumn('SELECT provider FROM dashboard_cookie_definition_translation'));
    }

    public function testAStaleDefinitionEditWritesNothing(): void
    {
        $definition = $this->definition('_ga');
        $repository = new CookieDefinitionRepository($this->db);
        $this->em->getConnection()->executeStatement('UPDATE dashboard_cookie_definition SET duration = ?, row_version = 2 WHERE id = ?', ['1 year', $definition->getId()]);

        $definition->setDuration('1 day');
        $definition->addTranslation((new CookieDefinitionTranslation())->setLocale('en')->setProvider('Stale')->setPurpose('Stale'));

        try {
            $repository->saveEdit($definition, $this->em, 1);
            self::fail('A stale edit must be refused.');
        } catch (StaleVersionException) {
        }

        self::assertSame('1 year', $this->em->getConnection()->fetchOne('SELECT duration FROM dashboard_cookie_definition WHERE id = ?', [$definition->getId()]));
        self::assertSame([], $this->em->getConnection()->fetchFirstColumn('SELECT provider FROM dashboard_cookie_definition_translation'), 'no half-saved translation');
        self::assertSame(0, $this->em->getConnection()->getTransactionNestingLevel());
        self::assertTrue($this->em->isOpen());
    }

    public function testEveryVersionedTableMapsTheColumn(): void
    {
        foreach (['dashboard_cookie_config', 'dashboard_cookie_definition'] as $table) {
            self::assertTrue($this->em->getConnection()->createSchemaManager()->introspectTable($table)->hasColumn('row_version'), $table);
        }
    }

    private function profile(string $name, bool $default): CookieConsentConfig
    {
        $config = (new CookieConsentConfig())->setName($name)->setEnabled(true)->setDefault($default);
        $this->em->persist($config);
        $this->em->flush();

        return $config;
    }

    private function definition(string $name): CookieDefinition
    {
        $definition = (new CookieDefinition())->setName($name)->setConfig($this->profile('Owner', true));
        $this->em->persist($definition);
        $this->em->flush();

        return $definition;
    }

    private function storedVersion(string $table, int $id): int
    {
        return (int) $this->em->getConnection()->fetchOne(sprintf('SELECT row_version FROM %s WHERE id = ?', $table), [$id]);
    }
}
