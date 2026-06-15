<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieDefinition;

/**
 * @extends ServiceEntityRepository<CookieDefinition>
 */
class CookieDefinitionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CookieDefinition::class);
    }

    /**
     * @return list<CookieDefinition>
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
