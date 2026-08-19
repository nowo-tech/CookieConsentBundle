<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Nowo\CookieConsentBundle\Config\CookieConsentConfigResolver;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;

/**
 * Clears consent-profile runtime caches when database configuration changes.
 */
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postRemove)]
final class CookieConsentConfigRuntimeCacheListener
{
    public function __construct(
        private readonly CookieConsentConfigRepository $configRepository,
        private readonly CookieConsentConfigResolver $configResolver,
    ) {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->invalidateWhenConfigChanged($args->getObject());
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->invalidateWhenConfigChanged($args->getObject());
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $this->invalidateWhenConfigChanged($args->getObject());
    }

    public function invalidateWhenConfigChanged(object $entity): void
    {
        if (!$entity instanceof CookieConsentConfig) {
            return;
        }

        $this->configRepository->clearRuntimeCache();
        $this->configResolver->clearRuntimeCache();
    }
}
