<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;

use function in_array;
use function sprintf;

#[ORM\Entity(repositoryClass: CookieConsentConfigRepository::class)]
#[ORM\Table(name: 'nowo_cookie_consent_config')]
/**
 * Doctrine entity storing cookie consent modal display and targeting settings.
 */
class CookieConsentConfig
{
    public const CONSENT_MODAL_LAYOUT_TYPES = ['box', 'cloud', 'bar'];

    public const COLOR_THEMES = ['light', 'dark', 'dark-turquoise', 'light-funky', 'elegant-black'];

    public const AUTO_SHOW_ROUTE_MODE_ALL = 'all';

    public const AUTO_SHOW_ROUTE_MODE_ONLY = 'only';

    public const AUTO_SHOW_ROUTE_MODE_EXCEPT = 'except';

    public const AUTO_SHOW_ROUTE_MODES = [
        self::AUTO_SHOW_ROUTE_MODE_ALL,
        self::AUTO_SHOW_ROUTE_MODE_ONLY,
        self::AUTO_SHOW_ROUTE_MODE_EXCEPT,
    ];

    public const PREFERENCES_BUBBLE_POSITION_BOTTOM_RIGHT = 'bottom-right';

    public const PREFERENCES_BUBBLE_POSITION_BOTTOM_LEFT = 'bottom-left';

    public const PREFERENCES_BUBBLE_POSITION_TOP_RIGHT = 'top-right';

    public const PREFERENCES_BUBBLE_POSITION_TOP_LEFT = 'top-left';

    public const PREFERENCES_BUBBLE_POSITIONS = [
        self::PREFERENCES_BUBBLE_POSITION_BOTTOM_RIGHT,
        self::PREFERENCES_BUBBLE_POSITION_BOTTOM_LEFT,
        self::PREFERENCES_BUBBLE_POSITION_TOP_RIGHT,
        self::PREFERENCES_BUBBLE_POSITION_TOP_LEFT,
    ];

    /** @var array<string, list<string>> */
    public const CONSENT_MODAL_VARIANT_TYPES = [
        self::CONSENT_MODAL_LAYOUT_TYPES[0] => ['wide', 'inline'],
        self::CONSENT_MODAL_LAYOUT_TYPES[1] => ['inline'],
        self::CONSENT_MODAL_LAYOUT_TYPES[2] => ['inline'],
    ];

    /** @var array<string, list<string>> */
    public const CONSENT_MODAL_POSITION_Y_TYPES = [
        self::CONSENT_MODAL_LAYOUT_TYPES[0] => ['top', 'middle', 'bottom'],
        self::CONSENT_MODAL_LAYOUT_TYPES[1] => ['top', 'middle', 'bottom'],
        self::CONSENT_MODAL_LAYOUT_TYPES[2] => ['bottom'],
    ];

