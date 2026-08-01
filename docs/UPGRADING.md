# Upgrade Guide

This guide provides step-by-step instructions for upgrading Cookie Consent Bundle between versions.

## Table of contents

- [General upgrade process](#general-upgrade-process)
- [To 1.5.0](#to-150)
- [To 1.4.8](#to-148)
- [To 1.4.5](#to-145)
  - [Breaking changes](#breaking-changes)
- [To 1.4.4](#to-144)
  - [Breaking changes](#breaking-changes)
- [To 1.4.3](#to-143)
  - [Breaking changes](#breaking-changes)
- [To 1.4.2](#to-142)
  - [Breaking changes](#breaking-changes)
- [To 1.4.1](#to-141)
  - [Breaking changes](#breaking-changes)
- [To 1.4.0](#to-140)
  - [Configuration](#configuration)
  - [Breaking changes](#breaking-changes)
- [To 1.3.6](#to-136)
  - [Breaking changes](#breaking-changes)
- [To 1.3.5](#to-135)
  - [Breaking changes](#breaking-changes)
- [To 1.3.4](#to-134)
  - [Breaking changes](#breaking-changes)
- [To 1.3.3](#to-133)
  - [Breaking changes](#breaking-changes)
- [To 1.3.2](#to-132)
  - [Breaking changes](#breaking-changes)
- [To 1.3.1](#to-131)
  - [Breaking changes](#breaking-changes)
- [To 1.3.0](#to-130)
  - [Breaking changes](#breaking-changes)
- [UI theme changes](#ui-theme-changes)
- [To 1.2.0](#to-120)
  - [New optional configuration](#new-optional-configuration)
  - [Profile settings admin](#profile-settings-admin)
  - [Frontend assets](#frontend-assets)
  - [Breaking changes](#breaking-changes)
- [To 1.1.1](#to-111)
  - [Breaking changes](#breaking-changes)
- [To 1.1.0](#to-110)
  - [New optional configuration](#new-optional-configuration)
  - [Cookie inventory entities](#cookie-inventory-entities)
  - [Frontend assets](#frontend-assets)
  - [Twig embed change (recommended)](#twig-embed-change-recommended)
  - [Breaking changes](#breaking-changes)
- [To 1.0.0 (initial release)](#to-100-initial-release)
  - [Requirements](#requirements)
  - [Enable and configure](#enable-and-configure)
  - [Breaking changes](#breaking-changes)
- [Future versions](#future-versions)
- [Getting help](#getting-help)

## General upgrade process

1. **Backup** your `config/packages/nowo_cookie_consent.yaml` (and any Doctrine config entities if you use `use_database_config`)
2. **Review** [CHANGELOG.md](CHANGELOG.md) for breaking changes
3. **Update**: `composer update nowo-tech/cookie-consent-bundle`
4. **Clear cache**: `php bin/console cache:clear`
5. **Rebuild assets** if you ship the bundled JS: `php bin/console assets:install`
6. **Test** the consent modal and logging in your environments

## To 1.5.0

```bash
composer update nowo-tech/cookie-consent-bundle
php bin/console cache:clear
php bin/console assets:install
```

Settings admin is split into **route-based sections**:

- `GET /cookie-consent-config/{id}/settings` still works and redirects to `/settings/profile` (`nowo_cookie_consent_config_settings_edit`).
- Each section is edited at `/cookie-consent-config/{id}/settings/{section}` (`nowo_cookie_consent_config_settings_section`).
- `CookieConsentConfigSettingsType` now scopes fields via the `section` form option (enum or slug). Creating the form without options still defaults to **profile**.

Hosts that forked `admin/config/settings.html.twig` should rebase on the new single-card + tab partials, or drop the fork and restyle `.nowo-ui-tabs` / `.nowo-ui-form-wrap` from the host layout.

### Breaking changes

- Submitting the old monolithic settings form in one POST is no longer possible (fields are per section). Deep links that assumed a single scrollable page should point at the relevant `{section}` slug instead.

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

Admin Twig templates now extend `nowo_cookie_consent_layout_template`. Legacy `admin/cookie_definition/layout.html.twig` and `admin/config/layout.html.twig` remain as BC aliases.

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
