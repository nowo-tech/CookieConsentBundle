# Cookie Consent Bundle

[![CI](https://github.com/nowo-tech/CookieConsentBundle/actions/workflows/ci.yml/badge.svg)](https://github.com/nowo-tech/CookieConsentBundle/actions/workflows/ci.yml) [![Packagist Version](https://img.shields.io/packagist/v/nowo-tech/cookie-consent-bundle.svg?style=flat)](https://packagist.org/packages/nowo-tech/cookie-consent-bundle) [![Packagist Downloads](https://img.shields.io/packagist/dt/nowo-tech/cookie-consent-bundle.svg)](https://packagist.org/packages/nowo-tech/cookie-consent-bundle) [![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE) [![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php)](https://php.net) [![Symfony](https://img.shields.io/badge/Symfony-6%2B%20%7C%207.4%20%7C%208.0%20%7C%208.1%2B-000000?logo=symfony)](https://symfony.com) [![GitHub stars](https://img.shields.io/github/stars/nowo-tech/cookie-consent-bundle.svg?style=social&label=Star)](https://github.com/nowo-tech/CookieConsentBundle) [![Coverage](https://img.shields.io/badge/Coverage-100%25-brightgreen)](#tests-and-coverage)

> ⭐ **Found this useful?** [Install from Packagist](https://packagist.org/packages/nowo-tech/cookie-consent-bundle) · Give it a **star** on [GitHub](https://github.com/nowo-tech/CookieConsentBundle) so more developers can find it.

Symfony bundle that renders a GDPR cookie consent modal with category toggles, optional per-cookie selection, cookie inventory, AJAX form submission, optional consent logging, and configurable Doctrine table prefix.

Frontend behavior is implemented in TypeScript and built with Vite (`make assets` → `src/Resources/public/nowo-consent-modal.js`).

Extracted and modernized from the [podologiapriego.com](https://github.com) implementation (FatalNetwork/ConnectHolland CookieConsentBundle fork).

## Documentation

- [Installation](docs/INSTALLATION.md)
- [Configuration](docs/CONFIGURATION.md)
- [Usage](docs/USAGE.md)
- [Contributing](docs/CONTRIBUTING.md)
- [Changelog](docs/CHANGELOG.md)
- [Upgrading](docs/UPGRADING.md)
- [Release](docs/RELEASE.md)
- [Security](docs/SECURITY.md)
- [Engram](docs/ENGRAM.md)
- [Spec-driven development](docs/SPEC-DRIVEN-DEVELOPMENT.md)

### Additional documentation

- [Demo with FrankenPHP](docs/DEMO-FRANKENPHP.md)

## Quick start

```bash
composer require nowo-tech/cookie-consent-bundle
```

```yaml
# config/packages/nowo_cookie_consent.yaml
nowo_cookie_consent:
    table_prefix: 'app_'   # optional; yields app_nowo_cookie_consent_log
    use_logger: true
```

```twig
{# templates/base.html.twig #}
{% if nowo_cookie_consent_should_embed_modal() %}
    {{ render(path('nowo_cookie_consent.show_if_not_set')) }}
{% endif %}
```

Install public assets:

```bash
php bin/console assets:install
```

## Demo

```bash
make -C demo up-symfony8
# Bootstrap demo: http://localhost:8014

make -C demo up-symfony8-tailwind
# Tailwind demo: http://localhost:8015
```

- `demo/symfony8/` — FrankenPHP Symfony 8 app with Bootstrap 5
- `demo/symfony8-tailwind/` — same demo with Tailwind CSS and `ui_theme: tailwind`

See [Demo with FrankenPHP](docs/DEMO-FRANKENPHP.md) for development vs. production worker mode.

## Tests and coverage

| Language | Lines (approx.) | Command |
| --- | --- | --- |
| PHP | ~100% | `make test-coverage` |
| TypeScript | ~94% | `make test-ts` |

```bash
make test
make test-coverage
make test-ts
make assets
make release-check
```

PHP coverage target is ~100% (currently ~100%; see [Release](docs/RELEASE.md)); exclusions must be justified in `phpunit.xml.dist`. TypeScript coverage enforces a minimum of 90% (Vitest thresholds + `.scripts/ts-coverage-percent.sh`).
