# Cookie Consent Bundle

[![CI](https://github.com/nowo-tech/CookieConsentBundle/actions/workflows/ci.yml/badge.svg)](https://github.com/nowo-tech/CookieConsentBundle/actions/workflows/ci.yml) [![Packagist Version](https://img.shields.io/packagist/v/nowo-tech/cookie-consent-bundle.svg?style=flat)](https://packagist.org/packages/nowo-tech/cookie-consent-bundle) [![Packagist Downloads](https://img.shields.io/packagist/dt/nowo-tech/cookie-consent-bundle.svg)](https://packagist.org/packages/nowo-tech/cookie-consent-bundle) [![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE) [![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php)](https://php.net) [![Symfony](https://img.shields.io/badge/Symfony-7.4%20%7C%208.0%20%7C%208.1%2B-000000?logo=symfony)](https://symfony.com) [![GitHub stars](https://img.shields.io/github/stars/nowo-tech/cookie-consent-bundle.svg?style=social&label=Star)](https://github.com/nowo-tech/CookieConsentBundle) [![Coverage](https://img.shields.io/badge/Coverage-99.39%25-brightgreen)](#tests-and-coverage)

> ⭐ **Found this useful?** [Install from Packagist](https://packagist.org/packages/nowo-tech/cookie-consent-bundle) · Give it a **star** on [GitHub](https://github.com/nowo-tech/CookieConsentBundle) so more developers can find it.

Symfony bundle that renders a GDPR cookie consent modal with category toggles, optional per-cookie selection, cookie inventory, AJAX form submission, optional consent logging, and configurable Doctrine table prefix.
Frontend behavior is implemented in TypeScript and built with Vite (`make assets` → `src/Resources/public/nowo-consent-modal.js`).

![FrankenPHP Friendly Worker Mode](docs/images/frankenphp-friendly.png)

This bundle is **FrankenPHP worker mode friendly**, including when the kernel is **not** reset between requests (`reset_kernel false`). See the [FrankenPHP worker audit](docs/FRANKENPHP-WORKER-AUDIT.md).

<table>
  <tr>
    <td align="center" valign="top" width="33%">
      <img src="docs/images/demo/overview.webp" alt="Demo home page with the two-step consent banner (dark-turquoise theme): Manage preferences, Reject all and Accept all buttons over the page overlay" />
      <br /><sub>Consent banner — two-step modal with page overlay</sub>
    </td>
    <td align="center" valign="top" width="33%">
      <img src="docs/images/demo/admin-cookie-definitions.webp" alt="Cookie inventory admin list: name, provider, purpose, category, duration, allowed-by-default badge and row actions, paginated" />
      <br /><sub>Admin — cookie inventory per consent profile</sub>
    </td>
    <td align="center" valign="top" width="33%">
      <img src="docs/images/demo/admin-settings.webp" alt="Consent profile settings with section tabs, Appearance tab open: colour theme, page overlay, dark mode, transitions and two-step modal options" />
      <br /><sub>Admin — profile settings with section tabs</sub>
    </td>
  </tr>
</table>


## Features

- **GDPR modal** — category toggles, optional per-cookie selection, AJAX submit, TypeScript + Vite (`nowo-consent-modal.js`) and standalone CSS (`nowo-cookie-consent.css`, CSP-friendly).
- **Themes** — `ui_theme: bootstrap` (default) or `tailwind`; dual FrankenPHP demos.
- **Cookie inventory** — definitions in YAML and/or Doctrine; optional consent logging with configurable table prefix.
- **Admin UI** — `/cookie-consent-config` (profile, behavior, appearance, modals, route targeting) plus CRUD for cookie definitions; built on the UiKit 2.0 page scaffolds (REQ-UI-003): themed once through `nowo_ui_kit` (layouts, theme slots, tokens) like every Nowo bundle; no CDN assets ([TEMPLATES](docs/TEMPLATES.md)).
- **Preferences bubble** — reopen consent after the first choice.
- **Route targeting** — `render_routes` / `skip_render_routes`; helpers `nowo_cookie_consent_should_render()` / `nowo_cookie_consent_render()`.
- **Database config** — `use_database_config` so the admin UI can override YAML at runtime; profile copy via `nowo_cookie_consent_trans()`.
- **Cold-start** — cooperates with SiteBackupBundle when the schema is not ready yet; the "table exists" probe is memoized per worker (`schema_ready_cache_ttl`, default 60 s) so public pages skip it after warm-up.
- **CSP nonce** — bundle `<script>` tags carry the request attribute `csp_nonce` (nowo-tech convention); JS-injected `<style>` copies the loader script nonce; no inline handlers or inline `style` attributes.
- **Worker-safe** — RequestStack / ManagerRegistry per call; runtime memos cleared on every main request.

## Quick start

```bash
composer require nowo-tech/cookie-consent-bundle
```

```yaml
# config/packages/nowo_cookie_consent.yaml
nowo_cookie_consent:
    doctrine:
        table_prefix: 'app_'   # optional; yields app_dashboard_cookie_log
    use_logger: true
```

```twig
{# templates/base.html.twig — preferred: no kernel sub-request #}
{% if nowo_cookie_consent_should_render() %}
    {{ nowo_cookie_consent_render() }}
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

## Documentation

- [Installation](docs/INSTALLATION.md)
- [Configuration](docs/CONFIGURATION.md)
- [Usage](docs/USAGE.md)
- [Contributing](docs/CONTRIBUTING.md)
- [Code of Conduct](CODE_OF_CONDUCT.md)
- [Changelog](docs/CHANGELOG.md)
- [Upgrading](docs/UPGRADING.md)
- [Release](docs/RELEASE.md)
- [Security](docs/SECURITY.md)
- [Engram](docs/ENGRAM.md)
- [Spec-driven development](docs/SPEC-DRIVEN-DEVELOPMENT.md)
- [GitHub Spec Kit](docs/SPEC-KIT.md)

### Additional documentation

- [PSR evaluation (REQ-CS-007)](docs/PSR.md)
- [Demo with FrankenPHP](docs/DEMO-FRANKENPHP.md)
- [FrankenPHP worker audit](docs/FRANKENPHP-WORKER-AUDIT.md)
- [GitHub Actions CI requirements](docs/GITHUB_CI.md)

## Tests and coverage

| Language | Lines (approx.) | Command |
| --- | --- | --- |
| PHP | **99.39%** line coverage on `src/` (gate ≥99%; run `make test-coverage` to refresh). See [`docs/COVERAGE.md`](docs/COVERAGE.md). | `make test-coverage` |
| TypeScript | **97.19%** lines (min of statements/branches/functions/lines: **90.34%**, gate ≥90%) | `make test-ts` |

```bash
make test
make coverage-check
make test-ts
make assets
make release-check
```

PHP coverage target is **≥99%** lines (prefer 100%; see [Release](docs/RELEASE.md) and [`docs/COVERAGE.md`](docs/COVERAGE.md)). TypeScript coverage enforces a minimum of 90% (Vitest thresholds + `.scripts/ts-coverage-percent.sh`).

## License

This bundle is released under the [MIT License](LICENSE).
