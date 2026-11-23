<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Provisioning;

use Nowo\CookieConsentBundle\Entity\CookieDefinition;

use function array_filter;
use function array_key_exists;
use function array_values;
use function sprintf;

/**
 * One cookie of the default profile's inventory (1.36), for {@see CookieDefinitionSpecs}.
 *
 * Natural key: the cookie name within the default profile, compared byte for byte (`===`, REQ-SEC-006): "NID" and
 * "nid" are two cookies. Only the fields given are written: a null field (or a key missing from {@see fromArray()})
 * keeps the stored value of an existing cookie, or takes the entity default for a new one (category `required`,
 * duration "", `first_party`, sort order 0). As on the admin page, a cookie of the `required` category is always
 * pre-ticked: `allowedByDefault: false` with an effective `required` category is refused. Translations of other
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

    /** The category whose cookies are always pre-ticked (admin page parity). */
    public const REQUIRED_CATEGORY = 'required';

    /** Cookie party types. */
    public const TYPES = [CookieDefinition::TYPE_FIRST_PARTY, CookieDefinition::TYPE_THIRD_PARTY];

    /** Keys of {@see fromArray()}: the `cookie_inventory` shape (snake_case, with the camelCase aliases). */
    public const KEYS = ['name', 'category', 'duration', 'type', 'sort_order', 'sortOrder', 'allowed_by_default', 'allowedByDefault', 'translations'];

    public readonly string $name;

    public readonly ?string $category;

    public readonly ?string $duration;

    public readonly ?string $type;

    public readonly ?int $sortOrder;

    /** @var array<string, CookieDefinitionTranslationSpec> by locale (keys prefixed) */
    private array $translations = [];

    /**
     * Every field but the name is optional: null keeps the stored value (or the entity default for a new cookie).
     *
     * @param string $name The cookie name (natural key; max 120, no surrounding whitespace or control characters)
     * @param string|null $category The consent category key ("required", "analytics", "marketing", ...; max 40)
     * @param string|null $duration Retention label shown to visitors ("Session", "2 years"; max 60)
     * @param string|null $type `first_party` or `third_party`
     * @param int|null $sortOrder Display order within the profile (signed 32-bit)
     * @param bool|null $allowedByDefault Pre-ticked before consent; false is refused for the `required` category
     *
     * @throws InvalidProvisioningSpecException
     */
    public function __construct(
        string $name,
        ?string $category = null,
        ?string $duration = null,
        ?string $type = null,
        ?int $sortOrder = null,
        public readonly ?bool $allowedByDefault = null,
    ) {
        $this->name      = SpecAssert::key('Cookie definition name', $name, self::NAME_MAX_LENGTH);
        $this->category  = $category === null ? null : SpecAssert::key(sprintf('Cookie definition "%s" category', $name), $category, self::CATEGORY_MAX_LENGTH);
        $this->duration  = $duration === null ? null : SpecAssert::length(sprintf('Cookie definition "%s" duration', $name), $duration, self::DURATION_MAX_LENGTH);
        $this->type      = $type === null ? null : SpecAssert::choice(sprintf('Cookie definition "%s" type', $name), $type, self::TYPES);
        $this->sortOrder = $sortOrder === null ? null : SpecAssert::int32(sprintf('Cookie definition "%s" sort order', $name), $sortOrder);
        if ($category === self::REQUIRED_CATEGORY && $this->allowedByDefault === false) {
            throw self::requiredNotPreTicked($name);
        }
    }

    /**
     * Builds the definition from a decoded fixture, in the shape of a `cookie_inventory` entry: `name` (required),
     * `category`, `duration`, `type`, `sort_order` (alias `sortOrder`), `allowed_by_default` (alias
     * `allowedByDefault`) and `translations`, a map of locale to `{"provider": ..., "purpose": ...}`. A missing (or
     * null) key keeps the stored value of an existing cookie. Unknown keys and values of the wrong type are refused (no
     * coercion).
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
        $sort      = $map['sort_order'] ?? $map['sortOrder'] ?? null;
        $byDefault = $map['allowed_by_default'] ?? $map['allowedByDefault'] ?? null;
        $spec      = new self(
            $name,
            SpecAssert::nullableString($field('category'), $map['category'] ?? null),
            SpecAssert::nullableString($field('duration'), $map['duration'] ?? null),
            SpecAssert::nullableString($field('type'), $map['type'] ?? null),
            $sort === null ? null : SpecAssert::int($field('sort_order'), $sort),
            $byDefault === null ? null : SpecAssert::bool($field('allowed_by_default'), $byDefault),
        );

        foreach (SpecAssert::map($field('translations'), $map['translations'] ?? []) as $locale => $texts) {
            $spec = $spec->withTranslation(CookieDefinitionTranslationSpec::fromArray($locale, SpecAssert::map($field('translation "' . $locale . '"'), $texts)));
        }

        return $spec;
    }

    /**
     * `allowedByDefault: false` on a cookie whose effective category is `required` (the admin page always pre-ticks it).
     */
    public static function requiredNotPreTicked(string $name): InvalidProvisioningSpecException
    {
        return new InvalidProvisioningSpecException(sprintf('Cookie definition "%s": a cookie of the "required" category is always pre-ticked; allowedByDefault false is refused.', $name));
    }

    /**
     * The fields provisioning writes (the ones given), by entity field name.
     *
     * @return array<string, bool|int|string>
     */
    public function fields(): array
    {
        return array_filter([
            'category'         => $this->category,
            'duration'         => $this->duration,
            'type'             => $this->type,
            'sortOrder'        => $this->sortOrder,
            'allowedByDefault' => $this->allowedByDefault,
        ], static fn (string|int|bool|null $value): bool => $value !== null);
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
