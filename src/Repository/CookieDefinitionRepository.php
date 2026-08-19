<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieDefinition;

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
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CookieDefinition::class);
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
            ->orderBy('d.sortOrder', 'ASC')
            ->addOrderBy('d.name', 'ASC');

        if ($pageSize !== null) {
            $page     = max(1, $page);
            $pageSize = max(1, $pageSize);
            $qb->setFirstResult(($page - 1) * $pageSize)
                ->setMaxResults($pageSize);
        }

        /** @var list<CookieDefinition> $definitions */
        $definitions = $qb->getQuery()->getResult();

        return $definitions;
    }

    /**
     * Returns whether at least one cookie definition exists for a configuration profile.
     *
     * Prefer this over loading the full inventory when only presence matters.
     *
     * @return bool
     */
    public function existsByConfig(CookieConsentConfig $config): bool
    {
        return $this->countByConfig($config) > 0;
    }

    /**
     * Counts cookie definitions for a configuration profile.
     *
     * @return int
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
