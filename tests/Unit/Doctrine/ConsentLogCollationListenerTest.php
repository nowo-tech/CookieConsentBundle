<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Doctrine;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Doctrine\ORM\Tools\Event\GenerateSchemaTableEventArgs;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\ToolEvents;
use Nowo\CookieConsentBundle\Doctrine\ConsentLogCollationListener;
use Nowo\CookieConsentBundle\Entity\CookieConsentLog;
use Nowo\CookieConsentBundle\Entity\CookieDefinition;
use Nowo\CookieConsentBundle\Tests\Support\RecordingLogger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function array_map;
use function array_values;
use function dirname;
use function implode;
use function is_string;
use function ksort;
use function sort;

use const PHP_VERSION_ID;

/**
 * REQ-SEC-006 items 3 and 8: binary NO PAD collation on `cookie_consent_key` / `ip_address` of the consent log.
 */
final class ConsentLogCollationListenerTest extends TestCase
{
    /**
     * @return iterable<string, array{AbstractPlatform, string|null, string|null}>
     */
    public static function platforms(): iterable
    {
        yield 'MariaDB' => [new MariaDBPlatform(), '11.4.3-MariaDB', 'utf8mb4_nopad_bin'];
        yield 'MySQL 8.0.17' => [new MySQL80Platform(), '8.0.17', 'utf8mb4_0900_bin'];
        yield 'MySQL 8.4' => [new MySQL80Platform(), '8.4.3', 'utf8mb4_0900_bin'];
        yield 'MySQL 8.0.16' => [new MySQL80Platform(), '8.0.16', 'utf8mb4_bin'];
        yield 'MySQL 8.0 without patch' => [new MySQL80Platform(), '8.0', 'utf8mb4_bin'];
        yield 'MySQL 8 without minor' => [new MySQL80Platform(), '8', 'utf8mb4_bin'];
        yield 'MySQL 8 unknown version' => [new MySQL80Platform(), null, 'utf8mb4_0900_bin'];
        yield 'MySQL 8 unparsable version' => [new MySQL80Platform(), 'latest', 'utf8mb4_0900_bin'];
        yield 'MySQL 5.7' => [new MySQLPlatform(), '5.7.44', 'utf8mb4_bin'];
        yield 'PostgreSQL' => [new PostgreSQLPlatform(), '16', null];
        yield 'SQLite' => [new SQLitePlatform(), null, null];
    }

    #[DataProvider('platforms')]
    public function testTheCollationFollowsThePlatformAndServerVersion(AbstractPlatform $platform, ?string $version, ?string $expected): void
    {
        self::assertSame($expected, ConsentLogCollationListener::collationFor($platform, $version));
    }

    public function testTheMappedIdColumnsGetTheCollation(): void
    {
        $args = $this->args($this->manager(new MariaDBPlatform()));
        (new ConsentLogCollationListener())->postGenerateSchema($args);

        $table = $args->getSchema()->getTable('t');
        self::assertSame('utf8mb4_nopad_bin', $this->collation($table, 'consent_key_col'));
        self::assertSame('utf8mb4', $this->charset($table, 'consent_key_col'));
        self::assertSame('utf8mb4_nopad_bin', $this->collation($table, 'ip_address'));
        self::assertNull($this->collation($table, 'scope'));
    }

    public function testTheConfiguredServerVersionDecidesOnMysql8(): void
    {
        $args = $this->args($this->manager(new MySQL80Platform(), ['primary' => ['serverVersion' => '8.4.3']]));
        (new ConsentLogCollationListener())->postGenerateSchema($args);
        self::assertSame('utf8mb4_0900_bin', $this->collation($args->getSchema()->getTable('t'), 'consent_key_col'));

        $args = $this->args($this->manager(new MySQL80Platform(), [], '8.0.12'));
        (new ConsentLogCollationListener())->postGenerateSchema($args);
        self::assertSame('utf8mb4_bin', $this->collation($args->getSchema()->getTable('t'), 'ip_address'));
    }

    public function testOtherPlatformsAndManagersAreLeftAlone(): void
    {
        foreach ([new SQLitePlatform(), new PostgreSQLPlatform()] as $platform) {
            $args   = $this->args($this->manager($platform));
            $schema = $args->getSchema();
            (new ConsentLogCollationListener())->postGenerateSchema($args);
            self::assertSame($schema, $args->getSchema());
            self::assertNull($this->collation($schema->getTable('t'), 'consent_key_col'));
        }

        // a manager that does not map the consent log (another bundle's, or the host's)
        $args   = $this->args($this->manager(new MariaDBPlatform(), transient: true));
        $schema = $args->getSchema();
        (new ConsentLogCollationListener())->postGenerateSchema($args);
        self::assertSame($schema, $args->getSchema());
        self::assertNull($this->collation($schema->getTable('t'), 'consent_key_col'));

        // a schema without the table
        $args = new GenerateSchemaEventArgs($this->manager(new MariaDBPlatform()), new Schema());
        (new ConsentLogCollationListener())->postGenerateSchema($args);
        self::assertFalse($args->getSchema()->hasTable('t'));

        $args = $this->args($this->manager(new MariaDBPlatform(), mapped: false));
        (new ConsentLogCollationListener())->postGenerateSchema($args);
        self::assertNull($this->collation($args->getSchema()->getTable('t'), 'consent_key_col'));
    }

