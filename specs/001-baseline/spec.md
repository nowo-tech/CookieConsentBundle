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
- **FR-CFG-004**: `ColdStartRequestAttributes` — request attribute contract that skips database-backed consent during cold start.
- **FR-DI-002**: `SystemClock` implements `Psr\Clock\ClockInterface` when the host does not provide a clock.
- **FR-SEC-001**: Admin access checker interface + configurable/allow-all implementations; `CookieConsentSecurityPass`; `CookieConsentAdminAccessSubscriber` enforces roles on admin CRUD (demo may use allow-all).

### Entities & repositories

- **FR-ORM-001**: Config, config translations, cookie definitions, definition translations, consent logs.
- **FR-ORM-002**: Repositories for config and definitions.

### Cookie runtime

- **FR-COOKIE-001**: `CookieChecker`, `CookieHandler`, `CookieLogger` — read `RequestStack` / obtain EntityManager per call (no request capture in constructors).

### FrankenPHP worker

- **FR-WORKER-001**: Shared services must not capture `Request`, user, locale or consent state across requests when the kernel is not reset (`reset_kernel false` / scenario B).
- **FR-WORKER-002**: Memoizing services (`CookieConsentConfigResolver`, `CookieInventoryProvider`, `CookieConsentConfigRepository`) implement `ResetInterface` and are cleared by `CookieConsentRuntimeCacheResetSubscriber` on every main `kernel.request`.
- **FR-WORKER-003**: Database profile copy is exposed via `nowo_cookie_consent_trans()`; do not register per-request catalogues with `Translator::addResource()`.

### Controllers

- **FR-CTRL-001**: Public consent, config API, admin settings, cookie definition admin.

### Forms & events

- **FR-FORM-001**: Consent, config settings, definition types (incl. CSRF-protected `DeleteCookieDefinitionType`).
- **FR-FORM-002**: `FormKitPlaceholders` — neutralises FormKit's injected `attr.placeholder` keys the bundle does not ship.

### Admin (UiKit page contract)

- **FR-ADMIN-001**: `AdminPageFactory` builds the UiKit admin page view models (cookie inventory list, definition form, profile settings); `CookieConsentAreaNavProvider` publishes the Settings | Cookie inventory area nav to the UiKit registry; admin CSS on UiKit tokens.
- **FR-ADMIN-002**: `CookieConsentConfigSettingsSection` maps admin tabs to per-section settings form types (`Form/Settings/*`, plus a single-page `Full` type for legacy hosts).
- **FR-EVT-001**: Form, translation, schema-ready and runtime-cache-reset subscribers.
- **FR-EVT-002**: Schema-ready subscriber memoizes a positive table probe per worker for `schema_ready_cache_ttl` seconds (default 60, `0` disables); negative probes are never memoized; the memo is not reset per request.
- **FR-CSP-001**: Every bundle-rendered `<script>`/`<style>` carries `nonce` from request attribute `csp_nonce` when present (`app.request` null-safe); no inline event handlers or inline `style` attributes in bundle templates.
- **FR-CSP-002**: JS-injected `<style>` copies the loader script nonce (`document.currentScript`), then `<meta name="csp-nonce">`, then the first nonced `<script>`.

### Twig

- **FR-TWIG-001**: Path pass; `CookieConsentTwigExtension`, `CmpUxTwigExtension`, `CookieConsentAdminTwigExtension`, `CookieConsentTranslationTwigExtension`.
- **FR-TWIG-003**: Bootstrap/Tailwind modal, admin views, form themes.

### Frontend (TypeScript)

- **FR-UI-001–015**: Modal entry, category/granular toggles, iframe manager, step wizard, theme/visual config, diagnostics, logger.
- **FR-UI-014**: `intro-more.ts` + `_intro_more.html.twig` — "Show more" toggle of the clamped first-layer intro on phones (1.23).
- **FR-UI-015**: `motion.ts` — close with a fade and a timer fallback; immediate close without a transition.
- **FR-BUILD-001**: Vite output `nowo-consent-modal.js`.
- **FR-SEC-002**: `csrf.ts` — SameOrigin CSRF helpers for XHR consent posts.
- **FR-TEST-TS-001**: Co-located Vitest under `src/Resources/assets/src/*.test.ts` (16 files).

### i18n & assets

- **FR-I18N-001**: Nine locale YAML files (incl. Catalan, Polish).
- **FR-ASSET-001**: Legacy/built CSS themes.
- **FR-ASSET-002**: CSP-safe stylesheets `nowo-cookie-consent.css` (modal) and `nowo-cookie-consent-admin.css` (admin).
- **FR-I18N-002**: `ConfiguredLocales` normalises `enabled_locales` (literal list, container parameter or env var); admin cookie definitions edit one translation tab per enabled locale.

### Routing & DI

- **FR-ROUT-001**: `routing.yaml`.
- **FR-DI-001**: `services.yaml`.


### Ports, personal data and exact keys (1.27–1.30)

