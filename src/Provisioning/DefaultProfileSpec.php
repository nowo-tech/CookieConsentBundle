<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Provisioning;

use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;

use function array_is_list;
use function array_key_exists;
use function array_keys;
use function array_merge;
use function array_unique;
use function array_values;
use function in_array;
use function is_array;
use function is_string;
use function preg_match;
use function sprintf;

/**
 * What the enabled default consent profile must look like after {@see CookieConsentProvisionerInterface::ensureDefaultProfile()} (1.36).
 *
 * Immutable: every `with*()` returns a copy. Only the settings given are managed: a setting never given keeps its
 * stored value (or the entity default when the profile is created), so a seeder can own a few settings and leave the
 * rest to the admin pages. One `with*()` exists for every display and targeting setting of the profile; the profile's
 * own state is not provisioned (`enabled` and `is_default` are always true for the default profile; the re-consent
 * `revision` / `reconsent_required_at` and the `row_version` belong to the admin pages).
 *
 * Settings are validated when they are given (the same choices and formats as the entity and the admin form, plus
 * the layout combinations the admin form offers), so an invalid specification fails before anything is written.
 * Copy per locale: {@see withTranslation()} ({@see ProfileTranslationSpec}; a locale given twice is refused).
 */
final class DefaultProfileSpec
{
    /**
     * Every managed setting, by name (the entity property), and its kind.
     *
     * @var array<string, '?string'|'bool'|'int'|'list'|'string'>
     */
    public const SETTINGS = [
        'name'                               => '?string',
        'priority'                           => 'int',
        'routePatterns'                      => 'list',
        'autoShow'                           => 'bool',
        'autoShowRouteMode'                  => 'string',
        'autoShowRoutes'                     => 'list',
        'manageScriptTags'                   => 'bool',
        'autoClearCookies'                   => 'bool',
        'hideFromBots'                       => 'bool',
        'disablePageInteraction'             => 'bool',
        'lazyHtmlGeneration'                 => 'bool',
        'consentModalLayout'                 => 'string',
        'consentModalVariant'                => 'string',
        'consentModalPositionY'              => 'string',
        'consentModalPositionX'              => '?string',
        'consentModalEqualWeightButtons'     => 'bool',
        'consentModalFlipButtons'            => 'bool',
        'preferencesModalLayout'             => 'string',
        'preferencesModalVariant'            => 'string',
        'preferencesModalPositionY'          => 'string',
        'preferencesModalPositionX'          => '?string',
        'preferencesModalEqualWeightButtons' => 'bool',
        'preferencesModalFlipButtons'        => 'bool',
        'colorTheme'                         => 'string',
        'darkModeEnabled'                    => 'bool',
        'disableTransitions'                 => 'bool',
        'twoStepModal'                       => 'bool',
        'openPreferencesModal'               => 'bool',
        'manageIframePlaceholders'           => 'bool',
        'granularCookieSelection'            => 'bool',
        'preferencesBubbleEnabled'           => 'bool',
        'preferencesBubblePosition'          => 'string',
        'preferencesBubbleBorderColor'       => '?string',
        'preferencesBubbleIcon'              => '?string',
    ];

    /** Longest stored name (column length). */
    public const NAME_MAX_LENGTH = 100;

    /** Longest route name or pattern of the route lists. */
    public const ROUTE_MAX_LENGTH = 255;

    /** @var array<string, bool|int|list<string>|string|null> */
    private array $settings = [];

    /** @var array<string, ProfileTranslationSpec> by locale (keys prefixed, so a numeric locale-like key stays a string) */
    private array $translations = [];

