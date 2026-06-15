<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;

/**
 * Doctrine repository for {@see CookieConsentConfig} entities.
 *
 * @extends ServiceEntityRepository<CookieConsentConfig>
 */
class CookieConsentConfigRepository extends ServiceEntityRepository
{
    /**
     * Creates a new consent configuration repository.
     *
     * @param ManagerRegistry $registry The Doctrine manager registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CookieConsentConfig::class);
    }

    /**
     * Returns the enabled default consent configuration, if any.
     *
     * @return CookieConsentConfig|null The default configuration or null
     */
    public function findDefaultEnabled(): ?CookieConsentConfig
    {
        return $this->createQueryBuilder('config')
            ->andWhere('config.enabled = :enabled')
            ->andWhere('config.default = :default')
            ->setParameter('enabled', true)
            ->setParameter('default', true)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Returns all enabled consent configurations ordered by priority.
     *
     * @return list<CookieConsentConfig>
     */
    public function findAllEnabled(): array
    {
        /** @var list<CookieConsentConfig> $configs */
        $configs = $this->createQueryBuilder('config')
            ->andWhere('config.enabled = :enabled')
            ->setParameter('enabled', true)
            ->orderBy('config.priority', 'DESC')
            ->addOrderBy('config.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $configs;
    }

    /**
     * Returns enabled non-default consent configurations ordered by priority.
     *
     * @return list<CookieConsentConfig>
     */
    public function findAllEnabledNonDefault(): array
    {
        /** @var list<CookieConsentConfig> $configs */
        $configs = $this->createQueryBuilder('config')
            ->andWhere('config.enabled = :enabled')
            ->andWhere('config.default = :default')
            ->setParameter('enabled', true)
            ->setParameter('default', false)
            ->orderBy('config.priority', 'DESC')
            ->addOrderBy('config.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $configs;
    }
}
