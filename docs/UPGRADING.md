# Upgrade Guide

This guide provides step-by-step instructions for upgrading Cookie Consent Bundle between versions.

## General upgrade process

1. **Backup** your `config/packages/nowo_cookie_consent.yaml` (and any Doctrine config entities if you use `use_database_config`)
2. **Review** [CHANGELOG.md](CHANGELOG.md) for breaking changes
3. **Update**: `composer update nowo-tech/cookie-consent-bundle`
4. **Clear cache**: `php bin/console cache:clear`
5. **Rebuild assets** if you ship the bundled JS: `php bin/console assets:install`
6. **Test** the consent modal and logging in your environments

## Database migrations

When `use_logger` or `use_database_config` is enabled, ensure Doctrine migrations for bundle tables are applied after upgrading. Table names respect `table_prefix` (see [CONFIGURATION.md](CONFIGURATION.md)).

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

- `{prefix}nowo_cookie_consent_cookie_definition` (includes `allowed_by_default`)
- `{prefix}nowo_cookie_consent_cookie_definition_translation`

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
