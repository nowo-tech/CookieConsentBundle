# FrankenPHP worker mode audit (kernel not reset between requests)

| Field | Value |
|-------|-------|
| Package | `nowo-tech/cookie-consent-bundle` (`symfony-bundle`) |
| Audited revision | `v1.10.0` |
| Audit date | 2026-09-24 |
| Method | Manual review of every PHP file under `src/` (services, controllers, subscribers, Doctrine listener, Twig extensions, renderer, form types, repositories, DI extension, compiler passes, `Resources/config/services.yaml`). Symfony `Translator` / `LocaleSwitcher` internals checked in `vendor/` where the bundle depends on them |
| **Verdict** | ✅ **Viable under scenario B** (`reset_kernel false`) — request data is read per call, bundle memos are cleared at the start of every main request by a bundle-owned subscriber, database texts no longer mutate the shared translator. Clearing the application's EntityManager identity map between requests remains the application's responsibility |
| Original verdict | ❌ Not viable (as of `v1.9.7` / `e36c6b8`) — `CookieChecker` and `CookieLogger` captured the main `Request` in their constructor |
| Remediation (shipped in `v1.10.0`) | W-01…W-06 resolved: `CookieChecker`/`CookieLogger` use `RequestStack` per call; `nowo_cookie_consent_trans()` replaces `Translator::addResource()`; `CookieConsentRuntimeCacheResetSubscriber` (`kernel.request` 4096, main only); extended Doctrine invalidation; `CookieLogger` uses `ManagerRegistry` + `detach()`; API locale normalized. Regression tests simulate consecutive requests on the same instances without `reset()` |

## Execution model assumed

FrankenPHP worker mode boots the Symfony kernel once per worker and serves many requests with the same container. This audit assumes the **strict** variant: the kernel is **not** rebooted between requests, so every shared service, static property and PHP global survives from one request to the next. Two scenarios are evaluated:

- **A — kernel not rebooted, `services_resetter` still runs:** services tagged `kernel.reset` (or implementing `ResetInterface`) are reset between requests.
- **B — no reset at all:** nothing is reset; any per-request state kept in a service leaks into the next request.

A bundle that is safe under **B** is safe under **A** and under classic mode / PHP-FPM.

## Summary

| Area | Status | Notes |
|------|--------|-------|
| Mutable state in shared services | ✅ | `CookieChecker` / `CookieLogger` no longer store the request (W-01, W-02). `CookieLogger::$pendingLogs` is cleared in `finally` on every call |
| Static properties / `static` locals | ✅ | Only pure static helpers (`CookieInventoryNormalizer`, `PreferencesBubbleIconSanitizer`, `ColdStartRequestAttributes`, enums); no static properties |
| `ResetInterface` / `kernel.reset` coverage | ✅ | `CookieConsentConfigResolver`, `CookieInventoryProvider`, `CookieConsentConfigRepository` implement `ResetInterface` (scenario A) **and** are reset by `CookieConsentRuntimeCacheResetSubscriber` at the start of every main request (scenario B). `CookieChecker` / `CookieLogger` are stateless |
| Request / user / locale captured in services | ✅ | All services read `RequestStack` per call (W-01, W-02 resolved) |
| Superglobals, `$_ENV`, `putenv`, `ini_set`, `setlocale`, timezone | ✅ | None used. `CookieHandler` calls `date('r')` (default timezone, not changed by the bundle) |
| Doctrine / EntityManager | ✅ (bundle side) | `CookieLogger` gets the manager per call via `ManagerRegistry`, resets it when closed, detaches its log rows after flush; entity memos are request-scoped (W-05). Clearing the app's identity map remains the application's job under B |
| Output, headers, `exit`, shutdown functions | ✅ | Cookies are set on the `Response` object (`CookieHandler::saveCookie()`), no `setcookie()` / `header()` |
| Resources (files, sockets, cURL) held open | ✅ | None |
| Memory growth across requests | ✅ | No `Translator::addResource()` any more (W-03); caches keyed by user input live for one request only (W-04); API locales are normalized to `enabled_locales` (W-06) |
| Blocking I/O and timeouts | ✅ | No outbound HTTP; only DB queries |
| Third-party static state | ✅ | FormKit trait state is scoped per `buildForm()`; Symfony `Translator` state is covered in W-03 / W-06 |
| PHPStan FrankenPHP rulesets | ✅ | `ruleset-classic.neon` + `ruleset-worker.neon` included in `phpstan.neon.dist`; `composer phpstan` passes with no new ignores |

## Services reviewed

