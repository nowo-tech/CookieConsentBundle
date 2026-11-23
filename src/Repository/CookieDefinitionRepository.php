<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\CookieConsentBundle\Concurrency\RowVersionGuard;
use Nowo\CookieConsentBundle\Concurrency\StaleVersionException;
use Nowo\CookieConsentBundle\Doctrine\CookieConsentHostFlushReporter;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieDefinition;
use Nowo\TransactionDoctrine\DoctrineTransactionRunner;
use SortDirection;

use function iterator_to_array;
use function max;

/**
 * Doctrine repository for {@see CookieDefinition} entities.
 *
 * @extends ServiceEntityRepository<CookieDefinition>
 */
class CookieDefinitionRepository extends ServiceEntityRepository
{
    /**
     * Creates a new cookie definition repository.
     *
     * @param ManagerRegistry $registry The Doctrine manager registry
     * @param CookieConsentHostFlushReporter|null $hostFlushReporter 1.35: reports the application's pending changes a save flushes (host mode only)
     */
    public function __construct(ManagerRegistry $registry, private readonly ?CookieConsentHostFlushReporter $hostFlushReporter = null)
    {
        parent::__construct($registry, CookieDefinition::class);
    }

    /**
     * Saves the admin edit of an existing cookie definition in one transaction (1.33, REQ-RUNTIME-003 rules 1 and 4).
     *
     * The definition's row version is checked and advanced first (compare-and-swap, which also takes the row lock),
     * then the unit of work is flushed (the definition and its translations). A definition saved by someone else
     * since the page was opened throws {@see StaleVersionException} and nothing is written; a lock conflict is thrown
     * as `TransientStorageFailure` (409 + CONFLICT_001 on the admin route). The manager is never cleared or reset.
     *
     * @param CookieDefinition $definition The edited definition (managed by `$entityManager`, with the form's changes)
     * @param EntityManagerInterface $entityManager The manager of the bundle entities
     * @param int $expectedVersion The row version of the edited page (the form's hidden token)
     *
     * @throws StaleVersionException when `$expectedVersion` is no longer the definition's version
     */
    public function saveEdit(CookieDefinition $definition, EntityManagerInterface $entityManager, int $expectedVersion): void
    {
        DoctrineTransactionRunner::forConnection($entityManager->getConnection())->run(function () use ($definition, $entityManager, $expectedVersion): void {
            RowVersionGuard::advance($entityManager, $definition, $expectedVersion);
            // 1.35: lists the application's pending changes this flush writes (2.0 no longer does); read-only.
            $this->hostFlushReporter?->beforeFlush($entityManager);
            $entityManager->flush();
        });
    }

    /**
     * Returns cookie definitions for a configuration profile ordered for display.
     *
     * @param CookieConsentConfig $config The consent configuration profile
     *
     * @return list<CookieDefinition> Ordered cookie definitions
     */
    public function findByConfigOrdered(CookieConsentConfig $config): array
    {
        return $this->findByConfigOrderedPaginated($config);
    }

    /**
     * Returns a page of cookie definitions for a profile, with translations eager-loaded.
     *
     * @param CookieConsentConfig $config The consent configuration profile
     * @param int $page 1-based page number
     * @param int|null $pageSize Null returns all matching rows
     *
     * @return list<CookieDefinition> Ordered cookie definitions for the page
     */
    public function findByConfigOrderedPaginated(CookieConsentConfig $config, int $page = 1, ?int $pageSize = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->leftJoin('d.translations', 't')
            ->addSelect('t')
            ->andWhere('d.config = :config')
            ->setParameter('config', $config)
            ->orderBy('d.sortOrder', SortDirection::Ascending)
            ->addOrderBy('d.name', SortDirection::Ascending);

        if ($pageSize === null) {
            /** @var list<CookieDefinition> $definitions */
            $definitions = $qb->getQuery()->getResult();

            return $definitions;
        }

        $page     = max(1, $page);
        $pageSize = max(1, $pageSize);
        $qb->setFirstResult(($page - 1) * $pageSize)
            ->setMaxResults($pageSize);

        // The translations are fetch-joined: limit definitions, not SQL rows (one row per translation).
        /** @var list<CookieDefinition> $definitions */
        $definitions = iterator_to_array(new Paginator($qb->getQuery(), true), false);

        return $definitions;
    }

    /**
     * Returns whether at least one cookie definition exists for a configuration profile.
     *
     * Prefer this over loading the full inventory when only presence matters.
     */
    public function existsByConfig(CookieConsentConfig $config): bool
    {
        return $this->countByConfig($config) > 0;
    }

    /**
     * Counts cookie definitions for a configuration profile.
     */
    public function countByConfig(CookieConsentConfig $config): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.config = :config')
            ->setParameter('config', $config)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
