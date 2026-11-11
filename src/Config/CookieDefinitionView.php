<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Config;

/**
 * Read-only row of the cookie inventory for one locale (1.28, architecture bar rule 1): from the profile's
 * `CookieDefinition` rows or the YAML `cookie_inventory`, never an entity.
 */
final readonly class CookieDefinitionView
{
    public function __construct(
        public string $name,
        public string $provider,
        public string $purpose,
        public string $duration,
        public string $category,
        public string $type,
        public bool $allowedByDefault,
    ) {
    }

    /**
     * @param array{name: string, provider: string, purpose: string, duration: string, category: string, type: string, allowed_by_default: bool} $row
     */
    public static function fromRow(array $row): self
    {
        return new self($row['name'], $row['provider'], $row['purpose'], $row['duration'], $row['category'], $row['type'], $row['allowed_by_default']);
    }
}
