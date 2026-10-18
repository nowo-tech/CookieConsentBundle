# Upgrade Guide

## Table of contents

- [Unreleased](#unreleased)
  - [Template contract (1.16)](#template-contract-116)
- [To 1.15.2](#to-1152)
- [To 1.15.1](#to-1151)
- [To 1.15.0](#to-1150)
- [To 1.14.0](#to-1140)
- [To 1.13.1](#to-1131)
- [To 1.13.0](#to-1130)
- [To 1.12.0](#to-1120)
- [To 1.11.2](#to-1112)
- [To 1.11.1](#to-1111)
- [To 1.11.0](#to-1110)
- [To 1.10.1](#to-1101)
- [To 1.10.0 (FrankenPHP worker remediation)](#to-1100-frankenphp-worker-remediation)
- [From 1.9.6 to 1.9.7](#from-196-to-197)
  - [Notes](#notes)
- [From 1.9.5 to 1.9.6](#from-195-to-196)
- [General upgrade process](#general-upgrade-process)
- [To 1.9.5](#to-195)
- [To 1.9.4](#to-194)
- [To 1.9.3](#to-193)
- [To 1.9.2](#to-192)
- [To 1.9.1](#to-191)
- [To 1.9.0](#to-190)
  - [CSP / standalone CSS (recommended for style-src nonces)](#csp--standalone-css-recommended-for-style-src-nonces)
  - [Recommended layout defaults (DB profiles)](#recommended-layout-defaults-db-profiles)
- [To 1.8.0](#to-180)
- [To 1.7.0](#to-170)
  - [Site Backup interoperability (cold start)](#site-backup-interoperability-cold-start)
- [To 1.6.3](#to-163)
  - [Optional: public-only consent fragment](#optional-public-only-consent-fragment)
- [To 1.6.2](#to-162)
- [To 1.6.1](#to-161)
  - [Optional: skip consent fragment on dashboards](#optional-skip-consent-fragment-on-dashboards)
- [To 1.6.0](#to-160)
  - [UiKit composition (REQ-UI-001-kit)](#uikit-composition-req-ui-001-kit)
  - [Twig Extra Bundle (REQ-TWIG-004)](#twig-extra-bundle-req-twig-004)
  - [FormKitBundle](#formkitbundle)
  - [Twig-CS-Fixer (maintainers)](#twig-cs-fixer-maintainers)
- [To 1.5.2](#to-152)
  - [Breaking changes](#breaking-changes)
- [To 1.5.1](#to-151)
- [To 1.5.0](#to-150)
  - [Breaking changes](#breaking-changes-1)
- [To 1.4.8](#to-148)
  - [Breaking changes](#breaking-changes-2)
- [To 1.4.5](#to-145)
  - [Breaking changes](#breaking-changes-3)
- [To 1.4.4](#to-144)
  - [Breaking changes](#breaking-changes-4)
- [To 1.4.3](#to-143)
  - [Breaking changes](#breaking-changes-5)
- [To 1.4.2](#to-142)
  - [Breaking changes](#breaking-changes-6)
- [To 1.4.1](#to-141)
  - [Breaking changes](#breaking-changes-7)
- [To 1.4.0](#to-140)
  - [Configuration](#configuration)
  - [Breaking changes](#breaking-changes-8)
- [To 1.3.6](#to-136)
  - [Breaking changes](#breaking-changes-9)
- [To 1.3.5](#to-135)
  - [Breaking changes](#breaking-changes-10)
- [To 1.3.4](#to-134)
  - [Breaking changes](#breaking-changes-11)
- [To 1.3.3](#to-133)
  - [Breaking changes](#breaking-changes-12)
- [To 1.3.2](#to-132)
  - [Breaking changes](#breaking-changes-13)
- [To 1.3.1](#to-131)
  - [Breaking changes](#breaking-changes-14)
- [To 1.3.0](#to-130)
  - [Breaking changes](#breaking-changes-15)
- [UI theme changes](#ui-theme-changes)
- [To 1.2.0](#to-120)
  - [New optional configuration](#new-optional-configuration)
  - [Profile settings admin](#profile-settings-admin)
  - [Frontend assets](#frontend-assets)
  - [Breaking changes](#breaking-changes-16)
- [To 1.1.1](#to-111)
  - [Breaking changes](#breaking-changes-17)
- [To 1.1.0](#to-110)
  - [New optional configuration](#new-optional-configuration-1)
  - [Cookie inventory entities](#cookie-inventory-entities)
  - [Frontend assets](#frontend-assets-1)
  - [Twig embed change (recommended)](#twig-embed-change-recommended)
  - [Breaking changes](#breaking-changes-18)
- [To 1.0.0 (initial release)](#to-100-initial-release)
  - [Requirements](#requirements)
  - [Enable and configure](#enable-and-configure)
  - [Breaking changes](#breaking-changes-19)
- [Future versions](#future-versions)
- [Getting help](#getting-help)

## Unreleased

### Template contract (1.16)

The admin pages follow the **UiKit 2.x page contract** (REQ-UI-003) and require **`nowo-tech/ui-kit-bundle` ^2.1**
(`composer update nowo-tech/ui-kit-bundle`). Hosts that only configured `nowo_cookie_consent` / `nowo_ui_kit` need no
template work; check the points below.

**Pages → scaffolds** ([TEMPLATES.md](TEMPLATES.md))

| Page | Template | Scaffold / view model | Data blocks |
| --- | --- | --- | --- |
| Cookie inventory | `admin/cookie_definition/index.html.twig` | `page/list` / `ListPage` | `nowo_ui_list_row` |
| Cookie definition | `admin/cookie_definition/form.html.twig` | `page/form` / `FormPage` | `nowo_ui_form_body` |
| Profile settings | `admin/config/settings.html.twig` | `page/settings` / `SettingsPage` | `nowo_ui_settings_section` |

No bundle block of its own: the pages do not override `nowo_ui_page_header` or `nowo_ui_content`. The area
navigation (Settings | Cookie inventory) is `page.header.tabs` (UiKit `AreaNav`), rendered by the UiKit page header
under the title row (region `area-nav`, `<nav data-turbo="false">` as in 1.x), and is published to the UiKit
area-nav registry (area `cookie_consent`, `Admin\CookieConsentAreaNavProvider`).

**What hosts may notice**

1. **Layout.** `web_ui.layout_template` defaults to `null`: the pages render in `nowo_ui_kit.layouts.admin` (default
   `@NowoUiKit/layout/admin.html.twig`, which loads `nowo-ui.css` but **no CSS framework**). The removed default
   `@NowoCookieConsentBundle/admin/layout.html.twig` (Bootstrap 5.3.3 from jsDelivr) is treated as `null` when set
   explicitly. Point `nowo_ui_kit.layouts.admin` (or `web_ui.layout_template`) at a layout that loads your CSS and
   renders the canonical blocks (`nowo_ui_document_title`, `nowo_ui_head`, `nowo_ui_styles`, `nowo_ui_navigation`,
   `nowo_ui_page_header`, `nowo_ui_flashes`, `nowo_ui_content`, `nowo_ui_modals`, `nowo_ui_scripts`). A 1.x layout
   that only rendered `body` / `title` / `stylesheets` / `javascripts` needs those blocks (UiKit
   `docs/PAGE_CONTRACT.md#example-host-integration` has a one-file bridge).
2. **Form theme.** `bootstrap_5_layout.html.twig` is no longer forced on the admin forms: set `twig.form_themes`
   (or keep the deprecated `web_ui.chrome.templates.form_theme`, passed to the scaffolds as `FormPage::$formThemes` /
   `SettingsSection::$formThemes`).
3. **Markup.** Header, tabs, table, row actions, confirm `<dialog>`s, pagination, empty state and form frame are UiKit
   markup (`nowo-ui-*` + theme slots). CSS or tests targeting 1.x classes (`.card.shadow-sm`, `.table-responsive`,
   `nowo-ui-action-create` / `-save` / `-cancel`, `nowo-ui-tabs__list`) should use the theme slots and the regions
   (`[data-nowo-ui-region="table|pagination|area-nav|tabs|form"]`, `[data-testid="cookie-consent-definitions"]`,
   `cookie-consent-definition-form`, `cookie-consent-settings`). The row delete forms are named
   `delete_cookie_definition_<id>` (unique DOM ids); the delete action still accepts the 1.x payload
   `delete_cookie_definition[_token]` and keeps the CSRF token ids (`delete-cookie-definition-<id>`).
4. **Row actions** inherit `nowo_ui_kit.row_actions_display` (default `icon`); 1.x showed text. Keep the text with
   `nowo_ui_kit.row_actions_display: text` (or the deprecated `web_ui.chrome.row_actions_display: text`).
5. **Template context.** Templates set through `web_ui.templates.<page>` receive `page` (the view model; area
   navigation in `page.header.tabs`); the 1.x variables stay (`config`, `definitions`, `delete_forms`, `pages`,
   `total`, `pageSize`, `definition`, `form`, `isNew`, `section`, `sections`) except `page`, which was the page number
   (use `page.pagination.page`).
6. **Area navigation.** The tabs moved from above the page header (block `nowo_cookie_consent_admin_area_nav`,
   removed) into the UiKit page header, below the title row. A host
   `page_header` partial override (`theme.partials.page_header`) renders them only if it renders `header.tabs` (UiKit
   UPGRADING "To 2.1.0"). To replace or drop the tabs, register an `AreaNavProviderInterface` for area
   `cookie_consent` with a higher priority than the bundle's (return an empty `AreaNav` to drop them).
   `admin/_admin_area_nav.html.twig` is deprecated: it renders the registry's navigation for 1.x host templates that
   include it.

**Removed templates:** `admin/layout.html.twig`, `admin/base.html.twig`, `admin/_pagination.html.twig`,
`admin/_tabs.html.twig`, `admin/_chrome.html.twig`, `admin/cookie_definition/_table.html.twig`,
`admin/config/_settings_section_tabs.html.twig`, `admin/cookie_definition/layout.html.twig`,
`admin/config/layout.html.twig`. **Removed blocks:** `nowo_cookie_consent_admin_area_nav|page_header|page_title|
page_intro|page_actions|table_wrapper|pagination|table_head|row_actions|empty_state|form_wrapper|form_body|form_actions|
section_tabs|settings_section|section_header|styles_after` → use the scaffold data blocks, the UiKit theme or a
scaffold override (`templates/bundles/NowoUiKitBundle/page/*.html.twig`).

**Migrating overrides.** Delete forks under `templates/bundles/NowoCookieConsentBundle/admin/`. For a genuinely
bundle-specific change use `web_ui.templates.<page>` with a thin
`{% extends '@!NowoCookieConsentBundle/admin/…' %}` that overrides a data block.

**Deprecated configuration** (still read in 1.x, removed in 2.0)

| Key | Replacement |
| --- | --- |
| `web_ui.css_framework` | `nowo_ui_kit.css_framework` (still seeded from this key when unset there) |
| `web_ui.icon_set` | `nowo_ui_kit.icon_set` (same) |
| `web_ui.chrome.classes.*` / `attributes.*` | `nowo_ui_kit.theme.bundles.NowoCookieConsentBundle.classes` / `.attributes` (bridged automatically, table below) |
| `web_ui.chrome.templates.tabs` / `pagination` | `…NowoCookieConsentBundle.partials.tabs` / `pagination` (bridged; the partials now receive UiKit's input: `items` / `pagination`) |
| `web_ui.chrome.templates.form_theme` | `twig.form_themes` (still applied, as `FormPage` / `SettingsSection::$formThemes`) |
| `web_ui.chrome.templates.form_fields` | `web_ui.templates.<page>` overriding `nowo_ui_form_body` / `nowo_ui_settings_section` (still applied) |
| `web_ui.chrome.templates.styles_after` | your layout's `nowo_ui_styles` or `theme.tokens` (still applied) |
| `web_ui.chrome.row_actions_display` | `nowo_ui_kit.row_actions_display` (still applied when not `inherit`) |
| `web_ui.chrome.page_title_tag` | `theme.partials.page_header` (ignored) |
| Twig globals `nowo_cookie_consent_layout_template`, `_css_framework`, `_icon_set` | `nowo_ui_layout`, `nowo_ui_kit_css_framework`, `nowo_ui_kit_icon_set` |

**`web_ui.chrome` → UiKit slot bridge** (classes are bridged with `framework_defaults: false`, i.e. they replace the
whole class list as in 1.x; a host `nowo_ui_kit.theme.bundles.NowoCookieConsentBundle` entry wins):

| 1.x key | UiKit slot |
| --- | --- |
| `page_header` / `page_header_form` | `page_header` of the list / form page (`…NowoCookieConsentBundle.pages.list` / `.pages.form`, UiKit 2.1) |
| `page_title` / `page_intro` | `page_title` / `page_intro` |
| `area_nav`, `section_tabs` | `tabs` |
| `tab_item` / `tab_item_active` | `tab` / `tab_active` (= `tab_item` + `tab_item_active`) |
| `table_panel`, `table_wrap` | `table_wrap` |
| `table` | `table` |
| `table_muted_cell` / `table_actions_cell` / `empty_cell` | `table_cell_muted` / `table_actions_cell` / `empty` |
| `form_panel` | `form_panel` |
| `settings_panel` / `settings_panel_body` | `section` / `card_body` |
| `form` | `form` |
| `settings_actions` / `form_actions` | `form_actions` of the settings / form page (`.pages.settings` / `.pages.form`) |
| `section_title` / `section_lead` | `card_title` / `card_intro` |
| `button_primary` / `button_secondary` / `confirm_delete` | `btn_primary` / `btn_secondary` / `btn_danger` |
| `pagination` / `pagination_summary` / `pagination_button` | `pagination` / `pagination_info` / `pagination_link` |
| `tab_list`, `form_panel_body`, `confirm_cancel`, `pagination_actions` | — (no slot; ignored) |

```yaml
# Before (1.13–1.15)
nowo_cookie_consent:
    web_ui:
        layout_template: 'admin/_cookie_consent_layout.html.twig'
        css_framework: tailwind
        chrome:
            classes: { table: 'data-table', button_primary: 'btn-primary' }
            attributes: { table_panel: { data-testid: cookie-consent-definitions-table } }

# After (1.16)
nowo_ui_kit:
    css_framework: tailwind
    layouts: { admin: 'layouts/admin.html.twig' }   # renders the nowo_ui_* blocks
    theme:
        bundles:
            NowoCookieConsentBundle:
                classes:
                    table: { value: 'data-table', framework_defaults: false }
                    btn_primary: { value: 'btn-primary', framework_defaults: false }
                attributes: { table_wrap: { data-testid: cookie-consent-definitions-table } }
```


## To 1.15.2

From **1.15.1** — fix container compilation regression from 1.15.1 (admin access subscriber class)

```bash
composer update nowo-tech/cookie-consent-bundle
```

- Fixes a regression in 1.15.1: any application failed to compile the container ("Invalid service ...CookieConsentAdminAccessSubscriber: the class is not set"). Upgrade from 1.15.1 immediately; no configuration changes.
- **Admin form markup:** the `cookie_consent` FormKit profile seeded by the bundle no longer adds `form-control`, `form-select`, `form-check-input` or a `.form-check` row class (the admin form theme, `bootstrap_5_layout.html.twig` by default, adds them). If you set `web_ui.chrome.templates.form_theme: ~` and your own form theme relied on those classes, add them in your theme or define your own `nowo_form_kit.profiles.cookie_consent`. Admin fields no longer render `{form}.{field}.placeholder` translation keys as placeholders.

## To 1.15.1

From **1.15.0** — security - admin access subscriber now registered in real applications.

```bash
composer update nowo-tech/cookie-consent-bundle
```

- **Behaviour change (admin security):** the bundle admin access subscriber is now really registered. Admin routes enforce `security.access_roles` (default `ROLE_ADMIN`) or your custom `security.access_checker` (users without the role get 403), and answer 404 when `web_ui.enabled: false`. Previously only the host firewall / `access_control` protected them. Check that admin users have one of the configured roles; demos without SecurityBundle keep working with `security.allow_unauthenticated: true`.

## To 1.15.0

From **1.14.0** — complete ca/pl translations, dead config deprecated, full configuration reference, Symfony >=7.4, ext-dom.

```bash
composer update nowo-tech/cookie-consent-bundle
```

- Requires Symfony `^7.4 || ^8.0` and `ext-dom`.
- `doctrine.connection` and `web_ui.path_prefix` are deprecated and ignored (they never had an effect): remove them from `config/packages/nowo_cookie_consent.yaml` to silence the deprecation. To serve the admin under another path, import the routes with a `prefix` and protect that path with `access_control`.

## To 1.14.0

From **1.13.1** — security: delete CSRF fix, admin fail-closed, allowlist bubble icon, IPv6 anonymization.

```bash
composer update nowo-tech/cookie-consent-bundle
```

- `preferences_bubble_icon` must now be well-formed XML (inline SVG or simple span-style markup). `style` and unknown attributes are dropped, unsafe YAML values fail container compilation, and unsafe stored icons render the default icon.
- With `web_ui.enabled: false` the admin routes now return 404 (the access subscriber is always registered).
- IPv6 addresses in the consent log are now truncated to the first 48 bits.

## To 1.13.1

From **1.13.0** — dependency refresh and repository maintenance.

```bash
composer update nowo-tech/cookie-consent-bundle
```

- No breaking changes. No application upgrade steps.

## To 1.13.0

From **1.12.0** — admin chrome hooks (class map, attribute map, partial paths, Twig blocks).

```bash
composer update nowo-tech/cookie-consent-bundle
php bin/console cache:clear
```

- **No breaking changes.** Without `web_ui.chrome` the admin pages render the same markup as 1.12.0 (only previously untranslated strings now show text instead of raw keys, and the pagination `aria-label` is translated).
- **Shrink admin forks:** if you override `admin/*.html.twig` only to repaint chrome (tabs, panels, buttons, table classes, form theme, pagination, extra stylesheet), delete the override and configure `nowo_cookie_consent.web_ui.chrome` instead — see [USAGE.md — Customising the admin chrome](USAGE.md#customising-the-admin-chrome).
- **Keep a fork but make it thin:** `{% extends '@!NowoCookieConsentBundle/admin/<page>.html.twig' %}` and override only the `nowo_cookie_consent_admin_*` block you need.
- **Existing full overrides keep working.** If you keep one, port the vendor behaviour you rely on (delete forms come from the controller's `delete_forms`; row "Edit" label now passes `%name%`).
- Custom `tabs` / `pagination` partials receive the contracts documented in USAGE.

## To 1.12.0

From **1.11.2** — schema-ready memo per worker; CSP nonce on bundle scripts/styles.

```bash
composer update nowo-tech/cookie-consent-bundle
php bin/console cache:clear
```

- **No breaking changes.** Optional new option `schema_ready_cache_ttl` (seconds, default `60`; `0` restores the previous probe-every-request behaviour).
- **Remove host shims:** apps that pre-set `_nowo_cookie_consent_schema_ready` from their own per-worker memo subscriber (e.g. `CookieConsentSchemaReadyMemoSubscriber`) can delete it; the bundle now does the same memo internally.
- **CSP:** if your app sets the request attribute `csp_nonce` (shared nowo-tech convention), bundle `<script>` tags render with `nonce="…"` automatically, and the JS-injected modal `<style>` copies the loader script nonce. Without `csp_nonce` the markup is unchanged.
- **Template overrides:** if you override `cookie_consent.html.twig`, `cookie_consent.tailwind.html.twig`, `_diagnostics_script.html.twig`, `admin/*.html.twig` or `cookie_consent_preferences_bubble.html.twig`, port the `_csp_nonce` lines. The bubble now renders `data-nowo-bubble-accent` instead of `style="--nowo-cc-bubble-accent: …"`; an overridden template that keeps the inline `style` still works (but is blocked by strict `style-src-attr`).

## To 1.11.2

From **1.11.1** — dependency refresh.

```bash
composer update nowo-tech/cookie-consent-bundle
```

- No breaking changes. No application upgrade steps.

## To 1.11.1

From **1.11.0** — consent cookies always `Secure` + `SameSite=Lax` (TLS-terminated / FrankenPHP).

```bash
composer update nowo-tech/cookie-consent-bundle
php bin/console cache:clear
```

- No config changes. After upgrade, clear site cookies once if an old non-`Secure` consent cookie lingered.
- Plain `http://` local hosts will not persist consent cookies (by design); use HTTPS locally or accept the banner on HTTP.

## To 1.11.0

From **1.10.1** — `access_roles` fail-closed; Doctrine `SortDirection`; demo e2e.

```bash
composer update nowo-tech/cookie-consent-bundle
php bin/console cache:clear
```

- Empty `security.access_roles` denies admin routes. Set roles / custom checker, or demo-only `allow_unauthenticated`.
- Ensure `doctrine/orm` is `^3.7` (SortDirection).

## To 1.10.1

From **1.10.0** — REQ-CS-008 Igor FrankenPHP worker audit (igor-php require-dev, igor.json, make igor).

```bash
composer update nowo-tech/cookie-consent-bundle
php bin/console cache:clear
```

- No application upgrade steps for require-dev Igor tooling (REQ-CS-008). Consumers do not pull `igor-php/igor-php` transitively.

This guide provides step-by-step instructions for upgrading Cookie Consent Bundle between versions.

## To 1.10.0 (FrankenPHP worker remediation)

The bundle is safe in FrankenPHP worker mode even when the kernel is **not** reset between requests
(`reset_kernel false` / scenario B). See [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md).
**No required application changes** unless you override the bundle templates or services listed below.

```bash
composer update nowo-tech/cookie-consent-bundle
php bin/console cache:clear
php bin/console assets:install
```

1. **Overridden modal templates:** database texts (`use_database_config: true`) are no longer injected into the
   translator. If you copied `cookie_consent.html.twig` / `cookie_consent.tailwind.html.twig`, replace
   `'nowo_cookie_consent.title'|trans({}, 'NowoCookieConsentBundle')` (and `.intro`, `.read_more`, `.privacy_route`) with
   `nowo_cookie_consent_trans('nowo_cookie_consent.title', display_config ?? null)`. Plain `|trans` keeps returning the
   YAML translations.
2. **Direct instantiation:** `CookieConsentModalRenderer` and `CookieConsentConfigTranslationSubscriber` (both `final`)
   no longer take a `TranslatorInterface` argument. Container users are not affected.
3. **`CookieLogger` subclasses:** the constructor gained an optional 5th argument `?ManagerRegistry $managerRegistry`;
   the request is read in `log()` instead of the constructor. Do not store the request, user or consent data in
   properties of subclasses of `CookieLogger`, `CookieChecker` or `CookieConsentFormSubscriber`.
4. `/cookie-consent/config?locale=` now falls back to an enabled locale (primary subtag, then `default_locale`)
   instead of accepting any value.
5. **Host Doctrine under scenario B:** clearing the application's EntityManager identity map between requests remains
   the host application's responsibility (the bundle detaches its own consent log rows after flush).

## From 1.9.6 to 1.9.7

FrankenPHP worker memoization now clears via `ResetInterface`. **No required application changes.**

```bash
composer update nowo-tech/cookie-consent-bundle
php bin/console cache:clear
```

### Notes

1. Host shims that call `clearRuntimeCache()` between worker requests can be removed.
2. Behaviour for non-worker / classic PHP-FPM is unchanged.

## From 1.9.5 to 1.9.6

PHP minimum is **8.2**. **No API or configuration changes** for integrators.

```bash
composer update nowo-tech/cookie-consent-bundle
php bin/console cache:clear
```

## General upgrade process

1. **Backup** your `config/packages/nowo_cookie_consent.yaml` (and any Doctrine config entities if you use `use_database_config`)
2. **Review** [CHANGELOG.md](CHANGELOG.md) for breaking changes
3. **Update**: `composer update nowo-tech/cookie-consent-bundle`
4. **Clear cache**: `php bin/console cache:clear`
5. **Rebuild assets** if you ship the bundled JS: `php bin/console assets:install`
6. **Test** the consent modal and logging in your environments

## To 1.9.5

Optional performance: replace ESI/sub-request embeds with the in-process Twig helper:

```twig
{% if nowo_cookie_consent_should_render() %}
    {{ nowo_cookie_consent_render() }}
{% endif %}
```

`{{ render(path('nowo_cookie_consent.show')) }}` remains supported.

```bash
composer update nowo-tech/cookie-consent-bundle
php bin/console cache:clear
```

## To 1.9.4

No application upgrade steps (dev/demo frontend Dependabot bumps only).

```bash
composer update nowo-tech/cookie-consent-bundle
```

## To 1.9.3

No application upgrade steps.

```bash
composer update nowo-tech/cookie-consent-bundle
```

## To 1.9.2

No application upgrade steps. Consent profile Doctrine lookups are memoized per request; admin profile writes clear runtime caches automatically.

```bash
composer update nowo-tech/cookie-consent-bundle
php bin/console cache:clear
```

## To 1.9.1

No application upgrade steps. **Demos only:** Hot Reload Bundle `^1.4` (FrankenPHP Mercure/`hot_reload`, `dev`/`test`).

```bash
composer update nowo-tech/cookie-consent-bundle
php bin/console cache:clear
```

## To 1.9.0

```bash
composer update nowo-tech/cookie-consent-bundle
php bin/console assets:install
php bin/console cache:clear
```

### CSP / standalone CSS (recommended for style-src nonces)

Browsers ignore CSS injected via `<style>` when CSP uses **style nonces** (and does not allow `'unsafe-inline'`). From 1.9.0, link the shipped stylesheet **before** the modal script and mark it so JS skips injection:

```twig
<link rel="stylesheet"
      href="{{ asset('nowo-cookie-consent.css', 'nowo_cookie_consent') }}"
      data-nowo-cookie-consent-css>
```

Equivalents: `data-nowo-cookie-consent-external-css="true"` on `<html>`, or `data-nowo-external-css="true"` on `#cookieconsent`.

Hosts that previously mirrored modal chrome in application SCSS (category cards, overlay position, buttons) can drop that fork once the kit CSS is linked and tokens are remapped with `--nowo-cc-*`.

### Recommended layout defaults (DB profiles)

New `CookieConsentConfig` rows default to **box / wide / bottom / left** with **equal-weight buttons**. Existing profiles keep stored values. Align admin settings (or seed fixtures) if you want the same corner on older installs:

| Field | Recommended |
|-------|-------------|
| Consent modal layout | `box` |
| Consent modal variant | `wide` |
| Consent modal position | `bottom` + `left` |
| Equal-weight buttons | enabled |
| Preferences modal | same corner + equal-weight |

No Doctrine migration is required for existing databases (column defaults only affect newly inserted rows without explicit values).

## To 1.8.0

```bash
composer update nowo-tech/cookie-consent-bundle
php bin/console cache:clear
```

No configuration change required. After SiteBackup reports the MySQL schema exists, Cookie Consent probes `dashboard_cookie_config` and sets `_nowo_cookie_consent_schema_ready=false` until migrations create that table — so `/setup` does not 500 mid-wizard.

## To 1.7.0

```bash
composer update nowo-tech/cookie-consent-bundle
php bin/console cache:clear
```

### Site Backup interoperability (cold start)

When [Site Backup Bundle](https://github.com/nowo-tech/SiteBackupBundle) (or another bootstrap layer) runs before the application schema exists, it can set request attribute `_nowo_site_backup_schema_exists` to `false`. Cookie Consent Bundle then skips Doctrine-backed config resolution, response form handling, and returns `false` from `nowo_cookie_consent_should_render()` on the main request.

Hosts may also set `_nowo_cookie_consent_schema_ready: false` on a request to force the same skip without Site Backup.

Attribute names are public constants on `Nowo\CookieConsentBundle\Http\ColdStartRequestAttributes`. No configuration change is required when Site Backup already publishes the shared attribute.

Recommended Twig guard during cold start (unchanged pattern):

```twig
{% if nowo_cookie_consent_should_render() %}
    {{ render(path('nowo_cookie_consent.show')) }}
{% endif %}
```

## To 1.6.3

```bash
composer update nowo-tech/cookie-consent-bundle
php bin/console cache:clear
```

### Optional: public-only consent fragment

Prefer a whitelist over a growing deny list:

```yaml
nowo_cookie_consent:
    render_routes:
        - home
        - home_locale
        - guest_*
        - legal_*
        - nowo_auth_kit_*
        - health_*
        - nowo_pwa_*
    skip_render_routes: []
```

## To 1.6.2

```bash
composer update nowo-tech/cookie-consent-bundle
php bin/console cache:clear
```

No configuration changes required. Consent modal inventory tables now reuse a single Doctrine load per request (form `finishView` + provider memoization).

## To 1.6.1

```bash
composer update nowo-tech/cookie-consent-bundle
php bin/console cache:clear
```

### Optional: skip consent fragment on dashboards

Set `skip_render_routes` (route name patterns, `fnmatch` wildcards) so authenticated shells do not load the consent modal markup or hit inventory Doctrine queries:

```yaml
nowo_cookie_consent:
    skip_render_routes:
        - staff_*
        - admin_*
```

Hosts may also wrap the embed:

```twig
{% if nowo_cookie_consent_should_render() %}
    {{ render(path('nowo_cookie_consent.show')) }}
{% endif %}
```

## To 1.6.0

```bash
composer update nowo-tech/cookie-consent-bundle
php bin/console cache:clear
php bin/console assets:install
```

### UiKit composition (REQ-UI-001-kit)

Admin `nowo-ui.css` now comes from **UiKitBundle** (`asset('css/nowo-ui.css', 'nowo_ui_kit')`). Hosts that linked the old `nowo_cookie_consent` CSS path for admin chrome must switch the asset package name (or rely on `admin/base.html.twig`). Modal assets remain on `nowo_cookie_consent`. This package requires `nowo-tech/ui-kit-bundle` `^1.4`. Admin templates import `@NowoUiKitBundle/macros/ui.html.twig` for `ui.flash` / `ui.btn` / confirm dialogs (`nowo-ui-confirm.js`); remove duplicate flash markup and native `confirm()` from forked child templates.

### Twig Extra Bundle (REQ-TWIG-004)

Hosts that render this bundle's Twig templates must install:

```bash
composer require twig/extra-bundle twig/string-extra
```

and enable `Twig\Extra\TwigExtraBundle\TwigExtraBundle`. Flex recipes usually register it automatically.

### FormKitBundle

Admin form types use FormKit `FormOptionsTrait` + profile `cookie_consent`. Require `nowo-tech/form-kit-bundle` `^2.0` (pulled in by this package).

### Twig-CS-Fixer (maintainers)

Package maintainers: `composer twig:lint` / `composer twig:fix` use `.twig-cs-fixer.php` over `src/` (and `templates/` when present).

**No public PHP API breaking changes** beyond the admin CSS asset package path and UiKit dependency.

## To 1.5.2

```bash
composer update nowo-tech/cookie-consent-bundle
php bin/console cache:clear
```

Patch release for **demo / CI maintainers** (REQ-MAKE-009 / REQ-TEST-011): FrankenPHP demos resolve Twig Inspector from Packagist (`*`) instead of mounting a sibling `TwigInspectorBundle` path. Standalone GitHub Actions checkouts can run `make demo-smoke` without a monorepo layout.

**No configuration or public API breaking changes** for bundle consumers. Host apps that do not run the bundled demos need no action beyond a normal `composer update` if they track this package.

### Breaking changes

None.

## To 1.5.1

```bash
composer update nowo-tech/cookie-consent-bundle
php bin/console cache:clear
```

Patch for host admin layouts (REQ-UI-001):

- `admin/base.html.twig` now calls `{{ parent() }}` on **both** `stylesheets` and `javascripts`, so host JS is not dropped when `web_ui.layout_template` points at your project layout.
- Legacy BC aliases `admin/config/layout.html.twig` and `admin/cookie_definition/layout.html.twig` extend `admin/base.html.twig` (not the demo root layout).
- If you forked `admin/base.html.twig` or those BC aliases, rebase or drop the fork so stacking still applies.

**No public PHP API breaking changes.**

## To 1.5.0

```bash
composer update nowo-tech/cookie-consent-bundle
php bin/console cache:clear
php bin/console assets:install
```

Settings admin is split into **route-based sections**, each with its own FormType:

- `GET /cookie-consent-config/{id}/settings` still works and redirects to `/settings/profile` (`nowo_cookie_consent_config_settings_edit`).
- Each section is edited at `/cookie-consent-config/{id}/settings/{section}` (`nowo_cookie_consent_config_settings_section`).
- Use `CookieConsentConfigSettingsSection::formType()` to resolve the FormType class (e.g. `CookieConsentConfigProfileSettingsType`, `…BehaviorSettingsType`, …).
- `CookieConsentConfigSettingsType` remains as a **deprecated** alias of `CookieConsentConfigFullSettingsType` (all sections on one form) for demos / legacy single-page editors.

Hosts that forked `admin/config/settings.html.twig` should rebase on the new single-card + tab partials, or drop the fork and restyle `.nowo-ui-tabs` / `.nowo-ui-form-wrap` from the host layout.

### Breaking changes

- Bundle admin no longer submits every settings field in one POST (fields are per section / FormType). Deep links that assumed a single scrollable page should point at the relevant `{section}` slug instead.
- There is no `section` form option; pick the FormType via the enum instead.

## To 1.4.8

```bash
composer update nowo-tech/cookie-consent-bundle
php bin/console cache:clear
php bin/console assets:install
```

Patch release: the bundled `nowo-consent-modal.js` prepares Symfony SameOrigin CSRF (double-submit cookie + `csrf-token` header) before XHR, because native `submit` never fires on modal buttons.

**Recommended:** set `nowo_cookie_consent.csrf_protection: true` (the default). Hosts that disabled CSRF only to make XHR work can re-enable it after upgrading. **No public API breaking changes**.

### Breaking changes

None.

## To 1.4.5

```bash
composer update nowo-tech/cookie-consent-bundle
php bin/console cache:clear
php bin/console assets:install
```

Patch release: Twig app overrides via `prependPath`, and Tailwind modal/form theme colors driven by `--nowo-cc-*` instead of indigo/slate utility classes. **No configuration or public API breaking changes**.

Optional cleanup for hosts that worked around earlier gaps:

- Remove a manual `twig.paths` entry that only registered `templates/bundles/NowoCookieConsentBundle` for the `NowoCookieConsentBundle` namespace.
- Drop a full-file override of `form/cookie_consent_theme.tailwind.html.twig` if it only stripped Tailwind button color utilities.

### Breaking changes

None.

## To 1.4.4

```bash
composer update nowo-tech/cookie-consent-bundle
php bin/console cache:clear
```

Patch release: development and demo Composer locks refreshed to Symfony **7.4.15** / **8.1.2**. **No configuration or public API breaking changes** for bundle consumers.

### Breaking changes

None.

## To 1.4.3

```bash
composer update nowo-tech/cookie-consent-bundle
php bin/console cache:clear
```

Patch release: admin pages extend `admin/base.html.twig` (still resolves `web_ui.layout_template`), Twig override docs consolidated under [USAGE.md](USAGE.md) (REQ-TWIG-001), and README documentation links reordered. **No configuration or public API breaking changes** for bundle consumers.

If you fully override admin page templates in the app, keep extending `@NowoCookieConsentBundle/admin/base.html.twig` (or your own layout) so `web_ui.layout_template` continues to apply. Prefer `web_ui.layout_template` over copying entire admin pages.

### Breaking changes

None.

## To 1.4.2

```bash
composer update nowo-tech/cookie-consent-bundle
php bin/console cache:clear
```

Patch release: maintainer/demo Makefile Compose V2 preference with V1 fallback (REQ-MAKE-010), shell-wrapped Compose helpers for WSL Make compatibility, and optional monorepo `update-deps` includes for standalone CI checkouts (REQ-MAKE-009). **No configuration or public API breaking changes** for bundle consumers.

### Breaking changes

None.

## To 1.4.1

```bash
composer update nowo-tech/cookie-consent-bundle
php bin/console cache:clear
```

Patch release: documentation TOCs and `docs/COVERAGE.md`, Spec Kit inventory updates, `make check-open-prs` / `make coverage-check` in `release-check`, PHPUnit coverage for admin security/DI paths, Symfony deprecation gate (`max[direct]=0`), demo lock refresh for `psr/clock`, and a Tailwind modal fix for granular cookie fields. Removed unused DI `_defaults.bind` `$httpOnly` (still passed explicitly to `CookieHandler`). **No configuration or public API breaking changes** for bundle consumers.

Maintainers: prefer `make coverage-check` (fail-under 99% lines) over bare `test-coverage` before tagging. Rebuild demos after lock refresh so `psr/clock` is present.

### Breaking changes

None.

## To 1.4.0

```bash
composer update nowo-tech/cookie-consent-bundle
php bin/console cache:clear
```

Minor release: admin Web UI + security access checker (`web_ui.*`, `security.*`), paginated cookie definition lists, PSR Clock on cookie write/log paths, and typed array config nodes.

### Configuration

New optional keys (defaults preserve previous behavior for public consent modal):

```yaml
nowo_cookie_consent:
    web_ui:
        enabled: true
        path_prefix: /cookie-consent-config
        layout_template: '@NowoCookieConsentBundle/admin/layout.html.twig'
        css_framework: bootstrap5
        icon_set: bootstrap-icons
        list_page_size: 20
    security:
        access_roles: [ROLE_ADMIN]
        allow_unauthenticated: false
```

Production hosts **must** keep `security.allow_unauthenticated: false` and protect `web_ui.path_prefix` with `security.access_control`. Demos may set `allow_unauthenticated: true`.

Admin Twig templates now extend `nowo_cookie_consent_layout_template`. Legacy `admin/cookie_definition/layout.html.twig` and `admin/config/layout.html.twig` remain as BC aliases (they now extend `admin/base.html.twig` so host `layout_template` stacking still applies).

If you construct `CookieHandler` or `CookieLogger` manually, inject `Psr\Clock\ClockInterface` (and optionally `Psr\Log\LoggerInterface` for the logger).

### Breaking changes

None for typical YAML + autowired consumers. Manual service construction of `CookieHandler` / `CookieLogger` requires the new constructor arguments.

## To 1.3.6

```bash
composer update nowo-tech/cookie-consent-bundle
php bin/console cache:clear
```

Patch release: Nowo bundle standards compliance (FrankenPHP Friendly banner, English doc samples, demo Makefile targets, PHP coverage ≥99.95%). **No configuration or public API breaking changes** for bundle consumers.

Demo maintainers: `demo/Makefile` now exposes aggregate `up` / `down` / `update-bundle` (`DEMO=symfony8` by default); each demo also has `restart`.

### Breaking changes

None.

## To 1.3.5

```bash
composer update nowo-tech/cookie-consent-bundle
php bin/console cache:clear
```

Patch release: PHPStan FrankenPHP rules (REQ-CS-005), empty baseline, type/DI hygiene, and CS Fixer import symbols. **No configuration or public API breaking changes** for bundle consumers.

If you replace `CookieConsentFormSubscriber` via a compiler pass / `setClass`, `$useLogger` is now an explicit service argument (same parameter `%nowo_cookie_consent.use_logger%`); no app YAML change is required when using the default definition.

### Breaking changes

None.

## To 1.3.4

```bash
composer update nowo-tech/cookie-consent-bundle
php bin/console cache:clear
```

Patch release: frontend/CI dependency bumps and demo FrankenPHP `FRANKENPHP_MODE` support. **No configuration, API, or runtime changes** for bundle consumers.

Demo maintainers: set `FRANKENPHP_MODE=classic` or `worker` in `.env` (see [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md)); recreate containers after changing it.

### Breaking changes

None.

## To 1.3.3

```bash
composer update nowo-tech/cookie-consent-bundle
php bin/console cache:clear
```

Patch release: contributor Code of Conduct, REQ-GIT-001 git hygiene (hooks/CI), and expanded PHPUnit coverage. **No configuration, API, or runtime changes** for bundle consumers.

### Breaking changes

None.

## To 1.3.2

```bash
composer update nowo-tech/cookie-consent-bundle
php bin/console cache:clear
php bin/console assets:install
```

Patch release: registers the `nowo_cookie_consent` Symfony asset package and loads `nowo-consent-modal.js` through the asset helper. **No configuration or API breaking changes** for bundle consumers.

If you override `cookie_consent.html.twig` or `cookie_consent.tailwind.html.twig` and copied the old hardcoded script tag, update it to:

```twig
<script src="{{ asset('nowo-consent-modal.js', 'nowo_cookie_consent') }}" defer></script>
```

### Breaking changes

None.

## To 1.3.1

```bash
composer update nowo-tech/cookie-consent-bundle
php bin/console cache:clear
```

Patch release: GitHub Spec Kit maintainer tooling, demo `update-deps` fix, and dev lock sync. **No configuration, API, or runtime changes** for bundle consumers.

### Breaking changes

None.

## To 1.3.0

```bash
composer update nowo-tech/cookie-consent-bundle
php bin/console cache:clear
```

**Breaking:** entity table names changed from `nowo_cookie_consent_*` to `dashboard_cookie_*`.

1. Rename existing tables (or drop and recreate in dev). See [CHANGELOG.md](CHANGELOG.md) for the full mapping.
2. Move `table_prefix` to `doctrine.table_prefix` in YAML (root key is deprecated but still supported as fallback).
3. Regenerate or adjust application migrations if you manage bundle tables manually.
4. Clear cache: `php bin/console cache:clear`.

When `use_logger` or `use_database_config` is enabled, ensure Doctrine migrations for bundle tables are applied after upgrading. Table names respect `doctrine.table_prefix` (see [CONFIGURATION.md](CONFIGURATION.md)).

### Breaking changes

- Doctrine table names: `nowo_cookie_consent_*` → `dashboard_cookie_*`
- Root `table_prefix` deprecated in favor of `doctrine.table_prefix` (fallback retained)

No frontend asset or Twig API changes.

## UI theme changes

If you switch `ui_theme` from `bootstrap` to `tailwind` (or vice versa):

1. Update `nowo_cookie_consent.ui_theme` in YAML
2. Load the matching CSS framework in your layout (Bootstrap 5 or Tailwind)
3. Override the correct Twig templates (see the theme table in [CONFIGURATION.md](CONFIGURATION.md))

## To 1.2.0

```bash
composer update nowo-tech/cookie-consent-bundle
php bin/console cache:clear
php bin/console assets:install
```

Minor release: page interaction overlay, bundle profile settings admin, and preferences bubble customization. **No breaking changes** for existing integrations.

### New optional configuration

| Option | Default | Purpose |
| --- | --- | --- |
| `disable_page_interaction` | `false` | Full-page overlay and scroll lock until consent |
| `preferences_bubble_border_color` | `null` | Hex color for bubble outline and default SVG icon |
| `preferences_bubble_icon` | `null` | Custom HTML/SVG for bubble icon; empty = default cookie SVG |

When `use_database_config: true`, new columns on `CookieConsentConfig` include `disable_page_interaction` (if not already present), `preferences_bubble_border_color`, and `preferences_bubble_icon`. Apply Doctrine migrations or `schema:update`.

### Profile settings admin

Import bundle routes to expose `/cookie-consent-config/{id}/settings` (`CookieConsentConfigSettingsAdminController`). The demo already wires this under its admin shell. See [USAGE.md](USAGE.md).

### Frontend assets

Reinstall public assets — bubble styling and modal positioning fixes require the updated CSS bundled in `nowo-consent-modal.js` build output:

```bash
php bin/console assets:install
```

### Breaking changes

None.

## To 1.1.1

```bash
composer update nowo-tech/cookie-consent-bundle
php bin/console cache:clear
php bin/console assets:install
```

Patch release: documentation, security write-up, Nowo bundle standards compliance, and PHPDoc/test tooling. **No configuration or API changes** for consumers.

### Breaking changes

None.

## To 1.1.0

```bash
composer update nowo-tech/cookie-consent-bundle
php bin/console cache:clear
php bin/console assets:install
```

### New optional configuration

All new options default to off or safe values — existing integrations keep working without YAML changes.

| Option | Default | Purpose |
| --- | --- | --- |
| `use_cookie_inventory` | `false` | Show cookie table in preferences modal |
| `cookie_inventory` | `[]` | Static YAML fallback when DB has no rows |
| `preferences_bubble_enabled` | `false` | Floating button to reopen preferences |
| `preferences_bubble_position` | `bottom-right` | Bubble corner |
| `granular_cookie_selection` | `false` | Per-cookie toggles (profile flag when using DB config) |

When `use_database_config: true`, new columns on `CookieConsentConfig` include `granular_cookie_selection`, `preferences_bubble_enabled`, and `preferences_bubble_position`. Apply Doctrine migrations or `schema:update`.

### Cookie inventory entities

If you store definitions in the database, create:

- `{prefix}dashboard_cookie_definition` (includes `allowed_by_default`)
- `{prefix}dashboard_cookie_definition_translation`

Register admin routes for `CookieDefinitionAdminController` in your application, or implement your own CRUD using `CookieDefinitionType`.

### Frontend assets

Rebuild or reinstall public assets after upgrading — granular toggles and the preferences close button require the updated `nowo-consent-modal.js`:

```bash
# Consumers
php bin/console assets:install

# Bundle maintainers
make assets
```

### Twig embed change (recommended)

When the preferences bubble is enabled, keep the modal in the DOM after consent:

```twig
{% if nowo_cookie_consent_should_embed_modal() %}
    {{ render(path('nowo_cookie_consent.show_if_not_set')) }}
{% endif %}
```

### Breaking changes

None.

## To 1.0.0 (initial release)

This is the first stable release. Install or require the package:

```bash
composer require nowo-tech/cookie-consent-bundle:^1.0
```

### Requirements

- PHP `>=8.1` (<8.6). Symfony **8.0** and **8.1** require **PHP 8.4+**.
- Symfony **7.4**, **8.0**, or **8.1** (minimum tested minors). The bundle also resolves on Symfony 6.x and 7.0–7.3 when `composer.json` constraints allow.
- Doctrine ORM when `use_logger: true` or `use_database_config: true`

### Enable and configure

1. Register the bundle (or use the Symfony Flex recipe — see [Installation](INSTALLATION.md)).
2. Import routes in `config/routes/nowo_cookie_consent.yaml`.
3. Run `php bin/console assets:install`.
4. Create the consent log table when `use_logger: true`.

See [Installation](INSTALLATION.md) and [Configuration](CONFIGURATION.md).

### Breaking changes

None — there is no prior stable release.

## Future versions

For upgrade instructions between versions, see the [Changelog](CHANGELOG.md).

## Getting help

- [Usage](USAGE.md) — integration examples
- [Configuration](CONFIGURATION.md) — all options
- [GitHub Issues](https://github.com/nowo-tech/CookieConsentBundle/issues)
