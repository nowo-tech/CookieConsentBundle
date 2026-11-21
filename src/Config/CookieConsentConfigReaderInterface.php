<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Config;

/**
 * Read port over the consent profiles (1.34, REQ-ARCH-001 rules 1 and 3): host code (a DDD application layer, an
 * admin entry point, a bootstrap check) reads the profiles as {@see ConsentProfileView} value objects and references
 * them by id, instead of type-hinting the concrete `CookieConsentConfigRepository` and holding the
 * `CookieConsentConfig` entity.
 *
 * Only ENABLED profiles are returned (the ones the consent modal and the admin pages use). Read-only: nothing is
 * written, flushed or locked. Reads go through the bundle's own manager (the private one with
 * `doctrine.private_entity_manager: true`) and its per-unit memo, cleared at every request boundary.
 *
 * Without Doctrine (a host on the YAML path) there are no profile rows: every method answers null or an empty list.
 *
 * Alias it to your own service to replace it.
 */
interface CookieConsentConfigReaderInterface
{
    /**
     * The id of the enabled default profile, or null when there is none.
     */
    public function defaultProfileId(): ?int;

    /**
     * The enabled default profile, with its copy for `$locale` when given (null: no copy).
     */
    public function defaultProfile(?string $locale = null): ?ConsentProfileView;

    /**
     * The enabled profile with that id, with its copy for `$locale` when given; null when the profile does not exist or
     * is disabled.
     */
    public function enabledProfile(int $profileId, ?string $locale = null): ?ConsentProfileView;

    /**
     * Every enabled profile, by priority (highest first) then id, with its copy for `$locale` when given.
     *
     * @return list<ConsentProfileView>
     */
    public function enabledProfiles(?string $locale = null): array;
}
