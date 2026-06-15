# Configuration

## Table of contents

- [Table prefix](#table-prefix)
- [Locale detection](#locale-detection)
  - [UI theme](#ui-theme)
- [Translations](#translations)
- [Database configuration](#database-configuration)
- [Cookie inventory](#cookie-inventory)
- [Granular cookie selection](#granular-cookie-selection)
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

Override keys in `translations/NowoCookieConsentBundle.{locale}.yaml`.

Set `nowo_cookie_consent.privacy_route` to a Symfony route name to link the privacy policy from the modal.

## Database configuration

When `use_database_config` is `true`, the bundle loads copy and display settings from Doctrine entities:

- `CookieConsentConfig` — behavior and modal layout (one default enabled record is typical)
- `CookieConsentConfigTranslation` — per-locale texts mapped to bundle translation keys

Tables: `nowo_cookie_consent_config` and `nowo_cookie_consent_config_translation` (with optional `table_prefix`).

The Symfony 8 demo enables this option and provides an admin CRUD at `/demo/admin/cookie-consent-config`.

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

## Preferences bubble

When `preferences_bubble_enabled` is `true`, the bundle renders a fixed circular button with a cookie icon. It uses the same `data-nowo-open-consent` handler as manual “Cookie settings” links and opens the preferences step of the modal.

Keep the modal in the DOM after consent is saved:

```twig
{% if nowo_cookie_consent_should_embed_modal() %}
    {{ render(path('nowo_cookie_consent.show_if_not_set')) }}
{% endif %}
```

Position with `preferences_bubble_position`: `bottom-right` (default), `bottom-left`, `top-right`, or `top-left`.

With `use_database_config`, configure per profile via `CookieConsentConfig::preferencesBubbleEnabled` and `preferencesBubblePosition`.

Twig helpers:

- `nowo_cookie_consent_preferences_bubble_enabled()`
- `nowo_cookie_consent_preferences_bubble_position()`
- `nowo_cookie_consent_should_embed_modal()`

Override markup: `templates/bundles/NowoCookieConsentBundle/cookie_consent_preferences_bubble.html.twig`.

### Two-step modal

When `two_step_modal` is enabled on the profile, the preferences step includes a close control (`data-nowo-hide-preferences`) that returns to the compact consent banner without closing the modal.

## Config API (GET)

When `fetch_config_via_api` is `true`, the bundle exposes JSON endpoints compatible with the podologiapriego flow:

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

| Template | Override path |
| --- | --- |
| Main modal | `templates/bundles/NowoCookieConsentBundle/cookie_consent.html.twig` |
| Form theme | `templates/bundles/NowoCookieConsentBundle/form/cookie_consent_theme.html.twig` |

## Twig helpers

- `nowo_cookie_consent_is_saved()`
- `nowo_cookie_consent_is_category_allowed('analytics')`
- `nowo_cookie_consent_is_open_by_default(route, disabled_routes)`
- `nowo_cookie_consent_enabled_locales()`
- `nowo_cookie_consent_locale()`
- `nowo_cookie_consent_cookie_inventory()`
- `nowo_cookie_consent_granular_cookie_selection()`
- `nowo_cookie_consent_should_embed_modal()`
- `nowo_cookie_consent_preferences_bubble_enabled()`
- `nowo_cookie_consent_preferences_bubble_position()`
- `nowo_cookie_consent_two_step_modal()`
