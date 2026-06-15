<?php

declare(strict_types=1);

namespace App\Demo;

/**
 * Backward-compatible flat cookie list derived from {@see DemoCookieCatalog}.
 *
 * Returns English provider and purpose strings for legacy consumers that do not
 * yet support per-locale cookie definition translations.
 */
final class DemoCookieDefinitions
{
    /**
     * @return list<array{
     *     name: string,
     *     provider: string,
     *     purpose: string,
     *     duration: string,
     *     category: string,
     *     type: string,
     *     sortOrder: int
     * }>
     */
    public static function samples(): array
    {
        $samples = [];

        foreach (DemoCookieCatalog::cookies() as $cookie) {
            $translation = $cookie['translations']['en'];

            $samples[] = [
                'name'      => $cookie['name'],
                'provider'  => $translation['provider'],
                'purpose'   => $translation['purpose'],
                'duration'  => $cookie['duration'],
                'category'  => $cookie['category'],
                'type'      => $cookie['type'],
                'sortOrder' => $cookie['sortOrder'],
            ];
        }

        return $samples;
    }
}
