# Concurrency

How the bundle stays correct when visitors and admins write at the same time (nowo-tech REQ-RUNTIME-003). This page
lists the write paths, the conflict answers and codes, the host options and the residual risks.

The consent log is GDPR evidence (proof of consent): the rules below exist so that no consent is lost, stored twice,
half stored, or confirmed to the visitor without its log rows.

## Table of contents

- [Rules the bundle follows](#rules-the-bundle-follows)
- [Write paths](#write-paths)
- [Conflict answers and codes](#conflict-answers-and-codes)
- [What is not a conflict](#what-is-not-a-conflict)
- [Host options](#host-options)
- [Retries](#retries)
- [Tests and CI](#tests-and-ci)
- [Residual risks](#residual-risks)

## Rules the bundle follows

- **One transaction per write unit.** The consent log writes its rows (one per category) with DBAL `INSERT`s in one
  transaction through nowo-tech/transaction-doctrine's runner (`DoctrineTransactionRunner::forConnection()`). The
  profile settings save (version check + flush + "only one default" rule) runs in one runner transaction, and so does
  the cookie definition edit (version check + flush). The cookie definition create / delete is a single ORM `flush()`,
  which Doctrine runs in one transaction. The GDPR eraser deletes in
  batches of 500, one transaction per batch (or inside the caller's transaction, such as UserKit's erasure run).
- **Your entity manager is never flushed, cleared or reset by a consent.** The consent log bypasses the unit of work,
  so your pending changes are never committed by a consent POST. On the bundle's admin routes, a manager closed by a
  failed flush is replaced through the registry (`CookieConsentConflictListener`); an open one is never touched.
- **Nested transactions repair themselves.** Inside an open transaction of yours, the consent rows go in a savepoint.
  After a failure the runner rolls back to that savepoint; if the server dropped it (MySQL / MariaDB after a deadlock)
  the connection is closed instead of being left poisoned. Your transaction's own work is kept.
- **No consent cookie without its proof of consent.** Since 1.31 the log is written before the consent cookies are set
  and before `ConsentSavedEvent`. A failed log sets no cookie and dispatches no event.
- **Failures are never swallowed.** A failure of the consent write is either a conflict answer (below) or a real error.
  Only reads before the submission (building the form, resolving the profile during a cold start) are skipped.
- **No I/O under a lock.** The bundle sends no mail, HTTP call or file write while it holds a lock.
- **FrankenPHP worker mode with kernel reset off.** Every service is stateless per request; the commit ledger of the
  conflict answer is a monotonic counter of the process, read by difference only.

## Write paths

| Write path | Transaction | Control | Conflict answer |
| --- | --- | --- | --- |
| Consent POST (`CookieConsentFormSubscriber` on `kernel.response` of the page the form posts to, `CookieLogger::log()`) | One runner transaction (a savepoint inside yours): one `INSERT` per category | Append-only: no read-modify-write, counter or "only one" rule; each consent is its own set of rows | 409 `CONFLICT_001` / `CONFLICT_002` (the application's error page); no cookie set |
| Admin profile settings (`CookieConsentConfigSettingsAdminController::section`, `CookieConsentConfigRepository::saveSettings()`) | One runner transaction: lock, version check, flush, update | Optimistic `row_version` (compare-and-swap, below). "Only one default profile": `SELECT … FOR UPDATE` of the enabled profiles in id order, then one atomic `UPDATE` clears the other defaults and advances their `row_version` | Lock conflict: 409 `CONFLICT_001` / `CONFLICT_002`. Stale page: 409 `COOKIE_CONSENT_STALE_VERSION` (domain) |
| Admin cookie definition edit (`CookieDefinitionAdminController::edit`, `CookieDefinitionRepository::saveEdit()`) | One runner transaction: version check, flush (definition and translations) | Optimistic `row_version` (compare-and-swap, below); unique `(config_id, name)`; unique `(definition_id, locale)` translation | Lock conflict: 409 `CONFLICT_001` / `CONFLICT_002`. Stale page: 409 `COOKIE_CONSENT_STALE_VERSION` (domain). Unique key: 409 `COOKIE_CONSENT_DUPLICATE_KEY` (domain) |
| Admin cookie definition create / delete (`CookieDefinitionAdminController`) | One flush | Create: unique `(config_id, name)`. Delete by id, idempotent: deleting a definition another admin just edited removes it, which is what the admin asked for | Lock conflict: 409 `CONFLICT_001` / `CONFLICT_002`. Unique key: 409 `COOKIE_CONSENT_DUPLICATE_KEY` (domain) |
| GDPR erasure (`CookieConsentPersonalDataEraser::erase()`, UserKit eraser) | One transaction per batch of 500 ids, or the caller's transaction | Delete by id; idempotent (a second run deletes nothing) | Rethrown to the caller (UserKit, your command) |
| Profile and cookie definition translations | Written by the settings / definition saves above (cascade), in their transaction | Unique `(config_id, locale)` / `(definition_id, locale)` | As the save that writes them; a unique key on the definition page answers the domain 409 |

`flush()` in `src/`: the settings save and the definition edit (each inside its repository's runner) and the cookie
definition controller's single flush for a create or a delete.

### Lost updates: the row version (1.33)

`CookieConsentConfig` and `CookieDefinition` have an integer column `row_version` (default 1). Every admin edit form
carries it as a hidden field `rowVersion` (unmapped; the version of the page). On save, inside the save's transaction
and before the flush, `RowVersionGuard::advance()` runs one atomic
`UPDATE … SET row_version = row_version + 1 WHERE id = ? AND row_version = ?`:

- one row matched: the page was current, the row now holds the next version (and the row lock until the commit), and the
  flush writes the change;
- no row matched: another admin saved the row after the page was opened (or deleted it). `StaleVersionException` is
  thrown before anything is flushed; the controller drops the form's changes from the managed entities (`refresh()` of
  the row and of its managed translations: the manager is never cleared or reset, and a later flush of it writes nothing
  of the refused page) and re-renders the form with **409**,
  `X-Error-Code: COOKIE_CONSENT_STALE_VERSION` and an "changed by someone else, reload" message. No `X-Conflict-Code`
  and no `Retry-After`: it is not a lock conflict, and repeating the same page can never succeed.

Two admins saving at the same moment: the second `UPDATE` waits for the first one's commit (a lock wait, bounded by the
database timeout like any other), then matches no row. Only a wait past the timeout is `CONFLICT_001`.

The check reads the database, never the identity map, so a manager that kept a loaded row across requests (worker mode
with the kernel reset off) cannot make a stale page look current. A missing or non-numeric token is stale (a custom
template that lists the fields one by one must render `form.rowVersion`; `form_rest()` does).

The version advances on every admin save, also when only a translation changed (the translation rows belong to the
definition's save). Making a profile the default also advances the version of the profiles it demotes, so a page of the
old default opened before cannot write the old flag back. `saveSettings($config, $em)` without the third argument (host
code) saves without the check. The bundle has no Messenger handlers and no console command that writes.

## Conflict answers and codes

On the consent POST and the bundle's routes (`CookieConsentConflictListener`; your own routes keep your error handling):

| Code | When | Status | `Retry-After` | Client should |
| --- | --- | --- | --- | --- |
| `CONFLICT_001` | Deadlock, lock wait timeout or serialization failure (MySQL 1213 / 1205, MariaDB 1020, PostgreSQL 40001 / 40P01 / 55P03, anywhere in the exception chain, or the runner's `TransientStorageFailure`) and nothing committed since the request started | 409 (`conflict_response.status`) | Yes: `conflict_response.retry_after`, else 1 s | Retry |
| `CONFLICT_002` | The same after a commit earlier in the request (transaction-doctrine's request mark, `ConflictCode::forRequest()`, when its bundle is registered with `commit_detection`; otherwise the bundle's own ledger, fed by transaction-doctrine's commit-tracking middleware on the bundle's connection), a `ConcurrencyConflict` that committed, or a lost savepoint with no deadlock behind it (outcome unknown) | 409 (`conflict_response.status`) | No | Reload, do not repeat blindly |
| `COOKIE_CONSENT_STALE_VERSION` (`X-Error-Code`) | An admin save of a profile or a cookie definition made on a page that another admin's save has outdated (1.33) | 409 (HTML: the form again with the message) | No | Reload the page and apply the change again |
| `COOKIE_CONSENT_DUPLICATE_KEY` (`X-Error-Code`) | An admin save refused by a unique key: two admins adding the same cookie name or translation at once (or a name already used) | 409 | No | Reload the page and apply the change again |

- The `CONFLICT_*` code is in the `X-Conflict-Code` header, which only ever holds `CONFLICT_001` or `CONFLICT_002`. The
  domain answer has no `X-Conflict-Code`. If you call the consent endpoint cross-origin, list `X-Conflict-Code` in
  `Access-Control-Expose-Headers`.
- The answer is an `HttpException` with a fixed, client-safe message, rendered by your error page (HTML, or JSON for
  JSON requests). The storage error stays the previous exception for your logs; no SQL or value reaches the client.
- The consent modal's script posts with XHR and only closes the modal on a 2xx answer: after a 409 the modal stays
  open and the visitor saves again.
- A consent whose log rows committed and that then fails in a listener of `ConsentSavedEvent` (your code) on a lock
  conflict answers `CONFLICT_002` with commit tracking on (`commit_tracking: bundle` / `all`, or transaction-doctrine's
  `commit_detection`): the rows are stored, the cookies are not set. With `commit_tracking: off` and no
  transaction-doctrine ledger the answer is `CONFLICT_001`, and the visitor's retry stores a second set of rows: harmless,
  since the log is append-only and every consent is its own set.
- The codes are `Nowo\TransactionDoctrine\ConflictCode` values.

## What is not a conflict

- **Unique constraint violations** are not lock conflicts. On the admin routes they answer the domain 409 above. The
  consent log has no unique key; anywhere else a unique violation stays a real error.
- **Lost connections** are never mapped: the outcome of the write is unknown and nothing tells it was a lock.
- **Any other failure of the consent write** (a missing table, a host logger that throws) is a real error since 1.31:
  no cookie is set and the request fails. Before 1.31 a database error of the log was logged at debug level and the
  consent cookies were set without log rows.
- Validation errors stay form errors (422).
- A **stale admin page** is not a conflict either (see the row version above): its own domain answer, never
  `CONFLICT_001` / `CONFLICT_002`.

## Host options

```yaml
nowo_cookie_consent:
    conflict_response:
        status: null              # 409 (default) or 503
        retry_after: null         # seconds for CONFLICT_001
        commit_tracking: bundle   # bundle (default), all or off
```

- Unset (null), the bundle follows `nowo_transaction_doctrine.conflict_response` when nowo-tech/transaction-doctrine's
  Symfony bundle is registered, so the whole application answers the same; otherwise 409 with `Retry-After: 1`.
- `status: 503` for proxies or monitors that treat 503 as retryable; the codes and the `Retry-After` rule stay the same.
- `commit_tracking` decides which commits make a later conflict `CONFLICT_002` when transaction-doctrine's
  `commit_detection` is off (or its bundle is not registered):
  - `bundle` (default): the bundle registers transaction-doctrine's `CommitTrackingMiddleware` on the connection of its
    entity manager only (`doctrine.entity_manager`, else your default manager). Your other connections are not wrapped.
  - `all`: every Doctrine connection, so a commit of yours on another connection earlier in the request counts too.
  - `off`: no middleware; only the failure itself (and transaction-doctrine's request mark) decides.

  With transaction-doctrine's bundle registered and `commit_detection` on (its default), the bundle reuses that
  bundle's ledger and adds no middleware of its own, whatever this option says: no connection is wrapped twice.
- `doctrine.entity_manager`: give the bundle its own manager (see [CONFIGURATION](CONFIGURATION.md#dedicated-entity-manager))
  so a failed admin flush never closes yours.
- A DDD host that keeps the consent log in its own store (`CookieLoggerInterface` alias) gets the same answer when its
  logger throws a `TransientStorageFailure` or a DBAL lock error.
- Database settings: READ COMMITTED and a short `innodb_lock_wait_timeout` / `lock_timeout` (a few seconds) for HTTP;
  the wait is bounded by the database, not by the bundle.

## Retries

- HTTP writes make **one attempt**: the visitor or the admin retries after a `CONFLICT_001`. The bundle has no
  automatic replay, so no entity of an earlier attempt is reused.
- A retried consent after `CONFLICT_001` stores exactly one set of rows (nothing of the failed one was committed). Every
  consent the visitor saves is its own set of rows by design (the log is a history of decisions); the rows are never
  updated in place.
- The GDPR erasure is safe to run again: a failed batch is unchanged and the next run finds the remaining rows.
- The bundle has no Messenger handlers.

## Tests and CI

- `tests/Integration/Database/CookieConsentConcurrencyDatabaseTest.php` plays two sessions against real servers, each
  with a short `innodb_lock_wait_timeout` / `lock_wait_timeout` / `lock_timeout`:
  - two consents at once never wait for each other and both are stored whole (no lost or duplicated row);
  - a consent whose log meets a lock (InnoDB next-key lock, PostgreSQL table lock) answers 409 + `Retry-After` +
    `CONFLICT_001` (503 with the host option), stores no row and sets no cookie; the connection is usable and the retry
    stores exactly one consent;
  - after a commit earlier in the request (counted by the real commit-tracking middleware) it answers `CONFLICT_002`
    without `Retry-After`;
  - inside an open host transaction only the log's savepoint rolls back: the host's work commits;
  - two admins making two profiles the default at once (a second PHP process) leave exactly one default, the second
    save having waited for the first one's locks; a save that meets the other's lock past its timeout answers
    `CONFLICT_001` and saves nothing;
  - two admins open the same profile: the first save is whole (`row_version` 2), the second is refused as stale with
    nothing written, its connection and manager stay usable, and after a reload it saves;
  - a simultaneous profile save and a simultaneous cookie definition edit (a second PHP process holding the row lock):
    the loser waits, then is refused as stale, and the stored row is the winner's whole (no lost update); past the lock
    timeout the loser gets `CONFLICT_001` material (`TransientStorageFailure`), not a stale answer;
  - making a profile the default makes an open page of the demoted profile stale: never two defaults.
- `tests/Integration/Database/RowVersionDatabaseTest.php` (SQLite, and the three servers in the same job): the guard
  advances once and refuses the same token twice, a missing token and a deleted row are stale, the settings save and the
  definition edit check and advance the version (a translation-only change too), a stale save writes nothing, the manager
  stays open and the nesting level 0.
- `tests/Integration/Kernel/AdminPagesKernelTest.php`: the edit pages carry the token; a stale definition edit and a stale
  profile save answer 409 with `COOKIE_CONSENT_STALE_VERSION`, the message and the typed text, store nothing, and save
  after a reload; a submission without the token is refused.
- `tests/Unit/EventListener/CookieConsentConflictListenerTest.php` maps every case: `CONFLICT_001` for each vendor
  code, `CONFLICT_002` (own ledger, transaction-doctrine's mark, committed `ConcurrencyConflict`, lost savepoint), 503,
  a runner's hint, the duplicate key on each admin controller, lost connections and other errors left alone, host
  routes untouched, closed managers replaced and open ones never reset.
- `tests/Unit/EventSubscriber/CookieConsentFormSubscriberConflictTest.php`: the consent POST answers, sets no cookie
  and dispatches no event on a failed log, is not handled twice on the error response, and real errors propagate.
- `CookieConsentCommitTrackingPassTest` (with DoctrineBundle's `MiddlewaresPass`): `bundle` wraps only the bundle's
  connection, `all` every connection, `off` none, and transaction-doctrine's ledger is reused with no middleware of the
  bundle when its `commit_detection` is on.
- The CI job **Real databases (MySQL 8.4, MariaDB 11.4, PostgreSQL 16)** runs the `database` group on DBAL 3 and 4 with
  `--fail-on-skipped` and is a required check of `master`. The **GDPR ports** jobs stay as they were. The
  **Dependency floor** job runs the suites with the lowest allowed nowo-tech/transaction-* versions and checks that the
  demo locks install them.

```bash
COOKIE_CONSENT_TEST_DATABASE_URL='mysql://root@127.0.0.1:3306/cookie_consent?serverVersion=8.4.0' \
    vendor/bin/phpunit --group database --fail-on-skipped tests/Integration/Database
```

## Residual risks

- **Nothing accepted for the admin edits of a profile or a cookie definition** since 1.33: a stale page is refused (see
  the row version above). The version is per row: two admins editing different sections (tabs) of the same profile
  conflict too, as the second page is outdated by the first save. A host's own template that drops the hidden
  `rowVersion` field gets the stale answer on every save, never a silent overwrite.
- **Profiles that are disabled keep their default flag**, as before: only enabled profiles take part in the "only one
  default" rule (the resolver only reads enabled profiles).
- **DBAL 3 without savepoints.** On DBAL 3 with `use_savepoints: false`, a consent log that fails inside an open
  transaction of yours marks that transaction rollback-only (DBAL cannot roll back a nested level alone). DBAL 4 always
  uses savepoints.
- **Commit detection covers the bundle's connection by default.** Without transaction-doctrine's `commit_detection`, a
  commit of yours on another Doctrine connection earlier in the request is counted only with `commit_tracking: all`, and
  a commit through another client (PDO, another library) is never counted.
- **A host closed manager.** Without `doctrine.entity_manager` a failed admin flush closes your default manager; the
  bundle replaces it on its own routes (it was closed already), never an open one.