    /**
     * An empty specification: manages nothing yet (`ensureDefaultProfile()` then only makes sure the profile exists).
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Builds a specification from a decoded fixture (JSON / YAML): the keys are the setting names of {@see SETTINGS}
     * (camelCase, as the `with*()` methods) plus `translations`, a map of locale to the texts of
     * {@see ProfileTranslationSpec::fromArray()}. Unknown keys and values of the wrong type are refused (no coercion).
     *
     * @param array<mixed> $data
     *
     * @throws InvalidProvisioningSpecException
     */
    public static function fromArray(array $data): self
    {
        $map = SpecAssert::map('the default profile specification', $data);
        SpecAssert::knownKeys('the default profile specification', $map, array_merge(array_keys(self::SETTINGS), ['translations']));

        $spec = new self();
        foreach (array_keys(self::SETTINGS) as $setting) {
            if (!array_key_exists($setting, $map)) {
                continue;
            }

            $field = self::field($setting);
            $value = $map[$setting];
            $spec  = match ($setting) {
                'name'                               => $spec->withName(SpecAssert::nullableString($field, $value)),
                'priority'                           => $spec->withPriority(SpecAssert::int($field, $value)),
                'routePatterns'                      => $spec->withRoutePatterns(...self::stringList($field, $value)),
                'autoShow'                           => $spec->withAutoShow(SpecAssert::bool($field, $value)),
                'autoShowRouteMode'                  => $spec->withAutoShowRouteMode(SpecAssert::string($field, $value)),
                'autoShowRoutes'                     => $spec->withAutoShowRoutes(...self::stringList($field, $value)),
                'manageScriptTags'                   => $spec->withManageScriptTags(SpecAssert::bool($field, $value)),
                'autoClearCookies'                   => $spec->withAutoClearCookies(SpecAssert::bool($field, $value)),
                'hideFromBots'                       => $spec->withHideFromBots(SpecAssert::bool($field, $value)),
                'disablePageInteraction'             => $spec->withDisablePageInteraction(SpecAssert::bool($field, $value)),
                'lazyHtmlGeneration'                 => $spec->withLazyHtmlGeneration(SpecAssert::bool($field, $value)),
                'consentModalLayout'                 => $spec->withConsentModalLayout(SpecAssert::string($field, $value)),
                'consentModalVariant'                => $spec->withConsentModalVariant(SpecAssert::string($field, $value)),
                'consentModalPositionY'              => $spec->withConsentModalPositionY(SpecAssert::string($field, $value)),
                'consentModalPositionX'              => $spec->withConsentModalPositionX(SpecAssert::nullableString($field, $value)),
                'consentModalEqualWeightButtons'     => $spec->withConsentModalEqualWeightButtons(SpecAssert::bool($field, $value)),
                'consentModalFlipButtons'            => $spec->withConsentModalFlipButtons(SpecAssert::bool($field, $value)),
                'preferencesModalLayout'             => $spec->withPreferencesModalLayout(SpecAssert::string($field, $value)),
                'preferencesModalVariant'            => $spec->withPreferencesModalVariant(SpecAssert::string($field, $value)),
                'preferencesModalPositionY'          => $spec->withPreferencesModalPositionY(SpecAssert::string($field, $value)),
                'preferencesModalPositionX'          => $spec->withPreferencesModalPositionX(SpecAssert::nullableString($field, $value)),
                'preferencesModalEqualWeightButtons' => $spec->withPreferencesModalEqualWeightButtons(SpecAssert::bool($field, $value)),
                'preferencesModalFlipButtons'        => $spec->withPreferencesModalFlipButtons(SpecAssert::bool($field, $value)),
                'colorTheme'                         => $spec->withColorTheme(SpecAssert::string($field, $value)),
                'darkModeEnabled'                    => $spec->withDarkModeEnabled(SpecAssert::bool($field, $value)),
                'disableTransitions'                 => $spec->withDisableTransitions(SpecAssert::bool($field, $value)),
                'twoStepModal'                       => $spec->withTwoStepModal(SpecAssert::bool($field, $value)),
                'openPreferencesModal'               => $spec->withOpenPreferencesModal(SpecAssert::bool($field, $value)),
                'manageIframePlaceholders'           => $spec->withManageIframePlaceholders(SpecAssert::bool($field, $value)),
                'granularCookieSelection'            => $spec->withGranularCookieSelection(SpecAssert::bool($field, $value)),
                'preferencesBubbleEnabled'           => $spec->withPreferencesBubbleEnabled(SpecAssert::bool($field, $value)),
                'preferencesBubblePosition'          => $spec->withPreferencesBubblePosition(SpecAssert::string($field, $value)),
                'preferencesBubbleBorderColor'       => $spec->withPreferencesBubbleBorderColor(SpecAssert::nullableString($field, $value)),
                'preferencesBubbleIcon'              => $spec->withPreferencesBubbleIcon(SpecAssert::nullableString($field, $value)),
            };
        }

        foreach (SpecAssert::map('the default profile translations', $map['translations'] ?? []) as $locale => $texts) {
            $spec = $spec->withTranslation(ProfileTranslationSpec::fromArray($locale, SpecAssert::map(sprintf('the profile translation "%s"', $locale), $texts)));
        }

        return $spec;
    }

