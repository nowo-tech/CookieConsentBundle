<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Doctrine;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\CookieConsentBundle\Doctrine\CookieConsentHostFlushReporter;
use Nowo\CookieConsentBundle\Doctrine\UtcDateTimeImmutableType;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Tests\Integration\Kernel\HostEntity\HostNote;
use Nowo\TransactionDoctrine\HostFlushProbe;
use Nowo\TransactionDoctrine\PrivateManager\PrivateEntityManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function dirname;
use function restore_error_handler;
use function set_error_handler;

use const E_USER_DEPRECATED;
use const PHP_VERSION_ID;

/**
 * 1.35: the reporter hands the application's pending changes to transaction-doctrine's host-flush probe (one
 * deprecation per class and unit of work), never reports the bundle's own entities, never inspects a private manager
 * and changes nothing.
 */
#[CoversClass(CookieConsentHostFlushReporter::class)]
final class CookieConsentHostFlushReporterTest extends TestCase
{
    private const MESSAGE = 'Your pending changes to Nowo\CookieConsentBundle\Tests\Integration\Kernel\HostEntity\HostNote were flushed by NowoCookieConsentBundle; 2.0 no longer does.';

    public function testReportsTheApplicationsPendingChangesOncePerUnitOfWork(): void
    {
        $entityManager = $this->entityManager();
        $entityManager->persist((new CookieConsentConfig())->setName('Bundle row'));
        $note = new HostNote();
        $entityManager->persist($note);
        $reporter = new CookieConsentHostFlushReporter(new HostFlushProbe());

        self::assertSame([self::MESSAGE], $this->deprecations(static fn () => $reporter->beforeFlush($entityManager)));
        self::assertSame([], $this->deprecations(static fn () => $reporter->beforeFlush($entityManager)), 'once per unit of work');
        self::assertTrue($entityManager->getUnitOfWork()->isScheduledForInsert($note), 'read-only: nothing flushed or detached');

        $reporter->reset();
        self::assertSame([self::MESSAGE], $this->deprecations(static fn () => $reporter->beforeFlush($entityManager)), 'a new unit reports again');
    }

    public function testTheBundlesOwnEntitiesAreNeverReported(): void
    {
        $entityManager = $this->entityManager();
        $entityManager->persist((new CookieConsentConfig())->setName('Bundle row'));

        self::assertSame([], $this->deprecations(static fn () => (new CookieConsentHostFlushReporter())->beforeFlush($entityManager)));
    }

    public function testAPrivateManagerIsNeverInspected(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(self::never())->method(self::anything());
        $private = new PrivateEntityManager($registry, 'default', CookieConsentConfig::class, 'NowoCookieConsentBundle');

        self::assertSame([], $this->deprecations(static fn () => (new CookieConsentHostFlushReporter())->beforeFlush($private)));
        self::assertFalse($private->isCreated());
    }

    private function entityManager(): EntityManager
    {
        UtcDateTimeImmutableType::register();
        $root   = dirname(__DIR__, 3);
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [$root . '/src/Entity', $root . '/tests/Integration/Kernel/HostEntity'],
            isDevMode: true,
        );
        if (PHP_VERSION_ID >= 80400) {
            $config->enableNativeLazyObjects(true);
        }

        $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
        (new SchemaTool($entityManager))->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        return $entityManager;
    }

    /**
     * @param callable(): void $call
     *
     * @return list<string>
     */
    private function deprecations(callable $call): array
    {
        $deprecations = [];
        // @phpstan-ignore frankenphp.worker.noSetErrorExceptionHandler (test only; restored below)
        set_error_handler(static function (int $level, string $message) use (&$deprecations): bool {
            $deprecations[] = $message;

            return true;
        }, E_USER_DEPRECATED);

        try {
            $call();
        } finally {
            restore_error_handler();
        }

        return $deprecations;
    }
}
