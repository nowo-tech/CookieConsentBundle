<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Cookie;

use DateTimeImmutable;
use Exception;
use JsonException;
use Nowo\CookieConsentBundle\Config\ResolvedCookieConsentConfig;
use Nowo\CookieConsentBundle\Enum\CookieNameEnum;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

use function array_key_exists;
use function is_array;
use function is_string;
use function trim;

use const JSON_THROW_ON_ERROR;

/**
 * Reads cookie consent state from the current HTTP request.
 *
 * The request is looked up on every call: the service is shared and may outlive
 * a single request (FrankenPHP worker mode), so no request data is stored on it.
 *
 * @final since 1.19, will be final in 2.0 (not an extension point; decorate or replace the service instead)
 */
class CookieChecker
{
    /**
     * Creates a new cookie consent checker.
     *
     * @param RequestStack $requestStack The HTTP request stack
     */
    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    private function getRequest(): ?Request
    {
        return $this->requestStack->getMainRequest() ?? $this->requestStack->getCurrentRequest();
    }

    /**
     * Returns whether the user has saved cookie consent preferences that are still valid.
     *
     * 1.25 (JH-L1): a consent saved before the active profile's last re-consent request counts as not saved, so the
     * banner opens again and only necessary cookies apply until the visitor answers.
     *
     * @return bool True when the main consent cookie is present and not outdated
     */
    public function isCookieConsentSavedByUser(): bool
    {
        return $this->hasConsentCookie() && !$this->isConsentOutdated();
    }

    /**
     * Returns whether the main consent cookie is present, outdated or not.
     *
     * @return bool True when the `Cookie_Consent` cookie is sent
     */
    public function hasConsentCookie(): bool
    {
        $request = $this->getRequest();

        if (!$request instanceof Request) {
            return false;
        }

        return $request->cookies->has(CookieNameEnum::COOKIE_CONSENT_NAME);
    }

    /**
     * Returns whether the visitor's saved consent predates the active profile's last re-consent request.
     *
     * The comparison uses the `Cookie_Consent` value (the UTC time the consent was saved). Without a consent cookie,
     * or when the active profile never asked for re-consent, the consent is not outdated. A consent cookie whose
     * value cannot be read as a date is outdated once the profile asked for re-consent.
     *
     * @return bool True when the visitor must be asked again
     */
    public function isConsentOutdated(): bool
    {
        if (!$this->hasConsentCookie()) {
            return false;
        }

        $resolved = $this->getResolvedConfig();

        if (!$resolved instanceof ResolvedCookieConsentConfig) {
            return false;
        }

        return $resolved->getConfig()->isConsentOutdated($this->getConsentSavedAt());
    }

    /**
     * Returns when the visitor saved consent, read from the `Cookie_Consent` value.
     *
     * Accepts the 1.x ISO-8601 UTC value and older formats PHP can parse (e.g. RFC 2822 `date('r')`).
     *
     * @return DateTimeImmutable|null The consent time, or null without a readable consent cookie
     */
    public function getConsentSavedAt(): ?DateTimeImmutable
    {
        $raw = $this->getRequest()?->cookies->get(CookieNameEnum::COOKIE_CONSENT_NAME);

        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($raw);
        } catch (Exception) {
            return null;
        }
    }

    private function getResolvedConfig(): ?ResolvedCookieConsentConfig
    {
        foreach ([$this->requestStack->getMainRequest(), $this->requestStack->getCurrentRequest()] as $request) {
            if (!$request instanceof Request) {
                continue;
            }

            $resolved = $request->attributes->get('nowo_cookie_consent_config');

            if ($resolved instanceof ResolvedCookieConsentConfig) {
                return $resolved;
            }
        }

        return null;
    }

    /**
     * Returns whether the given category is allowed in the current request.
     *
     * @param string $category The cookie category identifier
     *
     * @return bool True when the category cookie is set to allowed
     */
    public function isCategoryAllowedByUser(string $category): bool
    {
        $request = $this->getRequest();

        if (!$request instanceof Request || $this->isConsentOutdated()) {
            return false;
        }

        return $request->cookies->get(CookieNameEnum::getCookieCategoryName($category)) === 'true';
    }

    /**
     * Returns whether a specific optional cookie is allowed for the current user.
     *
     * @param string $cookieName The cookie identifier
     * @param string $category The consent category slug
     *
     * @return bool True when the cookie is allowed
     */
    public function isCookieAllowedByUser(string $cookieName, string $category): bool
    {
        if ($category === 'required') {
            return true;
        }

        $granular = $this->getGranularPreferences();

        if ($granular !== null && array_key_exists($cookieName, $granular)) {
            return $granular[$cookieName];
        }

        return $this->isCategoryAllowedByUser($category);
    }

    /**
     * Returns the decoded per-cookie consent map from the request cookie.
     *
     * @return array<string, bool>|null The granular preferences or null when absent
     */
    public function getGranularPreferences(): ?array
    {
        $request = $this->getRequest();

        if (!$request instanceof Request || $this->isConsentOutdated()) {
            return null;
        }

        $raw = $request->cookies->get(CookieNameEnum::COOKIE_CONSENT_GRANULAR_NAME);

        if (!is_string($raw) || $raw === '') {
            return null;
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (!is_array($decoded)) {
            return null;
        }

        $preferences = [];

        foreach ($decoded as $name => $allowed) {
            if (!is_string($name)) {
                continue;
            }

            $preferences[$name] = $allowed === true || $allowed === 'true';
        }

        return $preferences;
    }
}
