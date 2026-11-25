<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Schema\ColumnEditor;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\CookieConsentBundle\Entity\CookieConsentLog;
use Psr\Log\LoggerInterface;
use ReflectionMethod;

use function is_array;
use function is_callable;
use function is_object;
use function is_string;
use function method_exists;
use function preg_match;
use function version_compare;

/**
 * Since 1.30.2 (REQ-SEC-006, look-alike ids): binary NO PAD collation on the `cookie_consent_key` and `ip_address`
 * columns of the consent log on MySQL / MariaDB.
 *
 * The default MySQL / MariaDB collations fold case and accents and the PAD SPACE ones ignore trailing spaces, so a
 * `cookie_consent_key = :key` or `ip_address = :ip` pre-filter can return another visitor's rows. The GDPR export and
 * erasure re-check every row with `===`; this collation keeps the SQL pre-filter exact as well: MariaDB
 * `utf8mb4_nopad_bin`, MySQL 8.0.17+ `utf8mb4_0900_bin`, older MySQL `utf8mb4_bin` (PAD SPACE, the only binary
 * utf8mb4 collation it has; the re-check covers it). PostgreSQL and SQLite compare exactly and are left alone (a
 * `collation` option is not portable, so it cannot live in the mapping).
 *
 * Applied when the schema is generated from the mapping (`doctrine:migrations:diff`, `doctrine:schema:update`);
 * existing tables need the `ALTER` of UPGRADING. A MySQL `server_version` without its patch number (`8`, `8.0`) cannot
 * select `utf8mb4_0900_bin`: a warning is logged and `utf8mb4_bin` is used.
 *
 * 1.33.1 (audit XI): applied on `postGenerateSchema`, once the schema tool has added every foreign key, by table name.
 * 1.30.2–1.33.0 replaced the table on `postGenerateSchemaTable` (`setClassTable()`, DBAL 4.5+), and ORM 3.7–3.8's schema
 * tool then added the table's foreign keys to the table it had replaced, losing them on MySQL / MariaDB (latent: the
 * consent log has no foreign key today). Now:
 *
 * - DBAL 4.5+ with an ORM that has `GenerateSchemaEventArgs::setSchema()`: the table is rebuilt with its table editor
 *   (foreign keys and indexes included) and swapped into a new schema given back with `setSchema()`;
 * - otherwise (older DBAL): the column's platform options are set in place.
 *
 * Stateless (REQ-RUNTIME-002): the platform is read from the schema tool's entity manager on each event.
 *
 * @internal
 */
