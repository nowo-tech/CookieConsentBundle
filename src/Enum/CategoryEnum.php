<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Enum;

/**
 * Supported optional cookie consent categories.
 */
final class CategoryEnum
{
    public const CATEGORY_ANALYTICS = 'analytics';

    public const CATEGORY_TRACKING = 'tracking';

    public const CATEGORY_MARKETING = 'marketing';

    public const CATEGORY_PREFERENCES = 'preferences';
}
