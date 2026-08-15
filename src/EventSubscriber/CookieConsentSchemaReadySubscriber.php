<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\EventSubscriber;

use Doctrine\DBAL\Connection;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Http\ColdStartRequestAttributes;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Throwable;

/**
 * Marks consent as not schema-ready when the CookieConsent config table is missing.
 *
 * Complements SiteBackup cold-start: after {@code database_create} the named schema
 * may exist while migrations have not created {@see CookieConsentConfig} yet.
 */
final class CookieConsentSchemaReadySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Connection $connection,
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

        if ($this->configTableExists()) {
            return;
        }

        $request->attributes->set(ColdStartRequestAttributes::COOKIE_CONSENT_SCHEMA_READY, false);
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