    /** @var array<string, list<string>> */
    public const CONSENT_MODAL_POSITION_X_TYPES = [
        self::CONSENT_MODAL_LAYOUT_TYPES[0] => ['left', 'center', 'right'],
        self::CONSENT_MODAL_LAYOUT_TYPES[1] => ['left', 'center', 'right'],
        self::CONSENT_MODAL_LAYOUT_TYPES[2] => [],
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    /** @phpstan-ignore property.unusedType */
    private ?int $id = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $enabled = true;

    #[ORM\Column(name: 'is_default', options: ['default' => false])]
    private bool $default = false;

    #[ORM\Column(name: 'auto_show', options: ['default' => true])]
    private bool $autoShow = true;

    #[ORM\Column(options: ['default' => 0])]
    private int $revision = 0;

    #[ORM\Column(name: 'manage_script_tags', options: ['default' => false])]
    private bool $manageScriptTags = false;

    #[ORM\Column(name: 'auto_clear_cookies', options: ['default' => false])]
    private bool $autoClearCookies = false;

    #[ORM\Column(name: 'hide_from_bots', options: ['default' => true])]
    private bool $hideFromBots = true;

    #[ORM\Column(name: 'disable_page_interaction', options: ['default' => false])]
    private bool $disablePageInteraction = false;

    #[ORM\Column(name: 'lazy_html_generation', options: ['default' => false])]
    private bool $lazyHtmlGeneration = false;

    #[ORM\Column(name: 'consent_modal_layout', length: 20, options: ['default' => 'box'])]
    private string $consentModalLayout = self::CONSENT_MODAL_LAYOUT_TYPES[0];

    #[ORM\Column(name: 'consent_modal_variant', length: 20, options: ['default' => 'wide'])]
    private string $consentModalVariant = 'wide';

    #[ORM\Column(name: 'consent_modal_position_y', length: 20, options: ['default' => 'bottom'])]
    private string $consentModalPositionY = 'bottom';

    #[ORM\Column(name: 'consent_modal_position_x', length: 20, nullable: true, options: ['default' => 'center'])]
    private ?string $consentModalPositionX = 'center';

    #[ORM\Column(name: 'consent_modal_equal_weight_buttons', options: ['default' => false])]
    private bool $consentModalEqualWeightButtons = false;

    #[ORM\Column(name: 'consent_modal_flip_buttons', options: ['default' => false])]
    private bool $consentModalFlipButtons = false;

    #[ORM\Column(name: 'auto_show_route_mode', length: 20, options: ['default' => 'all'])]
    private string $autoShowRouteMode = self::AUTO_SHOW_ROUTE_MODE_ALL;

    /** @var list<string> */
    #[ORM\Column(name: 'auto_show_routes', type: Types::JSON)]
    private array $autoShowRoutes = [];

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $name = null;

    /** @var list<string> */
    #[ORM\Column(name: 'route_patterns', type: Types::JSON)]
    private array $routePatterns = [];

    #[ORM\Column(options: ['default' => 0])]
    private int $priority = 0;

    #[ORM\Column(name: 'preferences_modal_layout', length: 20, options: ['default' => 'box'])]
    private string $preferencesModalLayout = self::CONSENT_MODAL_LAYOUT_TYPES[0];

    #[ORM\Column(name: 'preferences_modal_variant', length: 20, options: ['default' => 'wide'])]
    private string $preferencesModalVariant = 'wide';

    #[ORM\Column(name: 'preferences_modal_position_y', length: 20, options: ['default' => 'middle'])]
    private string $preferencesModalPositionY = 'middle';

    #[ORM\Column(name: 'preferences_modal_position_x', length: 20, nullable: true, options: ['default' => 'center'])]
    private ?string $preferencesModalPositionX = 'center';

    #[ORM\Column(name: 'preferences_modal_equal_weight_buttons', options: ['default' => false])]
    private bool $preferencesModalEqualWeightButtons = false;

    #[ORM\Column(name: 'preferences_modal_flip_buttons', options: ['default' => false])]
    private bool $preferencesModalFlipButtons = false;

    #[ORM\Column(name: 'color_theme', length: 30, options: ['default' => 'light'])]
    private string $colorTheme = self::COLOR_THEMES[0];

    #[ORM\Column(name: 'dark_mode_enabled', options: ['default' => false])]
    private bool $darkModeEnabled = false;

    #[ORM\Column(name: 'disable_transitions', options: ['default' => false])]
    private bool $disableTransitions = false;

    #[ORM\Column(name: 'two_step_modal', options: ['default' => false])]
    private bool $twoStepModal = false;

    #[ORM\Column(name: 'open_preferences_modal', options: ['default' => false])]
    private bool $openPreferencesModal = false;

    #[ORM\Column(name: 'manage_iframe_placeholders', options: ['default' => false])]
    private bool $manageIframePlaceholders = false;

    #[ORM\Column(name: 'granular_cookie_selection', options: ['default' => false])]
    private bool $granularCookieSelection = false;

    #[ORM\Column(name: 'preferences_bubble_enabled', options: ['default' => false])]
    private bool $preferencesBubbleEnabled = false;

    #[ORM\Column(name: 'preferences_bubble_position', length: 20, options: ['default' => 'bottom-right'])]
    private string $preferencesBubblePosition = self::PREFERENCES_BUBBLE_POSITION_BOTTOM_RIGHT;

    /** @var Collection<int, CookieConsentConfigTranslation> */
    #[ORM\OneToMany(targetEntity: CookieConsentConfigTranslation::class, mappedBy: 'config', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $translations;

    /** @var Collection<int, CookieDefinition> */
    #[ORM\OneToMany(targetEntity: CookieDefinition::class, mappedBy: 'config', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $cookieDefinitions;

    /**
     * Initializes the translation collection.
     */
    public function __construct()
    {
        $this->translations = new ArrayCollection();
        $this->cookieDefinitions = new ArrayCollection();
    }

    /**
     * Returns the consent configuration primary key.
     *
     * @return int|null The entity identifier
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Returns whether the configuration is enabled.
     *
     * @return bool True when the configuration is active
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Sets whether the configuration is enabled.
     *
     * @param bool $enabled True to enable the configuration
     *
     * @return self Fluent interface
     */
    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }

