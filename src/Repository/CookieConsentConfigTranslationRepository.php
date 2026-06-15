<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfigTranslation;

/**
 * Doctrine repository for {@see CookieConsentConfigTranslation} entities.
 *
 * @extends ServiceEntityRepository<CookieConsentConfigTranslation>
 */
class CookieConsentConfigTranslationRepository extends ServiceEntityRepository
{
    /**
     * Creates a new consent configuration translation repository.
     *
     * @param ManagerRegistry $registry The Doctrine manager registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CookieConsentConfigTranslation::class);
    }

    /**
     * Finds the translation for a configuration and locale pair.
     *
     * @param CookieConsentConfig $config The parent configuration
     * @param string $locale The locale code
     *
     * @return CookieConsentConfigTranslation|null The matching translation or null
     */
    public function findOneForConfigAndLocale(CookieConsentConfig $config, string $locale): ?CookieConsentConfigTranslation
    {
        return $this->findOneBy([
            'config' => $config,
            'locale' => $locale,
        ]);
    }
}
