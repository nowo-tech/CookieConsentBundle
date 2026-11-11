<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Config;

use DateTimeImmutable;

/**
 * Read-only view of the consent profile resolved for a request (1.28, architecture bar rule 1): scalars only, so host
 * code (a DDD application layer, a projection, an API) never holds the `CookieConsentConfig` entity.
 */
final readonly class ConsentProfileView
{
    /**
     * @param int|null $id profile id (null only for a profile not saved yet)
     * @param list<string> $routePatterns route patterns the profile targets (empty for the default profile)
     * @param string|null $locale locale of the translated copy, null when the profile has none for the request
     * @param array<string, string> $translationMessages translated copy keyed by translation identifier
     * @param DateTimeImmutable|null $reconsentRequiredAt consents saved before this time are asked again
     */
    public function __construct(
        public ?int $id,
        public ?string $name,
        public int $revision,
        public bool $isDefault,
        public int $priority,
        public bool $autoShow,
        public array $routePatterns,
        public ?string $locale,
        public array $translationMessages,
        public ?DateTimeImmutable $reconsentRequiredAt,
    ) {
    }
}
