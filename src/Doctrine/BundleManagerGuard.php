<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use LogicException;

use function sprintf;

/**
 * 2.0 (REQ-ARCH-001 rule 6): the repository saves write only through the bundle's own manager (the private entity
 * manager, or a dedicated `doctrine.entity_manager`), and only an entity that manager holds.
 *
 * A save given the application's manager would flush the application's own pending changes with it; a save given an
 * entity the bundle's manager does not hold (loaded or created through the application's manager) would write nothing
 * or insert it again. Both fail before anything is written. Managers are compared through their unit of work (a
 * private manager wraps the current one).
 *
 * @internal
 */
final class BundleManagerGuard
{
    /**
     * @param EntityManagerInterface $given the manager the caller passed
     * @param EntityManagerInterface $bundle the repository's own manager
     * @param object $entity the entity to save
     * @param string $method the public save, for the message
     *
     * @throws LogicException when `$given` is not the bundle's manager or does not hold `$entity`
     */
    public static function assertBundleManager(EntityManagerInterface $given, EntityManagerInterface $bundle, object $entity, string $method): void
    {
        if ($given->getUnitOfWork() === $bundle->getUnitOfWork() && $given->contains($entity)) {
            return;
        }

        throw new LogicException(sprintf('%s() needs the bundle\'s entity manager and an entity it holds: since 2.0 the bundle writes its profiles and cookie definitions through its own (private) entity manager, never through yours. Load the entity through the bundle\'s repository and pass the manager that holds it (the admin controllers do), or write through Nowo\CookieConsentBundle\Provisioning\CookieConsentProvisionerInterface. Nothing was written. See UPGRADING.md "To 2.0.0".', $method));
    }
}
