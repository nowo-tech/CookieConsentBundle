<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Nowo\CookieConsentBundle\Config\CookieConsentConfigResolver;
use Nowo\CookieConsentBundle\Config\CookieInventoryProvider;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfigTranslation;
use Nowo\CookieConsentBundle\Entity\CookieDefinition;
use Nowo\CookieConsentBundle\Entity\CookieDefinitionTranslation;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;

/**
 * Clears consent-profile runtime caches when database configuration changes.
 */
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postRemove)]
final class CookieConsentConfigRuntimeCacheListener
{
    /**
     * Receives the services whose runtime caches are cleared.
     */
    public function __construct(
        private readonly CookieConsentConfigRepository $configRepository,
        private readonly CookieConsentConfigResolver $configResolver,
        private readonly ?CookieInventoryProvider $inventoryProvider = null,
    ) {
    }

    /**
     * Clears the caches after a consent entity is created.
     */
    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->invalidateWhenConfigChanged($args->getObject());
    }

    /**
     * Clears the caches after a consent entity is updated.
     */
    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->invalidateWhenConfigChanged($args->getObject());
    }

    /**
     * Clears the caches after a consent entity is removed.
     */
    public function postRemove(PostRemoveEventArgs $args): void
    {
        $this->invalidateWhenConfigChanged($args->getObject());
    }

    /**
     * Clears the caches when the entity is a consent config, a cookie definition or one of their translations.
     */
    public function invalidateWhenConfigChanged(object $entity): void
    {
        if (!$entity instanceof CookieConsentConfig
            && !$entity instanceof CookieConsentConfigTranslation
            && !$entity instanceof CookieDefinition
            && !$entity instanceof CookieDefinitionTranslation
        ) {
            return;
        }

        // @igor-ignore - Not shared worker service state.
        $this->configRepository->clearRuntimeCache();
        // @igor-ignore - Not shared worker service state.
        $this->configResolver->clearRuntimeCache();
        $this->inventoryProvider?->clearRuntimeCache();
    }
}
