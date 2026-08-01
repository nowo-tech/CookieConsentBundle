# Release

This checklist helps maintainers prepare and publish a release safely.

## Table of contents

- [Pre-release](#pre-release)
- [Security checklist (12.4.1)](#security-checklist-1241)
- [Tag and publish](#tag-and-publish)
- [Post-release checks](#post-release-checks)
- [Coverage goals](#coverage-goals)
- [Release history](#release-history)

## Pre-release

Run the full release pipeline:

```bash
make release-check
```

Expected steps:

- Asset build (`pnpm run build`)
- Composer validation and lock sync
- Code style checks
- Static analysis (Rector dry run + PHPStan)
- PHP and TypeScript test suites with coverage
- Demo verification (`demo/Makefile` `release-check`)

## Security checklist (12.4.1)

Before tagging, confirm each item in [SECURITY.md — Release security checklist](SECURITY.md#release-security-checklist-1241). Note confirmation in the release PR or tag message.

## Tag and publish

1. Move `[Unreleased]` entries in `docs/CHANGELOG.md` to a new `## [X.Y.Z] - YYYY-MM-DD` section.
2. Update `docs/UPGRADING.md` if consumers must change code or configuration.
3. Create an **annotated** tag: `git tag -a vX.Y.Z -m "Release vX.Y.Z"`.
4. Push the tag: `git push origin vX.Y.Z`.
5. Confirm GitHub workflows `release.yml` and `sync-releases.yml` completed successfully.

## Post-release checks

- Verify Packagist metadata is updated.
- Confirm the GitHub release contains the tag message and changelog section.
- Validate installation in a clean Symfony app:

```bash
composer require nowo-tech/cookie-consent-bundle
```

- Smoke-test the consent modal (bootstrap and tailwind if applicable).

## Coverage goals

- **PHP**: **≥99%** line coverage (prefer **100%**; `make test-coverage`)
- **TypeScript**: **~94%** line coverage, **90%** minimum enforced (`make test-ts`)

Update README **Tests and coverage** percentages after each release when coverage changes materially.

## Release history

| Version | Date | Notes |
| --- | --- | --- |
| [1.5.0](CHANGELOG.md#150---2026-08-01) | 2026-08-01 | Route-based settings sections; one FormType per tab under `Form/Settings/`; admin area/section tabs; single form card |
| [1.4.9](CHANGELOG.md#149---2026-08-01) | 2026-08-01 | `nowo-ui.css` for custom/tailwind/none admin; inject from base when using host layout |
| [1.4.8](CHANGELOG.md#148---2026-07-30) | 2026-07-30 | Modal JS SameOrigin CSRF double-submit for XHR (`csrf_protection: true` safe with Stimulus-less posts) |
| [1.4.7](CHANGELOG.md#147---2026-07-30) | 2026-07-30 | Twig CSRF field via `form.children._token` |
| [1.4.6](CHANGELOG.md#146---2026-07-30) | 2026-07-30 | Skip CSRF widget when consent form has no `_token` |
| [1.4.5](CHANGELOG.md#145---2026-07-30) | 2026-07-30 | Twig `prependPath` for app overrides; Tailwind theme uses `--nowo-cc-*` (no indigo/slate utilities) |
| [1.4.4](CHANGELOG.md#144---2026-07-30) | 2026-07-30 | Composer locks: Symfony 7.4.15 / demo 8.1.2 |
| [1.4.3](CHANGELOG.md#143---2026-07-30) | 2026-07-30 | Admin `base.html.twig`, Twig override docs in USAGE (REQ-TWIG-001), README docs links |
| [1.4.2](CHANGELOG.md#142---2026-07-29) | 2026-07-29 | Compose V2/V1 Makefile fallback; optional monorepo includes; WSL Compose shell helper |
| [1.4.1](CHANGELOG.md#141---2026-07-28) | 2026-07-28 | Coverage gate docs/tooling, Spec Kit inventory, deprecation CI gate, demo lock/migration fixes |
| [1.4.0](CHANGELOG.md#140---2026-07-27) | 2026-07-27 | Admin `web_ui` / `security`, pagination, PSR Clock, PHPStan + demo-smoke CI |
| [1.3.6](CHANGELOG.md#136---2026-07-27) | 2026-07-27 | Standards compliance: DOCS-016/017, MAKE-003/007, PHP coverage ≥99.95% |
| [1.3.5](CHANGELOG.md#135---2026-07-24) | 2026-07-24 | PHPStan FrankenPHP (REQ-CS-005), empty baseline, DI/type hygiene |
| [1.3.4](CHANGELOG.md#134---2026-07-22) | 2026-07-22 | Vite 8 / happy-dom 20, GHA bumps, demo `FRANKENPHP_MODE` |
| [1.3.3](CHANGELOG.md#133---2026-07-20) | 2026-07-20 | REQ-GIT-001 hygiene, Code of Conduct, expanded PHPUnit coverage |
| [1.3.2](CHANGELOG.md#132---2026-07-13) | 2026-07-13 | Asset package `nowo_cookie_consent`, AssetMapper-compatible script loading |
| [1.3.1](CHANGELOG.md#131---2026-07-09) | 2026-07-09 | Spec Kit baseline, demo update-deps fix, dev lock sync |
| [1.3.0](CHANGELOG.md#130---2026-07-05) | 2026-07-05 | Dashboard table names, `doctrine.table_prefix`, locale translations |
| [1.2.0](CHANGELOG.md#120---2026-06-15) | 2026-06-15 | Page overlay, settings admin, bubble customization, modal position fix |
| [1.1.1](CHANGELOG.md#111---2026-06-15) | 2026-06-15 | Standards compliance, SECURITY/docs, TS coverage gate |
| [1.1.0](CHANGELOG.md#110---2026-06-15) | 2026-06-15 | Cookie inventory, granular selection, preferences bubble |
| [1.0.0](CHANGELOG.md#100---2026-06-15) | 2026-06-15 | First stable release |

After creating the release commit and tag, run `make check-no-cursor-coauthor` again **before** `git push` (REQ-GIT-001). The release commit itself is not covered by an earlier `release-check` run.
