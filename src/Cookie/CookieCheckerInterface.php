<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Cookie;

use DateTimeImmutable;

/**
 * Reads the visitor's cookie consent from the current request (1.26, DDD P2).
 *
 * The documented read API of the bundle. {@see CookieChecker} is the default implementation; type-hint this interface
 * in host adapters so they can be test-doubled, and alias it to your own service to replace the read side.
 */
interface CookieCheckerInterface
{
    /**
     * Returns whether the visitor saved consent preferences that are still valid (present and not outdated).
     *
     * @return bool True when the main consent cookie is present and not outdated
     */
    public function isCookieConsentSavedByUser(): bool;

    /**
     * Returns whether the main consent cookie is present, outdated or not.
     *
     * @return bool True when the `Cookie_Consent` cookie is sent
     */
    public function hasConsentCookie(): bool;

    /**
     * Returns whether the saved consent predates the active profile's last re-consent request.
     *
     * @return bool True when the visitor must be asked again
     */
    public function isConsentOutdated(): bool;

    /**
     * Returns when the visitor saved consent.
     *
     * @return DateTimeImmutable|null The consent time, or null without a readable consent cookie
     */
    public function getConsentSavedAt(): ?DateTimeImmutable;

    /**
     * Returns whether the given category is allowed in the current request.
     *
     * @param string $category The cookie category identifier
     *
     * @return bool True when the category is allowed
     */
    public function isCategoryAllowedByUser(string $category): bool;

    /**
     * Returns whether a specific optional cookie is allowed for the current visitor.
     *
     * @param string $cookieName The cookie identifier
     * @param string $category The consent category slug
     *
     * @return bool True when the cookie is allowed
     */
    public function isCookieAllowedByUser(string $cookieName, string $category): bool;

    /**
     * Returns the per-cookie consent map.
     *
     * @return array<string, bool>|null The granular preferences or null when absent
     */
    public function getGranularPreferences(): ?array;
}
