<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Support;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Nowo\CookieConsentBundle\Doctrine\UtcDateTimeImmutableType;

use function dirname;

use const PHP_VERSION_ID;

/**
 * Real ORM entity manager on in-memory SQLite with the bundle schema (tests only).
 */
final class SqliteEntityManagerFactory
{
    public static function create(): EntityManager
    {
        UtcDateTimeImmutableType::register();

        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [dirname(__DIR__, 2) . '/src/Entity'],
            isDevMode: true,
        );

        if (PHP_VERSION_ID >= 80400) {
            $config->enableNativeLazyObjects(true);
        }

        $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
        (new SchemaTool($entityManager))->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        return $entityManager;
    }
}
