<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\CookieConsentBundle\Entity\CookieDefinitionTranslation;

/**
 * Doctrine repository for {@see CookieDefinitionTranslation} entities.
 *
 * @extends ServiceEntityRepository<CookieDefinitionTranslation>
 */
class CookieDefinitionTranslationRepository extends ServiceEntityRepository
{
    /**
     * Creates a new cookie definition translation repository.
     *
     * @param ManagerRegistry $registry The Doctrine manager registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CookieDefinitionTranslation::class);
    }
}
