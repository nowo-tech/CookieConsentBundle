<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\EventSubscriber;

use Doctrine\DBAL\Connection;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Http\ColdStartRequestAttributes;
use Psr\Clock\ClockInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Service\ResetInterface;
use Throwable;

/**
 * Marks consent as not schema-ready when the CookieConsent config table is missing.
 *
 * Complements SiteBackup cold-start: after {@code database_create} the named schema
 * may exist while migrations have not created {@see CookieConsentConfig} yet.
 *
 * A positive probe ("table exists") is memoized per process for {@see $schemaReadyCacheTtl}
 * seconds. The memo deliberately survives between requests (this service does not implement
 * {@see ResetInterface}), so in FrankenPHP / RoadRunner worker mode
 * anonymous public pages do no {@code information_schema} probe after warm-up. A missing table
 * is never memoized: the probe keeps running until migrations create it.
 */
final class CookieConsentSchemaReadySubscriber implements EventSubscriberInterface
{
    public const DEFAULT_SCHEMA_READY_CACHE_TTL = 60;

    /** Unix timestamp until which the last positive probe is trusted; null = probe on next request. */
    private ?int $readyUntil = null;

    /**
     * @param int $schemaReadyCacheTtl Seconds a positive probe is reused within one worker; 0 disables the memo
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly ?ClockInterface $clock = null,
        private readonly int $schemaReadyCacheTtl = self::DEFAULT_SCHEMA_READY_CACHE_TTL,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // After SiteBackup cold-start probe (35/34); before CookieConsent translations (19).
        return [KernelEvents::REQUEST => ['onKernelRequest', 33]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if ($request->attributes->has(ColdStartRequestAttributes::COOKIE_CONSENT_SCHEMA_READY)) {
            return;
        }

        if ($request->attributes->get(ColdStartRequestAttributes::SITE_BACKUP_SCHEMA_EXISTS) === false) {
            return;
        }

        if ($this->isSchemaReady()) {
            return;
        }

        $request->attributes->set(ColdStartRequestAttributes::COOKIE_CONSENT_SCHEMA_READY, false);
    }

    /**
     * Forgets the memoized positive probe (e.g. after dropping the schema in tests or tooling).
     */
    public function clearSchemaReadyMemo(): void
    {
        // @igor-ignore - Intentional cross-request memo of a positive schema probe (bounded by TTL).
        $this->readyUntil = null;
    }

    private function isSchemaReady(): bool
    {
        $now = $this->now();
        if ($this->readyUntil !== null && $now < $this->readyUntil) {
            return true;
        }

        if (!$this->configTableExists()) {
            // @igor-ignore - Intentional cross-request memo of a positive schema probe (bounded by TTL).
            $this->readyUntil = null;

            return false;
        }

        // @igor-ignore - Intentional cross-request memo of a positive schema probe (bounded by TTL).
        $this->readyUntil = $this->schemaReadyCacheTtl > 0 ? $now + $this->schemaReadyCacheTtl : null;

        return true;
    }

    private function now(): int
    {
        return $this->clock instanceof ClockInterface ? $this->clock->now()->getTimestamp() : time();
    }

    private function configTableExists(): bool
    {
        try {
            $schemaManager = $this->connection->createSchemaManager();

            return $schemaManager->tablesExist([CookieConsentConfig::TABLE_NAME]);
        } catch (Throwable) {
            try {
                // Fallback when SchemaManager / platform differs.
                $this->connection->executeQuery('SELECT 1 FROM ' . CookieConsentConfig::TABLE_NAME . ' LIMIT 1');

                return true;
            } catch (Throwable) {
                return false;
            }
        }
    }
}
