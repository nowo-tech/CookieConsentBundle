# Spec-driven development

In this repository, **spec-driven development** has two layers that stay in sync:

1. **Product behavior** — what **CookieConsentBundle** guarantees to applications that integrate it (see [`USAGE.md`](USAGE.md), [`CONFIGURATION.md`](CONFIGURATION.md), [`INSTALLATION.md`](INSTALLATION.md)). **PHPUnit**, **Vitest**, and **PHPStan** enforce contracts in CI where applicable.
2. **Traceability anchors** — stable **`REQ-*`** identifiers in Makefiles and demos so changes to scripts, ports, and demo workflows stay discoverable from issues and PRs.

There is no separate executable spec language (for example Gherkin); tests and static analysis are the mechanical proof alongside this document.

---

## User stories

| ID | Story |
| --- | --- |
| US-01 | **As a** Symfony integrator, **I want** a GDPR cookie consent modal with category toggles **so that** users can accept or reject non-essential cookies. |
| US-02 | **As an** integrator, **I want** optional consent logging and database-backed copy **so that** I can audit choices and manage texts without redeploying. |
| US-03 | **As an** integrator, **I want** Bootstrap or Tailwind markup **so that** the modal matches my UI stack (`ui_theme`). |
| US-04 | **As an** integrator, **I want** Twig helpers and translation overrides **so that** I can embed and localize the modal predictably. |
| US-05 | **As a** maintainer, **I want** behavior covered by automated tests **so that** regressions are caught in CI. |
| US-06 | **As a** contributor, **I want** `REQ-*` anchors on scripted flows **so that** PRs cite the same identifiers as this document. |

**Out of scope:** guarantees outside the documented public API, undocumented demo-only admin CRUD, and behavior outside declared PHP/Symfony compatibility ranges.

---

## Bundle functional scope

**Goal:** Symfony bundle providing a GDPR cookie consent modal with category toggles, AJAX submission, optional consent logging, configurable Doctrine table prefix, and optional database-driven configuration.

**In scope**

- Documented integration (root `README.md` and `docs/`).
- Configuration in [`CONFIGURATION.md`](CONFIGURATION.md) and runtime behavior in [`USAGE.md`](USAGE.md).
- Frontend entrypoint `nowo-consent-modal.js` built from TypeScript via Vite.
- Consumer-facing changes in [`CHANGELOG.md`](CHANGELOG.md) and [`UPGRADING.md`](UPGRADING.md).

**Explicit non-goals**

- Cookie scanning / automatic classification of third-party scripts.
- CMP replacement for enterprise consent platforms.
- **`demo/`** admin UI: illustrative unless explicitly documented as stable API.

---

## Validating the functional spec

- Run **`make qa`** or **`make release-check`** as documented in [`CONTRIBUTING.md`](CONTRIBUTING.md).
- Run **PHPUnit** and **Vitest** locally and in CI for code changes.
- New or changed behavior should add or adjust tests under `tests/` and `src/Resources/assets/src/*.test.ts`.

---

## Requirement identifiers (`REQ-*`)

| ID | Where | What it marks |
| --- | --- | --- |
| `REQ-DEMO-005` | `demo/symfony8/Makefile`, `demo/symfony8-tailwind/Makefile` | Canonical `make up`: `Waiting…`, `Installing…`, `Demo started at:` from `PORT`. |
| `REQ-DEMO-007` | `demo/Makefile`, demo Makefiles | `update-bundle` before demo tests in `release-check`. |
| `REQ-MAKE-001` | Root `Makefile` | Docker-driven development workflow for the bundle. |
| `REQ-MAKE-008` | Root `Makefile` | `update-deps` via shared `.scripts/`. |

When you change scripted behavior, update the existing `REQ-*` comment or add a new ID and document it here.

---

## Suggested workflow for contributors

1. Clarify behavior in an issue or draft PR (product + Makefiles/demos).
2. Implement with tests and static analysis.
3. Anchor scripts and demos when dev UX changes.
4. Ship integrator docs when configuration or public behavior changes.

---

## Relationship to Engram / external checklists

[`ENGRAM.md`](ENGRAM.md) covers Nowo-wide documentation checklist items. This document ties together **what the package does**, **how we verify it**, and **local `REQ-*` habits**. Both coexist: Engram for org-level compliance, this file for product + traceability.

---

## See also

- [`USAGE.md`](USAGE.md)
- [`CONFIGURATION.md`](CONFIGURATION.md)
- [`CONTRIBUTING.md`](CONTRIBUTING.md)
- [`RELEASE.md`](RELEASE.md)
- [`DEMO-FRANKENPHP.md`](DEMO-FRANKENPHP.md)
