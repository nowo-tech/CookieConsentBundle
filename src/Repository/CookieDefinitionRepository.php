<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieDefinition;

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
        /** @var list<CookieDefinition> $definitions */
        $definitions = $this->createQueryBuilder('d')
            ->andWhere('d.config = :config')
            ->setParameter('config', $config)
            ->orderBy('d.sortOrder', 'ASC')
            ->addOrderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $definitions;
    }
}
