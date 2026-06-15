# Security

## Threat model (summary)

- Consent cookies use `Secure` and configurable `HttpOnly` flags.
- IP addresses stored in the log entity are anonymized (last octet masked).
- Enable CSRF protection on the consent form in production (`csrf_protection: true`).
- Modal copy and config API responses are rendered through Twig and Symfony forms; escape user-provided database config in admin UIs in consuming applications.
- Do not commit secrets or production DSN credentials.

Report vulnerabilities privately via [GitHub Security Advisories](https://github.com/nowo-tech/cookie-consent-bundle/security/advisories) or email **hectorfranco@nowo.tech**. See also [`.github/SECURITY.md`](../.github/SECURITY.md).

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
