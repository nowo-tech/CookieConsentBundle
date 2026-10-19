# Templates

The admin pages of the bundle follow the UiKit 2.x **page contract** (UiKit ^2.1) (REQ-UI-003): each page extends a UiKit page
scaffold, the controller passes a UiKit view model (`page`) built by
`Nowo\CookieConsentBundle\Admin\AdminPageFactory`, and the bundle fills only the data blocks. Header, actions, area and
section tabs, table frame, row actions, confirm dialogs, pagination, empty state, form frame and flashes come from
UiKit and its theme (`nowo_ui_kit.theme`), so a host themes them **once** for every bundle. References: UiKit
`docs/PAGE_CONTRACT.md`, `docs/THEME_SLOTS.md`.

The public consent banner / preferences modal (`cookie_consent.html.twig`, `cookie_consent.tailwind.html.twig` and
their partials) is front-end UI rendered inside the host's own pages: it is **not** an admin page, does not use the
scaffolds and keeps its own templates and CSS (no CDN assets).

## Table of contents

- [Pages](#pages)
- [Context and data blocks](#context-and-data-blocks)
- [Unique pages](#unique-pages)
- [Per-page overrides](#per-page-overrides)
- [Layout](#layout)
- [Theme exception (`theme.bundles.NowoCookieConsentBundle`)](#theme-exception-themebundlesnowocookieconsentbundle)
- [Allowed exceptions](#allowed-exceptions)

## Pages

| Page | Template | Scaffold | View model | Data blocks filled |
| --- | --- | --- | --- | --- |
| Cookie inventory of a profile | `admin/cookie_definition/index.html.twig` | `page/list` | `ListPage` (columns: name, provider, purpose, category, duration, allowedByDefault; rowActions: edit, delete with `Confirm`; pagination; empty state with a create action; header action: create) | `nowo_ui_list_row` |
| Cookie definition (new / edit) | `admin/cookie_definition/form.html.twig` | `page/form` | `FormPage` (submit "Save cookie", cancel → inventory) | `nowo_ui_form_body` (scalar fields in a two-column grid, "allowed by default", translations as locale tabs `_translations_tabs.html.twig`, then `parent()` for the rest; deprecated `form_fields` partial first when configured — the fields it renders are skipped) |
| Profile settings (one section per route: profile, behavior, appearance, consent-modal, preferences-modal, route-targeting) | `admin/config/settings.html.twig` | `page/settings` | `SettingsPage` (six `SettingsSection`s with `href` = tabs, `current` = the route section; only the current one carries its form) | `nowo_ui_settings_section` (`parent()`; deprecated `form_fields` partial when configured) |

Root attributes (`ListPage::attributes` etc.) for tests: `data-testid="cookie-consent-definitions"`,
`cookie-consent-definition-form`, `cookie-consent-settings`; standard regions are reachable with
`[data-nowo-ui-region="table|pagination|empty|confirms|form|tabs|section"]`, and the area navigation with
`[data-nowo-ui-region="area-nav"]`.

No page overrides `nowo_ui_page_header` or `nowo_ui_content`: the area navigation is `page.header.tabs` and the form
theme is `page.formThemes` / `section.formThemes` (UiKit 2.1), both rendered by the scaffolds.

## Context and data blocks

Every page receives:

| Variable | Content |
| --- | --- |
| `page` | the view model (`ListPage`, `FormPage`, `SettingsPage`); `page.bundle` = `NowoCookieConsentBundle`, `page.layout` = `web_ui.layout_template`; `page.header.tabs` = the area navigation (`View\AreaNav`: Settings / Cookie inventory, `data-turbo="false"`), rendered by the UiKit page header through the `tabs` partial (region `area-nav`); `page.formThemes` (form page) / `section.formThemes` (current settings section) = the deprecated `web_ui.chrome.templates.form_theme`, else empty |
| 1.x variables | `config`; inventory: `definitions`, `delete_forms`, `pages`, `total`, `pageSize`; form: `definition`, `form` (FormView), `isNew`; settings: `form`, `section`, `sections` — kept for host templates set through `web_ui.templates.<page>` |

**Area navigation.** `Admin\CookieConsentAreaNavProvider` publishes it to the UiKit area-nav registry (area
`cookie_consent`; context `configId` or `config`, optional `section`: `settings` | `cookies`), so DashboardMenu or a
host menu can reuse it: `nowo_ui_area_nav('cookie_consent', {configId: 1})`. The page headers take their tabs from
the registry too: register your own `Nowo\UiKitBundle\Navigation\AreaNavProviderInterface` for `cookie_consent`
with a higher priority (tag `nowo_ui_kit.area_nav_provider`) to replace the tabs, or return an empty `AreaNav` to drop
them (for instance when DashboardMenu renders the navigation). `admin/_admin_area_nav.html.twig` is deprecated (kept
for 1.x host templates that include it; it renders the registry's navigation).

Rows of the inventory list are plain arrays: `id`, `name`, `provider`, `purpose`, `category` (`View\Label`, translate with
`|nowo_ui_trans`), `duration`, `allowedByDefault` (bool). Cells use the `table_cell`, `table_cell_muted`, `code`, `muted`,
`badge_success` and `badge_neutral` slots and carry `data-label` (card mode) and `data-nowo-ui-priority`; name,
category, duration and default carry `nowo-cc-admin-nowrap`, the purpose `nowo-cc-admin-clamp` (two lines, full text in
`title`).

**Cookie definition form.** `nowo_ui_form_body` renders `name`, `duration`, `category`, `type`, `sortOrder` inside
`<div class="nowo-cc-admin-form__grid" data-nowo-cc-region="definition-fields">`, then `allowedByDefault`, then the
translations through `admin/cookie_definition/_translations_tabs.html.twig` (1.18; `<fieldset data-nowo-cc-region="translations">`
with a legend and a help line, then `@NowoFormKitBundle/form/_locale_tabs.html.twig` in `entries` mode rendered by the
UiKit `locale_tabs` partial: one fixed tab per `nowo_cookie_consent.enabled_locales`, the default locale first and
starred, DOM ids `tab-nowo-cc-definition-translations-<locale>` / `…-<locale>-panel`, the locale as a hidden input,
provider and purpose in the panel, the first tab with errors opened; translations of locales that are not enabled are
listed read-only in `<div data-nowo-cc-region="other-translations">` and kept on save), then `parent()` renders whatever is left
(CSRF token, fields a host form extension adds). Text controls come from the seeded `cookie_consent` FormKit profile with
the UiKit `input_full` slot.

**Bundle stylesheet.** The list and form pages append `bundles/nowocookieconsent/nowo-cookie-consent-admin.css` to
`nowo_ui_styles` (local asset, no CDN). It only lays out the bundle's own data-block markup (`nowo-cc-admin-*` classes:
form grid, translations table, nowrap / clamp cells of the inventory; one-line row actions come from UiKit 2.2) and reads UiKit tokens; a
host theme can override it like any stylesheet loaded after UiKit's.

## Unique pages

- (none) — every admin page extends a UiKit scaffold.

## Per-page overrides

`nowo_cookie_consent.web_ui.templates.<page>` with `<page>` = `cookie_definition_index`, `cookie_definition_form`,
`settings`: a host template that extends the shipped one and overrides a data block, e.g.

```twig
{# templates/admin/cookie_inventory.html.twig #}
{% extends '@!NowoCookieConsentBundle/admin/cookie_definition/index.html.twig' %}
{% block nowo_ui_list_row %}
    {{ parent() }}
    {# extra cells… #}
{% endblock %}
```

```yaml
nowo_cookie_consent:
    web_ui:
        templates:
            cookie_definition_index: 'admin/cookie_inventory.html.twig'
```

Prefer theming UiKit (`nowo_ui_kit.theme`, or `templates/bundles/NowoUiKitBundle/page/*.html.twig` for a whole view
type) over per-page overrides.

## Layout

`web_ui.layout_template` (default `null`) is the per-bundle layout override; null uses `nowo_ui_kit.layouts.admin`
(default `@NowoUiKit/layout/admin.html.twig`). A host layout must render the UiKit canonical blocks
(`nowo_ui_document_title`, `nowo_ui_head`, `nowo_ui_styles`, `nowo_ui_navigation`, `nowo_ui_page_header`,
`nowo_ui_flashes`, `nowo_ui_content`, `nowo_ui_modals`, `nowo_ui_scripts`); see UiKit `docs/PAGE_CONTRACT.md#layouts`.
The bundle ships no layout of its own (the 1.x Bootstrap-CDN `admin/layout.html.twig` was removed in 1.16).

## Theme exception (`theme.bundles.NowoCookieConsentBundle`)

Per-bundle classes / attributes / partials go in `nowo_ui_kit.theme.bundles.NowoCookieConsentBundle`. The deprecated
`nowo_cookie_consent.web_ui.chrome` map (1.13–1.15) is bridged there automatically (prepended, so a host's own
exception wins); the 1.x keys of a single page (`page_header`, `page_header_form`, `form_actions`,
`settings_actions`) go to `theme.bundles.NowoCookieConsentBundle.pages.<type>` (UiKit 2.1). Mapping table:
[UPGRADING — Template contract](UPGRADING.md#template-contract-116).

## Allowed exceptions

- (none) — `PageContractTest` runs with an empty `allowedClasses()` for the `none`, `bootstrap5` and `tailwind`
  frameworks.
