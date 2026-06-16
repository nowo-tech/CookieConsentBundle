<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Config;

use InvalidArgumentException;

/**
 * Validates custom HTML/SVG markup for the preferences bubble icon.
 */
final class PreferencesBubbleIconSanitizer
{
    public const DEMO_EMOJI_ICON_HTML = '<span class="nowo-cookie-consent__preferences-bubble-emoji" aria-hidden="true">🍪</span>';

    /**
     * @param string|null $html Raw icon markup from configuration
     *
     * @return string|null Sanitized markup or null when empty
     */
    public static function sanitize(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $html = trim($html);

        if ($html === '') {
            return null;
        }

        $blocked = [
            '/<script\b/i',
            '/javascript:/i',
            '/\son\w+\s*=/i',
            '/<iframe\b/i',
            '/<object\b/i',
            '/<embed\b/i',
            '/<link\b/i',
            '/<style\b/i',
        ];

        foreach ($blocked as $pattern) {
            if (preg_match($pattern, $html)) {
                throw new InvalidArgumentException('Invalid preferences bubble icon markup.');
            }
        }

        return $html;
    }
}
