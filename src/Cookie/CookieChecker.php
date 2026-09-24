<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Cookie;

use JsonException;
use Nowo\CookieConsentBundle\Enum\CookieNameEnum;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

use function array_key_exists;
use function is_array;
use function is_string;

use const JSON_THROW_ON_ERROR;

/**
 * Reads cookie consent state from the current HTTP request.
 *
 * The main request is looked up on every call: the service is shared and may outlive
 * a single request (FrankenPHP worker mode), so no request data is stored on it.
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

    /**
     * Returns whether the user has saved cookie consent preferences.
     *
     * @return bool True when the main consent cookie is present
     */
    public function isCookieConsentSavedByUser(): bool
    {
        $request = $this->requestStack->getMainRequest();

        if (!$request instanceof Request) {
            return false;
        }

        return $request->cookies->has(CookieNameEnum::COOKIE_CONSENT_NAME);
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
        $request = $this->requestStack->getMainRequest();

        if (!$request instanceof Request) {
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
        $request = $this->requestStack->getMainRequest();

        if (!$request instanceof Request) {
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
