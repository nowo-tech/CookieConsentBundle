# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.3.0] - 2026-07-05

### Changed

- **Breaking:** Doctrine table names now use the `dashboard_cookie_*` prefix (e.g. `dashboard_cookie_log`, `dashboard_cookie_config`, `dashboard_cookie_definition`), aligned with [DashboardMenuBundle](https://github.com/nowo-tech/DashboardMenuBundle).
- **`doctrine.table_prefix`** — preferred config key (same structure as dashboard menu). Root `table_prefix` is deprecated but still supported.
- **Translations** — complete consent modal strings for `de`, `fr`, `it`, `nl`, and `pt` (previously partial English fallback).

### Migration

Rename existing tables or regenerate migrations. Examples:

| Old | New |
|-----|-----|
| `nowo_cookie_consent_log` | `dashboard_cookie_log` |
| `nowo_cookie_consent_config` | `dashboard_cookie_config` |
| `nowo_cookie_consent_config_translation` | `dashboard_cookie_config_translation` |
| `nowo_cookie_consent_cookie_definition` | `dashboard_cookie_definition` |
| `nowo_cookie_consent_cookie_definition_translation` | `dashboard_cookie_definition_translation` |

### Documentation

- [CONFIGURATION.md](CONFIGURATION.md) — `doctrine.table_prefix`, deprecated root `table_prefix`, updated default table names
- [INSTALLATION.md](INSTALLATION.md) — database table names and prefix configuration
- [UPGRADING.md](UPGRADING.md) — upgrade path from 1.2.0

[1.3.0]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.3.0

## [1.2.0] - 2026-06-15

### Added

- **`disable_page_interaction`** — global YAML option, Twig helper `nowo_cookie_consent_disable_page_interaction()`, and per-profile admin field for full-page overlay and scroll lock until consent
- **Profile settings admin in the bundle** — `CookieConsentConfigSettingsType`, `CookieConsentConfigSettingsAdminController`, and Bootstrap templates at `/cookie-consent-config/{id}/settings` (route `nowo_cookie_consent_config_settings_edit`)
- **Preferences bubble customization** — `preferences_bubble_border_color` (hex outline/icon color) and `preferences_bubble_icon` (custom HTML or SVG) via YAML or `CookieConsentConfig` when `use_database_config: true`
- **`PreferencesBubbleIconSanitizer`** — rejects dangerous markup (`<script>`, event handlers, etc.) before persisting custom bubble icons
- Twig helpers `nowo_cookie_consent_preferences_bubble_border_color()` and `nowo_cookie_consent_preferences_bubble_icon()`
- Default bubble SVG partial `_preferences_bubble_icon_default.html.twig`
- Symfony **8.1** and PHP **8.2–8.5** in CI; broader Symfony and Doctrine `composer.json` constraints

### Changed

- Preferences bubble: transparent background, configurable border color, larger icon area, flex-centered custom content
- Modal **vertical position** (`consent_modal_position_y: bottom`) fixed for box and cloud layouts (CSS flex column + Tailwind wrapper cleanup)
- Demo settings UI reuses bundle `CookieConsentConfigSettingsType` (removed duplicate demo form class)
- PHP test suite: **130 tests**
- Flex recipe documents commented `disable_page_interaction` example

### Fixed

- Tailwind consent modal no longer forces `items-center justify-center` on the overlay, which prevented bottom-aligned box modals from sitting at the viewport edge

### Documentation

- [CONFIGURATION.md](CONFIGURATION.md) — page overlay per profile, bubble border/icon, settings admin, Twig helpers
- [USAGE.md](USAGE.md) — profile settings admin and custom bubble icon
- [SECURITY.md](SECURITY.md) — settings admin surface and bubble icon sanitization
- [UPGRADING.md](UPGRADING.md) — upgrade path from 1.1.1

[1.2.0]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.2.0

## [1.1.1] - 2026-06-15

### Added

- TypeScript tests for `iframe-manager`, `apply-theme`, and expanded coverage for `apply-config`, `step-manager`, and `granular-cookie-toggles`
- Maintainer script `.scripts/complete-public-phpdoc.php` and Makefile target `validate-phpdoc` (included in `release-check`)

### Changed

- **Nowo bundle standards alignment** — REQ-MAKE-008 markers in Makefiles, REQ-DOCS-009 README CTA blockquote, expanded `docs/SECURITY.md`, full Twig/i18n override docs in [CONFIGURATION.md](CONFIGURATION.md)
- **PHPDoc (REQ-CS-001)** — English PHPDoc completed on public classes and methods across `src/`
- **TypeScript coverage** — Vitest thresholds at 90%; `.scripts/ts-coverage-percent.sh` fails below minimum; README reports **~94%** TS lines (PHP remains **~100%**)

### Fixed

- PHPUnit fixtures updated for `CookieInventoryProvider` and expanded `CookieConsentConfigPayloadFactory` / `CookieConsentType` constructors (`CookieConsentTwigExtensionTest`, `CookieConsentConfigPayloadFactoryTest`, `CookieConsentConfigApiControllerTest`, `CookieConsentTypeTest`)

### Documentation

- [SECURITY.md](SECURITY.md) — scope, attack surface, threat model, mitigations, logging, dependency policy
- [CONFIGURATION.md](CONFIGURATION.md) — Twig override procedure and full template table; translation override procedure with YAML example
- [UPGRADING.md](UPGRADING.md) — upgrade path from 1.1.0

[1.1.1]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.1.1

## [1.1.0] - 2026-06-15

### Added

- **Granular cookie selection** — optional per-cookie toggles inside each category block when `granular_cookie_selection` is enabled on the active profile (`CookieConsentConfig::granularCookieSelection` or bundle default)
- **Cookie inventory** — `use_cookie_inventory`, static `cookie_inventory` YAML, and Doctrine entities `CookieDefinition` / `CookieDefinitionTranslation` with translatable provider and purpose
- **`allowed_by_default`** on cookie definitions — controls pre-checked state for individual cookies and category toggles before consent is saved
- **Per-cookie consent storage** — optional cookie map persisted alongside category choices; `CookieChecker::isCookieAllowedByUser()` respects granular preferences
- **Preferences bubble** — floating cookie button (`preferences_bubble_enabled`, `preferences_bubble_position`) to reopen the modal after consent; Twig helper `nowo_cookie_consent_should_embed_modal()`
- **Cookie definition admin CRUD** — `CookieDefinitionAdminController` and Bootstrap admin views under `/cookie-consent-config/{id}/cookies` (wire routes in your app)
- **Two-step modal navigation** — close control on the preferences step returns to the consent banner (`data-nowo-hide-preferences`)
- **CookieConsent v3 alignment** — color themes, two-step banner, preference sections, iframe placeholders, diagnostics script, category/granular toggle TypeScript modules
- **Twig helpers** — `nowo_cookie_consent_granular_cookie_selection()`, `nowo_cookie_consent_preferences_bubble_*()`, `nowo_cookie_consent_two_step_modal()`, and related CMP UX helpers
- **Demo enhancements** — legal pages, cookie inventory CRUD with locale tabs, playground preset seed, shared demo catalog (`DemoCookieCatalog`)

### Changed

- Rebuilt `nowo-consent-modal.js` (granular toggle CSS, step manager, category sync)
- `CookieConsentType` initial state derives category and cookie checkboxes from inventory `allowed_by_default` when no consent cookie exists
- Database-backed config payload and CMP UX resolver expose new profile flags
- PHP test suite expanded (**123 tests**); TypeScript tests cover step manager, granular toggles, and category toggles

### Documentation

- [CONFIGURATION.md](CONFIGURATION.md) — cookie inventory, granular selection, preferences bubble, admin CRUD
- [USAGE.md](USAGE.md) — embed modal with bubble, per-cookie checks, inventory admin
- [UPGRADING.md](UPGRADING.md) — upgrade path from 1.0.0

[1.1.0]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.1.0

## [1.0.0] - 2026-06-15

First stable release of the modernized Nowo Cookie Consent Bundle.

### Added

- GDPR cookie consent modal with category toggles, AJAX form submission, and optional Doctrine consent logging
- TypeScript frontend (`nowo-consent-modal.js`) built with Vite; Bootstrap and Tailwind UI themes (`ui_theme`)
- Database-backed modal configuration (`use_database_config`) with per-locale translations and optional config API (`fetch_config_via_api`)
- Locales: `en`, `es`, `it`, `fr`, `de`, `pt`, `nl`, `pl`, `ca` with `Accept-Language` detection
- Symfony Flex recipe (`.symfony/recipe/nowo-tech/cookie-consent-bundle/1.0`)
- FrankenPHP demos: `demo/symfony8` (Bootstrap, port **8014**) and `demo/symfony8-tailwind` (Tailwind, port **8015**)
- Nowo bundle standards: GitHub workflows (CI, release, sync-releases), issue/PR templates, Dependabot, PR lint, stale bot, CodeRabbit, Scrutinizer config, Copilot instructions
- Cursor/Engram setup (`.cursor/`, `docs/ENGRAM.md`, `docs/SPEC-DRIVEN-DEVELOPMENT.md`)
- Docs: INSTALLATION, CONFIGURATION, USAGE, CONTRIBUTING, CHANGELOG, UPGRADING, RELEASE, SECURITY, DEMO-FRANKENPHP

### Changed

- PHP test suite: **114 tests**, **100%** line coverage on `src/`
- TypeScript tests: **~96%** line coverage (Vitest)
- Demo `make up` follows canonical Nowo port protocol (`REQ-DEMO-005`)
- Demo `release-check` syncs bundle code via `update-bundle` before tests (`REQ-DEMO-007`)
- English PHPDoc on all public classes and methods in `src/`

### Documentation

- README: canonical badges, `## Documentation`, `## Tests and coverage` with percentages
- `docs/CONFIGURATION.md` table of contents; Twig override and translation procedures documented

[1.0.0]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.0.0
