<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Cookie;

use Nowo\CookieConsentBundle\Enum\CookieNameEnum;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Reads cookie consent state from the current HTTP request.
 */
class CookieChecker
{
    private readonly ?Request $request;

    /** @var array<string, bool>|null */
    private ?array $granularPreferences = null;

    /**
     * Creates a new cookie consent checker.
     *
     * @param RequestStack $requestStack The HTTP request stack
     */
    public function __construct(RequestStack $requestStack)
    {
        $this->request = $requestStack->getMainRequest();
    }

    /**
     * Returns whether the user has saved cookie consent preferences.
     *
     * @return bool True when the main consent cookie is present
     */
    public function isCookieConsentSavedByUser(): bool
    {
        if (!$this->request instanceof Request) {
            return false;
        }

        return $this->request->cookies->has(CookieNameEnum::COOKIE_CONSENT_NAME);
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
        if (!$this->request instanceof Request) {
            return false;
        }

        return $this->request->cookies->get(CookieNameEnum::getCookieCategoryName($category)) === 'true';
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
        if ($this->granularPreferences !== null) {
            return $this->granularPreferences;
        }

        if (!$this->request instanceof Request) {
            return null;
        }

        $raw = $this->request->cookies->get(CookieNameEnum::COOKIE_CONSENT_GRANULAR_NAME);

        if (!is_string($raw) || $raw === '') {
            return null;
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
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

        $this->granularPreferences = $preferences;

        return $this->granularPreferences;
    }
}