| Service | Shared | Mutable state | Scenario A | Scenario B |
|---------|--------|---------------|------------|------------|
| `Cookie\CookieChecker` | yes | none (`RequestStack` read per call) | ✅ | ✅ |
| `Cookie\CookieLogger` | yes | `$pendingLogs`, cleared in `finally` per call; manager obtained per call | ✅ | ✅ |
| `Cookie\CookieHandler` | yes | none (`readonly` flag + clock) | ✅ | ✅ |
| `Config\CookieConsentConfigResolver` | yes | `$resolvedByLocaleAndRoute` cache; `ResetInterface` + reset per main request | ✅ | ✅ |
| `Config\CookieInventoryProvider` | yes | 4 cache properties; `ResetInterface` + reset per main request | ✅ | ✅ |
| `Repository\CookieConsentConfigRepository` | yes | cached config entities; `ResetInterface` + reset per main request | ✅ | ✅ |
| `Config\CmpUxOptionsResolver`, `Config\CookieConsentConfigPayloadFactory`, `Config\CookieConsentConfigSelector`, `Config\CookieConsentRouteTargeting`, `Config\CookieConsentRoutePatternMatcher`, `Locale\LocaleResolver`, `Clock\SystemClock` | yes | none (`readonly` config; `RequestStack` read per call) | ✅ | ✅ |
| `Render\CookieConsentModalRenderer` | yes | none; stores the resolved config on the request only | ✅ | ✅ |
| `EventSubscriber\CookieConsentConfigTranslationSubscriber` (`kernel.request`, 19) | yes | none; stores the resolved config on the request only | ✅ | ✅ |
| `EventSubscriber\CookieConsentRuntimeCacheResetSubscriber` (`kernel.request`, 4096) — new | yes | none; resets the three memoizing services on main requests | ✅ | ✅ |
| `Twig\CookieConsentTranslationTwigExtension` — new | yes | none; reads the request attribute per call | ✅ | ✅ |
| `EventSubscriber\CookieConsentFormSubscriber` (`kernel.response`) | yes | none itself; depends on `CookieLogger` | ✅ | ✅ |
| `EventSubscriber\CookieConsentSchemaReadySubscriber` (`kernel.request`, 33) | yes | none; schema probe per request, result stored on the request | ✅ | ✅ |
| `EventSubscriber\CookieConsentAdminAccessSubscriber` | yes | none | ✅ | ✅ |
| `EventListener\CookieConsentConfigRuntimeCacheListener` (Doctrine) | yes | none; clears resolver/repository/inventory caches on config, copy and cookie definition changes | ✅ | ✅ |
| `Twig\CookieConsentTwigExtension` | yes | none itself; depends on `CookieChecker` | ✅ | ✅ |
| `Twig\CmpUxTwigExtension`, `Twig\CookieConsentAdminTwigExtension` | yes | none; globals are constant config strings | ✅ | ✅ |
| `Form\CookieConsentType` | yes | none itself; depends on `CookieChecker`; button labels from the request's resolved config | ✅ | ✅ |
| 10 other form types (`CookieDefinition*`, `DeleteCookieDefinitionType`, `Settings\*`) | yes | FormKit trait fields and `$activeTranslationDomain`, overwritten at the start of every `buildForm()` | ✅ | ✅ |
| 4 controllers (`CookieConsentController`, `CookieConsentConfigApiController`, `CookieConsentConfigSettingsAdminController`, `CookieDefinitionAdminController`) | yes (public) | none; only `readonly` injected services | ✅ | ✅ |
| `Security\ConfigurableCookieConsentAccessChecker` / `AllowAllCookieConsentAccessChecker` | yes | none; `isGranted()` per call | ✅ | ✅ |
| `DependencyInjection\TablePrefixListener` | yes | none (`readonly` prefix) | ✅ | ✅ |

Entities, `ResolvedCookieConsentConfig` and the `CookieConsentConfigSettingsSection` enum are value objects created per call; they are only kept in the caches listed above.

## Findings

### W-01 — `CookieChecker` freezes the first visitor's consent cookies (High)