    /**
     * Audit XI: the generated schema keeps every foreign key and index of the table (and the ones pointing to it), as
     * without the listener (1.30.2–1.33.0 replaced the table on `postGenerateSchemaTable`).
     */
    /**
     * @param array<string, mixed> $params
     */
    #[DataProvider('connections')]
    public function testEveryForeignKeyAndIndexIsKept(array $params, ?string $collation): void
    {
        $with    = $this->shape($this->schema($params, true, true));
        $without = $this->shape($this->schema($params, false, true));

        self::assertSame($without, $with);
        self::assertContains('definition_id->dashboard_cookie_definition(id) CASCADE', $with['dashboard_cookie_log']['fks']);
        self::assertSame($collation, $this->collation($this->schema($params, true, true)->getTable('dashboard_cookie_log'), 'cookie_consent_key'));
    }

    public function testForeignKeysOfTheEditedTableSurviveTheSwap(): void
    {
        $schema = new Schema();
        $owner  = $schema->createTable('host_owner');
        $owner->addColumn('id', 'integer');
        $owner->setPrimaryKey(['id']);
        $table = $schema->createTable('t');
        $table->addColumn('id', 'integer');
        $table->addColumn('consent_key_col', 'string', ['length' => 64]);
        $table->addColumn('ip_address', 'string', ['length' => 64]);
        $table->addColumn('owner_id', 'integer', ['notnull' => false]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['owner_id'], 'idx_t_owner');
        $table->addForeignKeyConstraint('host_owner', ['owner_id'], ['id'], ['onDelete' => 'SET NULL']);
        $pointer = $schema->createTable('host_pointer');
        $pointer->addColumn('t_id', 'integer');
        $pointer->addForeignKeyConstraint('t', ['t_id'], ['id']);
        $before = $this->shape($schema);

        $args = new GenerateSchemaEventArgs($this->manager(new MariaDBPlatform()), $schema);
        (new ConsentLogCollationListener())->postGenerateSchema($args);

        self::assertSame($before, $this->shape($args->getSchema()));
        self::assertSame('utf8mb4_nopad_bin', $this->collation($args->getSchema()->getTable('t'), 'consent_key_col'));
    }

    public function testTheFormerTableEventDoesNothing(): void
    {
        $table = new Table('t');
        $table->addColumn('cookie_consent_key', 'string', ['length' => 64]);
        $args = new GenerateSchemaTableEventArgs(new ClassMetadata(CookieConsentLog::class), new Schema(), $table);

        (new ConsentLogCollationListener())->postGenerateSchemaTable($args);

        self::assertSame($table, $args->getClassTable());
        self::assertNull($this->collation($table, 'cookie_consent_key'));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string|null}>
     */
    public static function connections(): iterable
    {
        yield 'MariaDB' => [['driver' => 'pdo_mysql', 'serverVersion' => '11.4.3-MariaDB'], 'utf8mb4_nopad_bin'];
        yield 'MySQL 8.4' => [['driver' => 'pdo_mysql', 'serverVersion' => '8.4.3'], 'utf8mb4_0900_bin'];
        yield 'MySQL 5.7' => [['driver' => 'pdo_mysql', 'serverVersion' => '5.7.44'], 'utf8mb4_bin'];
        yield 'SQLite' => [['driver' => 'pdo_sqlite', 'memory' => true], null];
    }

    public function testAMysqlServerVersionWithoutPatchIsWarnedAbout(): void
    {
        /** @var list<array{AbstractPlatform, string|null, int}> $cases */
        $cases = [
            [new MySQL80Platform(), '8.0', 1],
            [new MySQLPlatform(), '8', 1],
            [new MySQL80Platform(), '8.4.3', 0],
            [new MySQL80Platform(), '9.7', 0],
            [new MariaDBPlatform(), '11.4', 0],
            [new MySQLPlatform(), '5.7.44', 0],
            [new MySQL80Platform(), null, 0],
        ];
        foreach ($cases as [$platform, $version, $warnings]) {
            $logger = new RecordingLogger();
            (new ConsentLogCollationListener(null, $logger))
                ->postGenerateSchema($this->args($this->manager($platform, $version === null ? [] : ['serverVersion' => $version], $version === null ? '8.4.3' : null)));
            self::assertCount($warnings, $logger->records, (string) $version);
            if ($warnings === 1) {
                self::assertStringContainsString('has no patch number', (string) $logger->records[0]);
            }
        }
    }