    /**
     * Returns whether this is the default configuration.
     *
     * @return bool True when marked as the default configuration
     */
    public function isDefault(): bool
    {
        return $this->default;
    }

    /**
     * Sets whether this is the default configuration.
     *
     * @param bool $default True to mark as the default configuration
     *
     * @return self Fluent interface
     */
    public function setDefault(bool $default): self
    {
        $this->default = $default;

        return $this;
    }

    /**
     * Returns whether the consent modal should auto-show.
     *
     * @return bool True when auto-show is enabled
     */
    public function isAutoShow(): bool
    {
        return $this->autoShow;
    }

    /**
     * Sets whether the consent modal should auto-show.
     *
     * @param bool $autoShow True to enable auto-show
     *
     * @return self Fluent interface
     */
    public function setAutoShow(bool $autoShow): self
    {
        $this->autoShow = $autoShow;

        return $this;
    }

    /**
     * Returns the configuration revision number.
     *
     * @return int The revision counter
     */
    public function getRevision(): int
    {
        return $this->revision;
    }

    /**
     * Sets the configuration revision number.
     *
     * @param int $revision The revision counter
     *
     * @return self Fluent interface
     */
    public function setRevision(int $revision): self
    {
        $this->revision = $revision;

        return $this;
    }

    /**
     * Returns whether script tag management is enabled.
     *
     * @return bool True when script tags are managed by the bundle
     */
    public function isManageScriptTags(): bool
    {
        return $this->manageScriptTags;
    }

    /**
     * Sets whether script tag management is enabled.
     *
     * @param bool $manageScriptTags True to manage script tags
     *
     * @return self Fluent interface
     */
    public function setManageScriptTags(bool $manageScriptTags): self
    {
        $this->manageScriptTags = $manageScriptTags;

        return $this;
    }

    /**
     * Returns whether cookies are auto-cleared when consent is withdrawn.
     *
     * @return bool True when auto-clear is enabled
     */
    public function isAutoClearCookies(): bool
    {
        return $this->autoClearCookies;
    }

    /**
     * Sets whether cookies are auto-cleared when consent is withdrawn.
     *
     * @param bool $autoClearCookies True to enable auto-clear
     *
     * @return self Fluent interface
     */
    public function setAutoClearCookies(bool $autoClearCookies): self
    {
        $this->autoClearCookies = $autoClearCookies;

        return $this;
    }

    /**
     * Returns whether the modal is hidden from bots.
     *
     * @return bool True when bot traffic should not see the modal
     */
    public function isHideFromBots(): bool
    {
        return $this->hideFromBots;
    }

    /**
     * Sets whether the modal is hidden from bots.
     *
     * @param bool $hideFromBots True to hide the modal from bots
     *
     * @return self Fluent interface
     */
    public function setHideFromBots(bool $hideFromBots): self
    {
        $this->hideFromBots = $hideFromBots;

        return $this;
    }

    /**
     * Returns whether page interaction is disabled while the modal is open.
     *
     * @return bool True when page interaction is blocked
     */
    public function isDisablePageInteraction(): bool
    {
        return $this->disablePageInteraction;
    }