- **Where:** `src/Cookie/CookieChecker.php:23` (`private readonly ?Request $request`), `:33-36` (constructor stores `$requestStack->getMainRequest()`), `:26` and `:96-135` (`$granularPreferences` memo). Consumed by `src/Twig/CookieConsentTwigExtension.php:127,157,190,300,312,467` (`nowo_cookie_consent_is_saved`, `nowo_cookie_consent_is_category_allowed`, `nowo_cookie_consent_is_open_by_default`, `nowo_cookie_consent_should_embed_modal`, diagnostics) and `src/Form/CookieConsentType.php:267-280` (initial checkbox values).
- **Worker impact:** the service is shared and has no reset. It is built once per worker, the first time a template or the consent form needs it, and it keeps that request's `Request` object for the rest of the worker's life. Every later visitor on that worker is evaluated against the **first visitor's** cookies:
  - if the first visitor accepted analytics/marketing, `nowo_cookie_consent_is_category_allowed()` returns `true` for everyone, so tracking scripts guarded by it load without consent (GDPR/ePrivacy breach);
  - if the first visitor had saved consent, the modal is not opened / not embedded for new visitors (`isCookieConsentOpenByDefault()` returns `'false'`);
  - the preferences form shows the first visitor's choices;
  - if the service is first built with no main request, it answers "no consent" forever.
  This is a cross-user leak and a wrong consent decision under **A and B** (`services_resetter` does not touch this service).
- **Recommendation:** keep `RequestStack` in the service and call `getMainRequest()` inside each method. Drop the `$granularPreferences` memo, or key it by request (for example store the decoded map in a request attribute). Implementing `ResetInterface` alone is not enough: sub-requests and scenario B would still be wrong.
- **Status:** Resolved — `src/Cookie/CookieChecker.php` keeps the `RequestStack` and reads `getMainRequest()` in every method; the `$granularPreferences` memo was removed (the cookie is decoded per call). Constructor signature unchanged. Tests: `CookieCheckerTest::testConsecutiveRequestsOnSameInstanceDoNotLeakConsentState`, `testInstanceBuiltWithoutRequestSeesLaterRequests`, `CookieConsentTypeDatabaseLabelsTest`.

### W-02 — `CookieLogger` records the first visitor's IP for every consent (High)

- **Where:** `src/Cookie/CookieLogger.php:22` (`private readonly ?Request $request`), `:38` (constructor stores `$requestStack->getMainRequest()`), `:49-53` (IP read from the stored request). Called from `src/EventSubscriber/CookieConsentFormSubscriber.php:105-107` on `kernel.response`.
- **Worker impact:** the first request that builds `CookieConsentFormSubscriber` fixes the `Request` used by the logger. After that, every consent log row written by the worker (`CookieConsentLog.ipAddress`) stores the (partially masked) IP of that first visitor, attached to another user's consent key. The consent audit trail — the proof of consent the bundle exists to keep — becomes wrong and mixes personal data between users. If the service was first built without a main request, every later `log()` throws `RuntimeException('No request found')` from `kernel.response`. Happens under **A and B**.
- **Recommendation:** inject `RequestStack` and read `getMainRequest()` inside `log()`, or pass the `Request` from `CookieConsentFormSubscriber::handleFormSubmit()` (it already has it) as a method argument.
- **Status:** Resolved — `src/Cookie/CookieLogger.php` stores the `RequestStack` and reads the main request inside `log()` (public `log()` signature unchanged). Tests: `CookieLoggerTest::testConsecutiveRequestsOnSameInstanceRecordTheirOwnIp`, `testInstanceBuiltBeforeAnyRequestLogsLaterRequests`.

### W-03 — Database translations are appended to the Translator on every request (Medium)

- **Where:** `src/EventSubscriber/CookieConsentConfigTranslationSubscriber.php:80-89` (every main request when `use_database_config: true`) and `src/Render/CookieConsentModalRenderer.php:131-140`, both calling `Translator::addResource('array', $messages, $locale, 'NowoCookieConsentBundle')`.
- **Worker impact:** in production the service is FrameworkBundle's `Translator`, which is shared and has no `kernel.reset` tag. Its `addResource()` only appends to an internal `$resources` list (`vendor/symfony/framework-bundle/Translation/Translator.php:116-122`). That list is drained only when a catalogue is (re)built (`initialize()`), which does not happen for a catalogue that is already loaded or read from the cache file. In a long-lived worker the list therefore grows by one full message array per request, under **A and B**. The messages added after the first catalogue load are also not applied, so admin edits and route-specific profiles (different messages for the same locale and domain) are not reflected until the worker restarts. Side note, not verified at runtime: because the catalogue cache path does not depend on these runtime resources, the messages may also be ignored in classic mode when the catalogue cache is warm.
- **Recommendation:** do not mutate the global translator per request. Read the texts from `ResolvedCookieConsentConfig::getTranslationMessages()` (or the translation entity) in the renderer/templates, as `CookieConsentConfigPayloadFactory` already does, or decorate the translator with a request-scoped overlay that is cleared in `kernel.finish_request`.
- **Status:** Resolved — both `addResource()` calls were removed (`CookieConsentConfigTranslationSubscriber`, `CookieConsentModalRenderer`; their unused `TranslatorInterface` constructor argument was dropped, both classes are `final`). New Twig function `nowo_cookie_consent_trans(id, display_config = null)` (`src/Twig/CookieConsentTranslationTwigExtension.php`) returns the resolved profile text of the current request or falls back to the `NowoCookieConsentBundle` domain; the bundled modal templates use it. `CookieConsentType` uses the DB button texts as literal labels (`translation_domain: false`). This also fixes the classic-mode side note (texts ignored with a warm catalogue cache). Hosts with overridden modal templates must switch to the function (documented in `docs/UPGRADING.md`). Tests: `CookieConsentTranslationTwigExtensionTest::testConsecutiveRequestsDoNotLeakDatabaseTextsOrMutateTranslator`, `CookieConsentTypeDatabaseLabelsTest`, `CookieConsentConfigTranslationSubscriberTest::testConsecutiveRequestsGetTheirOwnLocaleCopy`.

