# Security

## Table of contents

- [Scope](#scope)
- [Attack surface](#attack-surface)
- [Threat model](#threat-model)
- [Mitigations](#mitigations)
- [Secrets and cryptography](#secrets-and-cryptography)
- [Logging](#logging)
- [Dependencies and updates](#dependencies-and-updates)
- [Permissions and exposure](#permissions-and-exposure)
- [Reporting a vulnerability](#reporting-a-vulnerability)
- [Release security checklist (12.4.1)](#release-security-checklist-1241)

## Scope

This document covers security considerations for **nowo-tech/cookie-consent-bundle** — a Symfony bundle that renders a GDPR cookie consent modal, stores optional consent logs, and may expose admin CRUD and a read-only config API.

**In scope:** HTTP endpoints shipped by the bundle, consent cookies, optional Doctrine entities, Twig templates, TypeScript frontend, and Flex recipe defaults.

**Out of scope:** Security of the host application (authentication, firewall rules, WAF, CDN), third-party analytics scripts the integrator loads after consent, and database server hardening.

## Attack surface

| Input / surface | Description |
| --- | --- |
| **HTTP POST (consent form)** | Category toggles, optional per-cookie choices, CSRF token when enabled |
| **HTTP GET (modal render)** | Routes `nowo_cookie_consent.show`, `nowo_cookie_consent.show_if_not_set` |
| **HTTP GET (config API)** | JSON at `/cookie-consent/config` when `fetch_config_via_api: true` |
| **HTTP (admin CRUD)** | `CookieDefinitionAdminController` when imported by the consuming app |
| **Configuration** | YAML under `nowo_cookie_consent`, env vars, Doctrine-backed profiles |
| **Cookies** | Consent preference cookies written by `CookieHandler` |
| **Database** | Optional tables for logs, config, translations, cookie definitions |
| **Twig / translations** | Bundle templates and translation keys rendered in the browser |

The bundle does **not** expose a CLI that mutates production data, outbound HTTP integrations, or file uploads.

## Threat model

| Category | Risk | Applicability |
| --- | --- | --- |
| **Injection (SQL/XSS)** | Malicious input in admin forms or config stored in DB rendered without escaping | Admin CRUD and DB-backed copy when enabled |
| **CSRF** | Forged consent or admin actions | Consent POST and admin delete/edit when CSRF is enabled |
| **Session / cookie tampering** | Forged consent cookie to bypass restrictions | Consent cookie JSON read by `CookieChecker` |
| **Information disclosure** | Config API or logs expose PII | Consent logs (anonymized IP), config JSON |
| **Authz / privilege escalation** | Unauthenticated access to admin CRUD | Admin routes must be protected by the host app |
| **DoS** | High-volume POST to consent endpoint | Lightweight handler; rate limiting is the app's responsibility |
| **Deserialization / path traversal** | Untrusted serialized data or template paths | Not used; Twig logical names only |
| **SSRF** | Outgoing requests triggered by bundle | Not applicable |

## Mitigations

| Threat | Control |
| --- | --- |
| **XSS in modal copy** | Twig auto-escaping; Symfony Form component for consent fields; integrators must escape custom admin fields in their layouts |
| **CSRF on consent form** | `csrf_protection: true` by default on `CookieConsentType`; disable only with care |
| **CSRF on admin delete** | `isCsrfTokenValid()` on delete action in `CookieDefinitionAdminController` |
| **Cookie flags** | `Secure` and configurable `HttpOnly` on consent cookies |
| **PII in logs** | IP addresses anonymized (last octet masked) in `CookieConsentLog` |
| **Invalid config IDs** | Admin controller returns 404 when config or definition does not belong to the profile |
| **Required cookies** | Category `required` cannot be disabled; granular toggles exclude required cookies |
| **Config API** | Read-only GET; returns display copy and GUI options, not secrets |

Integrators should:

- Protect admin routes with Symfony Security (roles, firewall).
- Keep `csrf_protection: true` in production.
- Serve the site over HTTPS so `Secure` cookies are effective.
- Review DB-backed modal copy before publishing (treat admins as trusted for HTML in descriptions if `|raw` is used in custom overrides).

## Secrets and cryptography

The bundle does **not** implement custom cryptography or store API keys.

- No secrets belong in `config/packages/nowo_cookie_consent.yaml` or the Flex recipe.
- Database DSN and mailer credentials stay in the host application `.env` (gitignored).
- Consent cookies hold category preferences, not authentication tokens.

## Logging

| Data | Behavior |
| --- | --- |
| **Consent log entity** | Optional; stores anonymized IP, category key, cookie name/value, timestamp |
| **Symfony logger** | Standard framework logging; bundle avoids logging full request bodies or raw consent payloads at debug level in production |
| **Secrets** | Must never appear in logs or consent cookies |

Disable `use_logger` if the application policy forbids storing even anonymized IPs.

## Dependencies and updates

- Run `composer audit` before each release and triage findings.
- Dependabot is configured for Composer, GitHub Actions, and npm (frontend build).
- Security fixes in Symfony, Doctrine, or Twig should be applied in consuming applications promptly.
- The published package excludes `/demo` and `/.cursor` via `archive.exclude`.

## Permissions and exposure

| Endpoint / feature | Exposure | Recommendation |
| --- | --- | --- |
| Consent modal routes | Public | Expected; idempotent GET |
| Consent form POST | Public | Enable CSRF; consider rate limiting at reverse proxy |
| Config API GET | Public when enabled | Returns non-secret UI copy only |
| Cookie definition admin | App-defined route import | Restrict to authenticated admins |
| Demo admin paths | Demo apps only | Do not deploy demo routes to production unchanged |

Document imported admin routes in your application runbook and assign least-privilege roles.

## Reporting a vulnerability

Report security issues **privately**:

1. Do **not** open a public GitHub issue for security-sensitive bugs.
2. Use [GitHub Security Advisories](https://github.com/nowo-tech/CookieConsentBundle/security/advisories) or email **hectorfranco@nowo.tech**.
3. Include steps to reproduce, affected versions, and impact.
4. We will acknowledge and coordinate disclosure after a fix is available.

See also [`.github/SECURITY.md`](../.github/SECURITY.md) for supported versions and reporting policy.

## Release security checklist (12.4.1)

Before each release, the maintainer confirms each item (note in the release PR or tag message is sufficient).

| Item | Check |
| --- | --- |
| `docs/SECURITY.md` and `.github/SECURITY.md` up to date | ☐ |
| `.env` / secrets in `.gitignore`; no credentials in repo | ☐ |
| Flex recipe / published config contains no secrets | ☐ |
| Input validation and output escaping on forms, Twig, and config API | ☐ |
| `composer audit` run; known issues reviewed | ☐ |
| Logs do not write raw consent payloads or secrets at debug level in production | ☐ |
| Cryptography N/A (no custom crypto in bundle) | ☐ |
| Routes and admin demo paths documented; least privilege in consumer apps | ☐ |
| Rate limits / DoS: consent endpoints are lightweight; consumer apps should apply standard HTTP limits | ☐ |