    /**
     * Sets whether page interaction is disabled while the modal is open.
     *
     * @param bool $disablePageInteraction True to block page interaction
     *
     * @return self Fluent interface
     */
    public function setDisablePageInteraction(bool $disablePageInteraction): self
    {
        $this->disablePageInteraction = $disablePageInteraction;

        return $this;
    }

    /**
     * Returns whether HTML generation is deferred.
     *
     * @return bool True when lazy HTML generation is enabled
     */
    public function isLazyHtmlGeneration(): bool
    {
        return $this->lazyHtmlGeneration;
    }

    /**
     * Sets whether HTML generation is deferred.
     *
     * @param bool $lazyHtmlGeneration True to enable lazy HTML generation
     *
     * @return self Fluent interface
     */
    public function setLazyHtmlGeneration(bool $lazyHtmlGeneration): self
    {
        $this->lazyHtmlGeneration = $lazyHtmlGeneration;

        return $this;
    }

    /**
     * Returns the consent modal layout.
     *
     * @return string The layout identifier
     */
    public function getConsentModalLayout(): string
    {
        return $this->consentModalLayout;
    }

    /**
     * Sets the consent modal layout.
     *
     * @param string $consentModalLayout The layout identifier
     *
     * @return self Fluent interface
     */
    public function setConsentModalLayout(string $consentModalLayout): self
    {
        if (!in_array($consentModalLayout, self::CONSENT_MODAL_LAYOUT_TYPES, true)) {
            throw new InvalidArgumentException(sprintf('Invalid consent modal layout "%s".', $consentModalLayout));
        }

        $this->consentModalLayout = $consentModalLayout;

        return $this;
    }

    /**
     * Returns the consent modal variant.
     *
     * @return string The variant identifier
     */
    public function getConsentModalVariant(): string
    {
        return $this->consentModalVariant;
    }

    /**
     * Sets the consent modal variant.
     *
     * @param string $consentModalVariant The variant identifier
     *
     * @return self Fluent interface
     */
    public function setConsentModalVariant(string $consentModalVariant): self
    {
        $this->consentModalVariant = $consentModalVariant;

        return $this;
    }

    /**
     * Returns the consent modal vertical position.
     *
     * @return string The vertical position identifier
     */
    public function getConsentModalPositionY(): string
    {
        return $this->consentModalPositionY;
    }

    /**
     * Sets the consent modal vertical position.
     *
     * @param string $consentModalPositionY The vertical position identifier
     *
     * @return self Fluent interface
     */
    public function setConsentModalPositionY(string $consentModalPositionY): self
    {
        $this->consentModalPositionY = $consentModalPositionY;

        return $this;
    }

    /**
     * Returns the consent modal horizontal position.
     *
     * @return string|null The horizontal position identifier
     */
    public function getConsentModalPositionX(): ?string
    {
        return $this->consentModalPositionX;
    }

    /**
     * Returns the combined consent modal position string.
     *
     * @return string The vertical and horizontal position combined
     */
    public function getConsentModalPosition(): string
    {
        return trim(sprintf('%s %s', $this->consentModalPositionY, $this->consentModalPositionX ?? ''));
    }

    /**
     * Sets the consent modal horizontal position.
     *
     * @param string|null $consentModalPositionX The horizontal position identifier
     *
     * @return self Fluent interface
     */
    public function setConsentModalPositionX(?string $consentModalPositionX): self
    {
        $this->consentModalPositionX = $consentModalPositionX;

        return $this;
    }

    /**
     * Returns whether consent modal buttons have equal weight.
     *
     * @return bool True when buttons use equal visual weight
     */
    public function isConsentModalEqualWeightButtons(): bool
    {
        return $this->consentModalEqualWeightButtons;
    }

    /**
     * Sets whether consent modal buttons have equal weight.
     *
     * @param bool $consentModalEqualWeightButtons True for equal button weight
     *
     * @return self Fluent interface
     */
    public function setConsentModalEqualWeightButtons(bool $consentModalEqualWeightButtons): self
    {
        $this->consentModalEqualWeightButtons = $consentModalEqualWeightButtons;

        return $this;
    }

