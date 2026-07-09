# Release

This checklist helps maintainers prepare and publish a release safely.

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

- **PHP**: **100%** line coverage (130 tests; `make test-coverage`)
- **TypeScript**: **~94%** line coverage, **90%** minimum enforced (`make test-ts`)

Update README **Tests and coverage** percentages after each release when coverage changes materially.

## Release history

| Version | Date | Notes |
| --- | --- | --- |
| [1.3.1](CHANGELOG.md#131---2026-07-09) | 2026-07-09 | Spec Kit baseline, demo update-deps fix, dev lock sync |
| [1.3.0](CHANGELOG.md#130---2026-07-05) | 2026-07-05 | Dashboard table names, `doctrine.table_prefix`, locale translations |
| [1.2.0](CHANGELOG.md#120---2026-06-15) | 2026-06-15 | Page overlay, settings admin, bubble customization, modal position fix |
| [1.1.1](CHANGELOG.md#111---2026-06-15) | 2026-06-15 | Standards compliance, SECURITY/docs, TS coverage gate |
| [1.1.0](CHANGELOG.md#110---2026-06-15) | 2026-06-15 | Cookie inventory, granular selection, preferences bubble |
| [1.0.0](CHANGELOG.md#100---2026-06-15) | 2026-06-15 | First stable release |
