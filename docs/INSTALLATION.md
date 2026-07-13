# Installation

## Requirements

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

This publishes `src/Resources/public` to `public/bundles/nowocookieconsent/` (`nowo-consent-modal.js`; styles injected at runtime). Templates load it via `asset('nowo-consent-modal.js', 'nowo_cookie_consent')`.

### AssetMapper

If your app uses [Symfony AssetMapper](https://symfony.com/doc/current/frontend/asset_mapper.html), the bundle registers the `nowo_cookie_consent` asset package. Run `assets:install` once so `nowo-consent-modal.js` is published to `public/bundles/nowocookieconsent/`.

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