### W-04 — Runtime config caches depend on `kernel.reset`, invalidation is partial and keys are user-controlled (Medium, scenario B)

- **Where:** `src/Config/CookieConsentConfigResolver.php:22,61-82` (cache keyed by `locale + "\0" + route`), `src/Config/CookieInventoryProvider.php:35-58,116-137,190-215` (caches keyed by config id and locale), `src/Repository/CookieConsentConfigRepository.php:22-30,65-131` (cached entities). Invalidation: `src/EventListener/CookieConsentConfigRuntimeCacheListener.php:19-53`. User-controlled keys: `src/Controller/CookieConsentConfigApiController.php:37-41,64-84` (`?locale=` and `?route=` query parameters) passed to `CookieConsentConfigPayloadFactory::build()` (`src/Config/CookieConsentConfigPayloadFactory.php:43-45`).
- **Worker impact:** the three services implement `ResetInterface` and their `reset()` methods clear every cache property, so scenario **A** is fine. Under **B**:
  - caches never expire. The Doctrine listener clears only the resolver and repository caches, only for `CookieConsentConfig` changes, and only in the worker that made the change. Changes to `CookieConsentConfigTranslation` or `CookieDefinition` (admin cookie inventory) are never invalidated, and other workers keep the old profile, texts and cookie tables until they restart;
  - `GET /cookie-consent/config?route=<anything>&locale=<anything>` adds one entry per distinct pair to `$resolvedByLocaleAndRoute` (and inventory entries per locale), so any client can grow worker memory without limit.
- **Recommendation:** keep `services_resetter` enabled. Also validate `locale` against `LocaleResolver::getEnabledLocales()` and `route` against the router (or cap the cache size) in the API controller, and extend the Doctrine listener to `CookieConsentConfigTranslation`, `CookieDefinition` and `CookieDefinitionTranslation` (also clearing `CookieInventoryProvider`).
- **Status:** Resolved — new `src/EventSubscriber/CookieConsentRuntimeCacheResetSubscriber.php` (`kernel.request`, priority 4096, main requests only) calls `reset()` on the resolver, inventory provider and config repository, so every memo lives for one request: other workers' admin changes are seen on the next request and user-controlled keys (`?route=`, `?locale=`) cannot accumulate. `ResetInterface` is kept for scenario A. `CookieConsentConfigRuntimeCacheListener` now also reacts to `CookieConsentConfigTranslation`, `CookieDefinition`, `CookieDefinitionTranslation` and clears `CookieInventoryProvider` (new optional constructor argument). Locale is normalized (W-06); `route` is not validated against the router because the per-request reset already bounds the cache. Tests: `CookieConsentRuntimeCacheResetSubscriberTest`, `CookieConsentConfigRuntimeCacheListenerTest::testInvalidatesAllRuntimeCachesWhenCopyOrInventoryChanges`.

### W-05 — Doctrine EntityManager state relies on DoctrineBundle's reset (Medium, scenario B)

