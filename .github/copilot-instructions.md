## AI contribution guidelines — Cookie Consent Bundle

Follow these rules when contributing PHP, TypeScript, Twig, and documentation to this repository.

---

## Project scope

- **Type:** Standalone Symfony bundle (`nowo-tech/cookie-consent-bundle`).
- **PHP:** `>=8.1 <8.6` with `declare(strict_types=1);` in every PHP file.
- **Symfony:** Support **6.x, 7.x, and 8.x** (`^6.0 || ^7.0 || ^8.0` on `symfony/*` constraints).
- **Mandatory Symfony minors for CI:** **7.4**, **8.0**, **8.1** (Symfony 8 requires PHP 8.4+).
- **Frontend:** TypeScript + Vite in `src/Resources/assets/`; built output in `src/Resources/public/`.
- **Language:** PHPDoc, inline comments, and user-facing docs in **English** only.

---

## PHP standards

- PSR-12 + Symfony coding standards; run `make cs-check` before finishing.
- Use **strict comparisons** (`===`) and constructor injection.
- Prefer `final` classes; keep BC on public config keys, routes, Twig functions, and cookie names.
- Do **not** use service autowiring in the bundle; wire services in `src/Resources/config/services.yaml`.
- Preserve the DI extension alias: `nowo_cookie_consent`.
- Entity table prefix is optional via `table_prefix` configuration.

---

## Bundle-specific conventions

- Cookie category names use snake-case strings (`analytics`, `marketing`, …).
- Route names are prefixed with `nowo_cookie_consent.`.
- Twig namespace: `NowoCookieConsentBundle` (see `TwigPathsPass`).
- Public assets publish to `public/bundles/nowocookieconsent/`.
- UI themes: `bootstrap` (default) and `tailwind` via `ui_theme` config.
- Database-backed modal copy is optional (`use_database_config`); YAML translations live under `src/Resources/translations/`.

---

## Tests and quality

- PHPUnit target: **~100% line coverage** on `src/`; justify exclusions in `phpunit.xml.dist`.
- TypeScript tests via Vitest: `make test-ts`.
- Full gate: `make release-check` (style, static analysis, PHP + TS coverage, demos).
- Use real collaborators or focused test doubles; avoid mocking `final` classes when PHPUnit 10 blocks it.

---

## Documentation

- Keep `README.md` badges and the **Documentation** section aligned with `docs/`.
- Update `docs/CHANGELOG.md` and `docs/UPGRADING.md` for user-visible changes.
- Flex recipe lives in `.symfony/recipe/nowo-tech/cookie-consent-bundle/1.0/`; document Flex steps in `docs/INSTALLATION.md`.

---

## Do not

- Introduce breaking changes to cookie names, form field names, or public Twig function signatures without a major release note.
- Commit secrets, `.env` files, or demo `var/` caches.
- Add Spanish PHPDoc or comments in `src/`.
- Touch unrelated files when fixing a focused issue.
