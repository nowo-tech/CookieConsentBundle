<?php

declare(strict_types=1);

namespace App\Demo;

final class DemoLocale
{
    public const DEFAULT = 'en';

    public const REQUIREMENT = 'en|es|it|fr|de|pt|nl|pl|ca';

    /** @var list<string> */
    public const ALL = ['en', 'es', 'it', 'fr', 'de', 'pt', 'nl', 'pl', 'ca'];

    public static function isSupported(string $locale): bool
    {
        return in_array($locale, self::ALL, true);
    }
}