    /**
     * The managed settings, by name (a subset of {@see SETTINGS}), with their validated values.
     *
     * @return array<string, bool|int|list<string>|string|null>
     */
    public function settings(): array
    {
        return $this->settings;
    }

    /**
     * The managed copies, in the order given.
     *
     * @return list<ProfileTranslationSpec>
     */
    public function translations(): array
    {
        return array_values($this->translations);
    }

    /**
     * Adds the copy of one locale.
     *
     * @throws InvalidProvisioningSpecException When that locale was given already
     */
    public function withTranslation(ProfileTranslationSpec $translation): self
    {
        $key = 'l:' . $translation->locale;
        if (isset($this->translations[$key])) {
            throw new InvalidProvisioningSpecException(sprintf('The profile translation "%s" is given twice.', $translation->locale));
        }

        $copy                     = clone $this;
        $copy->translations[$key] = $translation;

        return $copy;
    }

    /** Internal name of the profile (admin list; max 100 characters, trimmed; blank: none). */
    public function withName(?string $name): self
    {
        return $this->with('name', $name === null ? null : SpecAssert::length(self::field('name'), $name, self::NAME_MAX_LENGTH));
    }

    /** Priority among the enabled profiles (highest first). */
    public function withPriority(int $priority): self
    {
        return $this->with('priority', $priority);
    }

    /** Route patterns the profile applies to (each trimmed; blanks and duplicates dropped). */
    public function withRoutePatterns(string ...$patterns): self
    {
        return $this->with('routePatterns', $this->routes('routePatterns', $patterns));
    }

    /** Whether the consent modal opens by itself. */
    public function withAutoShow(bool $autoShow): self
    {
        return $this->with('autoShow', $autoShow);
    }

    /** Where the modal opens by itself: `all`, `only` or `except` the routes of {@see withAutoShowRoutes()}. */
    public function withAutoShowRouteMode(string $mode): self
    {
        return $this->with('autoShowRouteMode', SpecAssert::choice(self::field('autoShowRouteMode'), $mode, CookieConsentConfig::AUTO_SHOW_ROUTE_MODES));
    }

    /** Route names of the auto-show targeting (each trimmed; blanks and duplicates dropped). */
    public function withAutoShowRoutes(string ...$routes): self
    {
        return $this->with('autoShowRoutes', $this->routes('autoShowRoutes', $routes));
    }

    /** Whether the script manages `<script type="text/plain" data-category>` tags. */
    public function withManageScriptTags(bool $manageScriptTags): self
    {
        return $this->with('manageScriptTags', $manageScriptTags);
    }

    /** Whether cookies of a rejected category are cleared. */
    public function withAutoClearCookies(bool $autoClearCookies): self
    {
        return $this->with('autoClearCookies', $autoClearCookies);
    }

    /** Whether the modal is hidden from bots. */
    public function withHideFromBots(bool $hideFromBots): self
    {
        return $this->with('hideFromBots', $hideFromBots);
    }

    /** Whether the page is blocked until the visitor answers. */
    public function withDisablePageInteraction(bool $disablePageInteraction): self
    {
        return $this->with('disablePageInteraction', $disablePageInteraction);
    }

    /** Whether the modal markup is generated lazily. */
    public function withLazyHtmlGeneration(bool $lazyHtmlGeneration): self
    {
        return $this->with('lazyHtmlGeneration', $lazyHtmlGeneration);
    }

    /** Consent modal layout: `box`, `cloud` or `bar`. */
    public function withConsentModalLayout(string $layout): self
    {
        return $this->with('consentModalLayout', SpecAssert::choice(self::field('consentModalLayout'), $layout, CookieConsentConfig::CONSENT_MODAL_LAYOUT_TYPES));
    }

    /** Consent modal variant: `wide` (box only) or `inline`. */
    public function withConsentModalVariant(string $variant): self
    {
        return $this->with('consentModalVariant', SpecAssert::choice(self::field('consentModalVariant'), $variant, $this->union(CookieConsentConfig::CONSENT_MODAL_VARIANT_TYPES)));
    }