    public function testOlderDbalCollatesInPlace(): void
    {
        $table = new Table('t');
        $table->addColumn('actor_id', 'string', ['length' => 64]);

        self::assertNull(ConsentLogCollationListener::collate($table, 'actor_id', 'utf8mb4_nopad_bin', false));
        self::assertSame('utf8mb4_nopad_bin', $table->getColumn('actor_id')->getPlatformOption('collation'));
        self::assertSame('utf8mb4', $table->getColumn('actor_id')->getPlatformOption('charset'));
    }

    private function args(EntityManagerInterface $manager): GenerateSchemaEventArgs
    {
        $schema = new Schema();
        $table  = $schema->createTable('t');
        foreach (['consent_key_col', 'ip_address', 'scope'] as $column) {
            $table->addColumn($column, 'string', ['length' => 64]);
        }

        return new GenerateSchemaEventArgs($manager, $schema);
    }

    /**
     * A manager on a connection of that platform (a lazy one: nothing connects), mapping the consent log to table `t`.
     *
     * @param array<string, mixed> $params
     */
    private function manager(AbstractPlatform $platform, array $params = [], ?string $reported = null, bool $mapped = true, bool $transient = false): EntityManagerInterface
    {
        $metadata = new ClassMetadata(CookieConsentLog::class);
        $metadata->setPrimaryTable(['name' => 't']);
        if ($mapped) {
            // A renamed key column: the listener follows the mapping.
            $metadata->mapField(['fieldName' => 'cookieConsentKey', 'columnName' => 'consent_key_col', 'type' => 'string', 'length' => 64]);
            $metadata->mapField(['fieldName' => 'ipAddress', 'columnName' => 'ip_address', 'type' => 'string', 'length' => 64]);
        }
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform);
        $connection->method('getParams')->willReturn($params);
        $connection->method('getServerVersion')->willReturn($reported ?? '8.4.3');
        $factory = $this->createMock(ClassMetadataFactory::class);
        $factory->method('isTransient')->willReturn($transient);
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('getConnection')->willReturn($connection);
        $manager->method('getMetadataFactory')->willReturn($factory);
        $manager->method('getClassMetadata')->willReturn($metadata);

        return $manager;
    }

    /**
     * The bundle schema of a lazy connection with the given parameters, the log table optionally pointing to a cookie
     * definition (a host association, metadata built by hand).
     *
     * @param array<string, mixed> $params
     */
    private function schema(array $params, bool $listener, bool $hostAssociation = false): Schema
    {
        $config = ORMSetup::createAttributeMetadataConfiguration([dirname(__DIR__, 3) . '/src/Entity'], true);
        if (PHP_VERSION_ID >= 80400) {
            $config->enableNativeLazyObjects(true);
        }
        $eventManager = new EventManager();
        if ($listener) {
            $eventManager->addEventListener(ToolEvents::postGenerateSchema, new ConsentLogCollationListener());
        }
        $em      = new EntityManager(DriverManager::getConnection($params, $config), $config, $eventManager);
        $classes = $em->getMetadataFactory()->getAllMetadata();
        if ($hostAssociation) {
            $classes = array_map(static function (ClassMetadata $metadata): ClassMetadata {
                if ($metadata->getName() !== CookieConsentLog::class) {
                    return $metadata;
                }
                $metadata = clone $metadata;
                $metadata->mapManyToOne(['fieldName' => 'definition', 'targetEntity' => CookieDefinition::class, 'joinColumns' => [['name' => 'definition_id', 'referencedColumnName' => 'id', 'onDelete' => 'CASCADE']]]);

                return $metadata;
            }, $classes);
        }

        return (new SchemaTool($em))->getSchemaFromMetadata($classes);
    }

    /**
     * Foreign keys (local columns, foreign table and columns, ON DELETE) and index names per table.
     *
     * @return array<string, array{fks: list<string>, indexes: list<string>}>
     */
    private function shape(Schema $schema): array
    {
        $shape = [];
        foreach ($schema->getTables() as $table) {
            $fks = [];
            foreach ($table->getForeignKeys() as $fk) {
                $fks[] = implode(',', $fk->getLocalColumns()) . '->' . $fk->getForeignTableName() . '(' . implode(',', $fk->getForeignColumns()) . ') ' . ($fk->getOption('onDelete') ?? '-');
            }
            $indexes = array_map(static fn (Index $index): string => $index->getName(), array_values($table->getIndexes()));
            sort($fks);
            sort($indexes);
            $shape[$table->getName()] = ['fks' => $fks, 'indexes' => $indexes];
        }
        ksort($shape);

        return $shape;
    }

    private function collation(Table $table, string $column): ?string
    {
        $value = $table->getColumn($column)->getPlatformOptions()['collation'] ?? null;

        return is_string($value) ? $value : null;
    }

    private function charset(Table $table, string $column): ?string
    {
        $value = $table->getColumn($column)->getPlatformOptions()['charset'] ?? null;

        return is_string($value) ? $value : null;
    }
}