    /**
     * Returns whether consent modal buttons are flipped.
     *
     * @return bool True when primary and secondary buttons are flipped
     */
    public function isConsentModalFlipButtons(): bool
    {
        return $this->consentModalFlipButtons;
    }

    /**
     * Sets whether consent modal buttons are flipped.
     *
     * @param bool $consentModalFlipButtons True to flip button order
     *
     * @return self Fluent interface
     */
    public function setConsentModalFlipButtons(bool $consentModalFlipButtons): self
    {
        $this->consentModalFlipButtons = $consentModalFlipButtons;

        return $this;
    }

    /**
     * Returns the auto-show route targeting mode.
     *
     * @return string The route targeting mode
     */
    public function getAutoShowRouteMode(): string
    {
        return $this->autoShowRouteMode;
    }

    /**
     * Sets the auto-show route targeting mode.
     *
     * @param string $autoShowRouteMode The route targeting mode
     *
     * @return self Fluent interface
     */
    public function setAutoShowRouteMode(string $autoShowRouteMode): self
    {
        if (!in_array($autoShowRouteMode, self::AUTO_SHOW_ROUTE_MODES, true)) {
            throw new InvalidArgumentException(sprintf('Invalid auto show route mode "%s".', $autoShowRouteMode));
        }

        $this->autoShowRouteMode = $autoShowRouteMode;

        return $this;
    }

    /**
     * Returns the auto-show route names.
     *
     * @return list<string>
     */
    public function getAutoShowRoutes(): array
    {
        return $this->autoShowRoutes;
    }

    /**
     * Sets the auto-show route names.
     *
     * @param list<string> $autoShowRoutes
     *
     * @return self Fluent interface
     */
    public function setAutoShowRoutes(array $autoShowRoutes): self
    {
        $this->autoShowRoutes = array_values(array_unique(array_values(array_filter(array_map(trim(...), $autoShowRoutes)))));

        return $this;
    }

    /**
     * Returns the auto-show routes as newline-separated text.
     *
     * @return string The route names joined by newlines
     */
    public function getAutoShowRoutesText(): string
    {
        return implode("\n", $this->autoShowRoutes);
    }

    /**
     * Sets auto-show routes from newline-separated text.
     *
     * @param string|null $autoShowRoutesText The route names as text input
     *
     * @return self Fluent interface
     */
    public function setAutoShowRoutesText(?string $autoShowRoutesText): self
    {
        return $this->setAutoShowRoutes(self::parseRouteList($autoShowRoutesText ?? ''));
    }

    /**
     * Returns the configuration name.
     *
     * @return string|null The optional configuration name
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Sets the configuration name.
     *
     * @param string|null $name The optional configuration name
     *
     * @return self Fluent interface
     */
    public function setName(?string $name): self
    {
        $this->name = $name !== null && trim($name) !== '' ? trim($name) : null;

        return $this;
    }

    /**
     * Returns the route patterns used to match this configuration.
     *
     * @return list<string>
     */
    public function getRoutePatterns(): array
    {
        return $this->routePatterns;
    }

    /**
     * Sets the route patterns used to match this configuration.
     *
     * @param list<string> $routePatterns
     *
     * @return self Fluent interface
     */
    public function setRoutePatterns(array $routePatterns): self
    {
        $this->routePatterns = array_values(array_unique(array_values(array_filter(array_map(trim(...), $routePatterns)))));

        return $this;
    }

    /**
     * Returns route patterns as newline-separated text.
     *
     * @return string The route patterns joined by newlines
     */
    public function getRoutePatternsText(): string
    {
        return implode("\n", $this->routePatterns);
    }

    /**
     * Sets route patterns from newline-separated text.
     *
     * @param string|null $routePatternsText The route patterns as text input
     *
     * @return self Fluent interface
     */
    public function setRoutePatternsText(?string $routePatternsText): self
    {
        return $this->setRoutePatterns(self::parseRouteList($routePatternsText ?? ''));
    }