    /** Consent modal vertical position: `top`, `middle` or `bottom` (bar: bottom only). */
    public function withConsentModalPositionY(string $positionY): self
    {
        return $this->with('consentModalPositionY', SpecAssert::choice(self::field('consentModalPositionY'), $positionY, $this->union(CookieConsentConfig::CONSENT_MODAL_POSITION_Y_TYPES)));
    }

    /** Consent modal horizontal position: `left`, `center`, `right`, or null (bar: null only). */
    public function withConsentModalPositionX(?string $positionX): self
    {
        return $this->with('consentModalPositionX', $positionX === null ? null : SpecAssert::choice(self::field('consentModalPositionX'), $positionX, $this->union(CookieConsentConfig::CONSENT_MODAL_POSITION_X_TYPES)));
    }

    /** Whether the consent modal buttons have equal weight. */
    public function withConsentModalEqualWeightButtons(bool $equalWeight): self
    {
        return $this->with('consentModalEqualWeightButtons', $equalWeight);
    }

    /** Whether the consent modal buttons are flipped. */
    public function withConsentModalFlipButtons(bool $flip): self
    {
        return $this->with('consentModalFlipButtons', $flip);
    }

    /** Preferences modal layout: `box`, `cloud` or `bar`. */
    public function withPreferencesModalLayout(string $layout): self
    {
        return $this->with('preferencesModalLayout', SpecAssert::choice(self::field('preferencesModalLayout'), $layout, CookieConsentConfig::CONSENT_MODAL_LAYOUT_TYPES));
    }

    /** Preferences modal variant: `wide` (box only) or `inline`. */
    public function withPreferencesModalVariant(string $variant): self
    {
        return $this->with('preferencesModalVariant', SpecAssert::choice(self::field('preferencesModalVariant'), $variant, $this->union(CookieConsentConfig::CONSENT_MODAL_VARIANT_TYPES)));
    }

    /** Preferences modal vertical position: `top`, `middle` or `bottom` (bar: bottom only). */
    public function withPreferencesModalPositionY(string $positionY): self
    {
        return $this->with('preferencesModalPositionY', SpecAssert::choice(self::field('preferencesModalPositionY'), $positionY, $this->union(CookieConsentConfig::CONSENT_MODAL_POSITION_Y_TYPES)));
    }

    /** Preferences modal horizontal position: `left`, `center`, `right`, or null (bar: null only). */
    public function withPreferencesModalPositionX(?string $positionX): self
    {
        return $this->with('preferencesModalPositionX', $positionX === null ? null : SpecAssert::choice(self::field('preferencesModalPositionX'), $positionX, $this->union(CookieConsentConfig::CONSENT_MODAL_POSITION_X_TYPES)));
    }

    /** Whether the preferences modal buttons have equal weight. */
    public function withPreferencesModalEqualWeightButtons(bool $equalWeight): self
    {
        return $this->with('preferencesModalEqualWeightButtons', $equalWeight);
    }

    /** Whether the preferences modal buttons are flipped. */
    public function withPreferencesModalFlipButtons(bool $flip): self
    {
        return $this->with('preferencesModalFlipButtons', $flip);
    }

    /** Color theme: one of {@see CookieConsentConfig::COLOR_THEMES}. */
    public function withColorTheme(string $colorTheme): self
    {
        return $this->with('colorTheme', SpecAssert::choice(self::field('colorTheme'), $colorTheme, CookieConsentConfig::COLOR_THEMES));
    }

    /** Whether the dark mode follows the visitor's preference. */
    public function withDarkModeEnabled(bool $darkModeEnabled): self
    {
        return $this->with('darkModeEnabled', $darkModeEnabled);
    }

    /** Whether the modal transitions are disabled. */
    public function withDisableTransitions(bool $disableTransitions): self
    {
        return $this->with('disableTransitions', $disableTransitions);
    }

    /** Whether the consent is asked in two steps. */
    public function withTwoStepModal(bool $twoStepModal): self
    {
        return $this->with('twoStepModal', $twoStepModal);
    }