- **Where:** `src/Cookie/CookieLogger.php:66` (`flush()` inside `kernel.response`), admin controllers (`src/Controller/CookieConsentConfigSettingsAdminController.php:92`, `src/Controller/CookieDefinitionAdminController.php:148-149,202-207`), plus the entity caches of W-04.
- **Worker impact:** under **A** DoctrineBundle clears the identity map and replaces a closed EntityManager between requests, and the bundle's own caches are reset at the same time. Under **B**, a failed flush (the `DBALException|ORMException` catch in `CookieConsentFormSubscriber::onResponse()` hides it) leaves the EntityManager closed for every later request of that worker, and the identity map keeps every `CookieConsentLog` row written by the worker, so memory grows with each consent submission.
- **Recommendation:** keep `services_resetter` enabled; if running under B, add a `kernel.terminate` listener that calls `ManagerRegistry::resetManager()` when the manager is closed and `clear()` otherwise, and set `max_requests` on the worker.
- **Status:** Resolved (bundle side) — `CookieLogger` takes an optional `?ManagerRegistry $managerRegistry` (wired to `@?doctrine`), obtains the manager of `CookieConsentLog` per call, resets it when closed (also right after a failed flush, before rethrowing) and `detach()`es the rows it persisted after flushing, so the identity map does not grow with consent submissions. Bundle entity memos are request-scoped (W-04). The bundle deliberately does **not** call `clear()` on the application's EntityManager: clearing the identity map between requests under scenario B remains the application's responsibility. Tests: `CookieLoggerTest::testDetachesOnlyTheEntitiesOfEachCallAfterFlush`, `testResetsClosedManagerBeforeLogging`, `testFailedFlushResetsClosedManagerAndRethrows`, fallback tests.

### W-06 — Unvalidated `locale` in the config API creates translator catalogues (Medium)

- **Where:** `src/Controller/CookieConsentConfigApiController.php:71-84` (`?locale=` used as is on `/cookie-consent/config`), then `src/Config/CookieConsentConfigPayloadFactory.php:283-286` (`$this->translator->trans($id, [], 'NowoCookieConsentBundle', $locale)`).
- **Worker impact:** every distinct locale string that passes Symfony's locale syntax check makes the shared `Translator` load and keep a new catalogue (and write a catalogue cache file). In a worker these catalogues stay in memory for the worker's life under **A and B**, so a client iterating locale values grows memory (and the cache directory). The localized route (`/{_locale}/cookie-consent/config`) limits the value to `[a-z]{2}(-[A-Z]{2})?`, the non-localized route does not.
- **Recommendation:** normalize the requested locale with `LocaleResolver` and fall back to the default locale when it is not in `enabled_locales` before building the payload.
- **Status:** Resolved — new `LocaleResolver::normalize()` (exact locale → primary subtag → `default_locale` → first enabled locale); `CookieConsentConfigApiController` applies it to `?locale=`, the request locale and `{_locale}`. Tests: `LocaleResolverTest::testNormalize*`, `CookieConsentConfigApiControllerTest::testUnknownQueryLocalesAreNormalizedBeforeReachingTheTranslator`, `testGetLocalizedConfigNormalizesDisabledLocale`.

Info notes:

- `CookieConsentSchemaReadySubscriber` runs a schema check (`createSchemaManager()->tablesExist()`) on every main request and stores the result on the request, not in the service. This is correct for a long-lived worker (no stale result) but costs one metadata query per request.
- `CookieHandler::save()` uses `date('r')` for the consent cookie value and `CookieConsentFormSubscriber::getCookieConsentKey()` uses `uniqid('', true)`; neither keeps state between requests.
- `AbstractCookieConsentConfigSettingsType::$activeTranslationDomain` (`src/Form/Settings/AbstractCookieConsentConfigSettingsType.php:105-111`) is overwritten at the start of every `buildForm()` of the settings types, so it cannot leak between requests.
- Both demos (`demo/symfony8`, `demo/symfony8-tailwind`) run FrankenPHP with a `worker` block in `docker/frankenphp/Caddyfile`; `Caddyfile.dev` uses classic mode, which hides W-01/W-02 during development.

## Usage recommendations in worker mode

- The remediated version is safe in worker mode with or without `services_resetter`. Keeping `services_resetter` enabled is still recommended for framework services (Doctrine identity map, security token, translator locale).
- Under scenario B the application must clear its own EntityManager between requests (the bundle only detaches its own log rows and resets a closed manager).
- With overridden modal templates and `use_database_config: true`, use `nowo_cookie_consent_trans()` for the profile texts (plain `|trans` returns only the YAML copy).
- Hosts that override `CookieConsentFormSubscriber`, `CookieChecker` or `CookieLogger` (the classes are not `final`) must not store the request, user or consent data in properties.

## Re-audit triggers

Re-run this audit when a change: touches `CookieChecker`, `CookieLogger` or any constructor that receives `RequestStack`; adds a property or cache to a service; adds or changes a `ResetInterface::reset()`; changes how database translations reach the translator; adds API parameters used as cache keys; or adds a new Doctrine listener.
