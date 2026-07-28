# Coverage policy

## Table of contents

- [PHP line coverage gate](#php-line-coverage-gate)
- [Justified exclusions](#justified-exclusions)
- [How to refresh](#how-to-refresh)

## PHP line coverage gate

`make coverage-check` / `composer coverage-check` enforce **≥ 99%** line coverage on the PHPUnit includable `src/` set (`REQ-TEST-003` / `REQ-TEST-006`).

Published README percentage must match the latest `coverage-output.txt` / CI artifact.

## Justified exclusions

No `<source><exclude>` entries in `phpunit.xml.dist` at this time — aggregate line coverage is **100%** on `src/` (verified 2026-07-28).

Do **not** add new `@codeCoverageIgnore` or PHPUnit exclusions without updating this document.

## How to refresh

```bash
make coverage-check
# or
composer coverage-check
```
