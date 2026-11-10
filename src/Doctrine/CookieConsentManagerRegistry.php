<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Doctrine;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;

use function str_starts_with;
use function strrpos;
use function substr;

/**
 * Manager registry seen by the bundle's repositories and logger when `doctrine.entity_manager` is set (1.27).
 *
 * The bundle entities resolve to that manager explicitly instead of through `getManagerForClass()` of the host
 * registry; every other call is delegated unchanged. Stateless (worker-safe): the manager is looked up per call.
 */
final readonly class CookieConsentManagerRegistry implements ManagerRegistry
{
    private const ENTITY_NAMESPACE = 'Nowo\\CookieConsentBundle\\Entity\\';

    /**
     * @param ManagerRegistry $inner The host registry (`doctrine`)
     * @param string $managerName The configured `doctrine.entity_manager`
     */
    public function __construct(
        private ManagerRegistry $inner,
        private string $managerName,
    ) {
    }

    public function getManagerForClass(string $class): ?ObjectManager
    {
        $proxyMarker = strrpos($class, '\\__CG__\\');
        $realClass   = $proxyMarker === false ? $class : substr($class, $proxyMarker + 8);

        if (str_starts_with($realClass, self::ENTITY_NAMESPACE)) {
            return $this->inner->getManager($this->managerName);
        }

        return $this->inner->getManagerForClass($class);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $persistentObject
     *
     * @return ObjectRepository<T>
     */
    public function getRepository(string $persistentObject, ?string $persistentManagerName = null): ObjectRepository
    {
        if ($persistentManagerName === null && str_starts_with($persistentObject, self::ENTITY_NAMESPACE)) {
            $persistentManagerName = $this->managerName;
        }

        return $this->inner->getRepository($persistentObject, $persistentManagerName);
    }

    public function getDefaultConnectionName(): string
    {
        return $this->inner->getDefaultConnectionName();
    }

    public function getConnection(?string $name = null): object
    {
        return $this->inner->getConnection($name);
    }

    public function getConnections(): array
    {
        return $this->inner->getConnections();
    }

    public function getConnectionNames(): array
    {
        return $this->inner->getConnectionNames();
    }

    public function getDefaultManagerName(): string
    {
        return $this->inner->getDefaultManagerName();
    }

    public function getManager(?string $name = null): ObjectManager
    {
        return $this->inner->getManager($name);
    }

    public function getManagers(): array
    {
        return $this->inner->getManagers();
    }

    public function resetManager(?string $name = null): ObjectManager
    {
        return $this->inner->resetManager($name);
    }

    public function getManagerNames(): array
    {
        return $this->inner->getManagerNames();
    }
}
