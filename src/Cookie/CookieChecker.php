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
}
