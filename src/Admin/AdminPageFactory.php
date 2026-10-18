<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Admin;

use InvalidArgumentException;
use Nowo\CookieConsentBundle\DependencyInjection\Configuration;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieDefinition;
use Nowo\CookieConsentBundle\Entity\CookieDefinitionTranslation;
use Nowo\UiKitBundle\Navigation\AreaNavRegistry;
use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\AreaNav;
use Nowo\UiKitBundle\View\AreaNavItem;
use Nowo\UiKitBundle\View\Column;
use Nowo\UiKitBundle\View\Confirm;
use Nowo\UiKitBundle\View\EmptyState;
use Nowo\UiKitBundle\View\FormPage;
use Nowo\UiKitBundle\View\Label;
use Nowo\UiKitBundle\View\ListPage;
use Nowo\UiKitBundle\View\PageHeader;
use Nowo\UiKitBundle\View\Pagination;
use Nowo\UiKitBundle\View\SettingsPage;
use Nowo\UiKitBundle\View\SettingsSection;
use Symfony\Component\Form\FormView;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use function sprintf;

/**
 * Builds the UiKit view models of the admin pages (REQ-UI-003): the cookie inventory list (`page/list`), the
 * cookie definition form (`page/form`) and the profile settings (`page/settings`), plus the area navigation
 * (Settings | Cookie inventory, `PageHeader::$tabs`: rendered by the UiKit page header, also published to the
 * UiKit area-nav registry by {@see CookieConsentAreaNavProvider}).
 *
 * View models are plain data (labels as translation keys of the bundle domain, URLs already generated): safe in
 * worker mode. The service itself is stateless.
 */
final class AdminPageFactory
{
    public const DOMAIN = 'NowoCookieConsentBundle';

    public const AREA_SETTINGS = 'settings';

    public const AREA_COOKIES = 'cookies';

    /** Root attributes of the area `<nav>` (1.x markup: the tabs are plain links, never Turbo visits). */
    public const AREA_NAV_ATTRIBUTES = ['data-turbo' => 'false'];

    private const KEY = 'nowo_cookie_consent.admin.';

