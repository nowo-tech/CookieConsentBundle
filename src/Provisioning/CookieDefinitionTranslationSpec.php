<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Provisioning;

use function sprintf;

/**
 * The provider and purpose of a cookie definition in one locale (1.36), for {@see CookieDefinitionSpec::withTranslation()}.
 *
 * A text is written only when it is given: null (or a key missing from {@see fromArray()}) keeps the stored text of an
 * existing translation ("" for a new one); an empty string clears it. Natural key: the locale, compared byte for byte (`===`,
 * REQ-SEC-006).
 */
final readonly class CookieDefinitionTranslationSpec
{
    /** Longest provider (column length). */
    public const PROVIDER_MAX_LENGTH = 120;

    /**
     * @param string $locale The locale ("en", "es", "pt_BR"), its natural key
     * @param string|null $provider Who sets the cookie (max 120); null keeps the stored text
     * @param string|null $purpose What the cookie is for; null keeps the stored text
     *
     * @throws InvalidProvisioningSpecException When the locale is malformed or a text is too long
     */
    public function __construct(
        public string $locale,
        public ?string $provider = null,
        public ?string $purpose = null,
    ) {
        SpecAssert::locale('Cookie definition translation locale', $locale);
        if ($provider !== null) {
            SpecAssert::length(sprintf('Cookie definition translation "%s" provider', $locale), $provider, self::PROVIDER_MAX_LENGTH);
        }
        if ($purpose !== null) {
            SpecAssert::length(sprintf('Cookie definition translation "%s" purpose', $locale), $purpose, null);
        }
    }

    /**
     * Builds the translation from a decoded fixture: `{"provider": "...", "purpose": "..."}` (both optional: a missing
     * key keeps the stored text), the same shape as a `cookie_inventory` translation. Unknown keys and non-strings are refused.
     *
     * @param array<mixed> $texts
     *
     * @throws InvalidProvisioningSpecException
     */
    public static function fromArray(string $locale, array $texts): self
    {
        $what = sprintf('the cookie definition translation "%s"', $locale);
        $data = SpecAssert::map($what, $texts);
        SpecAssert::knownKeys($what, $data, ['provider', 'purpose']);

        return new self(
            $locale,
            SpecAssert::nullableString(sprintf('Cookie definition translation "%s" provider', $locale), $data['provider'] ?? null),
            SpecAssert::nullableString(sprintf('Cookie definition translation "%s" purpose', $locale), $data['purpose'] ?? null),
        );
    }
}
