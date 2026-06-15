<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Enum;

/**
 * Cookie names used to store consent state in the browser.
 */
final class CookieNameEnum
{
    public const COOKIE_CONSENT_NAME = 'Cookie_Consent';

    public const COOKIE_CONSENT_KEY_NAME = 'Cookie_Consent_Key';

    public const COOKIE_CONSENT_GRANULAR_NAME = 'Cookie_Consent_Granular';

    public const COOKIE_CATEGORY_NAME_PREFIX = 'Cookie_Category_';

    /**
     * Returns the cookie name used for the given consent category.
     *
     * @param string $category The cookie category identifier
     *
     * @return string The category cookie name
     */
    public static function getCookieCategoryName(string $category): string
    {
        return self::COOKIE_CATEGORY_NAME_PREFIX . $category;
    }
}
