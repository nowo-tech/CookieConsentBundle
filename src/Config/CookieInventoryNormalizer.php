<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Config;

use Nowo\CookieConsentBundle\Entity\CookieDefinition;

/**
 * Normalizes cookie inventory entries from bundle YAML configuration.
 */
final class CookieInventoryNormalizer
{
    /**
     * @param list<array<string, mixed>> $entries
     *
     * @return list<array{
     *     name: string,
     *     duration: string,
     *     category: string,
     *     type: string,
     *     sort_order: int,
     *     allowed_by_default: bool,
     *     translations: array<string, array{provider: string, purpose: string}>
     * }>
     */
    public static function normalize(array $entries): array
    {
        $normalized = [];

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $name = isset($entry['name']) ? trim((string) $entry['name']) : '';

            if ($name === '') {
                continue;
            }

            $sortOrder = $entry['sort_order'] ?? $entry['sortOrder'] ?? 0;
            $sortOrder = is_numeric($sortOrder) ? (int) $sortOrder : 0;

            $type = isset($entry['type']) ? (string) $entry['type'] : CookieDefinition::TYPE_FIRST_PARTY;
            if (!in_array($type, [CookieDefinition::TYPE_FIRST_PARTY, CookieDefinition::TYPE_THIRD_PARTY], true)) {
                $type = CookieDefinition::TYPE_FIRST_PARTY;
            }

            /** @var array<string, array{provider: string, purpose: string}> $translations */
            $translations = [];

            if (isset($entry['translations']) && is_array($entry['translations'])) {
                foreach ($entry['translations'] as $locale => $row) {
                    if (!is_string($locale) || !is_array($row)) {
                        continue;
                    }

                    $translations[$locale] = [
                        'provider' => isset($row['provider']) ? (string) $row['provider'] : '',
                        'purpose'  => isset($row['purpose']) ? (string) $row['purpose'] : '',
                    ];
                }
            }

            if ($translations === [] && (isset($entry['provider']) || isset($entry['purpose']))) {
                $translations['en'] = [
                    'provider' => isset($entry['provider']) ? (string) $entry['provider'] : '',
                    'purpose'  => isset($entry['purpose']) ? (string) $entry['purpose'] : '',
                ];
            }

            $allowedByDefault = $entry['allowed_by_default'] ?? $entry['allowedByDefault'] ?? true;
            if (!is_bool($allowedByDefault)) {
                $allowedByDefault = filter_var($allowedByDefault, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true;
            }

            $normalized[] = [
                'name'               => $name,
                'duration'           => isset($entry['duration']) ? (string) $entry['duration'] : '',
                'category'           => isset($entry['category']) ? (string) $entry['category'] : 'required',
                'type'               => $type,
                'sort_order'         => $sortOrder,
                'allowed_by_default' => $allowedByDefault,
                'translations'       => $translations,
            ];
        }

        usort(
            $normalized,
            static fn (array $left, array $right): int => [$left['sort_order'], $left['name']] <=> [$right['sort_order'], $right['name']],
        );

        return $normalized;
    }
}