    /** Whether the preferences modal opens first. */
    public function withOpenPreferencesModal(bool $openPreferencesModal): self
    {
        return $this->with('openPreferencesModal', $openPreferencesModal);
    }

    /** Whether iframes get a placeholder until their category is accepted. */
    public function withManageIframePlaceholders(bool $manageIframePlaceholders): self
    {
        return $this->with('manageIframePlaceholders', $manageIframePlaceholders);
    }

    /** Whether cookies can be chosen one by one. */
    public function withGranularCookieSelection(bool $granularCookieSelection): self
    {
        return $this->with('granularCookieSelection', $granularCookieSelection);
    }

    /** Whether the floating preferences bubble is shown. */
    public function withPreferencesBubbleEnabled(bool $enabled): self
    {
        return $this->with('preferencesBubbleEnabled', $enabled);
    }

    /** Preferences bubble position: one of {@see CookieConsentConfig::PREFERENCES_BUBBLE_POSITIONS}. */
    public function withPreferencesBubblePosition(string $position): self
    {
        return $this->with('preferencesBubblePosition', SpecAssert::choice(self::field('preferencesBubblePosition'), $position, CookieConsentConfig::PREFERENCES_BUBBLE_POSITIONS));
    }

    /** Preferences bubble border color: `#rgb` / `#rrggbb`, or null (or "") for the default. */
    public function withPreferencesBubbleBorderColor(?string $color): self
    {
        if ($color !== null && $color !== '' && preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/D', $color) !== 1) {
            throw new InvalidProvisioningSpecException(sprintf('%s must be a hex color such as "#30363c", got "%s".', self::field('preferencesBubbleBorderColor'), $color));
        }

        return $this->with('preferencesBubbleBorderColor', $color);
    }

    /** Preferences bubble icon markup (sanitized by the bundle when stored), or null for the default icon. */
    public function withPreferencesBubbleIcon(?string $icon): self
    {
        return $this->with('preferencesBubbleIcon', $icon === null ? null : SpecAssert::length(self::field('preferencesBubbleIcon'), $icon, null));
    }

    /**
     * @param bool|int|list<string>|string|null $value
     */
    private function with(string $setting, bool|int|string|array|null $value): self
    {
        $copy                     = clone $this;
        $copy->settings[$setting] = $value;
        $copy->assertLayoutCombination('consentModal');
        $copy->assertLayoutCombination('preferencesModal');

        return $copy;
    }

    /**
     * The variant and positions given must exist for the layout given (the choices of the admin form).
     */
    private function assertLayoutCombination(string $modal): void
    {
        $layout = $this->settings[$modal . 'Layout'] ?? null;
        if (!is_string($layout)) {
            return;
        }

        foreach ([
            'Variant'   => CookieConsentConfig::CONSENT_MODAL_VARIANT_TYPES,
            'PositionY' => CookieConsentConfig::CONSENT_MODAL_POSITION_Y_TYPES,
            'PositionX' => CookieConsentConfig::CONSENT_MODAL_POSITION_X_TYPES,
        ] as $suffix => $choices) {
            $setting = $modal . $suffix;
            $value   = $this->settings[$setting] ?? null;
            if (is_string($value) && !in_array($value, $choices[$layout], true)) {
                throw new InvalidProvisioningSpecException(sprintf('Default profile setting "%s" "%s" is not available with the "%s" layout.', $setting, $value, $layout));
            }
        }
    }

    private static function field(string $setting): string
    {
        return sprintf('Default profile setting "%s"', $setting);
    }

    /**
     * @param array<int|string, string> $routes
     *
     * @return list<string>
     */
    private function routes(string $setting, array $routes): array
    {
        foreach ($routes as $route) {
            SpecAssert::length(self::field($setting), $route, self::ROUTE_MAX_LENGTH);
        }

        return array_values($routes);
    }

    /**
     * @param array<string, list<string>> $byLayout
     *
     * @return list<string>
     */
    private function union(array $byLayout): array
    {
        return array_values(array_unique(array_merge(...array_values($byLayout))));
    }

    /**
     * @return list<string>
     */
    private static function stringList(string $field, mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new InvalidProvisioningSpecException(sprintf('%s must be a list of strings.', $field));
        }

        $list = [];
        foreach ($value as $item) {
            $list[] = SpecAssert::string($field, $item);
        }

        return $list;
    }
}
