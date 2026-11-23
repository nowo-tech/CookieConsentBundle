<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Provisioning;

use Nowo\CookieConsentBundle\Entity\CookieDefinition;

use function array_key_exists;
use function array_values;
use function sprintf;

/**
 * One cookie of the default profile's inventory (1.36), for {@see CookieDefinitionSpecs}.
 *
 * Natural key: the cookie name within the default profile, compared byte for byte (`===`, REQ-SEC-006): "NID" and
 * "nid" are two cookies. Provisioning writes every field below and the translations given; translations of other
 * locales already stored are kept. Immutable: {@see withTranslation()} returns a copy.
 */
final class CookieDefinitionSpec
{
    /** Longest cookie name (column length). */
    public const NAME_MAX_LENGTH = 120;

    /** Longest duration label (column length). */
    public const DURATION_MAX_LENGTH = 60;

    /** Longest category key (column length). */
    public const CATEGORY_MAX_LENGTH = 40;

    /** Cookie party types. */
    public const TYPES = [CookieDefinition::TYPE_FIRST_PARTY, CookieDefinition::TYPE_THIRD_PARTY];

    /** Keys of {@see fromArray()}: the `cookie_inventory` shape (snake_case, with the camelCase aliases). */
    public const KEYS = ['name', 'category', 'duration', 'type', 'sort_order', 'sortOrder', 'allowed_by_default', 'allowedByDefault', 'translations'];

    public readonly string $name;

    public readonly string $category;

    public readonly string $duration;

    public readonly string $type;

    /** Pre-ticked before consent (granular selection). */
    public readonly bool $allowedByDefault;

    /** @var array<string, CookieDefinitionTranslationSpec> by locale (keys prefixed) */
    private array $translations = [];

    /**
     * @param string $name The cookie name (natural key; max 120, no surrounding whitespace or control characters)
     * @param string $category The consent category key ("required", "analytics", "marketing", ...; max 40)
     * @param string $duration Retention label shown to visitors ("Session", "2 years"; max 60)
     * @param string $type `first_party` or `third_party`
     * @param int $sortOrder Display order within the profile
     * @param bool|null $allowedByDefault Pre-ticked before consent; null: true for the "required" category, false otherwise (as `cookie_inventory`)
     *
     * @throws InvalidProvisioningSpecException
     */
    public function __construct(
        string $name,
        string $category = 'required',
        string $duration = '',
        string $type = CookieDefinition::TYPE_FIRST_PARTY,
        public readonly int $sortOrder = 0,
        ?bool $allowedByDefault = null,
    ) {
        $this->name             = SpecAssert::key('Cookie definition name', $name, self::NAME_MAX_LENGTH);
        $this->category         = SpecAssert::key(sprintf('Cookie definition "%s" category', $name), $category, self::CATEGORY_MAX_LENGTH);
        $this->duration         = SpecAssert::length(sprintf('Cookie definition "%s" duration', $name), $duration, self::DURATION_MAX_LENGTH);
        $this->type             = SpecAssert::choice(sprintf('Cookie definition "%s" type', $name), $type, self::TYPES);
        $this->allowedByDefault = $allowedByDefault ?? $category === 'required';
    }

    /**
     * Builds the definition from a decoded fixture, in the shape of a `cookie_inventory` entry: `name` (required),
     * `category`, `duration`, `type`, `sort_order` (alias `sortOrder`), `allowed_by_default` (alias
     * `allowedByDefault`) and `translations`, a map of locale to `{"provider": ..., "purpose": ...}`. Unknown keys and
     * values of the wrong type are refused (no coercion).
     *
     * @param array<mixed> $data
     *
     * @throws InvalidProvisioningSpecException
     */
    public static function fromArray(array $data): self
    {
        $map = SpecAssert::map('a cookie definition', $data);
        SpecAssert::knownKeys('a cookie definition', $map, self::KEYS);
        if (!array_key_exists('name', $map)) {
            throw new InvalidProvisioningSpecException('Missing key "name" in a cookie definition.');
        }

        $name      = SpecAssert::string('Cookie definition name', $map['name']);
        $field     = static fn (string $key): string => sprintf('Cookie definition "%s" %s', $name, $key);
        $sort      = $map['sort_order'] ?? $map['sortOrder'] ?? 0;
        $byDefault = array_key_exists('allowed_by_default', $map) ? $map['allowed_by_default'] : ($map['allowedByDefault'] ?? null);
        $spec      = new self(
            $name,
            SpecAssert::string($field('category'), $map['category'] ?? 'required'),
            SpecAssert::string($field('duration'), $map['duration'] ?? ''),
            SpecAssert::string($field('type'), $map['type'] ?? CookieDefinition::TYPE_FIRST_PARTY),
            SpecAssert::int($field('sort_order'), $sort),
            $byDefault === null ? null : SpecAssert::bool($field('allowed_by_default'), $byDefault),
        );

        foreach (SpecAssert::map($field('translations'), $map['translations'] ?? []) as $locale => $texts) {
            $spec = $spec->withTranslation(CookieDefinitionTranslationSpec::fromArray($locale, SpecAssert::map($field('translation "' . $locale . '"'), $texts)));
        }

        return $spec;
    }

    /**
     * Adds the provider and purpose of one locale.
     *
     * @throws InvalidProvisioningSpecException When that locale was given already
     */
    public function withTranslation(CookieDefinitionTranslationSpec $translation): self
    {
        $key = 'l:' . $translation->locale;
        if (isset($this->translations[$key])) {
            throw new InvalidProvisioningSpecException(sprintf('Cookie definition "%s": the translation "%s" is given twice.', $this->name, $translation->locale));
        }

        $copy                     = clone $this;
        $copy->translations[$key] = $translation;

        return $copy;
    }

    /**
     * The translations given, in order.
     *
     * @return list<CookieDefinitionTranslationSpec>
     */
    public function translations(): array
    {
        return array_values($this->translations);
    }
}
