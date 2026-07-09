# Feature Specification: CookieConsentBundle baseline (100% code coverage)

**Feature Branch**: `001-baseline`  
**Status**: Active  

**Package**: `nowo-tech/cookie-consent-bundle`  
**Configuration root**: `nowo_cookie_consent`  
**Code inventory**: [`code-inventory.md`](code-inventory.md)

---

## Summary

GDPR **cookie consent** for Symfony: modal with category toggles, granular per-cookie inventory, iframe blocking, YAML or Doctrine-backed config, optional consent logging, admin CRUD, CMP UX bridge, and TypeScript frontend (Vitest-covered).

---

## User Scenarios

### US-01 — First visit consent (P1)

**Given** no consent cookie, **When** page loads on a targeted route, **Then** modal renders with required + optional categories and blocks non-essential scripts/iframes until accepted.

### US-02 — Granular preferences (P1)

**Given** user opens preferences, **When** toggling categories or individual cookies, **Then** `CookieHandler` sets/removes cookies per `CookieDefinition` inventory.

### US-03 — Doctrine or YAML config (P1)

**Given** `config_source: doctrine`, **When** admin saves settings, **Then** `CookieConsentConfigResolver` merges DB config with route targeting; YAML mode uses static config tree.

### US-04 — Admin cookie inventory (P2)

**Given** admin routes enabled, **When** integrator manages definitions, **Then** CRUD persists translated `CookieDefinition` rows linked to categories.

### US-05 — Consent logging (P2)

**Given** `use_logger: true`, **When** user accepts/rejects, **Then** `CookieLogger` records anonymized consent events.

### US-06 — CMP / UX bridge (P3)

**Given** Symfony UX or CMP integration, **When** Twig renders, **Then** `CmpUxTwigExtension` exposes payload from `CookieConsentConfigPayloadFactory`.

---

## Requirements

### Bundle & config

- **FR-BUNDLE-001**: `NowoCookieConsentBundle` alias `nowo_cookie_consent`.
- **FR-CFG-001**: `Configuration` — doctrine connection/prefix, categories, logger, route targeting, visual/theme, admin, CMP options.
- **FR-CFG-002**: `NowoCookieConsentExtension`, `TablePrefixListener`, `TwigPathsPass`.
- **FR-CFG-003**: Config resolver stack (`CookieConsentConfigSelector`, route pattern matcher, inventory provider/normalizer).

### Entities & repositories

- **FR-ORM-001**: Config, config translations, cookie definitions, definition translations, consent logs.
- **FR-ORM-002**: Repositories for config and definitions.

### Cookie runtime

- **FR-COOKIE-001**: `CookieChecker`, `CookieHandler`, `CookieLogger`.

### Controllers

- **FR-CTRL-001**: Public consent, config API, admin settings, cookie definition admin.

### Forms & events

- **FR-FORM-001**: Consent, config settings, definition types.
- **FR-EVT-001**: Form and translation subscribers.

### Twig

- **FR-TWIG-001**: Path pass; `CookieConsentTwigExtension`, `CmpUxTwigExtension`.
- **FR-TWIG-003**: Bootstrap/Tailwind modal, admin views, form themes.

### Frontend (TypeScript)

- **FR-UI-001–013**: Modal entry, category/granular toggles, iframe manager, step wizard, theme/visual config, diagnostics, logger.
- **FR-BUILD-001**: Vite output `nowo-consent-modal.js`.
- **FR-TEST-TS-001**: Co-located Vitest under `src/Resources/assets/src/*.test.ts` (11 files).

### i18n & assets

- **FR-I18N-001**: Nine locale YAML files (incl. Catalan, Polish).
- **FR-ASSET-001**: Legacy/built CSS themes.

### Routing & DI

- **FR-ROUT-001**: `routing.yaml`.
- **FR-DI-001**: `services.yaml`.

---

## Success Criteria

- **SC-001**: **98/98** files under `src/` mapped (includes co-located Vitest).
- **SC-002**: Config keys match `docs/CONFIGURATION.md`.
- **SC-003**: `composer qa`, `pnpm test`, PHPUnit, PHPStan pass.

---

## Explicit non-goals

- Legal compliance guarantees (integrator responsibility).
- Admin authentication (host app).

---

## Validation

`composer qa`, `pnpm test`, PHPUnit, PHPStan, inventory audit.
