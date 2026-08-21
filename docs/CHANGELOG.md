# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Table of contents

- [[Unreleased]](#unreleased)
- [[1.9.5] - 2026-08-21](#195---2026-08-21)
- [[1.9.4] - 2026-08-20](#194---2026-08-20)
- [[1.9.2] - 2026-08-19](#192---2026-08-19)
- [[1.9.1] - 2026-08-18](#191---2026-08-18)
- [[1.9.0] - 2026-08-15](#190---2026-08-15)
- [[1.8.0] - 2026-08-15](#180---2026-08-15)
- [[1.7.0] - 2026-08-15](#170---2026-08-15)
- [[1.6.3] - 2026-08-11](#163---2026-08-11)
- [[1.6.2] - 2026-08-11](#162---2026-08-11)
- [[1.6.1] - 2026-08-11](#161---2026-08-11)
- [[1.6.0] - 2026-08-04](#160-2026-08-04)
- [[1.5.2] - 2026-08-03](#152-2026-08-03)
- [[1.5.1] - 2026-08-03](#151-2026-08-03)
- [[1.5.0] - 2026-08-01](#150-2026-08-01)
- [[1.4.9] - 2026-08-01](#149-2026-08-01)
- [[1.4.8] - 2026-07-30](#148-2026-07-30)
- [[1.4.7] - 2026-07-30](#147-2026-07-30)
- [[1.4.6] - 2026-07-30](#146-2026-07-30)
- [[1.4.5] - 2026-07-30](#145-2026-07-30)
  - [Changed](#changed)
  - [Documentation](#documentation)
- [[1.4.4] - 2026-07-30](#144-2026-07-30)
  - [Changed](#changed)
  - [Documentation](#documentation)
- [[1.4.3] - 2026-07-30](#143-2026-07-30)
  - [Added](#added)
  - [Changed](#changed)
  - [Documentation](#documentation)
- [[1.4.2] - 2026-07-29](#142-2026-07-29)
  - [Changed](#changed)
  - [Documentation](#documentation)
- [[1.4.1] - 2026-07-28](#141-2026-07-28)
  - [Added](#added)
  - [Changed](#changed)
  - [Fixed](#fixed)
  - [Documentation](#documentation)
- [[1.4.0] - 2026-07-27](#140-2026-07-27)
  - [Added](#added)
  - [Changed](#changed)
  - [Documentation](#documentation)
- [[1.3.6] - 2026-07-27](#136-2026-07-27)
  - [Added](#added)
  - [Changed](#changed)
  - [Documentation](#documentation)
- [[1.3.5] - 2026-07-24](#135-2026-07-24)
  - [Added](#added)
  - [Changed](#changed)
  - [Fixed](#fixed)
  - [Documentation](#documentation)
- [[1.3.4] - 2026-07-22](#134-2026-07-22)
  - [Changed](#changed)
  - [Documentation](#documentation)
- [[1.3.3] - 2026-07-20](#133-2026-07-20)
  - [Added](#added)
  - [Changed](#changed)
- [[1.3.2] - 2026-07-13](#132-2026-07-13)
  - [Added](#added)
  - [Changed](#changed)
  - [Fixed](#fixed)
  - [Documentation](#documentation)
- [[1.3.1] - 2026-07-09](#131-2026-07-09)
  - [Added](#added)
  - [Changed](#changed)
  - [Fixed](#fixed)
  - [Documentation](#documentation)
- [[1.3.0] - 2026-07-05](#130-2026-07-05)
  - [Changed](#changed)
  - [Migration](#migration)
  - [Documentation](#documentation)
- [[1.2.0] - 2026-06-15](#120-2026-06-15)
  - [Added](#added)
  - [Changed](#changed)
  - [Fixed](#fixed)
  - [Documentation](#documentation)
- [[1.1.1] - 2026-06-15](#111-2026-06-15)
  - [Added](#added)
  - [Changed](#changed)
  - [Fixed](#fixed)
  - [Documentation](#documentation)
- [[1.1.0] - 2026-06-15](#110-2026-06-15)
  - [Added](#added)
  - [Changed](#changed)
  - [Documentation](#documentation)
- [[1.0.0] - 2026-06-15](#100-2026-06-15)
  - [Added](#added)
  - [Changed](#changed)
  - [Documentation](#documentation)

## [Unreleased]

## [1.9.5] - 2026-08-21

### Added

- **`nowo_cookie_consent_render()`** Twig function and `CookieConsentModalRenderer`: embed the consent modal in the current request (no kernel sub-request). Prefer this over `{{ render(path('nowo_cookie_consent.show')) }}` in host layouts. Routes `nowo_cookie_consent.show` / `show_if_not_set` remain for ESI / legacy embeds.

[1.9.5]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.9.5

## [1.9.4] - 2026-08-20

### Security

- **Dev/demo frontend:** bump `vitest` to **^3.2.6**; pnpm overrides for `vite` ≥6.4.3, `postcss`, `nanoid`, `brace-expansion@>=4` (Dependabot High).

## [1.9.3] - 2026-08-19

### Security

- **CI:** run `composer audit --locked` after dependency install (REQ-SEC / P3).

## [1.9.2] - 2026-08-19

### Fixed

- **Consent profile lookups:** memoize `CookieConsentConfigRepository` enabled/default queries and `CookieConsentConfigResolver::resolve()` per request so the modal sub-request and Twig helpers do not repeat `findDefaultEnabled` against Doctrine. The consent fragment controller reuses `nowo_cookie_consent_config` from the main request when present; runtime caches are cleared on admin profile writes.

[1.9.2]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.9.2

## [1.9.1] - 2026-08-18

### Changed

- **Demos:** pin `nowo-tech/hot-reload-bundle` to `^1.4` with FrankenPHP Mercure/`hot_reload` (`dev`/`test` only).

[1.9.1]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.9.1
## [1.9.0] - 2026-08-15

### Added

- **Standalone modal CSS** `nowo-cookie-consent.css` in `src/Resources/public/` (emitted by Vite build). Hosts can `<link>` it via the `nowo_cookie_consent` asset package.
- **CSP-safe external CSS mode**: `nowo-consent-modal.js` skips injecting a runtime `<style>` block when `link[data-nowo-cookie-consent-css]` is present, or when `data-nowo-cookie-consent-external-css="true"` is set on `<html>`, or `data-nowo-external-css="true"` on `#cookieconsent`.
- Upstream Beacon skin polish in `cookie-consent.css`: category tint cards, ghost secondary actions, box layout side inset, preferences intro spacing, `html[data-theme]` light/dark token remaps, equal-weight button grid.

### Changed

- New `CookieConsentConfig` entity defaults for **new** DB profiles: consent/preferences modal **bottom + left**, **equal-weight buttons** on (existing rows unchanged).
- Config API payload fallbacks align with those layout defaults when no DB config is resolved.

### Documentation

- CONFIGURATION / USAGE / INSTALLATION: CSP `style-src` nonce guidance and recommended layout defaults.
- UPGRADING notes for linking kit CSS and optional removal of host-only consent SCSS.

## [1.8.0] - 2026-08-15

### Added
- **`CookieConsentSchemaReadySubscriber`**: when SiteBackup reports the MySQL schema exists but `dashboard_cookie_config` is missing (post-`database_create`, pre-migrations), sets `_nowo_cookie_consent_schema_ready=false` so Twig/listeners skip Doctrine-backed consent.
- `CookieConsentConfig::TABLE_NAME` constant for schema probes.

### Documentation
- UPGRADING / CHANGELOG notes for mid-migration cold-start.

## [1.7.0] - 2026-08-15

### Added

- Cold-start skip for database-backed consent: when the main request carries `_nowo_site_backup_schema_exists: false` (shared with Site Backup Bundle) or `_nowo_cookie_consent_schema_ready: false`, config resolution, form handling, and `nowo_cookie_consent_should_render()` skip Doctrine work. See `Nowo\CookieConsentBundle\Http\ColdStartRequestAttributes`.

### Documentation

- Site Backup interoperability and cold-start attributes documented in UPGRADING, CONFIGURATION, and USAGE.

## [1.6.3] - 2026-08-11

### Added

- `render_routes`: optional whitelist of route name patterns; when non-empty the consent fragment renders only on matches (ideal for public-only shells). Still respects `skip_render_routes` (deny wins).

### Documentation

- Documented public whitelist usage in CONFIGURATION and UPGRADING.

[1.9.0]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.9.0

[1.8.0]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.8.0

[1.7.0]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.7.0

[1.6.3]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.6.3

## [1.6.2] - 2026-08-11

### Fixed

- Cookie inventory Doctrine queries: memoize `listForLocale` / definition loads per request, use `existsByConfig` (COUNT) for presence checks instead of loading all rows twice, attach inventory once on the consent form view, and filter category tables from that list in form themes.

### Documentation

- Noted inventory query reduction in UPGRADING / RELEASE history.

[1.6.2]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.6.2

## [1.6.1] - 2026-08-11

### Added

- `skip_render_routes`: route name patterns (fnmatch wildcards) where `CookieConsentController::show` returns an empty private response and skips config/form/inventory work. Twig helper `nowo_cookie_consent_should_render()` lets hosts omit the ESI/`render()` sub-request on authenticated dashboards.

### Documentation

- Documented `skip_render_routes` in CONFIGURATION and UPGRADING.

[1.6.1]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.6.1

## [1.6.0] - 2026-08-04

### Changed

- **FormKitBundle:** depend on [`nowo-tech/form-kit-bundle`](https://github.com/nowo-tech/FormKitBundle) ^2.0. Admin form types use `FormOptionsTrait` + profile `cookie_consent` (`#[FormKitConfig]`). Extension prepends that profile when missing; form types are tagged `form.type` so `FormOptionsMerger` is injected.
- **UiKit:** Admin templates use `ui.btn` / `ui.row_actions` macros with `nowo_cookie_consent_css_framework` instead of hard-coded Bootstrap button classes.

### Added
- **REQ-TWIG-004:** require `twig/extra-bundle` + `twig/string-extra`; `make check-twig-extra` in `release-check`; demos register `TwigExtraBundle`.
- **Twig-CS-Fixer:** `vincentlanglet/twig-cs-fixer`, `.twig-cs-fixer.php`, `composer twig:lint` / `twig:fix`.

### Changed

- **REQ-UI-001-kit:** Admin `nowo-ui.css` now loads from UiKit (`asset('css/nowo-ui.css', 'nowo_ui_kit')`). Removed the forked `src/Resources/public/css/nowo-ui.css`. Requires `nowo-tech/ui-kit-bundle` `^1.4`. Cookie-consent modal assets (`cookieconsent.css`, `nowo-cc-themes.css`, `nowo-consent-modal.js`) stay on the `nowo_cookie_consent` package. Extension seeds `nowo_ui_kit` defaults from `web_ui.css_framework` / `icon_set` when the host has not configured UiKit. Admin `base.html.twig` imports `@NowoUiKitBundle/macros/ui.html.twig` (flashes via `ui.flash`, cookie-definition index create via `ui.btn`); duplicate inline flash loops removed from child templates.
- **REQ-UI-001 feedback:** Cookie inventory delete uses UiKit `_confirm.html.twig` + `nowo-ui-confirm.js` (POST+CSRF in dialog footer) instead of native `confirm()`.
- **REQ-TWIG-003:** Cookie definition admin form renders fields via `{% for child in form %}` / `form_row(child)`.

[1.6.0]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.6.0

## [1.5.2] - 2026-08-03

### Fixed

- Demo FrankenPHP stacks: install `nowo-tech/twig-inspector-bundle` from Packagist instead of a monorepo sibling path mount, so `make demo-smoke` / CI standalone checkouts no longer crash with `service "php" is not running` during entrypoint `composer install`.

### Documentation

- [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md) — Twig Inspector via Packagist; drop sibling-path troubleshooting tip
- [UPGRADING.md](UPGRADING.md) / [RELEASE.md](RELEASE.md) — 1.5.2 notes

## [1.5.1] - 2026-08-03

### Fixed

- **REQ-UI-001**: `admin/base.html.twig` stacks host `javascripts` with `{{ parent() }}` (stylesheets already did); legacy `admin/*/layout.html.twig` BC aliases extend `admin/base.html.twig` instead of hardcoding the demo layout.

### Changed

- Default `admin/layout.html.twig` keeps CDN Bootstrap in `stylesheets` / `javascripts` directly so `parent()` stacking from `admin/base.html.twig` does not drop host or demo assets.

### Documentation

- [CONFIGURATION.md](CONFIGURATION.md) / [USAGE.md](USAGE.md) — base shell stacking on both asset blocks; BC alias targets.

## [1.5.0] - 2026-08-01

### Added

- **Route-based settings sections** — `/cookie-consent-config/{id}/settings/{section}` (`nowo_cookie_consent_config_settings_section`) for Profile, Behavior, Appearance, Consent modal, Preferences modal, and Auto-show targeting. Each tab has its own FormType under `Form/Settings/` (resolved via `CookieConsentConfigSettingsSection::formType()`).
- Admin area nav partial `admin/_admin_area_nav.html.twig` (Settings | Cookie inventory) and section tabs `admin/config/_settings_section_tabs.html.twig`.
- Semantic `.nowo-ui-tabs` styles in `nowo-ui.css` for hosts using `web_ui.css_framework: custom` / `tailwind` / `none`.

### Changed

- `GET /cookie-consent-config/{id}/settings` (`nowo_cookie_consent_config_settings_edit`) now **redirects** to `/settings/profile` (BC for existing bookmarks and host links).
- Settings Twig uses a **single** form card with the save button inside; section navigation is outside the card (no nested section cards).
- `CookieConsentConfigSettingsType` is a **deprecated** alias of `CookieConsentConfigFullSettingsType` (all sections) for demos / legacy single-page editors. Full settings keep the form name `cookie_consent_config_settings` via `getBlockPrefix()`.

### Documentation

- [CONFIGURATION.md](CONFIGURATION.md) / [UPGRADING.md](UPGRADING.md) — canonical settings URLs and host upgrade notes.

## [1.4.9] - 2026-08-01

### Fixed

- Admin `base.html.twig` injects `nowo-ui.css` via host `stylesheets` / `parent()` when `web_ui.css_framework` is non-Bootstrap (host `layout_template` path).
### Added

- `src/Resources/public/css/nowo-ui.css` — standalone admin stylesheet activated when `web_ui.css_framework` is `custom`, `tailwind`, or `none`. Provides `--nowo-ui-*` CSS custom properties and semantic fallback styles for all Bootstrap class names used in admin templates (`.btn`, `.table`, `.card`, `.alert`, `.form-control`, `.badge`, `.container`, pagination, flex utilities) so the admin UI is usable without the Bootstrap CDN.
- Admin `layout.html.twig` now links `nowo-ui.css` (via the existing `nowo_cookie_consent` asset package) for any `css_framework` value that is not `bootstrap`, `bootstrap4`, `bootstrap5`, or `tabler`.

### Documentation

- [CONFIGURATION.md](CONFIGURATION.md) — new **`ui_theme` vs `web_ui.css_framework`** callout clarifying that these two options are independent (`ui_theme` controls the public modal markup; `web_ui.css_framework` controls the admin CSS stack). New **Using a custom CSS framework** section documents the `custom`/`tailwind`/`none` path, asset injection, and `--nowo-ui-*` token overrides.

## [1.4.8] - 2026-07-30

### Fixed

- Consent modal JS: prepare Symfony SameOrigin CSRF (double-submit cookie + `csrf-token` header) before XHR so hosts can keep `csrf_protection: true` without relying on native `submit` / Stimulus.

### Documentation

- [SECURITY.md](SECURITY.md), [USAGE.md](USAGE.md), [CONFIGURATION.md](CONFIGURATION.md) — document XHR CSRF double-submit path

## [1.4.7] - 2026-07-30

### Fixed

- Consent modal Twig: detect CSRF via `form.children._token` (Twig `form._token is defined` still accesses the missing field).

## [1.4.6] - 2026-07-30

### Fixed

- Consent modal Twig: render CSRF field only when `form._token` is defined (hosts with `csrf_protection: false`).

## [1.4.5] - 2026-07-30

### Changed

- **Twig overrides (REQ-TWIG-001)**: `TwigPathsPass` now `prependPath`s `templates/bundles/NowoCookieConsentBundle/` when present (AuthKit-compatible), so hosts no longer need a manual `twig.paths` entry for the namespace.
- **Tailwind modal / form theme**: dropped hard-coded indigo/slate/white utility colors; buttons and chrome use BEM + `--nowo-cc-*` tokens from the inlined `cookie-consent.css` (including `--nowo-cc-link` and panel/section borders).

### Documentation

- [USAGE.md](USAGE.md) — clarify `prependPath` behaviour for app overrides
- [UPGRADING.md](UPGRADING.md) — To 1.4.5 notes
- [RELEASE.md](RELEASE.md) — release history row

## [1.4.4] - 2026-07-30

### Changed

- **Dependencies**: Root and demo `composer.lock` refreshed (Symfony components **7.4.15** / demo apps **8.1.2**); demo `config/reference.php` regenerated.

### Documentation

- [UPGRADING.md](UPGRADING.md) — upgrade path from 1.4.3
- [RELEASE.md](RELEASE.md) — release history entry

[1.4.4]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.4.4

## [1.4.3] - 2026-07-30

### Added

- **Admin Twig (REQ-UI-001 / REQ-TWIG-001)**: `admin/base.html.twig` intermediate shell; admin pages extend it so host chrome stays on `web_ui.layout_template`.

### Changed

- Admin CRUD/settings templates extend `@NowoCookieConsentBundle/admin/base.html.twig` instead of inlining the layout global.
- README documentation links moved below Quick start / FrankenPHP demo.

### Documentation

- [USAGE.md](USAGE.md) — Overriding templates (REQ-TWIG-001) procedure, freeze rule, and overridable `<subpath>` table (includes `admin/base.html.twig`)
- [CONFIGURATION.md](CONFIGURATION.md) — Twig overrides section points to USAGE
- [UPGRADING.md](UPGRADING.md) — upgrade path from 1.4.2
- [RELEASE.md](RELEASE.md) — release history entry

[1.4.3]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.4.3

## [1.4.2] - 2026-07-29

### Changed

- **Tooling (REQ-MAKE-010)**: Root and demo Makefiles prefer `docker compose` (V2) and fall back to `docker-compose` (V1); demo recipes invoke Compose via a shell helper to avoid WSL Make `execve` EACCES on the Docker Desktop CLI symlink.
- **Tooling (REQ-MAKE-009)**: Monorepo `update-deps` Makefile includes are optional (`-include`) so standalone GitHub Actions checkouts do not fail.

### Documentation

- [UPGRADING.md](UPGRADING.md) — upgrade path from 1.4.1
- [RELEASE.md](RELEASE.md) — release history entry

[1.4.2]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.4.2

## [1.4.1] - 2026-07-28

### Added

- **Docs (REQ-DOCS-005 / REQ-TEST-003)**: Table of contents on long docs; `docs/COVERAGE.md` documents the PHPUnit coverage gate (100% lines on `src/`).
- **Spec Kit (REQ-SPECKIT-001 / REQ-SPECKIT-003)**: Full `src/` inventory (~107 files); `FR-SEC-001` / Twig admin / DI clock notes in baseline `spec.md`.
- **Tooling (REQ-REL-003 / REQ-MAKE-002 / REQ-TEST-003)**: `make check-open-prs`, `make coverage-check` (fail-under 99%), wired into `release-check`.
- **Tests**: Unit coverage for `CookieConsentSecurityPass`, access-checker DI branches, admin access subscriber events, definition pagination/count, admin index page clamp.

### Changed

- **CI / PHPUnit (REQ-SF-005)**: `SYMFONY_DEPRECATIONS_HELPER=max[direct]=0` in `phpunit.xml.dist` and CI test jobs.
- **Demos (REQ-DEMO-010 / REQ-GITIGNORE-003)**: `/.pnpm-store` in demo `.gitignore` files; demo locks pull `psr/clock`; `release-verify` follows redirects (`curl -sL`) for locale root; Tailwind migrations use `demo_tailwind_dashboard_*` table names; demo functional tests updated for DomCrawler checkboxes, playground copy, and Tailwind selectors.
- **DI**: Drop unused `_defaults.bind` `$httpOnly` (explicit on `CookieHandler`).
- **Tooling**: `check-open-prs` resolves `owner/repo` from `origin` when `gh` cannot map the git remote; `make coverage-check` reads `coverage-output.txt`.
- **Dependencies**: Closed Dependabot PRs #15 / #16 (conflicted happy-dom; TypeScript major) pending a deliberate upgrade path.
- README coverage badge and Tests section aligned to **100%** PHP lines on `src/` (`make coverage-check`).
- **Rector**: `CookieDefinitionAdminController::delete` return type narrowed to `RedirectResponse`.

### Fixed

- Tailwind consent modal no longer double-renders the `cookies` form field when granular selection is enabled (aligns with Bootstrap template + `_preference_sections`).

### Documentation

- [COVERAGE.md](COVERAGE.md) — PHPUnit coverage gate
- [UPGRADING.md](UPGRADING.md) — upgrade path from 1.4.0
- [RELEASE.md](RELEASE.md) — release history entry; TOC

[1.4.1]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.4.1

## [1.4.0] - 2026-07-27

### Added

- **Admin Web UI (REQ-UI-001 / REQ-UI-002)**: `web_ui` (`enabled`, `path_prefix`, `layout_template`, `css_framework`, `icon_set`, `list_page_size`), Twig globals, semantic `nowo-ui-*` hooks; `security.access_roles` + `CookieConsentAccessCheckerInterface` + admin access subscriber; host firewall guidance.
- **Pagination (REQ-PERF-001)**: Cookie definition admin index paginated via `web_ui.list_page_size` (default `20`) with translation eager-loading.
- **DI (REQ-DI-001)**: `psr/clock` + `SystemClock`; `CookieHandler` / `CookieLogger` use `ClockInterface`; optional PSR-3 logger on consent persist (no raw IP).
- **Dependencies**: `psr/log`, `symfony/security-core`, `symfony/security-csrf` declared in `require`.
- **CI**: Dedicated PHPStan job; `demo-smoke` workflow (REQ-TEST-011) wrapping `demo/Makefile` `release-verify`.

### Changed

- Configuration nodes `categories`, `disabled_routes`, `target_routes`, `enabled_locales`, and `preference_sections` are typed `arrayNode`s.
- Admin controllers are `final`; settings admin unchanged functionally.
- Demo configs set `security.allow_unauthenticated: true` (demo-only).
- `make release-check-demos` fails when demo release-check fails (no longer swallowed with `|| true`).
- Demo smoke (**REQ-TEST-011**) via `make demo-smoke` / `demo/Makefile` `release-verify` (HTTP 200) as part of `release-check`.

### Documentation

- [CONFIGURATION.md](CONFIGURATION.md) — Admin Web UI, security, Twig override table updates
- [USAGE.md](USAGE.md) — `layout_template` / access control pointers
- [UPGRADING.md](UPGRADING.md) — upgrade path from 1.3.6
- [SECURITY.md](SECURITY.md) — bundle-level admin access checker

[1.4.0]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.4.0

## [1.3.6] - 2026-07-27

### Added

- FrankenPHP Friendly Worker Mode banner in README + `docs/images/frankenphp-friendly.png` (**REQ-DOCS-017**)
- Demo `restart` targets; root `down-dev` alias; `demo/Makefile` aggregate `up` / `down` / `update-bundle` (**REQ-MAKE-003**, **REQ-MAKE-007**)

### Changed

- Split multi-class PHPUnit files so granular/marketing/translation tests are discovered (**211** tests)
- Expanded unit coverage to **≥99.95%** PHP line coverage (**REQ-TEST-003**)
- English-only sample strings in `docs/CONFIGURATION.md` translation override example (**REQ-DOCS-016**)

No configuration or public API breaking changes for bundle consumers.

### Documentation

- [UPGRADING.md](UPGRADING.md) — upgrade path from 1.3.5
- [RELEASE.md](RELEASE.md) — release history entry; coverage goals aligned to ≥99% PHP lines
- [README.md](../README.md) — coverage badge and Tests section percentages

[1.3.6]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.3.6

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
