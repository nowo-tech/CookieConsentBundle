<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;
use Nowo\CookieConsentBundle\Concurrency\RowVersionGuard;
use Nowo\CookieConsentBundle\Concurrency\StaleVersionException;
use Nowo\CookieConsentBundle\Doctrine\BundleManagerGuard;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\TransactionDoctrine\DoctrineTransactionRunner;
use SortDirection;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Doctrine repository for {@see CookieConsentConfig} entities.
 *
 * Enabled-profile lookups are memoized for the service lifetime (typically one request)
 * so repeated consent resolution does not re-query Doctrine.
 *
 * @extends ServiceEntityRepository<CookieConsentConfig>
 */
class CookieConsentConfigRepository extends ServiceEntityRepository implements ResetInterface
{
    private bool $defaultEnabledLoaded = false;

    private ?CookieConsentConfig $defaultEnabled = null;

    /** @var list<CookieConsentConfig>|null */
    private ?array $allEnabled = null;

    /** @var list<CookieConsentConfig>|null */
    private ?array $allEnabledNonDefault = null;

    /**
     * Creates a new consent configuration repository.
     *
     * @param ManagerRegistry $registry The Doctrine manager registry
     * @param object|null $hostFlushReporter @deprecated since 2.0, ignored (the 1.35 host-flush probe of the removed host mode); removed in 3.0
     */
    public function __construct(ManagerRegistry $registry, ?object $hostFlushReporter = null)
    {
        unset($hostFlushReporter);
        parent::__construct($registry, CookieConsentConfig::class);
    }

    /**
     * Clears in-memory lookup caches after admin writes or Doctrine flushes.
     *
     * Also used as {@see ResetInterface::reset()} between FrankenPHP worker requests.
     */
    public function clearRuntimeCache(): void
    {
        $this->reset();
    }

    /**
     * Clears the memoized enabled profiles (kernel reset / worker mode).
     */
    public function reset(): void
    {
        $this->defaultEnabledLoaded = false;
        $this->defaultEnabled       = null;
        $this->allEnabled           = null;
        $this->allEnabledNonDefault = null;
    }

    /**
     * Saves the admin changes of one profile in one transaction (1.31, REQ-RUNTIME-003 rules 1 and 4).
     *
     * When the profile is the default one, the "only one default" rule is enforced inside the same transaction:
     * - with `$expectedVersion`, the profile's row version is checked and advanced first (compare-and-swap, 1.33):
     *   a profile saved by someone else since the page was opened throws {@see StaleVersionException} and nothing is
     *   written;
     * - the enabled profiles are locked first, in id order (`SELECT ... FOR UPDATE`), so two saves never deadlock and
     *   the second one waits for the first and then reads its committed flags (PostgreSQL READ COMMITTED included);
     * - the flush writes the profile;
     * - one atomic UPDATE clears the flag of every other enabled profile.
     *
     * Two admins making two profiles the default at the same time therefore leave exactly one default (the later
     * commit), where the former read-then-write of the loaded profiles could leave both. A lock conflict is thrown as
     * {@see TransientStorageFailure} (409 + CONFLICT_001 on the admin route).
     *
     * @param EntityManagerInterface $entityManager the manager of the bundle entities (it holds the form's changes)
     * @param int|null $expectedVersion the row version of the edited page (the form's hidden token); null saves without the check but still advances the version (a host's own code)
     *
     * @throws LogicException 2.0: when `$entityManager` is not the bundle's manager or does not hold the entity (nothing is written)
     * @throws StaleVersionException when `$expectedVersion` is no longer the profile's version
     */
    public function saveSettings(CookieConsentConfig $config, EntityManagerInterface $entityManager, ?int $expectedVersion = null): void
    {
        BundleManagerGuard::assertBundleManager($entityManager, $this->getEntityManager(), $config, __METHOD__);

        DoctrineTransactionRunner::forConnection($entityManager->getConnection())->run(function () use ($config, $entityManager, $expectedVersion): void {
            $default = $config->isDefault();
            if ($default) {
                $this->lockEnabledProfiles();
            }

            // With no token (host code) the version still advances, unchecked: pages opened before are stale.
            RowVersionGuard::advance($entityManager, $config, $expectedVersion);

            $entityManager->flush();

            if ($default) {
                $this->clearOtherDefaults($config);
            }
        });
        $this->clearRuntimeCache();
    }

