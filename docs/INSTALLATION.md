# Installation

## Table of contents

- [Requirements](#requirements)
- [Composer](#composer)
- [Enable the bundle](#enable-the-bundle)
  - [With Symfony Flex](#with-symfony-flex)
  - [Without Flex](#without-flex)
- [Routes](#routes)
- [Assets](#assets)
  - [AssetMapper](#assetmapper)
- [Database](#database)

## Requirements

- **FormKitBundle** (`nowo-tech/form-kit-bundle` ^2.0) — dashboard/admin Symfony forms (`FormOptionsTrait`, profile `cookie_consent`). Register `NowoFormKitBundle` in `config/bundles.php` (Flex / demo). Optional host YAML: `config/packages/nowo_form_kit.yaml`.

- PHP `>=8.1` (<8.6). Symfony **8.0** and **8.1** require **PHP 8.4+**.
- Symfony **7.4**, **8.0**, or **8.1** (minimum tested minors). The bundle also supports Symfony 6.x and 7.0–7.3 when constraints resolve.
- Doctrine ORM (when `use_logger: true` or `use_database_config: true`)

## Composer

```bash
composer require nowo-tech/cookie-consent-bundle
```

## Enable the bundle

### With Symfony Flex

The recipe enables the bundle, adds `config/packages/nowo_cookie_consent.yaml`, and imports routes in `config/routes/nowo_cookie_consent.yaml`. Adjust configuration as needed (see [Configuration](CONFIGURATION.md)).

### Without Flex

Register the bundle manually:

```php
// config/bundles.php
Nowo\CookieConsentBundle\NowoCookieConsentBundle::class => ['all' => true],
```

Create `config/packages/nowo_cookie_consent.yaml`:

```yaml
nowo_cookie_consent:
    use_logger: true
```

## Routes

```yaml
# config/routes/nowo_cookie_consent.yaml
nowo_cookie_consent:
    resource: '@NowoCookieConsentBundle/Resources/config/routing.yaml'
```

## Assets

```bash
php bin/console assets:install
```

This publishes `src/Resources/public` to `public/bundles/nowocookieconsent/` (`nowo-consent-modal.js`, `nowo-cookie-consent.css`). By default the JS injects modal styles at runtime. For CSP policies that use **style-src nonces** (injected `<style>` tags are ignored), link the standalone CSS instead:

```twig
<link rel="stylesheet"
      href="{{ asset('nowo-cookie-consent.css', 'nowo_cookie_consent') }}"
      data-nowo-cookie-consent-css>
<script src="{{ asset('nowo-consent-modal.js', 'nowo_cookie_consent') }}" defer></script>
```

The `data-nowo-cookie-consent-css` marker tells the modal script to skip `<style>` injection. Templates that use the bundled fragment already load the JS via `asset('nowo-consent-modal.js', 'nowo_cookie_consent')`; add the `<link>` in your layout `<head>` when needed.

### AssetMapper

If your app uses [Symfony AssetMapper](https://symfony.com/doc/current/frontend/asset_mapper.html), the bundle registers the `nowo_cookie_consent` asset package. Run `assets:install` once so `nowo-consent-modal.js` and `nowo-cookie-consent.css` are published to `public/bundles/nowocookieconsent/`.

Contributors rebuild frontend assets with:

```bash
make assets
```

## Database

When logging is enabled, create the schema for `Nowo\CookieConsentBundle\Entity\CookieConsentLog`:

```bash
php bin/console doctrine:schema:update --force
```

Or generate a migration in your application.

Default table name: `dashboard_cookie_log`. Use `doctrine.table_prefix` in configuration to namespace tables per application.

When `use_database_config` and `use_cookie_inventory` are enabled, also create:

- `{prefix}dashboard_cookie_config`
- `{prefix}dashboard_cookie_config_translation`
- `{prefix}dashboard_cookie_definition`
- `{prefix}dashboard_cookie_definition_translation`

## Twig Extra Bundle (REQ-TWIG-004)

This package ships Twig templates. Host applications **must** install and enable Twig Extra:

```bash
composer require twig/extra-bundle twig/string-extra
```

Register `Twig\Extra\TwigExtraBundle\TwigExtraBundle` in `config/bundles.php` (Flex usually does this). Demos already include the same stack. The package `release-check` runs `make check-twig-extra` to guard this contract.

## UiKit Bundle (REQ-UI-001)

Admin pages compose **[UiKitBundle](https://github.com/nowo-tech/UiKitBundle)** (`nowo-tech/ui-kit-bundle` `^1.4`, required by this package). Hosts get it transitively; register the bundle when not using Flex auto-discovery:

```php
// config/bundles.php
Nowo\UiKitBundle\NowoUiKitBundle::class => ['all' => true],
```

Then install public assets (UiKit `nowo_ui_kit` package + this bundle’s modal assets):

```bash
php bin/console assets:install
```

Admin shells load UiKit `nowo-ui.css` / `nowo-ui-confirm.js` via `admin/base.html.twig`. Cookie-consent modal CSS/JS stay on the `nowo_cookie_consent` asset package.
