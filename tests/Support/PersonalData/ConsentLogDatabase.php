<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Support\PersonalData;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use LogicException;
use Nowo\CookieConsentBundle\Doctrine\UtcDateTimeImmutableType;

use function array_map;
use function dirname;
use function getenv;
use function is_string;
use function str_starts_with;

use const PHP_VERSION_ID;

/**
 * The consent log on in-memory SQLite, or on the database of `COOKIE_CONSENT_TEST_DATABASE_URL` (MySQL / PostgreSQL in
 * CI and the local Docker run), plus a one-manager registry of the bundle entities (tests only).
 */
final class ConsentLogDatabase implements ManagerRegistry
{
    public const URL_ENV = 'COOKIE_CONSENT_TEST_DATABASE_URL';

    private const ENTITY_NAMESPACE = 'Nowo\\CookieConsentBundle\\Entity\\';

    private function __construct(
        public readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * A fresh database with the bundle schema (dropped and recreated on an external database).
     */
    public static function create(bool $withSchema = true): self
    {
        $url  = self::url();
        $em   = self::connect()->entityManager;
        $tool = new SchemaTool($em);
        $all  = $em->getMetadataFactory()->getAllMetadata();
        if ($url !== null) {
            $tool->dropSchema($all);
        }
        if ($withSchema) {
            $tool->createSchema($all);
        }

        return new self($em);
    }

    /**
     * Another session on the same database (schema untouched), optionally with DBAL middlewares (1.31: concurrency
     * tests open a second connection, and count commits with transaction-doctrine's middleware).
     *
     * @param list<Middleware> $middlewares
     */
    public static function connect(array $middlewares = []): self
    {
        UtcDateTimeImmutableType::register();
        $config = ORMSetup::createAttributeMetadataConfiguration(paths: [dirname(__DIR__, 3) . '/src/Entity'], isDevMode: true);
        if (PHP_VERSION_ID >= 80400) {
            $config->enableNativeLazyObjects(true);
        }
        $config->setMiddlewares($middlewares);

        $url    = self::url();
        $params = $url === null ? ['driver' => 'pdo_sqlite', 'memory' => true] : (new DsnParser(['mysql' => 'pdo_mysql', 'pgsql' => 'pdo_pgsql', 'postgresql' => 'pdo_pgsql']))->parse($url);

        return new self(new EntityManager(DriverManager::getConnection($params, $config), $config));
    }

    /**
     * The external database URL, or null for in-memory SQLite.
     */
    public static function url(): ?string
    {
        $url = getenv(self::URL_ENV);

        return is_string($url) && $url !== '' ? $url : null;
    }

    public function platformName(): string
    {
        return $this->entityManager->getConnection()->getDatabasePlatform()::class;
    }

    /**
     * Inserts one consent log row and returns its id.
     */
    public function insert(string $key, string $ip, string $name, bool $value, DateTimeImmutable $at, ?int $revision = null): int
    {
        $connection = $this->entityManager->getConnection();
        $connection->insert('dashboard_cookie_log', [
            'ip_address'         => $ip,
            'cookie_consent_key' => $key,
            'cookie_name'        => $name,
            'cookie_value'       => $value,
            'timestamp'          => $at,
            'consent_revision'   => $revision,
        ], [
            'cookie_value' => Types::BOOLEAN,
            'timestamp'    => Types::DATETIME_IMMUTABLE,
        ]);

        return (int) $connection->lastInsertId();
    }

    /**
     * @return list<int> ids of the remaining rows, ascending
     */
    public function ids(): array
    {
        $ids = $this->entityManager->getConnection()->fetchFirstColumn('SELECT id FROM dashboard_cookie_log ORDER BY id');

        return array_map(intval(...), $ids);
    }

    public function close(): void
    {
        $this->entityManager->getConnection()->close();
    }

    public function getDefaultConnectionName(): string
    {
        return 'default';
    }

    public function getConnection(?string $name = null): Connection
    {
        return $this->entityManager->getConnection();
    }

    public function getConnections(): array
    {
        return ['default' => $this->entityManager->getConnection()];
    }

    public function getConnectionNames(): array
    {
        return ['default' => 'default'];
    }

    public function getDefaultManagerName(): string
    {
        return 'default';
    }

    public function getManager(?string $name = null): ObjectManager
    {
        return $this->entityManager;
    }

    public function getManagers(): array
    {
        return ['default' => $this->entityManager];
    }

    public function resetManager(?string $name = null): ObjectManager
    {
        throw new LogicException('Not supported in tests.');
    }

    public function getManagerNames(): array
    {
        return ['default' => 'default'];
    }

    public function getRepository(string $persistentObject, ?string $persistentManagerName = null): ObjectRepository
    {
        return $this->entityManager->getRepository($persistentObject);
    }

    public function getManagerForClass(string $class): ?ObjectManager
    {
        return str_starts_with($class, self::ENTITY_NAMESPACE) ? $this->entityManager : null;
    }
}