    /**
     * Locks the rows of the enabled profiles in id order (fixed order: no deadlock between two saves).
     */
    private function lockEnabledProfiles(): void
    {
        $this->createQueryBuilder('config')
            ->select('config.id')
            ->andWhere('config.enabled = :enabled')
            ->setParameter('enabled', true)
            ->orderBy('config.id', SortDirection::Ascending)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getScalarResult();
    }

    /**
     * Clears the default flag of every other enabled profile with one atomic UPDATE. It advances their row version too:
     * an admin who opened such a profile before this save is asked to reload, not left to write the old flag back.
     */
    private function clearOtherDefaults(CookieConsentConfig $keep): void
    {
        $this->getEntityManager()
            ->createQuery('UPDATE ' . CookieConsentConfig::class . ' config SET config.default = :off, config.rowVersion = config.rowVersion + 1 WHERE config.default = :on AND config.enabled = :enabled AND config.id <> :keep')
            ->setParameter('off', false)
            ->setParameter('on', true)
            ->setParameter('enabled', true)
            ->setParameter('keep', $keep->getId())
            ->execute();

        // The UPDATE bypasses the unit of work: the other profiles already loaded would keep a stale isDefault().
        $entityManager = $this->getEntityManager();
        foreach ($entityManager->getUnitOfWork()->getIdentityMap()[CookieConsentConfig::class] ?? [] as $loaded) {
            if ($loaded !== $keep && $loaded instanceof CookieConsentConfig && $loaded->isEnabled()) {
                $entityManager->refresh($loaded);
            }
        }
    }

    /**
     * Returns the enabled default consent configuration, if any.
     *
     * @return CookieConsentConfig|null The default configuration or null
     */
    public function findDefaultEnabled(): ?CookieConsentConfig
    {
        if ($this->defaultEnabledLoaded) {
            return $this->defaultEnabled;
        }

        $this->defaultEnabled = $this->createQueryBuilder('config')
            ->andWhere('config.enabled = :enabled')
            ->andWhere('config.default = :default')
            ->setParameter('enabled', true)
            ->setParameter('default', true)
            // 1.36 (audit ZAH-4): the lowest id if, against the rule, several are (the provisioning port picks the same).
            ->orderBy('config.id', SortDirection::Ascending)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $this->defaultEnabledLoaded = true;

        return $this->defaultEnabled;
    }

    /**
     * Returns all enabled consent configurations ordered by priority.
     *
     * @return list<CookieConsentConfig>
     */
    public function findAllEnabled(): array
    {
        if ($this->allEnabled !== null) {
            return $this->allEnabled;
        }

        /** @var list<CookieConsentConfig> $configs */
        $configs = $this->createQueryBuilder('config')
            ->andWhere('config.enabled = :enabled')
            ->setParameter('enabled', true)
            ->orderBy('config.priority', SortDirection::Descending)
            ->addOrderBy('config.id', SortDirection::Ascending)
            ->getQuery()
            ->getResult();

        return $this->allEnabled = $configs;
    }

    /**
     * Returns enabled non-default consent configurations ordered by priority.
     *
     * @return list<CookieConsentConfig>
     */
    public function findAllEnabledNonDefault(): array
    {
        if ($this->allEnabledNonDefault !== null) {
            return $this->allEnabledNonDefault;
        }

        /** @var list<CookieConsentConfig> $configs */
        $configs = $this->createQueryBuilder('config')
            ->andWhere('config.enabled = :enabled')
            ->andWhere('config.default = :default')
            ->setParameter('enabled', true)
            ->setParameter('default', false)
            ->orderBy('config.priority', SortDirection::Descending)
            ->addOrderBy('config.id', SortDirection::Ascending)
            ->getQuery()
            ->getResult();

        return $this->allEnabledNonDefault = $configs;
    }
}
