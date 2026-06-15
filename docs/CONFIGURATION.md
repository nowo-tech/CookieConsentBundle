# Configuration

## Table of contents

- [Table prefix](#table-prefix)
- [Locale detection](#locale-detection)
  - [UI theme](#ui-theme)
- [Translations](#translations)
- [Database configuration](#database-configuration)
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
