# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.3.5] - 2026-07-24

### Added

- **REQ-CS-005** — `nowo-tech/phpstan-frankenphp` (classic + worker rulesets) in `phpstan.neon.dist`
- Empty `phpstan-baseline.neon` (`ignoreErrors: []`) included from PHPStan config

### Changed

- PHPStan Level max hygiene: typed PHPDoc/generics, FQCN → `use` imports, inventory YAML cache as instance property
- **DI** — `$useLogger` bound explicitly on `CookieConsentFormSubscriber` (survives host `setClass` overrides; avoids unused global bind)
- PHP-CS-Fixer `fully_qualified_strict_types.import_symbols` enabled
- Dev lock refresh (PHPStan ecosystem, php-cs-fixer, demo locks)

### Fixed

- CSRF deny on cookie definition delete throws `AccessDeniedHttpException` (consistent 403)

No configuration or public API breaking changes for bundle consumers.

### Documentation

- [UPGRADING.md](UPGRADING.md) — upgrade path from 1.3.4
- [RELEASE.md](RELEASE.md) — release history entry

[1.3.5]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.3.5

## [1.3.4] - 2026-07-22

### Changed

- **Dev tooling** — Vite **8**, happy-dom **20**, `@types/node` **26**; GitHub Actions `checkout`/`setup-node` **v7**, `pnpm/action-setup` **v6**
- **Demos** — FrankenPHP mode via `FRANKENPHP_MODE` (`classic` \| `worker`, default `worker`); shared `docker/entrypoint.sh` instead of inline Dockerfile script

### Documentation

- [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md) — `FRANKENPHP_MODE` switching (classic vs worker)
- [UPGRADING.md](UPGRADING.md) — upgrade path from 1.3.3

No configuration, public API, or runtime changes for bundle consumers.

[1.3.4]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.3.4

## [1.3.3] - 2026-07-20

### Added

- **REQ-GIT-001** — git hygiene: `.githooks/commit-msg`, `make setup-hooks`, `make check-no-cursor-coauthor`, CI job `git-hygiene`, and [GITHUB_CI.md](GITHUB_CI.md)
- **Code of Conduct** — [CODE_OF_CONDUCT.md](../CODE_OF_CONDUCT.md) (Contributor Covenant)
- Expanded PHPUnit coverage for admin controllers, forms, Twig CMP UX helpers, repositories, and cookie/config edge cases (**180 tests**)

### Changed

- `make release-check` runs `check-no-cursor-coauthor` before the rest of the pipeline
- [CONTRIBUTING.md](CONTRIBUTING.md) and [RELEASE.md](RELEASE.md) document hook setup and pre-push co-author verification

No configuration, public API, or runtime changes for bundle consumers.

[1.3.3]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.3.3

## [1.3.2] - 2026-07-13

### Added

- **`nowo_cookie_consent` asset package** — `NowoCookieConsentExtension` implements `PrependExtensionInterface` and registers `framework.assets.packages.nowo_cookie_consent` (`base_path: /bundles/nowocookieconsent`)

### Changed

- Twig consent templates load `nowo-consent-modal.js` via `asset('nowo-consent-modal.js', 'nowo_cookie_consent')` with `defer` instead of a hardcoded `/bundles/nowocookieconsent/` path

### Fixed

- Consent modal JavaScript resolves correctly in apps using Symfony AssetMapper and standard asset packages

### Documentation

- [INSTALLATION.md](INSTALLATION.md) — AssetMapper note and asset package loading
- [UPGRADING.md](UPGRADING.md) — upgrade path from 1.3.1

No configuration or API breaking changes.

[1.3.2]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.3.2

## [1.3.1] - 2026-07-09

### Added

- **GitHub Spec Kit** — baseline spec at `specs/001-baseline/`, Cursor Agent skills (`.cursor/skills/speckit-*`), and operator manual [SPEC-KIT.md](SPEC-KIT.md)

### Changed

- [SPEC-DRIVEN-DEVELOPMENT.md](SPEC-DRIVEN-DEVELOPMENT.md) — three-layer spec model with Spec Kit baseline and maintainer sync checklist
- Dev dependency lock refresh (`phpunit`, `php-cs-fixer` in `require-dev` only)

### Fixed

- Demo `make update-deps` — define `SERVICE_PHP := php` in `demo/symfony8` and `demo/symfony8-tailwind` Makefiles (fixes `no such service: sh`)

### Documentation

- README link to [SPEC-KIT.md](SPEC-KIT.md)
- [UPGRADING.md](UPGRADING.md) — upgrade path from 1.3.0

No consumer-facing bundle API, configuration, or runtime changes.

[1.3.1]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.3.1

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