- **FR-VIEW-001**: `ConsentProfileView` / `CookieDefinitionView` — read-only scalar views, so host code never holds the entities (1.28).
- **FR-PORT-001**: `ConsentCookieWriterInterface` with the `ConsentCookieJar` / `ConsentCookie` value objects, applied by `Http\ConsentCookieJarApplier` / `ConsentCookieResponseWriter`, wired by `ConsentCookieWriterPass`; `CookieHandlerInterface` (Request / Response) deprecated since 1.30.
- **FR-COOKIE-002**: `ConsentIpAnonymizer` and `ConsentKey` (strict key format, random key otherwise, 1.30.2).
- **FR-DDD-001**: Host without Doctrine — `CookieConsentWithoutDoctrinePass`, `NullCookieLogger`.
- **FR-DDD-002**: Dedicated entity manager (`doctrine.entity_manager`) — `CookieConsentEntityManagerPass`, `CookieConsentManagerRegistry`, `ExcludedEntityNamespaceDriver` (DoctrineBundle 3.4, 1.30.1).
- **FR-EVT-003**: `ConsentSavedEvent` — scalars, dispatched after the consent was logged.
- **FR-GDPR-001**: Eraser port + `CookieConsentPersonalDataEraser`, `CookieConsentLogRows`, `CookieConsentErasureResult`, `CookieConsentPersonalDataErasedEvent` after commit.
- **FR-GDPR-002**: Subject VO (refuses unusable keys), subject resolver port, IP window VO / provider port, `IpWindowSubjectResolver`.
- **FR-GDPR-003**: Exporter port + `CookieConsentPersonalDataExporter`.
- **FR-GDPR-004**: Optional UserKit bridges (`PersonalData/UserKit`).
- **FR-EXACT-001**: Look-alike ids (REQ-SEC-006) — `ConsentLogCollationListener` (binary NO PAD collation on `cookie_consent_key` / `ip_address`, warning on a version without patch number), `===` re-checks of every row.

### Concurrency (1.31, REQ-RUNTIME-003)

- **FR-CONC-001**: The consent log is written before the consent cookies, in one transaction through transaction-doctrine's runner (`CookieLogger`); a failed log sets no cookie, dispatches no event and is never swallowed (`CookieConsentFormSubscriber`).
- **FR-CONC-002**: Lock conflicts of the consent POST and of the bundle routes answer 409 (or 503) + `X-Conflict-Code` `CONFLICT_001` (+ `Retry-After`) / `CONFLICT_002`; admin unique keys answer the domain 409 `COOKIE_CONSENT_DUPLICATE_KEY`; a closed bundle manager is replaced (`CookieConsentConflictListener`). Commit tracking is scoped to the bundle's connection or reuses transaction-doctrine's ledger (`CookieConsentCommitTrackingPass`).
- **FR-CONC-003**: "Only one default profile" — `CookieConsentConfigRepository::saveSettings()` locks the enabled profiles in id order, flushes and clears the other defaults with one atomic UPDATE, in one transaction.

- **FR-CONC-004**: Admin edits carry the row version (1.33, REQ-RUNTIME-003 rule 4): `RowVersionField` is the hidden base token of the edit form, `VersionedRowInterface` marks the entity, `RowVersionGuard` compare-and-swaps the version column and `StaleVersionException` reports a page that is no longer current.

### Config read port and private entity manager (1.34)

- **FR-CFG-005**: `CookieConsentConfigReaderInterface` (default `CookieConsentConfigReader`) is the read port over the enabled consent profiles, answering `ConsentProfileView` value objects (REQ-ARCH-001 rules 1 and 3).
- **FR-DDD-003**: `doctrine.private_entity_manager: true` (opt-in, REQ-ARCH-001 rule 6): `CookieConsentPrivateEntityManagerPass` wires the bundle's own entity manager and `CookieConsentPrivateManagerBoundarySubscriber` drops it, and clears the memos holding its entities, at every outermost unit-of-work boundary.

### Provisioning port (1.36)

- **FR-PROV-001**: `CookieConsentProvisionerInterface` (default `CookieConsentProvisioner`, DoctrineBundle hosts only) is the host write port for seeding: `ensureDefaultProfile(DefaultProfileSpec)` and `upsertCookieDefinitions(CookieDefinitionSpecs)` answer a `ProvisioningResult` (created / updated / unchanged, profile id). The ports take bundle value objects only (REQ-ARCH-001 rule 3), validated when built; `fromArray()` helpers refuse unknown keys and wrong types.
- **FR-PROV-002**: Idempotent by natural keys compared byte for byte (REQ-SEC-006); only the settings given are written; nothing is deleted; a changed row advances its `row_version`. Writes go through the port's own `PrivateEntityManager` (`nowo_cookie_consent.provisioner_entity_manager`, source resolved by `CookieConsentProvisionerPass`) in one runner transaction, dropped after every call: the application's managers are never flushed, cleared or reset (REQ-RUNTIME-002).
- **FR-PROV-003**: `ProvisioningLock` serializes provisioning calls (MySQL / MariaDB `GET_LOCK()`, PostgreSQL `pg_advisory_xact_lock()`); unique-key and lock conflicts are retried outside a transaction (REQ-RUNTIME-003).
---

## Success Criteria

- **SC-001**: **180/180** files under `src/` mapped in [`code-inventory.md`](code-inventory.md) (includes 19 co-located Vitest files; audited 2026-11-16, 1.30.2).
- **SC-002**: Config keys match `docs/CONFIGURATION.md`.
- **SC-003**: `composer qa`, `pnpm test`, PHPUnit, PHPStan pass.
- **SC-004**: FrankenPHP worker scenario B (`reset_kernel false`) viable per [`docs/FRANKENPHP-WORKER-AUDIT.md`](../../docs/FRANKENPHP-WORKER-AUDIT.md).

---

## Explicit non-goals

- Legal compliance guarantees (integrator responsibility).
- Admin authentication (host app).

---

## Validation

`composer qa`, `pnpm test`, PHPUnit, PHPStan, inventory audit.
