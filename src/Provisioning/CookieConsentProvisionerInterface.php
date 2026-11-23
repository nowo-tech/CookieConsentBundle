<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Provisioning;

use Nowo\CookieConsentBundle\Config\CookieConsentConfigReaderInterface;
use Nowo\TransactionContract\TransientStorageFailure;

/**
 * Host write port for seeding and provisioning the consent profile and its cookie inventory (1.36, REQ-ARCH-001
 * rules 1 and 3): a seeder, a fixture loader, an installer or a console command describes what must exist with value
 * objects; the bundle writes it with its own entity manager, never the application's.
 *
 * - **Own manager.** The rows are written through a private entity manager of the bundle on the connection of the
 *   bundle's manager (`doctrine.entity_manager`, else the default one), whatever `doctrine.private_entity_manager`
 *   says: the application's managers are never flushed, cleared, closed or reset, and their pending changes are never
 *   written. The private manager is dropped when the call returns (no state survives the call: FrankenPHP worker mode
 *   with the kernel reset off, long console commands).
 * - **One transaction per call** (transaction-doctrine's runner; a savepoint inside a transaction of yours, whose
 *   commit then decides).
 * - **Idempotent.** Natural keys, compared byte for byte (`===`, REQ-SEC-006): the enabled default profile, a profile
 *   copy's locale, a cookie's name in the default profile, a cookie copy's locale. A second call with the same
 *   specification writes nothing and reports every item unchanged. Nothing is ever deleted.
 * - **Concurrency-safe** (REQ-RUNTIME-003). Every call holds the bundle's provisioning lock (MySQL / MariaDB
 *   `GET_LOCK()`, PostgreSQL `pg_advisory_xact_lock()`; SQLite serializes writers), so two seeders at once never create
 *   two default profiles or the same cookie twice: the second waits, then finds the first one's rows. A unique key
 *   refusing a write (an admin adding the same cookie at that moment) and a lock conflict are retried (3 attempts
 *   outside a transaction; one inside yours). An item whose stored values change advances its `row_version`, so an
 *   admin page opened before is refused as stale instead of writing the old values back.
 *
 * Read access: {@see CookieConsentConfigReaderInterface} (profiles as view objects).
 *
 * Registered only with DoctrineBundle (a host on the YAML path has no profile tables).
 */
interface CookieConsentProvisionerInterface
{
    /**
     * Makes sure the enabled default profile exists and has the settings and copies of `$spec` (the settings the
     * specification does not give keep their stored value). Creates it (enabled, default) when there is none.
     *
     * @throws TransientStorageFailure When a lock conflict outlasted every attempt (retry later)
     * @throws CookieConsentProvisioningException When a unique key still refused a copy after every attempt
     *
     * @return ProvisioningResult One item: the profile (created, updated or unchanged) and its id
     */
    public function ensureDefaultProfile(DefaultProfileSpec $spec): ProvisioningResult;

    /**
     * Inserts or updates the cookie definitions of the enabled default profile, by cookie name. Definitions the list
     * does not name, and translations of other locales, are kept.
     *
     * @throws CookieConsentProvisioningException When there is no enabled default profile, or a unique key still
     *                                            refused a definition after every attempt (a look-alike name)
     * @throws TransientStorageFailure When a lock conflict outlasted every attempt (retry later)
     *
     * @return ProvisioningResult One item per definition of `$definitions`, and the default profile's id
     */
    public function upsertCookieDefinitions(CookieDefinitionSpecs $definitions): ProvisioningResult;
}