    /**
     * Returns the configuration priority.
     *
     * @return int The priority value
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * Sets the configuration priority.
     *
     * @param int $priority The priority value
     *
     * @return self Fluent interface
     */
    public function setPriority(int $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    /**
     * Returns the human-readable configuration name.
     *
     * @return string The display name for admin and debugging
     */
    public function getDisplayName(): string
    {
        if ($this->name !== null && $this->name !== '') {
            return $this->name;
        }

        if ($this->default) {
            return 'default';
        }

        return sprintf('config-%d', $this->id ?? 0);
    }

    /**
     * Parses a route list from comma- or newline-separated text.
     *
     * @param string $text The raw route list input
     *
     * @return list<string> The normalized route names
     */
    public static function parseRouteList(string $text): array
    {
        $parts  = preg_split('/[\r\n,]+/', $text) ?: [];
        $routes = [];

        foreach ($parts as $part) {
            $part = trim($part);

            if ($part !== '') {
                $routes[] = $part;
            }
        }

        return array_values(array_unique($routes));
    }

    /**
     * Returns the preferences modal layout.
     *
     * @return string The layout identifier
     */
    public function getPreferencesModalLayout(): string
    {
        return $this->preferencesModalLayout;
    }

    /**
     * Sets the preferences modal layout.
     *
     * @param string $preferencesModalLayout The layout identifier
     *
     * @return self Fluent interface
     */
    public function setPreferencesModalLayout(string $preferencesModalLayout): self
    {
        if (!in_array($preferencesModalLayout, self::CONSENT_MODAL_LAYOUT_TYPES, true)) {
            throw new InvalidArgumentException(sprintf('Invalid preferences modal layout "%s".', $preferencesModalLayout));
        }

        $this->preferencesModalLayout = $preferencesModalLayout;

        return $this;
    }

    /**
     * Returns the preferences modal variant.
     *
     * @return string The variant identifier
     */
    public function getPreferencesModalVariant(): string
    {
        return $this->preferencesModalVariant;
    }

    /**
     * Sets the preferences modal variant.
     *
     * @param string $preferencesModalVariant The variant identifier
     *
     * @return self Fluent interface
     */
    public function setPreferencesModalVariant(string $preferencesModalVariant): self
    {
        $this->preferencesModalVariant = $preferencesModalVariant;

        return $this;
    }

    /**
     * Returns the preferences modal vertical position.
     *
     * @return string The vertical position identifier
     */
    public function getPreferencesModalPositionY(): string
    {
        return $this->preferencesModalPositionY;
    }

    /**
     * Sets the preferences modal vertical position.
     *
     * @param string $preferencesModalPositionY The vertical position identifier
     *
     * @return self Fluent interface
     */
    public function setPreferencesModalPositionY(string $preferencesModalPositionY): self
    {
        $this->preferencesModalPositionY = $preferencesModalPositionY;

        return $this;
    }

    /**
     * Returns the preferences modal horizontal position.
     *
     * @return string|null The horizontal position identifier
     */
    public function getPreferencesModalPositionX(): ?string
    {
        return $this->preferencesModalPositionX;
    }

    /**
     * Returns the combined preferences modal position string.
     *
     * @return string The vertical and horizontal position combined
     */
    public function getPreferencesModalPosition(): string
    {
        return trim(sprintf('%s %s', $this->preferencesModalPositionY, $this->preferencesModalPositionX ?? ''));
    }

    /**
     * Sets the preferences modal horizontal position.
     *
     * @param string|null $preferencesModalPositionX The horizontal position identifier
     *
     * @return self Fluent interface
     */
    public function setPreferencesModalPositionX(?string $preferencesModalPositionX): self
    {
        $this->preferencesModalPositionX = $preferencesModalPositionX;

        return $this;
    }

    /**
     * Returns whether preferences modal buttons have equal weight.
     *
     * @return bool True when buttons use equal visual weight
     */
    public function isPreferencesModalEqualWeightButtons(): bool
    {
        return $this->preferencesModalEqualWeightButtons;
    }

    /**
     * Sets whether preferences modal buttons have equal weight.
     *
     * @param bool $preferencesModalEqualWeightButtons True for equal button weight
     *
     * @return self Fluent interface
     */
    public function setPreferencesModalEqualWeightButtons(bool $preferencesModalEqualWeightButtons): self
    {
        $this->preferencesModalEqualWeightButtons = $preferencesModalEqualWeightButtons;

        return $this;
    }

    /**
     * Returns whether preferences modal buttons are flipped.
     *
     * @return bool True when primary and secondary buttons are flipped
     */
    public function isPreferencesModalFlipButtons(): bool
    {
        return $this->preferencesModalFlipButtons;
    }

    /**
     * Sets whether preferences modal buttons are flipped.
     *
     * @param bool $preferencesModalFlipButtons True to flip button order
     *
     * @return self Fluent interface
     */
    public function setPreferencesModalFlipButtons(bool $preferencesModalFlipButtons): self
    {
        $this->preferencesModalFlipButtons = $preferencesModalFlipButtons;

        return $this;
    }

    /**
     * Returns the locale translations associated with this configuration.
     *
     * @return Collection<int, CookieConsentConfigTranslation>
     */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    /**
     * Adds a locale translation to this configuration.
     *
     * @param CookieConsentConfigTranslation $translation The translation to add
     *
     * @return self Fluent interface
     */
    public function addTranslation(CookieConsentConfigTranslation $translation): self
    {
        if (!$this->translations->contains($translation)) {
            $this->translations->add($translation);
            $translation->setConfig($this);
        }

        return $this;
    }

    /**
     * Removes a locale translation from this configuration.
     *
     * @param CookieConsentConfigTranslation $translation The translation to remove
     *
     * @return self Fluent interface
     */
    public function removeTranslation(CookieConsentConfigTranslation $translation): self
    {
        if ($this->translations->removeElement($translation) && $translation->getConfig() === $this) {
            $translation->setConfig(null);
        }

        return $this;
    }

    /**
     * Finds the translation for the given locale.
     *
     * @param string $locale The locale code
     *
     * @return CookieConsentConfigTranslation|null The matching translation or null
     */
    public function findTranslation(string $locale): ?CookieConsentConfigTranslation
    {
        foreach ($this->translations as $translation) {
            if ($translation->getLocale() === $locale) {
                return $translation;
            }
        }

        return null;
    }

    /**
     * Returns the modal color theme identifier.
     *
     * @return string One of the COLOR_THEMES constants
     */
    public function getColorTheme(): string
    {
        return $this->colorTheme;
    }

    /**
     * Sets the modal color theme identifier.
     *
     * @param string $colorTheme One of the COLOR_THEMES constants
     *
     * @return self Fluent interface
     */
    public function setColorTheme(string $colorTheme): self
    {
        if (!in_array($colorTheme, self::COLOR_THEMES, true)) {
            throw new InvalidArgumentException(sprintf('Invalid color theme "%s".', $colorTheme));
        }

        $this->colorTheme = $colorTheme;

        return $this;
    }

    /**
     * Returns whether dark mode styling is enabled.
     *
     * @return bool True when dark mode classes are applied
     */
    public function isDarkModeEnabled(): bool
    {
        return $this->darkModeEnabled;
    }

    /**
     * Enables or disables dark mode styling.
     *
     * @param bool $darkModeEnabled True to enable dark mode
     *
     * @return self Fluent interface
     */
    public function setDarkModeEnabled(bool $darkModeEnabled): self
    {
        $this->darkModeEnabled = $darkModeEnabled;

        return $this;
    }

    /**
     * Returns whether CSS transitions are disabled on the modal.
     *
     * @return bool True when transitions are suppressed
     */
    public function isDisableTransitions(): bool
    {
        return $this->disableTransitions;
    }

    /**
     * Enables or disables CSS transitions on the modal.
     *
     * @param bool $disableTransitions True to disable transitions
     *
     * @return self Fluent interface
     */
    public function setDisableTransitions(bool $disableTransitions): self
    {
        $this->disableTransitions = $disableTransitions;

        return $this;
    }

    /**
     * Returns whether the modal uses a two-step banner/preferences flow.
     *
     * @return bool True when the two-step layout is active
     */
    public function isTwoStepModal(): bool
    {
        return $this->twoStepModal;
    }

    /**
     * Enables or disables the two-step modal flow.
     *
     * @param bool $twoStepModal True to use banner and preferences steps
     *
     * @return self Fluent interface
     */
    public function setTwoStepModal(bool $twoStepModal): self
    {
        $this->twoStepModal = $twoStepModal;

        return $this;
    }

    /**
     * Returns whether the preferences step opens by default.
     *
     * @return bool True when preferences are shown first
     */
    public function isOpenPreferencesModal(): bool
    {
        return $this->openPreferencesModal;
    }

    /**
     * Sets whether the preferences step opens by default.
     *
     * @param bool $openPreferencesModal True to open preferences first
     *
     * @return self Fluent interface
     */
    public function setOpenPreferencesModal(bool $openPreferencesModal): self
    {
        $this->openPreferencesModal = $openPreferencesModal;

        return $this;
    }

    /**
     * Returns whether blocked iframe placeholders are managed after consent.
     *
     * @return bool True when iframe activation is enabled
     */
    public function isManageIframePlaceholders(): bool
    {
        return $this->manageIframePlaceholders;
    }

    /**
     * Enables or disables iframe placeholder management.
     *
     * @param bool $manageIframePlaceholders True to activate placeholders after consent
     *
     * @return self Fluent interface
     */
    public function setManageIframePlaceholders(bool $manageIframePlaceholders): self
    {
        $this->manageIframePlaceholders = $manageIframePlaceholders;

        return $this;
    }

    /**
     * Returns whether per-cookie toggles are shown in the preferences step.
     *
     * @return bool True when granular selection is enabled
     */
    public function isGranularCookieSelection(): bool
    {
        return $this->granularCookieSelection;
    }

    /**
     * Enables or disables granular per-cookie selection.
     *
     * @param bool $granularCookieSelection True to show per-cookie toggles
     *
     * @return self Fluent interface
     */
    public function setGranularCookieSelection(bool $granularCookieSelection): self
    {
        $this->granularCookieSelection = $granularCookieSelection;

        return $this;
    }

    /**
     * Returns whether the floating preferences bubble is enabled.
     *
     * @return bool True when the bubble button is rendered
     */
    public function isPreferencesBubbleEnabled(): bool
    {
        return $this->preferencesBubbleEnabled;
    }

    /**
     * Enables or disables the floating preferences bubble.
     *
     * @param bool $preferencesBubbleEnabled True to render the bubble button
     *
     * @return self Fluent interface
     */
    public function setPreferencesBubbleEnabled(bool $preferencesBubbleEnabled): self
    {
        $this->preferencesBubbleEnabled = $preferencesBubbleEnabled;

        return $this;
    }

    /**
     * Returns the screen corner used for the preferences bubble.
     *
     * @return string One of the PREFERENCES_BUBBLE_POSITIONS constants
     */
    public function getPreferencesBubblePosition(): string
    {
        return $this->preferencesBubblePosition;
    }

    /**
     * Sets the screen corner used for the preferences bubble.
     *
     * @param string $preferencesBubblePosition One of the PREFERENCES_BUBBLE_POSITIONS constants
     *
     * @return self Fluent interface
     */
    public function setPreferencesBubblePosition(string $preferencesBubblePosition): self
    {
        if (!in_array($preferencesBubblePosition, self::PREFERENCES_BUBBLE_POSITIONS, true)) {
            throw new InvalidArgumentException(sprintf('Invalid preferences bubble position "%s".', $preferencesBubblePosition));
        }

        $this->preferencesBubblePosition = $preferencesBubblePosition;

        return $this;
    }

    /**
     * Returns cookie definitions linked to this configuration profile.
     *
     * @return Collection<int, CookieDefinition> Cookie definition entities
     */
    public function getCookieDefinitions(): Collection
    {
        return $this->cookieDefinitions;
    }

    /**
     * Adds a cookie definition to this configuration profile.
     *
     * @param CookieDefinition $definition The cookie definition to attach
     *
     * @return self Fluent interface
     */
    public function addCookieDefinition(CookieDefinition $definition): self
    {
        if (!$this->cookieDefinitions->contains($definition)) {
            $this->cookieDefinitions->add($definition);
            $definition->setConfig($this);
        }

        return $this;
    }
}
