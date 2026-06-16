# Configuration

## Table of contents

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
- [Twig overrides](#twig-overrides)
- [Twig helpers](#twig-helpers)

Extension alias: `nowo_cookie_consent`

```yaml
# config/packages/nowo_cookie_consent.yaml
nowo_cookie_consent:
    # Prefix applied to Doctrine table names (optional)
    table_prefix: ''

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

    csrf_protection: true

    # Routes where the modal must not auto-open
    disabled_routes:
        - privacy
        - imprint

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

## Table prefix

When `table_prefix` is set to e.g. `app_`, the log table becomes `app_nowo_cookie_consent_log`. Useful when multiple apps share a database or when your project uses a global table prefix convention.

## Locale detection

The bundle resolves the modal locale automatically in this order:

1. `locale` query parameter
2. `_locale` or `locale` request attribute
3. Main request locale (for ESI/sub-requests)
4. Current request locale
5. `Accept-Language` header (when `detect_locale_from_accept_language` is true)
6. `default_locale`

Configure supported languages with `enabled_locales`. Bundle translations ship for `en`, `es`, `it`, `fr`, `de`, `pt`, `nl`, `pl`, and `ca`.

### UI theme

Choose the markup framework for the bundled consent modal:

```yaml
nowo_cookie_consent:
    ui_theme: bootstrap   # default
    # ui_theme: tailwind
```

- `bootstrap` — Bootstrap 5 modal markup (load Bootstrap CSS/JS in your layout, or rely on the CSS fallback in `nowo-consent-modal.js`).
- `tailwind` — Tailwind utility classes; load Tailwind in your layout (CDN or build). No Bootstrap required.

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

Example — Spanish override:

```yaml
# translations/NowoCookieConsentBundle.es.yaml
nowo_cookie_consent:
    consent_modal:
        title: 'Configuración de cookies'
        description: 'Usamos cookies para mejorar tu experiencia.'
    category:
        analytics: 'Analíticas'
```

4. Clear the Symfony cache in dev if translations do not appear: `php bin/console cache:clear`.

Set `nowo_cookie_consent.privacy_route` to a Symfony route name to link the privacy policy from the modal.

When `use_database_config: true`, per-locale copy can also be stored in `CookieConsentConfigTranslation` entities (admin CRUD); YAML overrides still apply to keys not overridden in the database layer.

## Database configuration

When `use_database_config` is `true`, the bundle loads copy and display settings from Doctrine entities:

- `CookieConsentConfig` — behavior and modal layout (one default enabled record is typical)
- `CookieConsentConfigTranslation` — per-locale texts mapped to bundle translation keys

Tables: `nowo_cookie_consent_config` and `nowo_cookie_consent_config_translation` (with optional `table_prefix`).

The Symfony 8 demo enables this option and provides an admin CRUD at `/demo/admin/cookie-consent-config`.

### Page overlay (per profile)

Enable the full-page overlay and scroll lock per consent profile:

1. Set `use_database_config: true` in `config/packages/nowo_cookie_consent.yaml`.
2. Open profile settings in the admin UI:
   - **Bundle admin** (import bundle routes): `/cookie-consent-config/{id}/settings` — route `nowo_cookie_consent_config_settings_edit`
   - **Symfony 8 demo**: **Admin → Cookie consent config → profile → Settings**
3. In **Appearance**, enable **Disable page interaction** (`disablePageInteraction`).
4. Adjust overlay intensity with **Color theme** on the same screen (`--nowo-cc-overlay` in CSS).

The bundle ships `CookieConsentConfigSettingsType`, `CookieConsentConfigSettingsAdminController`, and Bootstrap templates under `@NowoCookieConsentBundle/admin/config/`. Wire routes via `@NowoCookieConsentBundle/Resources/config/routing.yaml` (same as cookie inventory admin).

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

- `{prefix}nowo_cookie_consent_cookie_definition`
- `{prefix}nowo_cookie_consent_cookie_definition_translation`

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
                  purpose: Mantiene activa tu sesión.
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

Set `preferences_bubble_icon` to custom SVG or HTML markup (e.g. an emoji wrapped in a `<span>`). With `use_database_config: true`, configure per profile via `CookieConsentConfig::preferencesBubbleIcon` in the admin settings form. Leave empty to use the default cookie SVG.

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

## Twig overrides

Application templates under `templates/bundles/NowoCookieConsentBundle/` **always win** over the copies inside the package. The bundle registers paths via `TwigPathsPass` so Symfony resolves app overrides first.

### Procedure

1. Identify the `<subpath>` from the table below (path relative to `src/Resources/views/` inside the bundle).
2. Create in your application: `templates/bundles/NowoCookieConsentBundle/<subpath>.html.twig` (same relative path and filename).
3. Clear the cache in dev if needed: `php bin/console cache:clear`.

Example — override the Bootstrap modal shell:

```
templates/bundles/NowoCookieConsentBundle/cookie_consent.html.twig
```

Controllers and Twig use logical names such as `@NowoCookieConsentBundle/cookie_consent.html.twig`, never absolute filesystem paths.

### Overridable templates

| Subpath | Purpose |
| --- | --- |
| `cookie_consent.html.twig` | Main consent modal (Bootstrap theme) |
| `cookie_consent.tailwind.html.twig` | Main consent modal (Tailwind theme) |
| `form/cookie_consent_theme.html.twig` | Symfony form theme for consent fields (Bootstrap) |
| `form/cookie_consent_theme.tailwind.html.twig` | Form theme (Tailwind) |
| `cookie_consent_preferences_bubble.html.twig` | Floating “cookie settings” bubble button |
| `_preferences_bubble_icon_default.html.twig` | Default cookie SVG for the preferences bubble |
| `cookie_consent_manage_link.html.twig` | Inline link to reopen preferences |
| `_category_cookie_table.html.twig` | Per-category cookie inventory table (granular mode) |
| `_preference_sections.html.twig` | Preferences step category blocks |
| `_preferences_intro.html.twig` | Intro text on the preferences step |
| `_diagnostics_script.html.twig` | Optional diagnostics script partial |
| `admin/cookie_definition/layout.html.twig` | Admin CRUD layout shell |
| `admin/cookie_definition/index.html.twig` | Cookie definition list |
| `admin/cookie_definition/form.html.twig` | Create/edit cookie definition form |
| `admin/cookie_definition/_table.html.twig` | Admin list table partial |
| `admin/config/layout.html.twig` | Profile settings admin layout shell |
| `admin/config/settings.html.twig` | Profile settings form (overlay, theme, bubble, layout) |

Theme selection follows `ui_theme` (`bootstrap` or `tailwind`); override the modal and form theme rows that match your active theme.

See also [UI theme](#ui-theme) for theme-specific override paths.

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
- `nowo_cookie_consent_preferences_bubble_enabled()`
- `nowo_cookie_consent_preferences_bubble_position()`
- `nowo_cookie_consent_preferences_bubble_border_color()`
- `nowo_cookie_consent_preferences_bubble_icon()`
- `nowo_cookie_consent_two_step_modal()`
