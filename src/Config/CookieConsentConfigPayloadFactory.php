<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Config;

use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfigTranslation;
use Symfony\Contracts\Translation\TranslatorInterface;

use function is_array;
use function sprintf;

/**
 * Builds the JSON payload returned by the cookie consent configuration API.
 */
final class CookieConsentConfigPayloadFactory
{
    /**
     * Creates a new configuration payload factory.
     *
     * @param list<string> $categories
     */
    public function __construct(
        private readonly CookieConsentConfigResolver $configResolver,
        private readonly TranslatorInterface $translator,
        private readonly CookieInventoryProvider $inventoryProvider,
        private readonly array $categories,
        private readonly array $yamlPreferenceSections = [],
    ) {
    }

    /**
     * Builds the API response payload for the given locale and route.
     *
     * @param string $locale The requested locale code
     * @param string|null $route The current route name, if any
     *
     * @return array{code: int, data: array<string, mixed>}
     */
    public function build(string $locale, ?string $route = null): array
    {
        $resolved    = $this->configResolver->resolve($locale, $route);
        $config      = $resolved?->getConfig();
        $translation = $resolved?->getTranslation();

        return [
            'code' => 200,
            'data' => [
                'autoShow'                  => $config?->isAutoShow() ?? true,
                'revision'                  => $config?->getRevision() ?? 0,
                'manageScriptTags'          => $config?->isManageScriptTags() ?? false,
                'autoClearCookies'          => $config?->isAutoClearCookies() ?? false,
                'hideFromBots'              => $config?->isHideFromBots() ?? true,
                'disablePageInteraction'    => $config?->isDisablePageInteraction() ?? false,
                'lazyHtmlGeneration'        => $config?->isLazyHtmlGeneration() ?? false,
                'colorTheme'                => $config?->getColorTheme() ?? 'light',
                'darkModeEnabled'           => $config?->isDarkModeEnabled() ?? false,
                'disableTransitions'        => $config?->isDisableTransitions() ?? false,
                'twoStepModal'              => $config?->isTwoStepModal() ?? false,
                'openPreferencesModal'      => $config?->isOpenPreferencesModal() ?? false,
                'manageIframePlaceholders'  => $config?->isManageIframePlaceholders() ?? false,
                'granularCookieSelection'   => $config?->isGranularCookieSelection() ?? false,
                'preferencesBubbleEnabled'  => $config?->isPreferencesBubbleEnabled() ?? false,
                'preferencesBubblePosition' => $config?->getPreferencesBubblePosition() ?? CookieConsentConfig::PREFERENCES_BUBBLE_POSITION_BOTTOM_RIGHT,
                'routeTargeting'            => [
                    'mode'   => $config?->getAutoShowRouteMode() ?? CookieConsentConfig::AUTO_SHOW_ROUTE_MODE_ALL,
                    'routes' => $config?->getAutoShowRoutes() ?? [],
                ],
                'guiOptions' => [
                    'consentModal' => [
                        'layout'             => $config?->getConsentModalLayout() ?? CookieConsentConfig::CONSENT_MODAL_LAYOUT_TYPES[0],
                        'variant'            => $config?->getConsentModalVariant() ?? 'wide',
                        'position'           => $config?->getConsentModalPosition() ?? 'bottom center',
                        'equalWeightButtons' => $config?->isConsentModalEqualWeightButtons() ?? false,
                        'flipButtons'        => $config?->isConsentModalFlipButtons() ?? false,
                    ],
                    'preferencesModal' => [
                        'layout'             => $config?->getPreferencesModalLayout() ?? CookieConsentConfig::CONSENT_MODAL_LAYOUT_TYPES[0],
                        'variant'            => $config?->getPreferencesModalVariant() ?? 'wide',
                        'position'           => $config?->getPreferencesModalPosition() ?? 'middle center',
                        'equalWeightButtons' => $config?->isPreferencesModalEqualWeightButtons() ?? false,
                        'flipButtons'        => $config?->isPreferencesModalFlipButtons() ?? false,
                    ],
                ],
                'categories' => $this->buildCategories(),
                'language'   => [
                    'default'      => $locale,
                    'autoDetect'   => 'browser',
                    'translations' => [
                        $locale => [
                            'consentModal'     => $this->buildConsentModalTranslation($locale, $translation),
                            'preferencesModal' => $this->buildPreferencesModalTranslation($locale, $translation, $config),
                            'categories'       => $this->buildCategoryTranslations($locale),
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, array<string, bool>>
     */
    private function buildCategories(): array
    {
        $categories = [
            'required' => [
                'readOnly' => true,
            ],
        ];

        foreach ($this->categories as $category) {
            $categories[$category] = [];
        }

        return $categories;
    }

    /**
     * @return array<string, string|null>
     */
    private function buildConsentModalTranslation(string $locale, ?CookieConsentConfigTranslation $translation): array
    {
        return [
            'label'              => $translation?->getConsentModalLabel(),
            'title'              => $translation?->getConsentModalTitle() ?? $this->trans('nowo_cookie_consent.title', $locale),
            'description'        => $translation?->getConsentModalDescription() ?? $this->trans('nowo_cookie_consent.intro', $locale),
            'acceptAllBtn'       => $translation?->getConsentModalAcceptAllBtn() ?? $this->trans('nowo_cookie_consent.use_all_cookies', $locale),
            'acceptNecessaryBtn' => $translation?->getConsentModalAcceptNecessaryBtn() ?? $this->trans('nowo_cookie_consent.use_only_functional_cookies', $locale),
            'showPreferencesBtn' => $translation?->getConsentModalShowPreferencesBtn(),
            'footer'             => $translation?->getConsentModalFooter() ?? $this->trans('nowo_cookie_consent.read_more', $locale),
            'privacyRoute'       => $translation?->getPrivacyRoute() ?? $this->trans('nowo_cookie_consent.privacy_route', $locale) ?: null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPreferencesModalTranslation(
        string $locale,
        ?CookieConsentConfigTranslation $translation,
        ?CookieConsentConfig $config,
    ): array {
        return [
            'title' => $translation?->getPreferencesModalTitle()
                ?? $this->trans('nowo_cookie_consent.preferences.title', $locale),
            'usageTitle'         => $this->trans('nowo_cookie_consent.preferences.usage_title', $locale),
            'usageDescription'   => $this->trans('nowo_cookie_consent.preferences.usage_description', $locale),
            'acceptAllBtn'       => $translation?->getPreferencesModalAcceptAllBtn(),
            'acceptNecessaryBtn' => $translation?->getPreferencesModalAcceptNecessaryBtn(),
            'savePreferencesBtn' => $translation?->getPreferencesModalSavePreferencesBtn() ?? $this->trans('nowo_cookie_consent.save', $locale),
            'closeIconLabel'     => $translation?->getPreferencesModalCloseIconLabel(),
            'sections'           => $this->buildPreferenceSections($locale, $translation, $config),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildPreferenceSections(
        string $locale,
        ?CookieConsentConfigTranslation $translation,
        ?CookieConsentConfig $config,
    ): array {
        $configured = $translation?->getPreferenceSections() ?? $this->yamlPreferenceSections;
        $sections   = [];

        foreach ($configured as $section) {
            if (!is_array($section)) {
                continue;
            }

            $title       = isset($section['title']) ? (string) $section['title'] : '';
            $description = isset($section['description']) ? (string) $section['description'] : '';
            $categories  = $section['categories'] ?? [];

            if (!is_array($categories) || $categories === []) {
                $sections[] = ['title' => $title, 'description' => $description];

                continue;
            }

            foreach ($categories as $category) {
                $sections[] = $this->enrichSectionWithInventory(
                    [
                        'title'          => $title,
                        'description'    => $description,
                        'linkedCategory' => (string) $category,
                    ],
                    $config,
                    $locale,
                    (string) $category,
                );
            }
        }

        if ($sections !== []) {
            return $sections;
        }

        return $this->buildDefaultPreferenceSections($locale, $config);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildDefaultPreferenceSections(string $locale, ?CookieConsentConfig $config): array
    {
        $sections = [
            $this->enrichSectionWithInventory([
                'title'          => $this->trans('nowo_cookie_consent.required.title', $locale),
                'description'    => $this->trans('nowo_cookie_consent.required.description', $locale),
                'linkedCategory' => 'required',
            ], $config, $locale, 'required'),
        ];

        foreach ($this->categories as $category) {
            $sections[] = $this->enrichSectionWithInventory([
                'title'          => $this->trans(sprintf('nowo_cookie_consent.%s.title', $category), $locale),
                'description'    => $this->trans(sprintf('nowo_cookie_consent.%s.description', $category), $locale),
                'linkedCategory' => $category,
            ], $config, $locale, $category);
        }

        return $sections;
    }

    /**
     * @param array<string, mixed> $section
     *
     * @return array<string, mixed>
     */
    private function enrichSectionWithInventory(
        array $section,
        ?CookieConsentConfig $config,
        string $locale,
        string $category,
    ): array {
        if (!$this->inventoryProvider->isEnabled()) {
            return $section;
        }

        $body = $this->inventoryProvider->buildCookieTableBody($config, $locale, $category);

        if ($body === []) {
            return $section;
        }

        $section['cookieTable'] = [
            'caption' => $this->trans('nowo_cookie_consent.inventory.caption', $locale),
            'headers' => [
                'name'   => $this->trans('nowo_cookie_consent.inventory.name', $locale),
                'domain' => $this->trans('nowo_cookie_consent.inventory.provider', $locale),
                'desc'   => $this->trans('nowo_cookie_consent.inventory.purpose', $locale),
            ],
            'body' => $body,
        ];

        return $section;
    }

    /**
     * @return array<string, array{title: string, description: string}>
     */
    private function buildCategoryTranslations(string $locale): array
    {
        $result = [
            'required' => [
                'title'       => $this->trans('nowo_cookie_consent.required.title', $locale),
                'description' => $this->trans('nowo_cookie_consent.required.description', $locale),
            ],
        ];

        foreach ($this->categories as $category) {
            $result[$category] = [
                'title'       => $this->trans(sprintf('nowo_cookie_consent.%s.title', $category), $locale),
                'description' => $this->trans(sprintf('nowo_cookie_consent.%s.description', $category), $locale),
            ];
        }

        return $result;
    }

    private function trans(string $id, string $locale): string
    {
        return $this->translator->trans($id, [], 'NowoCookieConsentBundle', $locale);
    }
}
