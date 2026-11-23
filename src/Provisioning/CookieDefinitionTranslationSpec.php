<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Provisioning;

use function sprintf;

/**
 * The provider and purpose of a cookie definition in one locale (1.36), for {@see CookieDefinitionSpec::withTranslation()}.
 *
 * A complete value: provisioning writes both texts. Natural key: the locale, compared byte for byte (`===`,
 * REQ-SEC-006).
 */
final readonly class CookieDefinitionTranslationSpec
{
    /** Longest provider (column length). */
    public const PROVIDER_MAX_LENGTH = 120;

    /**
     * @param string $locale The locale ("en", "es", "pt_BR"), its natural key
     * @param string $provider Who sets the cookie (max 120)
     * @param string $purpose What the cookie is for
     *
     * @throws InvalidProvisioningSpecException When the locale is malformed or a text is too long
     */
    public function __construct(
        public string $locale,
        public string $provider = '',
        public string $purpose = '',
    ) {
        SpecAssert::locale('Cookie definition translation locale', $locale);
        SpecAssert::length(sprintf('Cookie definition translation "%s" provider', $locale), $provider, self::PROVIDER_MAX_LENGTH);
        SpecAssert::length(sprintf('Cookie definition translation "%s" purpose', $locale), $purpose, null);
    }

    /**
     * Builds the translation from a decoded fixture: `{"provider": "...", "purpose": "..."}` (both optional, default
     * ""), the same shape as a `cookie_inventory` translation. Unknown keys and non-strings are refused.
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
            SpecAssert::string(sprintf('Cookie definition translation "%s" provider', $locale), $data['provider'] ?? ''),
            SpecAssert::string(sprintf('Cookie definition translation "%s" purpose', $locale), $data['purpose'] ?? ''),
        );
    }
}
