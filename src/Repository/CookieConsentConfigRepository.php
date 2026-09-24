<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
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
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CookieConsentConfig::class);
    }

    /**
     * Clears in-memory lookup caches after admin writes or Doctrine flushes.
     *
     * Also used as {@see ResetInterface::reset()} between FrankenPHP worker requests.
     */
    public function clearRuntimeCache(): void
    {
        $this->defaultEnabledLoaded = false;
        $this->defaultEnabled       = null;
        $this->allEnabled           = null;
        $this->allEnabledNonDefault = null;
    }

    public function reset(): void
    {
        $this->clearRuntimeCache();
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
            ->orderBy('config.priority', 'DESC')
            ->addOrderBy('config.id', 'ASC')
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
            ->orderBy('config.priority', 'DESC')
            ->addOrderBy('config.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->allEnabledNonDefault = $configs;
    }
}