final readonly class ConsentLogCollationListener
{
    /** Fallback binary collation (MySQL < 8.0.17); PAD SPACE. */
    public const COLLATION = 'utf8mb4_bin';

    /** MySQL 8.0.17+ binary NO PAD collation. */
    public const MYSQL_NOPAD_COLLATION = 'utf8mb4_0900_bin';

    /** MariaDB binary NO PAD collation. */
    public const MARIADB_NOPAD_COLLATION = 'utf8mb4_nopad_bin';

    /** First MySQL release with `utf8mb4_0900_bin`. */
    public const MYSQL_NOPAD_SINCE = '8.0.17';

    /** Consent log fields that identify a visitor. */
    public const FIELDS = ['cookieConsentKey', 'ipAddress'];

    /**
     * @param ManagerRegistry|null $doctrine unused since 1.33.1 (the platform comes from the schema tool's entity
     *                                       manager); kept for BC
     */
    public function __construct(
        ?ManagerRegistry $doctrine = null,
        private ?LoggerInterface $logger = null,
    ) {
        unset($doctrine);
    }

    /**
     * The exact-match collation for that platform and server version, null when the platform already compares exactly.
     * A MySQL 8 platform with an unknown server version gets `utf8mb4_0900_bin`; a version without a patch number
     * (`8.0`) is read as `8.0.0` and keeps the `utf8mb4_bin` fallback.
     *
     * @return non-empty-string|null
     */
    public static function collationFor(AbstractPlatform $platform, ?string $serverVersion = null): ?string
    {
        return match (true) {
            $platform instanceof MariaDBPlatform       => self::MARIADB_NOPAD_COLLATION,
            $platform instanceof MySQL80Platform       => self::supportsMysqlNoPad($serverVersion) ? self::MYSQL_NOPAD_COLLATION : self::COLLATION,
            $platform instanceof AbstractMySQLPlatform => self::COLLATION,
            default                                    => null,
        };
    }

    /**
     * Gives the key columns of the consent log their exact-match collation in the generated schema, keeping every foreign
     * key and index of the table.
     */
    public function postGenerateSchema(GenerateSchemaEventArgs $args): void
    {
        $entityManager = $args->getEntityManager();
        if ($entityManager->getMetadataFactory()->isTransient(CookieConsentLog::class)) {
            return;
        }
        $connection = $entityManager->getConnection();
        $platform   = $connection->getDatabasePlatform();
        $version    = $platform instanceof MySQL80Platform ? $this->serverVersion($connection) : null;
        $collation  = self::collationFor($platform, $version);
        $metadata   = $entityManager->getClassMetadata(CookieConsentLog::class);
        $tableName  = $metadata->getTableName();
        $schema     = $args->getSchema();
        if ($collation === null || $tableName === '' || !$schema->hasTable($tableName)) {
            return;
        }
        $this->warnAboutPartialVersion($platform, $this->configuredVersion($connection), $collation);

        // the editor path replaces the table in a new schema: it needs Table::edit() and Schema::edit() (DBAL 4.5+) and an
        // ORM that accepts the new schema (3.7+)
        // @phpstan-ignore function.alreadyNarrowedType, function.alreadyNarrowedType, function.alreadyNarrowedType (older doctrine/dbal / doctrine/orm)
        $editor   = method_exists(Table::class, 'edit') && method_exists(Schema::class, 'edit') && method_exists($args, 'setSchema');
        $original = $table = $schema->getTable($tableName);
        foreach (self::FIELDS as $field) {
            $column = $metadata->hasField($field) ? $metadata->getColumnName($field) : null;
            if ($column !== null && $column !== '' && $table->hasColumn($column)) {
                $table = self::collate($table, $column, $collation, $editor) ?? $table;
            }
        }
        if ($table !== $original) {
            $args->setSchema($schema->edit()->dropTableByUnquotedName($tableName)->addTable($table)->create());
        }
    }

    /**
     * Gives `$column` the utf8mb4 charset and `$collation`: through a table editor (DBAL 4.5+; returns the new table) or
     * in place (older DBAL; returns null).
     *
     * @param non-empty-string $column
     * @param non-empty-string $collation
     */
    public static function collate(Table $table, string $column, string $collation, bool $editor): ?Table
    {
        if ($editor) {
            return $table->edit()->modifyColumnByUnquotedName($column, static function (ColumnEditor $columnEditor) use ($collation): void {
                $columnEditor->setCharset('utf8mb4')->setCollation($collation);
            })->create();
        }

        // DBAL 4.5 deprecates the setters for the editors, and no editor changes a table in place (ORM 3.6 cannot swap it)
        @$table->getColumn($column)
            ->setPlatformOption('charset', 'utf8mb4')
            ->setPlatformOption('collation', $collation);

        return null;
    }

    /**
     * REQ-SEC-006 item 8: a MySQL server version configured without its patch number (`8`, `8.0`) leaves the key columns
     * on `utf8mb4_bin`, which is PAD SPACE (`'bob '` = `'bob'`).
     */
    private function warnAboutPartialVersion(AbstractPlatform $platform, ?string $version, string $collation): void
    {
        if ($collation !== self::COLLATION || $platform instanceof MariaDBPlatform || $version === null || preg_match('/\A\d+\.\d+\.\d+/', $version) === 1) {
            return;
        }

        $this->logger?->warning('doctrine.dbal.server_version "{version}" has no patch number: the CookieConsent consent log key columns get the PAD SPACE collation utf8mb4_bin. Configure the full version (e.g. 8.4.3) to get utf8mb4_0900_bin.', ['bundle' => 'NowoCookieConsentBundle', 'version' => $version]);
    }

    /**
     * The server version configured on the connection (also on a primary / replica connection), if any.
     */
    private function configuredVersion(Connection $connection): ?string
    {
        $params  = $connection->getParams();
        $primary = $params['primary'] ?? null;
        $version = $params['serverVersion'] ?? (is_array($primary) ? ($primary['serverVersion'] ?? null) : null);

        return is_string($version) && $version !== '' ? $version : null;
    }

    /**
     * The configured server version, else the one the server reports (null when it cannot be read: unknown).
     */
    private function serverVersion(Connection $connection): ?string
    {
        $version = $this->configuredVersion($connection);
        if ($version !== null) {
            return $version;
        }
        // doctrine/dbal 4: public Connection::getServerVersion(); doctrine/dbal 3 keeps it private (the wrapped driver
        // connection reports it).
        if ((new ReflectionMethod($connection, 'getServerVersion'))->isPublic()) {
            return $connection->getServerVersion();
        }

        return $this->wrappedServerVersion($connection); // @codeCoverageIgnore
    }

    /**
     * doctrine/dbal 3 only.
     *
     * @codeCoverageIgnore
     */
    private function wrappedServerVersion(Connection $connection): ?string
    {
        $driver  = method_exists($connection, 'getWrappedConnection') ? $connection->getWrappedConnection() : null;
        $version = is_object($driver) && is_callable([$driver, 'getServerVersion']) ? $driver->getServerVersion() : null;

        return is_string($version) && $version !== '' ? $version : null;
    }

    private static function supportsMysqlNoPad(?string $serverVersion): bool
    {
        if ($serverVersion === null || preg_match('/\A(\d+)(?:\.(\d+))?(?:\.(\d+))?/', $serverVersion, $match) !== 1) {
            return true;
        }

        $version = $match[1] . '.' . (($match[2] ?? '') !== '' ? $match[2] : '0') . '.' . (($match[3] ?? '') !== '' ? $match[3] : '0');

        return version_compare($version, self::MYSQL_NOPAD_SINCE, '>=');
    }
}