    /**
     * @param string|null $layoutTemplate `web_ui.layout_template` (null = UiKit admin layout)
     * @param string|null $rowActionsDisplay `web_ui.chrome.row_actions_display` (deprecated; null = nowo_ui_kit.row_actions_display)
     * @param array<string, string> $templates `web_ui.templates` (page => template; missing pages use the shipped template)
     * @param list<string> $formThemes form themes of the admin forms (`FormPage` / `SettingsSection::$formThemes`):
     *                                 the deprecated `web_ui.chrome.templates.form_theme`, else none (host `twig.form_themes`)
     * @param AreaNavRegistry|null $areaNavRegistry UiKit area-nav registry: the page tabs come from the first provider
     *                                              of area `cookie_consent` (a host provider with a higher priority can
     *                                              replace them, or drop them with an empty `AreaNav`); null = {@see self::areaNav()}
     */
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ?string $layoutTemplate = null,
        private readonly ?string $rowActionsDisplay = null,
        private readonly array $templates = [],
        private readonly array $formThemes = [],
        private readonly ?AreaNavRegistry $areaNavRegistry = null,
    ) {
    }

    /**
     * Template of an admin page (`web_ui.templates.<page>`, else the shipped one; see Configuration::PAGE_TEMPLATES).
     *
     * @return string
     */
    public function template(string $page): string
    {
        $template = $this->templates[$page] ?? null;

        return $template !== null && $template !== '' ? $template : (Configuration::PAGE_TEMPLATES[$page] ?? throw new InvalidArgumentException(sprintf('Unknown admin page "%s".', $page)));
    }

    /**
     * Area navigation (Settings | Cookie inventory) of a consent profile, for `PageHeader::$tabs`.
     *
     * @param CookieConsentConfig|int $config consent profile (or its id)
     * @param string|null $current active section ({@see self::AREA_SETTINGS}, {@see self::AREA_COOKIES}; null = none)
     *
     * @return AreaNav
     */
    public function areaNav(CookieConsentConfig|int $config, ?string $current = null): AreaNav
    {
        $configId = $config instanceof CookieConsentConfig ? (int) $config->getId() : $config;

        return new AreaNav([
            new AreaNavItem(
                $this->label('area_nav.settings'),
                $this->urlGenerator->generate('nowo_cookie_consent_config_settings_edit', ['configId' => $configId]),
                $current === self::AREA_SETTINGS,
            ),
            new AreaNavItem(
                $this->label('area_nav.cookies'),
                $this->urlGenerator->generate('nowo_cookie_consent_cookie_definitions_index', ['configId' => $configId]),
                $current === self::AREA_COOKIES,
            ),
        ], $this->label('area_nav.label'), self::AREA_NAV_ATTRIBUTES);
    }

    /**
     * Cookie inventory of a consent profile (`page/list`).
     *
     * @param iterable<CookieDefinition> $definitions current page of definitions
     * @param array<int, FormView> $deleteForms CSRF-only delete forms keyed by definition id
     *
     * @return ListPage
     */
    public function definitionList(
        CookieConsentConfig $config,
        iterable $definitions,
        array $deleteForms,
        int $page,
        int $pageSize,
        int $total,
        string $locale,
    ): ListPage {
        $configId = (int) $config->getId();
        $newUrl   = $this->urlGenerator->generate('nowo_cookie_consent_cookie_definitions_new', ['configId' => $configId]);

        $rows = [];
        foreach ($definitions as $definition) {
            $id = $definition->getId();
            if ($id === null) {
                continue;
            }
            $rows[$id] = $this->definitionRow($definition, $locale);
        }

        $actions = function (array $row) use ($configId, $deleteForms): array {
            $id    = (int) $row['id'];
            $name  = (string) $row['name'];
            $label = $this->label('cookie_definition.delete');

            return [
                new Action(
                    'edit',
                    $this->label('cookie_definition.edit', ['%name%' => $name]),
                    $this->urlGenerator->generate('nowo_cookie_consent_cookie_definitions_edit', ['configId' => $configId, 'id' => $id]),
                    display: $this->rowActionsDisplay,
                ),
                new Action(
                    'delete',
                    $label,
                    $this->urlGenerator->generate('nowo_cookie_consent_cookie_definitions_delete', ['configId' => $configId, 'id' => $id]),
                    method: 'POST',
                    confirm: new Confirm(
                        $label,
                        $this->label('cookie_definition.delete_confirm', ['%name%' => $name]),
                        $label,
                        cancelLabel: $this->label('cookie_definition.cancel'),
                    ),
                    form: $deleteForms[$id] ?? null,
                    display: $this->rowActionsDisplay,
                ),
            ];
        };

        return new ListPage(
            (new PageHeader(
                $this->label('cookie_definition.title'),
                $this->label('cookie_definition.lead'),
                [Action::create($newUrl, $this->label('cookie_definition.new'))],
            ))->withTabs($this->pageTabs($config, self::AREA_COOKIES)),
            [
                new Column('name', $this->label('cookie_definition.table.name'), required: true),
                new Column('provider', $this->label('cookie_definition.table.provider'), priority: 2),
                new Column('purpose', $this->label('cookie_definition.table.purpose'), priority: 3),
                new Column('category', $this->label('cookie_definition.table.category')),
                new Column('duration', $this->label('cookie_definition.table.duration'), priority: 2),
                new Column('allowedByDefault', $this->label('cookie_definition.table.allowed_by_default'), priority: 3),
            ],
            $rows,
            rowActions: $actions,
            pagination: new Pagination(
                $page,
                $pageSize,
                $total,
                route: 'nowo_cookie_consent_cookie_definitions_index',
                routeParams: ['configId' => $configId],
            ),
            empty: new EmptyState(
                message: $this->label('cookie_definition.empty'),
                action: Action::create($newUrl, $this->label('cookie_definition.new')),
            ),
            bundle: AdminChrome::BUNDLE,
            layout: $this->layoutTemplate,
            attributes: ['data-testid' => 'cookie-consent-definitions'],
            actionsLabel: $this->label('cookie_definition.table.actions'),
        );
    }

    /**
     * Cookie definition create / edit form (`page/form`).
     *
     * @return FormPage
     */
    public function definitionForm(CookieConsentConfig $config, CookieDefinition $definition, FormView $form, bool $isNew): FormPage
    {
        $title = $isNew
            ? $this->label('cookie_definition.new_title')
            : $this->label('cookie_definition.edit_title', ['%name%' => $definition->getName()]);

        return new FormPage(
            (new PageHeader($title, $this->label('cookie_definition.form_lead', ['%profile%' => $config->getDisplayName()])))
                ->withTabs($this->pageTabs($config, self::AREA_COOKIES)),
            $form,
            submitLabel: $this->label('cookie_definition.save'),
            cancelHref: $this->urlGenerator->generate('nowo_cookie_consent_cookie_definitions_index', ['configId' => (int) $config->getId()]),
            cancelLabel: $this->label('cookie_definition.cancel'),
            bundle: AdminChrome::BUNDLE,
            layout: $this->layoutTemplate,
            attributes: ['data-testid' => 'cookie-consent-definition-form'],
            formThemes: $this->formThemes,
        );
    }

    /**
     * Profile settings, one section (tab) per route (`page/settings`); only the current section has its form.
     *
     * @return SettingsPage
     */
    public function settings(CookieConsentConfig $config, CookieConsentConfigSettingsSection $current, FormView $form): SettingsPage
    {
        $configId = (int) $config->getId();
        $sections = [];
        foreach (CookieConsentConfigSettingsSection::cases() as $section) {
            $suffix     = $section->translationSuffix();
            $sections[] = new SettingsSection(
                $section->value,
                $this->label('config.settings.section.' . $suffix),
                $this->urlGenerator->generate('nowo_cookie_consent_config_settings_section', ['configId' => $configId, 'section' => $section->value]),
                $section === $current ? $form : null,
                $this->label('config.settings.section.' . $suffix . '_lead'),
                $this->label('config.settings.save'),
                $section === $current ? $this->formThemes : [],
            );
        }

        return new SettingsPage(
            (new PageHeader(
                $this->label('config.settings.title'),
                $this->label('config.settings.lead', ['%profile%' => $config->getDisplayName()]),
            ))->withTabs($this->pageTabs($config, self::AREA_SETTINGS)),
            $sections,
            $current->value,
            bundle: AdminChrome::BUNDLE,
            layout: $this->layoutTemplate,
            attributes: ['data-testid' => 'cookie-consent-settings'],
        );
    }

    /**
     * Area navigation of a page header: the registry's answer for area `cookie_consent` (context: `config`,
     * `configId`, `section`), else the bundle's own.
     */
    private function pageTabs(CookieConsentConfig $config, string $section): AreaNav
    {
        return $this->areaNavRegistry?->get(CookieConsentAreaNavProvider::AREA, [
            'config'   => $config,
            'configId' => (int) $config->getId(),
            'section'  => $section,
        ]) ?? $this->areaNav($config, $section);
    }

    /**
     * Plain-data row of the cookie inventory list.
     *
     * @return array{id: int, name: string, provider: string, purpose: string, category: Label, duration: string, allowedByDefault: bool}
     */
    private function definitionRow(CookieDefinition $definition, string $locale): array
    {
        $translation = $definition->findTranslation($locale) ?? $definition->findTranslation('en');

        return [
            'id'               => (int) $definition->getId(),
            'name'             => $definition->getName(),
            'provider'         => $translation instanceof CookieDefinitionTranslation ? $translation->getProvider() : '',
            'purpose'          => $translation instanceof CookieDefinitionTranslation ? $translation->getPurpose() : '',
            'category'         => $this->label('cookie_definition.category.' . $definition->getCategory()),
            'duration'         => $definition->getDuration(),
            'allowedByDefault' => $definition->isAllowedByDefault(),
        ];
    }

    /**
     * @param array<string, string> $params
     */
    private function label(string $key, array $params = []): Label
    {
        return new Label(self::KEY . $key, self::DOMAIN, $params);
    }
}
