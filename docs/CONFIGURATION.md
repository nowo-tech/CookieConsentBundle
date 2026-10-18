# Configuration

## Table of contents

- [Configuration reference](#configuration-reference)
- [Table prefix](#table-prefix)
- [Locale detection](#locale-detection)
  - [UI theme](#ui-theme)
- [Translations](#translations)
- [Database configuration](#database-configuration)
- [Cookie inventory](#cookie-inventory)
- [Granular cookie selection](#granular-cookie-selection)
- [Page interaction overlay](#page-interaction-overlay)
- [Preferences bubble](#preferences-bubble)
- [Config API (GET)](#config-api-get)
- [Admin Web UI](#admin-web-ui)
- [Admin security](#admin-security)
- [Twig overrides](#twig-overrides)
- [Twig helpers](#twig-helpers)
- [Cold start / Site Backup](#cold-start--site-backup)

Extension alias: `nowo_cookie_consent`

```yaml
# config/packages/nowo_cookie_consent.yaml
nowo_cookie_consent:
    doctrine:
        table_prefix: ''

    # Deprecated: use doctrine.table_prefix instead
    # table_prefix: ''

    # Categories shown in the modal (required is always shown)
    categories:
        - analytics
        - marketing
        - preferences

    # Persist user choices to CookieConsentLog entity
    use_logger: true

    # Load modal texts and display settings from Doctrine entities
    use_database_config: false

    # Show cookie definitions (name, category, duration, provider, purpose) in modal and legal pages
    use_cookie_inventory: false

    # Seconds a positive "config table exists" probe is memoized per worker (FrankenPHP/RoadRunner).
    # 0 = probe information_schema on every main request (pre-1.12 behaviour).
    schema_ready_cache_ttl: 60

    # Floating cookie icon button to reopen preferences (any corner)
    preferences_bubble_enabled: false
    preferences_bubble_position: bottom-right   # bottom-left | top-right | top-left
    # preferences_bubble_border_color: null     # hex, e.g. #30363c — bubble outline and SVG icon color
    # preferences_bubble_icon: null             # custom HTML/SVG; empty = default cookie SVG

    # Fetch modal settings from GET /cookie-consent/config in the browser
    fetch_config_via_api: false

    # HttpOnly flag on consent cookies
    http_only: true

    # Optional route name used as form action
    form_action: null

    # Keep enabled. Modal JS double-submits SameOrigin CSRF for XHR (no native submit).
    csrf_protection: true

    # Routes where the modal must not auto-open
    disabled_routes:
        - privacy
        - imprint

    # Route name patterns where the consent fragment is not rendered at all
    # (empty response; no Doctrine inventory/config work). Wildcards via fnmatch.
    # skip_render_routes:
    #     - staff_*
    #     - admin_*

    # When non-empty: render ONLY on these public route patterns (whitelist)
    # render_routes:
    #     - home
    #     - home_locale
    #     - guest_*
    #     - legal_*
    #     - nowo_auth_kit_*
    #     - health_*
    #     - nowo_pwa_*

    # Locale detection for the consent modal
    default_locale: en
    enabled_locales:
        - en
        - es
        - it
        - fr
        - de
        - pt
        - nl
        - pl
        - ca
    ui_theme: bootstrap   # bootstrap (default) or tailwind
    detect_locale_from_accept_language: true

    # Full-page overlay and scroll lock until the user chooses an option
    disable_page_interaction: false
```

## Configuration reference

Every key accepted under `nowo_cookie_consent` (run `php bin/console config:dump-reference nowo_cookie_consent` for the live tree). When `use_database_config: true`, the per-profile `CookieConsentConfig` values (admin settings) override the YAML defaults marked *(DB override)*.

### Storage and Doctrine

| Key | Default | Description |
|-----|---------|-------------|
| `doctrine.table_prefix` | `''` | Prefix for every bundle table (see [Table prefix](#table-prefix)) |
| `doctrine.connection` | `default` | **Deprecated since 1.15, ignored.** The entities are always mapped on the default entity manager / connection; the option will be removed in 2.0 |
| `table_prefix` | `''` | **Deprecated since 1.3.** Use `doctrine.table_prefix` |
| `use_logger` | `true` | Persist consent choices to `CookieConsentLog` |
| `use_database_config` | `false` | Load modal copy and display settings from `CookieConsentConfig` profiles (see [Database configuration](#database-configuration)) |
| `schema_ready_cache_ttl` | `60` | Seconds a positive "config table exists" probe is memoized per worker; `0` probes every main request |
| `use_cookie_inventory` | `false` | Expose cookie definitions in the preferences modal and legal pages |
| `cookie_inventory` | `[]` | Static cookie definitions used when the active profile has no `CookieDefinition` rows (see [Cookie inventory](#cookie-inventory)) |

### Consent form and requests

| Key | Default | Description |
|-----|---------|-------------|
| `categories` | `[analytics, marketing, preferences]` | Optional categories shown in the modal (`required` is always shown) |
| `http_only` | `true` | HttpOnly flag on consent cookies |
| `form_action` | `null` | Optional route name used as the consent form action |
| `csrf_protection` | `true` | CSRF protection on the consent form (keep enabled) |
| `fetch_config_via_api` | `false` | Expose `GET /cookie-consent/config` and let the front-end load settings via `fetch()` (see [Config API](#config-api-get)) |

### Where the modal renders and opens

| Key | Default | Description |
|-----|---------|-------------|
| `disabled_routes` | `[privacy, imprint]` | Route names where the modal never auto-opens (always wins over targeting) |
| `skip_render_routes` | `[]` | Route patterns (fnmatch) where the consent fragment is not rendered at all (empty response, no inventory/config queries) |
| `render_routes` | `[]` | When non-empty, the fragment renders **only** on these route patterns; `skip_render_routes` still wins |
| `route_targeting_mode` | `all` | *(DB override)* Where the modal opens automatically: `all` (every route except `disabled_routes`), `only` (only routes matching `target_routes`), `except` (every route except those matching `target_routes`) |
| `target_routes` | `[]` | *(DB override)* Route names or fnmatch patterns (`admin_*`) used by `route_targeting_mode: only` / `except`; ignored with `all` |

```yaml
nowo_cookie_consent:
    route_targeting_mode: except
    target_routes:
        - admin_*
        - staff_dashboard
```

### Locale

| Key | Default | Description |
|-----|---------|-------------|
| `default_locale` | `en` | Fallback locale (see [Locale detection](#locale-detection)) |
| `enabled_locales` | `[en, es, it, fr, de, pt, nl, pl, ca]` | Locales supported by the modal. A list, or a parameter / env var holding one (`'%kernel.enabled_locales%'`, `'%app.locales%'`, `'%env(json:APP_LOCALES)%'`) — see [Locale detection](#locale-detection) |
| `detect_locale_from_accept_language` | `true` | Use `Accept-Language` when no explicit locale is available |

### Appearance and behaviour of the modal

| Key | Default | Description |
|-----|---------|-------------|
| `ui_theme` | `bootstrap` | Markup framework of the public modal: `bootstrap` or `tailwind` (see [UI theme](#ui-theme)) |
| `color_theme` | `light` | *(DB override)* Colour preset: `light`, `dark`, `dark-turquoise`, `light-funky`, `elegant-black` |
| `dark_mode_enabled` | `false` | *(DB override)* Force the dark palette (adds the dark-mode classes even with a light `color_theme`) |
| `disable_transitions` | `false` | *(DB override)* Disable CSS transitions and animations inside the modal (`data-nowo-disable-transitions`) |
| `disable_page_interaction` | `false` | *(DB override)* Full-page overlay and scroll lock until the visitor chooses (see [Page interaction overlay](#page-interaction-overlay)) |
| `two_step_modal` | `false` | *(DB override)* Show a compact banner first, then the detailed preferences step |
| `open_preferences_modal` | `false` | *(DB override)* Skip the banner and open the preferences step directly when the modal appears (`data-nowo-open-preferences`) |
| `manage_iframe_placeholders` | `false` | *(DB override)* Replace embedded iframes with placeholders until their category is allowed (`data-nowo-manage-iframe-placeholders`) |
| `granular_cookie_selection` | `false` | *(DB override)* Per-cookie toggles inside optional categories (see [Granular cookie selection](#granular-cookie-selection)) |
| `preferences_bubble_enabled` | `false` | *(DB override)* Floating button to reopen preferences (see [Preferences bubble](#preferences-bubble)) |
| `preferences_bubble_position` | `bottom-right` | `bottom-right`, `bottom-left`, `top-right`, `top-left` |
| `preferences_bubble_border_color` | `null` | Hex colour of the bubble outline and icon |
| `preferences_bubble_icon` | `null` | Custom inline SVG / simple HTML, allowlist-sanitized |
| `preference_sections` | `[]` | Optional grouping of the preferences step (see below) |

`preference_sections` is a list of `{title, description, categories}` entries. Sections **without** `categories` replace the default "Cookie usage" intro (title + description shown above the toggles); sections **with** `categories` set the order in which the category toggles are rendered (categories not listed in any section are not shown). Titles and descriptions are rendered as-is, so pass already-translated text or translation keys you resolve in an override template.

```yaml
nowo_cookie_consent:
    preference_sections:
        - title: 'How we use cookies'
          description: 'Strictly necessary cookies are always on. Everything else is optional.'
        - categories: [required, preferences]
        - categories: [analytics, marketing]
```

### Admin Web UI and security

| Key | Default | Description |
|-----|---------|-------------|
| `web_ui.enabled` | `true` | Admin UI on/off (admin routes answer 404 when `false`) |
| `web_ui.path_prefix` | `/cookie-consent-config` | **Deprecated since 1.15, ignored.** Admin routes are always `/cookie-consent-config/…`; move them with the `prefix` of the routes import |
| `web_ui.layout_template` | `null` | Per-bundle layout override; `null` = UiKit `nowo_ui_kit.layouts.admin` (REQ-UI-003) |
| `web_ui.templates.<page>` | `{}` | Per-page template override: `cookie_definition_index`, `cookie_definition_form`, `settings` |
| `web_ui.list_page_size` | `20` | Page size of admin lists |
| `web_ui.css_framework` | `null` | **Deprecated since 1.16** — set `nowo_ui_kit.css_framework` |
| `web_ui.icon_set` | `null` | **Deprecated since 1.16** — set `nowo_ui_kit.icon_set` |
| `web_ui.chrome.*` | — | **Deprecated since 1.16** — bridged into `nowo_ui_kit.theme.bundles.NowoCookieConsentBundle` (see [Admin Web UI](#admin-web-ui)) |
| `security.access_checker` | `null` | Service id implementing `CookieConsentAccessCheckerInterface` |
| `security.access_roles` | `[ROLE_ADMIN]` | Roles allowed in the admin (empty = deny) |
| `security.allow_unauthenticated` | `false` | Demo/dev only |

## Table prefix

When `doctrine.table_prefix` is set to e.g. `app_`, the log table becomes `app_dashboard_cookie_log`. Useful when multiple apps share a database or when your project uses a global table prefix convention (same pattern as [DashboardMenuBundle](https://github.com/nowo-tech/DashboardMenuBundle)).

Root `table_prefix` is deprecated but still supported as a fallback.

## Locale detection

The bundle resolves the modal locale automatically in this order:

1. `locale` query parameter
2. `_locale` or `locale` request attribute
3. Main request locale (for ESI/sub-requests)
4. Current request locale
5. `Accept-Language` header (when `detect_locale_from_accept_language` is true)
6. `default_locale`

Configure supported languages with `enabled_locales`. Bundle translations ship for `en`, `es`, `it`, `fr`, `de`, `pt`, `nl`, `pl`, and `ca`.

`enabled_locales` can reuse the host's locale list instead of repeating it:

```yaml
nowo_cookie_consent:
    enabled_locales: '%kernel.enabled_locales%'   # framework.enabled_locales, or '%app.locales%'
    # enabled_locales: '%env(json:APP_LOCALES)%'  # APP_LOCALES='["en","es"]' (or '%env(csv:APP_LOCALES)%' with APP_LOCALES=en,es)
```

Parameters are resolved when the container is built (`nowo_cookie_consent.enabled_locales` holds the resolved list).
An env var is read at runtime by the locale resolver (and `nowo_cookie_consent_enabled_locales()`). Values are trimmed
and de-duplicated. Any other list option (`target_routes`, `disabled_routes`, …) can also be a
build-time parameter: `prepend()` no longer processes the whole raw configuration.

### UI theme

Choose the markup framework for the bundled consent modal:

```yaml
nowo_cookie_consent:
    ui_theme: bootstrap   # default
    # ui_theme: tailwind
```

- `bootstrap` — Bootstrap 5 modal markup (load Bootstrap CSS/JS in your layout, or rely on the CSS fallback in `nowo-consent-modal.js`).
- `tailwind` — Tailwind utility classes; load Tailwind in your layout (CDN or build). No Bootstrap required.

### Modal CSS and CSP (style-src nonces)

By default `nowo-consent-modal.js` injects `cookie-consent.css` as a `<style>` tag. Hosts with CSP **style nonces** should link the standalone file instead so styles are not dropped:

```twig
<link rel="stylesheet"
      href="{{ asset('nowo-cookie-consent.css', 'nowo_cookie_consent') }}"
      data-nowo-cookie-consent-css>
```

Markers that skip injection: `link[data-nowo-cookie-consent-css]`, `html[data-nowo-cookie-consent-external-css="true"]`, or `#cookieconsent[data-nowo-external-css="true"]`.

### CSP nonce convention (`csp_nonce` request attribute)

Shared by all nowo-tech kits: when the host stores the per-request nonce in the request attribute `csp_nonce` (e.g. from a CSP subscriber), every `<script>` the bundle renders carries it:

```twig
{% set _csp_nonce = app is defined and app.request ? app.request.attributes.get('csp_nonce')|default('') : '' %}
<script src="…"{% if _csp_nonce %} nonce="{{ _csp_nonce }}"{% endif %} defer></script>
```

Covered: modal loader (Bootstrap + Tailwind), `_diagnostics_script.html.twig`, admin layout (Bootstrap bundle) and admin `nowo-ui-confirm.js`. Templates contain no inline event handlers and no inline `style` attributes (the preferences bubble accent uses `data-nowo-bubble-accent`, applied via CSSOM).

When JS injects the modal `<style>` it sets `nonce` from, in order: the loader script (`document.currentScript.nonce`), `<meta name="csp-nonce" content="…">`, the first `<script nonce>` on the page. So `style-src-elem 'nonce-…'` works without linking the standalone CSS; linking it (above) remains the zero-JS option.

### Schema-ready probe (worker mode)

`CookieConsentSchemaReadySubscriber` (`kernel.request`, priority 33) checks that the consent config table exists so cold-start requests skip database work. A positive result is memoized in the service for `schema_ready_cache_ttl` seconds and deliberately **not** reset between requests, so in FrankenPHP worker mode only the first request per worker per TTL window probes the schema. A missing table is never memoized (probing continues until migrations run). Set `schema_ready_cache_ttl: 0` to probe on every request.

### Recommended DB profile layout

When `use_database_config: true`, modal **layout / position / equal-weight buttons** live on `CookieConsentConfig` (admin Profile settings), not YAML. Recommended defaults (new entity rows from 1.9.0):

| Option | Recommended |
|--------|-------------|
| Consent modal | `box` + `wide` + **bottom** + **left** + equal-weight buttons |
| Preferences modal | same corner + equal-weight buttons |

Override `--nowo-cc-*` tokens in host CSS to match brand colors.

Override templates per theme:

| Theme | Modal | Form theme |
|-------|-------|------------|
| bootstrap | `templates/bundles/NowoCookieConsentBundle/cookie_consent.html.twig` | `form/cookie_consent_theme.html.twig` |
| tailwind | `templates/bundles/NowoCookieConsentBundle/cookie_consent.tailwind.html.twig` | `form/cookie_consent_theme.tailwind.html.twig` |

Twig helpers:

- `nowo_cookie_consent_enabled_locales()`
- `nowo_cookie_consent_locale()`

## Translations

Translation domain: **`NowoCookieConsentBundle`** (CamelCase, matching the bundle name).

The bundle ships YAML files for `en`, `es`, `it`, `fr`, `de`, `pt`, `nl`, `pl`, and `ca` under `src/Resources/translations/`. Symfony loads the app translations first; missing keys fall back to the bundle.

### How to override (application)

1. Use the same domain: `NowoCookieConsentBundle`.
2. Create a file in your application:
   - `translations/NowoCookieConsentBundle.<locale>.yaml` (or `.xlf` if your project uses XLF).
3. Override only the keys you need. Keys not defined in the app file use the bundle default.

Example — override for the Spanish locale file (English sample values; replace with your locale copy):

```yaml
# translations/NowoCookieConsentBundle.es.yaml
nowo_cookie_consent:
    consent_modal:
        title: 'Cookie settings'
        description: 'We use cookies to improve your experience.'
    category:
        analytics: 'Analytics'
```

4. Clear the Symfony cache in dev if translations do not appear: `php bin/console cache:clear`.

Set `nowo_cookie_consent.privacy_route` to a Symfony route name to link the privacy policy from the modal.

When `use_database_config: true`, per-locale copy can also be stored in `CookieConsentConfigTranslation` entities (admin CRUD); YAML overrides still apply to keys not overridden in the database layer.

## Database configuration

When `use_database_config` is `true`, the bundle loads copy and display settings from Doctrine entities:

- `CookieConsentConfig` — behavior and modal layout (one default enabled record is typical)
- `CookieConsentConfigTranslation` — per-locale texts mapped to bundle translation keys

Tables: `dashboard_cookie_config` and `dashboard_cookie_config_translation` (with optional `doctrine.table_prefix`).

The Symfony 8 demo enables this option and provides an admin CRUD at `/demo/admin/cookie-consent-config`.

### Page overlay (per profile)

Enable the full-page overlay and scroll lock per consent profile:

1. Set `use_database_config: true` in `config/packages/nowo_cookie_consent.yaml`.
2. Open profile settings in the admin UI:
   - **Bundle admin** (import bundle routes): `/cookie-consent-config/{id}/settings` redirects to `/settings/profile` — routes `nowo_cookie_consent_config_settings_edit` (redirect) and `nowo_cookie_consent_config_settings_section` (section form)
   - Section slugs: `profile`, `behavior`, `appearance`, `consent-modal`, `preferences-modal`, `route-targeting`
   - **Symfony 8 demo**: **Admin → Cookie consent config → profile → Settings**
3. Open the **Appearance** tab, enable **Disable page interaction** (`disablePageInteraction`).
4. Adjust overlay intensity with **Color theme** on the same screen (`--nowo-cc-overlay` in CSS).

The bundle ships one FormType per settings tab under `Nowo\CookieConsentBundle\Form\Settings\` (resolved via `CookieConsentConfigSettingsSection::formType()`), `CookieConsentConfigSettingsAdminController`, area/section tab partials, and Bootstrap templates under `@NowoCookieConsentBundle/admin/config/`. Wire routes via `@NowoCookieConsentBundle/Resources/config/routing.yaml` (same as cookie inventory admin).

The database value overrides the global YAML default when both are set. Twig helper: `nowo_cookie_consent_disable_page_interaction()`.

## Cookie inventory

When `use_cookie_inventory` is `true`, the bundle exposes structured cookie definitions in the preferences modal (per category) and via `nowo_cookie_consent_cookie_inventory()`.

Each entry includes:

| Field | Description |
| --- | --- |
| `name` | Cookie name (e.g. `_ga`, `PHPSESSID`) |
| `category` | Consent block / category (`required`, `analytics`, `marketing`, …) |
| `duration` | Retention period (free text, e.g. `Session`, `2 years`) |
| `type` | `first_party` or `third_party` |
| `provider` | Vendor or domain (translatable) |
| `purpose` | GDPR purpose text (translatable) |
| `allowed_by_default` | Pre-check in granular mode before consent is saved (YAML: `allowed_by_default`; DB column) |

### Database entities (recommended for admin CRUD)

When `use_database_config` is enabled, store definitions as Doctrine entities linked to each profile:

- `CookieDefinition` — `name`, `duration`, `category`, `type`, `sort_order`, `allowed_by_default`
- `CookieDefinitionTranslation` — per-locale `provider` and `purpose`

Tables (with optional prefix):

- `{prefix}dashboard_cookie_definition`
- `{prefix}dashboard_cookie_definition_translation`

Run `doctrine:schema:update` or add a migration after enabling the bundle entities. The demo seeds sample rows with `app:seed-cookie-definitions`.

### Static YAML inventory

When the active profile has no database rows, you can declare cookies in configuration:

```yaml
nowo_cookie_consent:
    use_cookie_inventory: true
    cookie_inventory:
        - name: PHPSESSID
          duration: Session
          category: required
          type: first_party
          sort_order: 0
          translations:
              en:
                  provider: This website
                  purpose: Keeps your session active.
              es:
                  provider: Este sitio
                  purpose: Keeps your session active.
        - name: _ga
          duration: 2 years
          category: analytics
          type: third_party
          sort_order: 10
          allowed_by_default: false
          translations:
              en:
                  provider: Google Analytics
                  purpose: Distinguishes users for statistics.
```

Database definitions take precedence over YAML when both exist for the active profile.

Twig helper: `nowo_cookie_consent_cookie_inventory()`.

### Admin CRUD (bundle)

The bundle ships `CookieDefinitionAdminController` and Bootstrap admin templates under `@NowoCookieConsentBundle/admin/cookie_definition/`. Import the controller route in your app (same pattern as your config admin). Forms use `CookieDefinitionType` with embedded translations.

Route name prefix: `nowo_cookie_consent_cookie_definitions_*`.

## Granular cookie selection

When enabled, optional categories show a cookie inventory table with an **Allow** column. Visitors can toggle individual cookies; category switches sync with per-cookie choices.

Enable globally (YAML default) or per profile when `use_database_config: true`:

```yaml
nowo_cookie_consent:
    use_cookie_inventory: true
    granular_cookie_selection: true   # bundle default; overridden by CookieConsentConfig when DB config is active
```

Required cookies are always on and never appear in the granular toggle list.

Twig helper: `nowo_cookie_consent_granular_cookie_selection()`.

Per-cookie consent is stored in the consent cookie JSON map. Check programmatically:

```php
$cookieChecker->isCookieAllowedByUser('_ga', 'analytics');
```

## Page interaction overlay

When `disable_page_interaction` is `true`, the modal adds a full-page overlay and blocks scrolling until the visitor accepts, rejects, or saves preferences.

Enable globally in YAML (works without Doctrine):

```yaml
nowo_cookie_consent:
    disable_page_interaction: true
```

With `use_database_config: true`, the active `CookieConsentConfig` profile overrides the YAML default via `disablePageInteraction`.

Overlay opacity follows the active `color_theme` (`--nowo-cc-overlay` CSS variable). Twig helper: `nowo_cookie_consent_disable_page_interaction()`.

## Preferences bubble

When `preferences_bubble_enabled` is `true`, the bundle renders a fixed circular button with a cookie icon. It uses the same `data-nowo-open-consent` handler as manual “Cookie settings” links and opens the preferences step of the modal.

Keep the modal in the DOM after consent is saved:

```twig
{% if nowo_cookie_consent_should_embed_modal() %}
    {{ render(path('nowo_cookie_consent.show_if_not_set')) }}
{% endif %}
```

Position with `preferences_bubble_position`: `bottom-right` (default), `bottom-left`, `top-right`, or `top-left`.

Set `preferences_bubble_border_color` (hex, e.g. `#60fed2`) for the transparent bubble outline and cookie icon. With `use_database_config: true`, configure per profile via `CookieConsentConfig::preferencesBubbleBorderColor` in the admin settings form.

Set `preferences_bubble_icon` to custom inline SVG or simple inline HTML (e.g. an emoji wrapped in a `<span>`). The markup must be well-formed XML and is allowlist-sanitized (SVG shapes/gradients and `span`/`i`/`b`/`strong`/`em`/`small`; no scripts, event handlers, `style`, external `href`/`url()`); invalid YAML values fail container compilation, invalid admin input is rejected and unsafe stored values fall back to the default icon. With `use_database_config: true`, configure per profile via `CookieConsentConfig::preferencesBubbleIcon` in the admin settings form. Leave empty to use the default cookie SVG.

Twig helpers:

- `nowo_cookie_consent_preferences_bubble_enabled()`
- `nowo_cookie_consent_preferences_bubble_position()`
- `nowo_cookie_consent_should_embed_modal()`

Override markup: `templates/bundles/NowoCookieConsentBundle/cookie_consent_preferences_bubble.html.twig`.

### Two-step modal

When `two_step_modal` is enabled on the profile, the preferences step includes a close control (`data-nowo-hide-preferences`) that returns to the compact consent banner without closing the modal.

## Config API (GET)

When `fetch_config_via_api` is `true`, the bundle exposes JSON endpoints for client-side modal configuration:

| Route | Path |
| --- | --- |
| `nowo_cookie_consent.config` | `/cookie-consent/config?locale=en` |
| `nowo_cookie_consent.config_localized` | `/{_locale}/cookie-consent/config` |

Response shape:

```json
{
  "code": 200,
  "data": {
    "autoShow": true,
    "guiOptions": { "consentModal": { "layout": "box", "position": "bottom center" } },
    "language": {
      "default": "en",
      "translations": {
        "en": {
          "consentModal": {
            "title": "Cookie settings",
            "description": "We use cookies..."
          }
        }
      }
    }
  }
}
```

The frontend script reads `data-nowo-config-url` on the modal, performs a `GET`, and applies the payload before opening the modal.

## Admin Web UI

The admin pages (cookie inventory, cookie definition form, profile settings) follow the **UiKit 2.0 page contract**
(REQ-UI-003): each page extends a UiKit scaffold (`page/list`, `page/form`, `page/settings`), the controllers pass UiKit
view models, and every structural element (page header, area / section tabs, table, row actions, confirm dialogs,
pagination, empty state, form frame, flashes) is painted by UiKit's theme slots. You theme them **once** in
`nowo_ui_kit` for every Nowo bundle; nothing in this bundle loads CSS frameworks from a CDN. Page → scaffold map, data
blocks and view-model fields: [TEMPLATES.md](TEMPLATES.md).

| Option | Default | Description |
| --- | --- | --- |
| `web_ui.enabled` | `true` | Enables the admin UI (access enforced by `CookieConsentAdminAccessSubscriber`). When `false`, admin routes answer 404 |
| `web_ui.path_prefix` | `/cookie-consent-config` | **Deprecated since 1.15 and ignored** (setting it triggers a deprecation). Admin routes are always mounted under `/cookie-consent-config`; to move them use the `prefix` option when importing `@NowoCookieConsentBundle/Resources/config/routing.yaml`, and protect the final path with `access_control` |
| `web_ui.layout_template` | `null` | Per-bundle layout override. `null` = `nowo_ui_kit.layouts.admin` (default `@NowoUiKit/layout/admin.html.twig`). The layout must render the UiKit canonical blocks (`nowo_ui_content`, `nowo_ui_page_header`, `nowo_ui_flashes`, …). The 1.x value `@NowoCookieConsentBundle/admin/layout.html.twig` is treated as `null` |
| `web_ui.templates.<page>` | `{}` | Per-page template (`cookie_definition_index`, `cookie_definition_form`, `settings`): a thin template extending `@!NowoCookieConsentBundle/…` that overrides a data block. Unknown pages fail at compile time |
| `web_ui.list_page_size` | `20` | Page size for cookie definition admin lists |
| `web_ui.css_framework` | `null` | **Deprecated since 1.16.** When set and `nowo_ui_kit.css_framework` is not, seeds it (as before) |
| `web_ui.icon_set` | `null` | **Deprecated since 1.16.** When set and `nowo_ui_kit.icon_set` is not, seeds it |
| `web_ui.chrome.classes` / `.attributes` / `.templates.tabs` / `.templates.pagination` | `{}` | **Deprecated since 1.16.** Bridged into `nowo_ui_kit.theme.bundles.NowoCookieConsentBundle` (classes → slots with `framework_defaults: false`, attributes → slot attributes, partials → UiKit partials; one-page keys → `pages.<type>`); mapping in [UPGRADING — Template contract](UPGRADING.md#template-contract-116) |
| `web_ui.chrome.templates.form_theme` / `.form_fields` / `.styles_after` | `~` | **Deprecated since 1.16**, still applied by the admin pages (no theme slot equivalent; `form_theme` as the UiKit `formThemes` of the admin forms) |
| `web_ui.chrome.row_actions_display` | `inherit` | **Deprecated since 1.16.** `text` / `icon` / `icon_text` forces the display of the list row actions; `inherit` = `nowo_ui_kit.row_actions_display` |
| `web_ui.chrome.page_title_tag` | `h1` | **Deprecated since 1.16 and ignored** — the heading comes from the UiKit `page_header` partial |

> **`ui_theme` vs the admin stack**
>
> `ui_theme` (`bootstrap` / `tailwind`) only selects the markup of the **public consent modal**. The admin pages take
> their CSS stack (`css_framework`), icons and row-action display from **`nowo_ui_kit`**.

Typical host configuration (once for every Nowo bundle):

```yaml
# config/packages/nowo_ui_kit.yaml
nowo_ui_kit:
    css_framework: bootstrap5
    layouts:
        admin: 'layouts/admin.html.twig'   # your layout or a one-file bridge rendering the nowo_ui_* blocks
    theme:
        tokens: { primary: '#0f766e' }
        classes: { table: 'data-table' }
        bundles:                            # optional exceptions for this bundle only
            NowoCookieConsentBundle:
                classes: { table: 'data-table data-table--dense' }

# config/packages/twig.yaml — the admin forms use your form theme
twig:
    form_themes: ['bootstrap_5_layout.html.twig']
```

```yaml
# config/packages/nowo_cookie_consent.yaml
nowo_cookie_consent:
    web_ui:
        list_page_size: 20
        # layout_template: 'layouts/cookie_admin.html.twig'   # only to differ from nowo_ui_kit.layouts.admin
```

The bundle ships no admin layout. UiKit's `layout/admin.html.twig` loads `nowo-ui.css` and the UiKit scripts from the
`nowo_ui_kit` asset package; load your CSS framework in your own layout (see the demos: `demo/symfony8` Bootstrap,
`demo/symfony8-tailwind` Tailwind).

## Admin security

```yaml
nowo_cookie_consent:
    security:
        access_roles: [ROLE_ADMIN]
        # access_checker: App\Security\CookieConsentAccessChecker
        allow_unauthenticated: false
```

Also lock the path in the host firewall:

```yaml
# config/packages/security.yaml
security:
    access_control:
        - { path: ^/cookie-consent-config, roles: ROLE_ADMIN }
```

`allow_unauthenticated: true` is for demos/CI only. Production must keep it `false` and require `symfony/security-bundle`. The default `CookieConsentAccessCheckerInterface` implementation grants access when any configured role is granted; set a custom `security.access_checker` service id to replace it.

## Twig overrides

**REQ-TWIG-001.** Namespace: **`NowoCookieConsentBundle`**. Full procedure, freeze rule, and the overridable `<subpath>` table: [USAGE.md — Overriding templates](USAGE.md#overriding-templates-req-twig-001). Theme-specific modal/form paths: [UI theme](#ui-theme). Prefer `web_ui.layout_template` over copying admin pages — [Admin Web UI](#admin-web-ui).

## Twig helpers

- `nowo_cookie_consent_is_saved()`
- `nowo_cookie_consent_is_category_allowed('analytics')`
- `nowo_cookie_consent_is_open_by_default(route, disabled_routes)`
- `nowo_cookie_consent_enabled_locales()`
- `nowo_cookie_consent_locale()`
- `nowo_cookie_consent_cookie_inventory()`
- `nowo_cookie_consent_granular_cookie_selection()`
- `nowo_cookie_consent_disable_page_interaction()`
- `nowo_cookie_consent_should_embed_modal()`
- `nowo_cookie_consent_should_render()`
- `nowo_cookie_consent_preferences_bubble_enabled()`
- `nowo_cookie_consent_preferences_bubble_position()`
- `nowo_cookie_consent_preferences_bubble_border_color()`
- `nowo_cookie_consent_preferences_bubble_icon()`
- `nowo_cookie_consent_two_step_modal()`

## Cold start / Site Backup

When the application database schema is not ready (for example during Site Backup Bundle cold start), set one of these **main-request** attributes to `false`:

| Attribute | Constant |
| --- | --- |
| `_nowo_site_backup_schema_exists` | `ColdStartRequestAttributes::SITE_BACKUP_SCHEMA_EXISTS` (shared with Site Backup Bundle) |
| `_nowo_cookie_consent_schema_ready` | `ColdStartRequestAttributes::COOKIE_CONSENT_SCHEMA_READY` (local override) |

Effects on the main request:

- `CookieConsentConfigTranslationSubscriber` skips config resolution.
- `CookieConsentFormSubscriber` skips consent form handling.
- `nowo_cookie_consent_should_render()` returns `false`.

If Doctrine is still unreachable, subscribers log at debug and soft-skip on `Doctrine\DBAL\Exception` / `Doctrine\ORM\Exception\ORMException` only.

No bundle configuration keys are required when Site Backup already publishes `_nowo_site_backup_schema_exists`.
