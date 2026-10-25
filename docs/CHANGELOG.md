# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Table of contents

- [[Unreleased]](#unreleased)
- [[1.19.3] - 2026-10-25](#1193---2026-10-25)
- [[1.19.2] - 2026-10-25](#1192---2026-10-25)
- [[1.19.1] - 2026-10-24](#1191---2026-10-24)
- [[1.19.0] - 2026-10-21](#1190---2026-10-21)
- [[1.18.2] - 2026-10-20](#1182---2026-10-20)
- [[1.18.1] - 2026-10-20](#1181---2026-10-20)
- [[1.18.0] - 2026-10-19](#1180---2026-10-19)
- [[1.17.0] - 2026-10-18](#1170---2026-10-18)
- [[1.16.3] - 2026-10-18](#1163---2026-10-18)
- [[1.16.2] - 2026-10-18](#1162---2026-10-18)
- [[1.16.1] - 2026-10-18](#1161---2026-10-18)
- [[1.16.0] - 2026-10-18](#1160---2026-10-18)
- [[1.15.2] - 2026-10-13](#1152---2026-10-13)
- [[1.15.1] - 2026-10-13](#1151---2026-10-13)
- [[1.15.0] - 2026-10-13](#1150---2026-10-13)
- [[1.14.0] - 2026-10-13](#1140---2026-10-13)
- [[1.13.1] - 2026-10-13](#1131---2026-10-13)
- [[1.13.0] - 2026-10-13](#1130---2026-10-13)
- [[1.12.0] - 2026-10-09](#1120---2026-10-09)
- [[1.11.2] - 2026-10-09](#1112---2026-10-09)
- [[1.11.1] - 2026-09-28](#1111---2026-09-28)
- [[1.11.0] - 2026-09-28](#1110---2026-09-28)
- [[1.10.1] - 2026-09-27](#1101---2026-09-27)
- [[1.10.0] - 2026-09-24](#1100---2026-09-24)
- [[1.9.7] - 2026-08-29](#197---2026-08-29)
- [[1.9.6] - 2026-08-24](#196---2026-08-24)
- [[1.9.5] - 2026-08-21](#195---2026-08-21)
- [[1.9.4] - 2026-08-20](#194---2026-08-20)
- [[1.9.3] - 2026-08-19](#193---2026-08-19)
- [[1.9.2] - 2026-08-19](#192---2026-08-19)
- [[1.9.1] - 2026-08-18](#191---2026-08-18)
- [[1.9.0] - 2026-08-15](#190---2026-08-15)
- [[1.8.0] - 2026-08-15](#180---2026-08-15)
- [[1.7.0] - 2026-08-15](#170---2026-08-15)
- [[1.6.3] - 2026-08-11](#163---2026-08-11)
- [[1.6.2] - 2026-08-11](#162---2026-08-11)
- [[1.6.1] - 2026-08-11](#161---2026-08-11)
- [[1.6.0] - 2026-08-04](#160---2026-08-04)
- [[1.5.2] - 2026-08-03](#152---2026-08-03)
- [[1.5.1] - 2026-08-03](#151---2026-08-03)
- [[1.5.0] - 2026-08-01](#150---2026-08-01)
- [[1.4.9] - 2026-08-01](#149---2026-08-01)
- [[1.4.8] - 2026-07-30](#148---2026-07-30)
- [[1.4.7] - 2026-07-30](#147---2026-07-30)
- [[1.4.6] - 2026-07-30](#146---2026-07-30)
- [[1.4.5] - 2026-07-30](#145---2026-07-30)
- [[1.4.4] - 2026-07-30](#144---2026-07-30)
- [[1.4.3] - 2026-07-30](#143---2026-07-30)
- [[1.4.2] - 2026-07-29](#142---2026-07-29)
- [[1.4.1] - 2026-07-28](#141---2026-07-28)
- [[1.4.0] - 2026-07-27](#140---2026-07-27)
- [[1.3.6] - 2026-07-27](#136---2026-07-27)
- [[1.3.5] - 2026-07-24](#135---2026-07-24)
- [[1.3.4] - 2026-07-22](#134---2026-07-22)
- [[1.3.3] - 2026-07-20](#133---2026-07-20)
- [[1.3.2] - 2026-07-13](#132---2026-07-13)
- [[1.3.1] - 2026-07-09](#131---2026-07-09)
- [[1.3.0] - 2026-07-05](#130---2026-07-05)
- [[1.2.0] - 2026-06-15](#120---2026-06-15)
- [[1.1.1] - 2026-06-15](#111---2026-06-15)
- [[1.1.0] - 2026-06-15](#110---2026-06-15)
- [[1.0.0] - 2026-06-15](#100---2026-06-15)

## [Unreleased]

### Changed

- Demo: the reset-consent, delete cookie definition, delete profile copy and delete profile buttons of both demos (`symfony8`, `symfony8-tailwind`) render FormKit `CsrfOnlyType` forms (`form_start` / child loop / `form_end`, same `_token` token ids; reset consent is now CSRF-protected) and the demo controllers validate the submitted Form (REQ-TWIG-005).
- The two checkbox form themes document that they are the allowlisted REQ-TWIG-005 "Form themes" exception (comment only).

### Added

- `make check-no-raw-html-form` now runs in `release-check` (REQ-TWIG-005), with an explicit allowlist for the two form-theme files.

## [1.19.3] - 2026-10-25

Consent is saved again with stateless CSRF (submit token id) and with doctrine.table_prefix (cold-start probe). See [UPGRADING](UPGRADING.md#to-1193).

### Fixed

- **Consent was never saved with stateless CSRF.** `CookieConsentType` had no `csrf_token_id`, so the token fell back
  to the form name `cookie_consent`: `framework.form.csrf_protection.token_id` only applies to the application's own
  form types, and `cookie_consent` is not in `stateless_token_ids`. Accept / reject / save returned 200 with the form
  error "The CSRF token is invalid" and no `Cookie_Consent` cookie. The form now uses the `submit` token id
  (`CookieConsentType::CSRF_TOKEN_ID`, in the default `stateless_token_ids`; still a valid session token id when CSRF
  is not stateless).
- **Consent was never saved with `doctrine.table_prefix`.** The cold-start probe (`CookieConsentSchemaReadySubscriber`,
  1.8.0) looked for `dashboard_cookie_config` without the configured prefix, so every request was flagged "schema not
  ready" and the consent form handler skipped saving. The probe now checks `<table_prefix>dashboard_cookie_config`.
  The demo functional and Playwright tests assert `Cookie_Consent` is set after accept / reject / save.

## [1.19.2] - 2026-10-25

Reproducible consent modal script (build id instead of build time) and a read-only validate-phpdoc. See [UPGRADING](UPGRADING.md#to-1192).

### Changed

- **Assets:** `nowo-consent-modal.js` is now reproducible. Instead of the build time, the bundle logger prints a build id: a hash of the TypeScript/CSS sources and `pnpm-lock.yaml`. Rebuilding unchanged sources (`make assets`, `make release-check`) no longer rewrites the committed script. The console line changes from `script loaded, build time: <ISO date>` to `script loaded, build: <id>`.
- **Developer tooling (REQ-CS-001):** `make validate-phpdoc` (`.scripts/complete-public-phpdoc.php`) is now read-only. It fails when a class or public method in `src/` has no PHPDoc block. Before, it added `@return` tags that only repeated the native type, `cs-fix` (`no_superfluous_phpdoc_tags`) removed them again, and `release-check` left 30 files modified. Added the missing docblocks on `SystemClock`, `CookieConsentConfigRuntimeCacheListener`, `DeleteCookieDefinitionType`, `CookieDefinitionType::__construct()` and `AbstractCookieConsentConfigSettingsType`.
- **Developer tooling (REQ-MAKE-002):** `make release-check` runs the full REQ-MAKE-002 chain in order (ensure-up, co-author and open-PR gates, check-twig-extra, validate-translations, assets, composer-sync, cs-fix, cs-check, rector-dry, phpstan, igor, validate-phpdoc, test-coverage, demo release-check, test-ts); the demo release-check now runs before test-ts. New `make check-no-raw-html-form` target (`.scripts/check-no-raw-html-form.sh`, REQ-TWIG-005); it joins the chain once the remaining raw form markup in the demos is migrated.

## [1.19.1] - 2026-10-24

Bundle views render form bodies through the child loop (REQ-TWIG-003). See [UPGRADING](UPGRADING.md#to-1191).

### Changed

- **Consent modal (REQ-TWIG-003):** `cookie_consent.html.twig` and `cookie_consent.tailwind.html.twig` keep their custom layout (buttons placed by name in the banner / preferences steps, categories grouped by `preference_sections`, rule 3) and end with a child loop that renders every child the layout did not place (the CSRF token, host extensions), followed by `form_end(form)` instead of an explicit `_token` widget and `render_rest: false`. `_preference_sections.html.twig` skips children already rendered and, when preference sections are configured, marks the categories no section lists and the per-cookie toggles no category table showed as rendered, so they stay hidden as before. The rendered markup is unchanged.
- **Demo (REQ-TWIG-003):** both demos (`symfony8`, `symfony8-tailwind`) render `demo/config/settings`, `demo/config/_form` and `demo/cookies/form` through the child loop (`form_row(child)`) instead of one `form_row(form.x)` per field; the settings sections open at the first field of each section, the cookie definition grid keeps its custom layout (rule 3) and translations keep the locale tabs.

### Fixed

- **Consent modal:** with granular cookie selection and no preference sections, the modal no longer fails with "Field cookies has already been rendered" (`_preference_sections.html.twig` now skips children the category tables already rendered).

## [1.19.0] - 2026-10-21

@final public services, injected form factory, PHP 8.5 demos, coverage and translation gates. See [UPGRADING](UPGRADING.md#to-1190).

### Changed

- Services, controllers, form types, the Twig extension and the DI classes that are not extension points are marked `@final` (real `final` in 2.0); `CookieConsentConfigFullSettingsType` (extended by the settings form) and the bundle class stay open (REQ-PHP-001). See [UPGRADING](UPGRADING.md).
- `CookieDefinitionAdminController` receives the form factory through its constructor (autowired) instead of `$this->container->get('form.factory')` (REQ-SF-003).
- Demos build on `dunglas/frankenphp:1-php8.5-alpine`, the newest PHP allowed by `require.php` (REQ-DEMO-010).
- `make test-coverage` prints the PHPUnit text summary, publishes `coverage-php.txt` and fails under 99% lines (`coverage-check` is now an alias); `release-check` starts with `ensure-up` and runs `validate-translations`, which now checks key parity against `en` (REQ-MAKE-002, REQ-MAKE-004, REQ-TEST-008).
- README and badge publish the measured PHP and TypeScript coverage (REQ-TEST-003, REQ-TEST-007); the release security checklist gains the AI security audit item (REQ-SEC-002); the co-author check accepts git worktrees.

## [1.18.2] - 2026-10-20

Dependency refresh (bundle and demos), php-cs-fixer ParallelConfig(2). See [UPGRADING](UPGRADING.md#to-1182).

### Changed

- Dependencies refreshed (Composer and pnpm/npm lock files of the bundle and its demos, campaign 2026-10-19); shipped assets rebuilt.
- `.php-cs-fixer.dist.php`: `new ParallelConfig(2)` (REQ-CS-001).

## [1.18.1] - 2026-10-20

Documentation: wave-1 REQ compliance (README, CONTRIBUTING, issue templates, Spec Kit inventory). See [UPGRADING](UPGRADING.md#to-1181).

### Documentation

- Documentation: wave-1 REQ compliance — canonical issue templates, README documentation list order (PSR under Additional documentation), CONTRIBUTING Cursor Agent queue settings (REQ-IDE-006) and Code of Conduct link, tables of contents on long docs, demo README links DEMO-FRANKENPHP, commented demo `.env.example`, Spec Kit inventory refresh after the UiKit admin migration (136/136).

## [1.18.0] - 2026-10-19

Cookie definition translations as fixed locale tabs (one per enabled locale; other locales kept read-only); bubble icon allow-list docs. See [UPGRADING](UPGRADING.md#to-1180).

### Changed

- **Cookie definition translations as locale tabs** (breaking for template overrides and posted field names): the
  add / delete rows table with a free-text locale is replaced by one fixed tab per enabled locale
  (`nowo_cookie_consent.enabled_locales`, default locale first) through the FormKit `locale_tabs` include (entries
  mode) and the UiKit `locale_tabs` partial. `CookieDefinitionType::translations` is unmapped and keyed by locale
  (`cookie_definition[translations][es][provider]`); on submit a filled tab adds its translation, an emptied tab
  (no provider, no purpose) removes it, and translations of locales that are not enabled are never touched (listed
  read-only under the tabs). New options `enabled_locales` (default: the locale resolver's list) and `default_locale`.
- `CookieDefinition::$translations` is indexed by locale (`indexBy: 'locale'`, no schema change) so validation errors
  map to the field in its tab; `addTranslation()` keys a translation by its locale.
- The admin controller no longer attaches a blank translation for the request locale on new / edit.
- Requires `nowo-tech/form-kit-bundle` **^2.13** (was ^2.9) and `nowo-tech/ui-kit-bundle` **^2.5.1** (was ^2.2).
- Demos: the host cookie form of both demos uses the same include; e2e test and the `admin-cookie-definition-form` screenshot updated.

### Added

- Translation keys `nowo_cookie_consent.admin.cookie_definition.fields.translations_help` and `.other_translations`
  in every shipped locale.

### Removed

- Template `admin/cookie_definition/_translations_table.html.twig` (replaced by `_translations_tabs.html.twig`).

### Documentation

- CONFIGURATION "Bubble icon allow-list": every SVG / HTML element and attribute `preferences_bubble_icon` keeps, what is rejected (filters such as `feDropShadow`, `img`, `foreignObject`, external `href` / `url()`) or silently dropped (`style`, `filter`, unknown attributes), and an example converting an `<img>` icon to inline SVG. A unit test keeps the table in sync with the sanitizer.

## [1.17.0] - 2026-10-18

Config `privacy_route` fallback for the privacy link of the modal; profile copy in the locale of the URL; the modal
no longer depends on the host Tailwind build; "allowed by default" check row. See [UPGRADING](UPGRADING.md#to-1170).

### Added

- `nowo_cookie_consent.privacy_route` (default `null`): route name the modal links as the privacy policy when the
  profile translation `nowo_cookie_consent.privacy_route` is empty (database profile or YAML catalogue). The profile
  value still wins. Used by the server-rendered modal (both themes) and the config API payload (`privacyRoute`);
  container parameter `nowo_cookie_consent.privacy_route`; Twig function `nowo_cookie_consent_privacy_route()`.
  LegalKitBundle sets it to `legal_privacy` unless the host does (one line instead of a translation edit per host).

### Fixed

- The database profile copy is resolved after Symfony's `LocaleListener`: `CookieConsentConfigTranslationSubscriber`
  ran at `kernel.request` priority 19, before the locale of `/{_locale}/…` was applied (16), so `/en/…` on an
  application whose default locale is `es` showed the Spanish profile copy mixed with English bundle strings. It now
  runs at priority 15 (real-kernel regression test with a prefixed non-default locale).
- The Tailwind modal no longer needs the host Tailwind build to scan the bundle views: the bundle stylesheet hides the
  closed modal (`.nowo-cookie-consent:not(.show)`) and positions the overlay (`fixed`, `inset: 0`, padding, dialog
  width) itself instead of relying on the `hidden` / `fixed` / `inset-0` / `p-4` / `max-w-4xl` utilities.
- Admin "new / edit cookie" form: the "allowed by default" checkbox and its label share one check row (the label
  wrapped under the checkbox with some host form themes); help and errors stay below.

## [1.16.3] - 2026-10-18

Enabled locales accept %parameters% and env vars (no compile error with a parameter). See [UPGRADING](UPGRADING.md#to-1163).

### Fixed

- `enabled_locales` given as a container parameter or env var (`'%kernel.enabled_locales%'`, `'%app.locales%'`,
  `'%env(json:APP_LOCALES)%'`) no longer breaks the container build ("Expected array, but got string"): `prepend()`
  processed the whole raw host config, where the parameter is still a string (and `kernel.enabled_locales` is not
  defined yet), and the node only accepted a literal list. `prepend()` now processes only the `web_ui` node (so any
  list option may be a build-time parameter), `enabled_locales` accepts a list or a parameter / env placeholder, and
  an env var is read at runtime by the locale resolver. See [CONFIGURATION](CONFIGURATION.md#locale-detection).

[1.16.3]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.16.3

## [1.16.2] - 2026-10-18

Requires UiKit ^2.2; locale and route-pattern placeholders are translated keys. See [UPGRADING](UPGRADING.md#to-1162).

### Changed

- Requires `nowo-tech/ui-kit-bundle` **^2.2** (default-theme polish: table header hierarchy, striped rows, subtle
  badges, secondary-level section tabs under the area nav, solid destructive confirm button with a close button). See
  [UPGRADING](UPGRADING.md#unreleased).

### Removed

- The inventory's scoped `flex-wrap: nowrap` rule for the edit / delete row actions
  (`nowo-cookie-consent-admin.css`): UiKit 2.2 keeps row actions on one line in every list (`width: 1%;
  white-space: nowrap` actions column).

### Fixed

- **Missing messages (profiler):** literal placeholders were looked up as messages of `NowoCookieConsentBundle` by
  the form theme: the translation locale example (`en`) and the route-pattern examples of the settings forms
  (`admin_*` / `cookie_consent_*`, `home` / `admin_*`). They are catalogue keys now
  (`nowo_cookie_consent.admin.cookie_definition.fields.locale_placeholder`,
  `nowo_cookie_consent.admin.config.settings.fields.route_patterns_placeholder`, `…auto_show_routes_placeholder`;
  9 locales), translated once by the theme. The `route_patterns_placeholder` / `auto_show_routes_placeholder` form
  options now default to those keys (a custom value is translated in the form domain, like any placeholder). A
  contract test renders every admin page with a `DataCollectorTranslator` and asserts no missing message.

### Demo

- The host layouts print the UiKit runtimes through the UiKit `scripts` partial; the Tailwind demo themes
  `btn_danger_solid` (danger confirm buttons) next to `btn_danger`.

[1.16.2]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.16.2

## [1.16.1] - 2026-10-18

Readable dark preferences modal (full token sets, accent save button), cookie definition form grid and translations table, one-line inventory rows; demo fixes. See [UPGRADING](UPGRADING.md#to-1161).

UI polish after the UiKit page-contract migration: readable dark preferences modal, cookie definition form and inventory list layout, demo fixes.

### Fixed

- **Preferences modal, dark themes:** with `dark-turquoise` and `elegant-black` the category headers inherited the light-theme pastel tints (white titles on pastel backgrounds, unreadable) and the footer the light grey strip. Both themes now declare the full token set: dark tints of the category hues (`--nowo-cc-card-*`), footer on the modal surface (`--nowo-cc-footer-bg: var(--nowo-cc-bg)`, also for `dark` / dark mode), separators, link and button hover tokens, a visible read-only toggle track. The base category tints derive from `--nowo-cc-category-block-bg` / `-border`, so a theme that only sets the block tokens stays coherent.
- **Preferences modal:** "Save preferences", the only action of the two-step preferences step, renders as the primary (accent) button instead of the secondary one (Bootstrap and Tailwind templates; class `nowo-cookie-consent__btn--save`). The scroll area keeps 1.5rem of bottom padding and a scroll padding so the last category / cookie row is not cut at the footer edge; cookie names in the cookie table use the theme text colour.
- **Cookie definition form:** the translations collection carried the input class (`nowo-ui-input`, capped at 20rem) and rendered one numbered fieldset per entry (`0`, `1`, …) with narrow inputs and tiny textareas.
- **Cookie inventory:** edit / delete row actions stacked vertically, short cells (name, category, duration, default) wrapped, long purposes made every row tall.

### Changed

- **Cookie definition form layout:** scalar fields in a two-column grid (one column on narrow screens), the translations as a full-width table — one row per locale with the locale as the row header, provider and purpose (readable textarea), per-row `aria-label`s and errors under each widget (`admin/cookie_definition/_translations_table.html.twig`). Form names, CSRF token and validation are unchanged.
- **Seeded `cookie_consent` FormKit profile:** text controls use the UiKit 2.1 `input_full` slot (`nowo-ui-input nowo-ui-input--full`) instead of `nowo-ui-input`; the collection wrapper gets no input classes. Hosts that define their own `nowo_form_kit.profiles.cookie_consent` are not affected.
- **Cookie inventory list:** the name uses the UiKit `code` slot; name, category, duration and default do not wrap; the purpose is clamped to two lines with the full text in `title`.
- The admin list and form pages link `bundles/nowocookieconsent/nowo-cookie-consent-admin.css` (layout of the bundle's own data blocks; reads UiKit tokens) in `nowo_ui_styles` — run `assets:install` after upgrading.
- **Demo:** the intro and legal texts say MySQL (the demos run MySQL 8.4, not SQLite); in dev the banner and the preferences bubble sit above the open WebProfiler toolbar; smaller blocked-iframe placeholder; `list_page_size: 10`. New e2e checks: dark-turquoise preferences contrast / footer / accent, inventory row layout, cookie form grid + translations table with a validation and save round-trip.

[1.16.1]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.16.1

## [1.16.0] - 2026-10-18

Admin UI on the UiKit 2.1 standard page contract (REQ-UI-003): list, form and settings scaffolds, area navigation in the page header, one host theme; `web_ui.chrome` bridged into `nowo_ui_kit.theme.bundles`. Validation errors instead of 500 on empty cookie fields; unique row delete forms; correct list pagination. See [UPGRADING](UPGRADING.md#to-1160).

### Added

- **UiKit 2.x page contract (REQ-UI-003):** the admin pages extend the UiKit scaffolds — cookie inventory → `page/list`, cookie definition → `page/form`, profile settings → `page/settings` — and the controllers pass UiKit view models built by the new `Admin\AdminPageFactory`. Page header, area and section tabs, table, row actions, delete confirm dialogs, pagination, empty state, form frame and flashes are painted by the UiKit theme slots, so `nowo_ui_kit.theme` themes them together with every other Nowo bundle. See [TEMPLATES.md](TEMPLATES.md).
- `web_ui.templates.<page>` (`cookie_definition_index`, `cookie_definition_form`, `settings`): per-page template override.
- `docs/TEMPLATES.md` (page → scaffold, data blocks, view-model fields) and a contract test (`PageContractTest`, UiKit `PageContractTestCase`) plus real-kernel tests of the admin pages.
- **Area navigation on the UiKit 2.1 contract:** Settings | Cookie inventory is the page header's `PageHeader::$tabs` (`View\AreaNav`, `<nav data-turbo="false">` as in 1.x), rendered by the UiKit page header; `Admin\CookieConsentAreaNavProvider` publishes it to the UiKit area-nav registry (area `cookie_consent`) for DashboardMenu / host menus, and the page headers read it from the registry (a host provider can replace or drop the tabs).
- Admin screenshots (`admin-cookie-definitions`, `admin-cookie-definition-form`, `admin-settings`) in the docs.

### Changed

- **Template contract** — see [UPGRADING](UPGRADING.md#template-contract-116): the admin page templates were rewritten on the scaffolds (their 1.x `nowo_cookie_consent_admin_*` chrome blocks, `…_area_nav` included, are gone); `web_ui.layout_template` defaults to `null` (UiKit `nowo_ui_kit.layouts.admin`) and must render the UiKit canonical blocks; the controllers pass `page` (view model, area navigation in `page.header.tabs`) next to the 1.x variables (`page` is no longer the page number). The area tabs now sit inside the UiKit page header, below the title row (1.x: above the header).
- Removed the local chrome: `admin/layout.html.twig` (Bootstrap from a CDN), `admin/base.html.twig`, `admin/_pagination.html.twig`, `admin/_tabs.html.twig`, `admin/_chrome.html.twig`, `admin/cookie_definition/_table.html.twig`, `admin/config/_settings_section_tabs.html.twig` and the `admin/cookie_definition/layout.html.twig` / `admin/config/layout.html.twig` aliases. No admin template loads CSS or JS from a CDN.
- The admin forms no longer force `bootstrap_5_layout.html.twig`: they use the host form theme (`twig.form_themes`).
- The seeded `cookie_consent` FormKit profile sets `auto_help: false` (the bundle ships no `{form}.{field}.help` keys: they rendered raw on the cookie definition form) and no longer adds `mb-2` to every row; `nowo-tech/form-kit-bundle` ^2.9. `FormKitPlaceholders` stays (explicit-placeholder handling the profile cannot express).
- No framework utility classes in bundle markup / form types (`DeleteCookieDefinitionType` lost `d-inline`, the bubble icon textarea `font-monospace`).
- `nowo-tech/ui-kit-bundle` ^2.1 (area navigation, per-page form themes and per-bundle page-type theme exceptions; the 2.0-based pre-release templates overrode `nowo_ui_page_header` for the tabs and `nowo_ui_content` for the form theme — no page overrides them now, and the `nowo_cookie_consent_admin_area_nav` block and the `area_nav` context variable of the pre-release are gone).
- The deprecated `web_ui.chrome.templates.form_theme` reaches the admin forms as `FormPage` / `SettingsSection::$formThemes`; the one-page `web_ui.chrome` keys are bridged per page type (`page_header` → list, `page_header_form` → form, `form_actions` → form, `settings_actions` → settings: `theme.bundles.NowoCookieConsentBundle.pages.<type>`) instead of one key winning over the other.
- Removed the unused translation keys `nowo_cookie_consent.admin.layout.title`, `admin.pagination.*` and `admin.config.settings.section_tabs_label`; added `nowo_cookie_consent.admin.config.settings.lead` (all locales).

### Fixed

- **Cookie inventory pagination:** `CookieDefinitionRepository::findByConfigOrderedPaginated()` fetch-joins the translations, so `setMaxResults()` limited SQL rows instead of definitions (a page of 20 showed 3 cookies when each had 9 translations). It now pages through Doctrine's `Paginator`.
- **Cookie definition form:** submitting an empty name (or an empty category, party or translation locale) reached the non-nullable entity setters and answered 500 (`setName(null)` TypeError). Empty text fields now map to `''` / `0` (`empty_data`, choices through a model transformer) and the entities carry validation constraints (`NotBlank` on name, category, party and translation locale; `Length` matching the columns; `Valid` on the translations), so the form is re-rendered with the errors (422). `symfony/validator` is now an explicit requirement (it already came with FormKit).
- **Duplicate DOM ids in the cookie list:** every row delete form was named `delete_cookie_definition`, so the hidden `_token` inputs of the confirm dialogs shared one id. Each row form is now `delete_cookie_definition_<id>`; the delete action reads that payload and still accepts the 1.x `delete_cookie_definition[_token]` and a top-level `_token`.

### Deprecated

- `web_ui.chrome` (`classes`, `attributes`, `templates`, `page_title_tag`, `row_actions_display`) and the `Admin\AdminChrome` class: the class and attribute maps and the `tabs` / `pagination` partials are bridged into `nowo_ui_kit.theme.bundles.NowoCookieConsentBundle`; `templates.form_theme|form_fields|styles_after` and `row_actions_display` are still applied; `page_title_tag` is ignored. Removed in 2.0.
- `web_ui.css_framework` and `web_ui.icon_set` (default now `null`): set `nowo_ui_kit.css_framework` / `icon_set`. When set they still seed UiKit.
- Twig globals `nowo_cookie_consent_layout_template`, `nowo_cookie_consent_css_framework`, `nowo_cookie_consent_icon_set` (no longer read by the bundle).
- `admin/_admin_area_nav.html.twig` (no longer used by the pages; renders the area-nav registry's navigation for 1.x host templates).

[1.16.0]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.16.0

## [1.15.2] - 2026-10-13

### Fixed

- **Container compilation (regression in 1.15.1):** `cache:clear` failed in every real application with `Invalid service "Nowo\CookieConsentBundle\EventSubscriber\CookieConsentAdminAccessSubscriber": the class is not set.` `CookieConsentSecurityPass` replaced the `container.excluded` placeholder with a definition registered without a class, and the pass runs after `ResolveClassPass`. The subscriber definition now sets its class, arguments and `kernel.event_subscriber` tag explicitly. A regression test boots a real kernel (FrameworkBundle + the bundle, with and without SecurityBundle) and asserts the subscriber listens to `kernel.controller`; `symfony/security-bundle` is now a dev dependency for that test.
- **Modal toggles:** `--nowo-cc-toggle-on-knob-bg` was read but never declared, so in themed modals a switched-on toggle drew its knob in the same dark colour as the track (knob invisible). It is now declared (white by default, the theme background in dark themes) and the checked icon in dark themes uses the track colour so it stays visible on the knob. A TS test now fails on any `var(--nowo-cc-*)` that the stylesheet never declares.
- **Admin forms:** the overlay checkbox (`disablePageInteraction`) rendered two `id` attributes (a hard-coded `attr.id`); all admin fields carried raw FormKit `{form}.{field}.placeholder` keys (visible on the profile name / priority / revision / bubble icon inputs) and explicit placeholders lost against them, so optional position selects showed an empty option instead of "Not applicable"; the seeded `cookie_consent` FormKit profile repeated `form-check-input` / `form-select` / `form-control` and the `.form-check` row wrapper that `bootstrap_5_layout` already adds (doubled classes, nested `.form-check`). Fields now only get placeholders the form type sets explicitly, and the profile leaves those classes to the form theme.

### Changed

- **CI:** every matrix cell (and the coverage job) now pins `symfony/security-bundle` to the cell's Symfony version, so Symfony 8.1 cells resolve again; the real-kernel test (`AdminAccessSubscriberKernelTest`) enables Doctrine native lazy objects on PHP 8.4+ with DoctrineBundle 2.x, fixing the "Symfony LazyGhost is not available" errors in the Symfony 7.4 / PHP 8.4–8.5 cells. Tests/CI only, no runtime change.

## [1.15.1] - 2026-10-13

### Security

- `CookieConsentAdminAccessSubscriber` is now actually registered in real applications: the `services.yaml` exclusion of the class leaves an abstract `container.excluded` placeholder definition, which made `CookieConsentSecurityPass` skip the registration. As a result `security.access_roles` / custom access checkers were never enforced on admin routes by the bundle, and the 1.14.0 "404 when `web_ui.enabled: false`" guard did not apply. Only a host-defined (non-excluded) subscriber definition now short-circuits the pass.

## [1.15.0] - 2026-10-13

### Fixed

- Catalan (`ca`) and Polish (`pl`) catalogues were missing 128 of the English keys (banner "Customize"/"Always enabled"/usage text, manage link, preferences bubble label, inventory columns, admin settings fields and choices); they are now fully translated. A new key-parity test fails when any shipped locale drifts from `en`.

### Changed

- Symfony requirement raised to `^7.4 || ^8.0` (FormKitBundle ^2 already required 7.4, so 6.x/7.0–7.3 never resolved); the Symfony 7.0 CI cell is dropped and the coverage job runs on 7.4. `ext-dom` is now declared (used by the preferences bubble icon sanitizer).
- `docs/CONFIGURATION.md` gains a full configuration reference, including the previously undocumented `dark_mode_enabled`, `disable_transitions`, `open_preferences_modal`, `manage_iframe_placeholders`, `route_targeting_mode`, `target_routes` and `preference_sections`.
- Dev: vitest and `@vitest/coverage-v8` upgraded to 4.1 (lockfile no longer contains the vulnerable vite/postcss/brace-expansion versions flagged by Dependabot).

### Deprecated

- `doctrine.connection` and `web_ui.path_prefix` were never used by the bundle; setting them now triggers a deprecation (since 1.15) and they will be removed in 2.0. Entities always use the default entity manager; move the admin routes with the `prefix` of the routes import.

## [1.14.0] - 2026-10-13

### Security

- Admin routes are no longer reachable when `web_ui.enabled: false`: `CookieConsentAdminAccessSubscriber` is now always registered and answers admin routes with 404 when the web UI is disabled (previously the controllers stayed routed with no access check).
- `preferences_bubble_icon` (YAML and per-profile admin setting, rendered with `|raw` on every public page) is now sanitized with a strict allowlist: well-formed inline SVG or simple inline HTML (`span`, `i`, `b`, `strong`, `em`, `small`) re-serialized from the DOM. Payloads such as `<svg/onload=…>`, `<img/src=x/onerror=…>` or entity-encoded `javascript:` are rejected; stored values are re-sanitized at render time (unsafe ones fall back to the default icon) and invalid YAML values fail container compilation.
- Consent log IPv6 addresses are now anonymized (only the first 48 bits are kept); they were previously stored unchanged.

### Fixed

- Deleting a cookie definition from the admin list always returned 403: the controller now reads the CSRF token posted by the rendered form (`delete_cookie_definition[_token]`); a top-level `_token` is still accepted for custom templates.

## [1.13.1] - 2026-10-13

### Changed

- CI: GitHub Actions aligned with the org baseline (`actions/checkout` v7, `codecov/codecov-action` v7, `actions/cache` v6).

### Dependencies

- `doctrine/orm` 3.7.4 → 3.7.5 (development lock; #69).
- `nowo-tech/ui-kit-bundle` 1.9.1 → 1.11.0 (development lock; #72).
- `vite` 8.3.2 → 8.3.4 (frontend tooling; #74).
- `@types/node` 26.6.4 → 26.6.5, `happy-dom` 20.14.5 → 20.14.6 (frontend tooling) and `nowo-tech/form-kit-bundle` 2.6.0 → 2.8.0 (development lock), applied locally to close #70, #71 and #73.

### Fixed

- **Shipped asset:** `src/Resources/public/nowo-consent-modal.js` rebuilt with the refreshed toolchain (equivalent output; no behaviour change).

## [1.13.0] - 2026-10-13

Admin chrome hooks so hosts can theme the admin Web UI without forking pages (spec [`002-admin-chrome-hooks`](../specs/002-admin-chrome-hooks/spec.md)). Default output is unchanged (golden-tested against the 1.12.0 markup).

### Added

- **`web_ui.chrome`** config subtree (Twig global `nowo_cookie_consent_admin_chrome`, resolved by `Nowo\CookieConsentBundle\Admin\AdminChrome`):
  - `classes` — CSS class map per element (page header/title/intro, area nav + section tabs, table panel/wrap/table/cells, empty state, form + settings panels/bodies/actions, section title/lead, buttons, confirm buttons, pagination). `~` keeps the UiKit macro default for the configured `css_framework`; an empty string on `form_panel_body` / `settings_panel_body` drops the inner wrapper.
  - `attributes` — extra HTML attributes per element (e.g. `data-testid`).
  - `templates` — partial paths: `tabs` (area + section tabs, default `admin/_tabs.html.twig`), `pagination` (receives `page`, `pages`, `total`, `page_size`, `item_count`, `framework`), `form_theme` (`~` = app default theme), `form_fields` (host partial receiving `{form}`), `styles_after` (included after the UiKit stylesheet in `admin/base.html.twig`).
  - `page_title_tag` (`h1`…`h6` / `none`) and `row_actions_display` (`text` / `icon` / `icon_text` / `inherit` = `nowo_ui_kit.row_actions_display`).
  - Unknown keys fail at container compile time.
- **Twig blocks** on every admin page: `nowo_cookie_consent_admin_area_nav`, `_page_header`, `_page_title`, `_page_intro`, `_page_actions`, `_table_wrapper`, `_pagination`, `_section_tabs`, `_settings_section`, `_section_header`, `_form_wrapper`, `_form_body`, `_form_actions`; `_table.html.twig` adds `_table_head`, `_row_actions`, `_empty_state`; `base.html.twig` adds `_styles_after`.
- **`@!NowoCookieConsentBundle/…`** namespace always points at the shipped templates, so a host fork can extend the original and override one block.
- Translation keys `nowo_cookie_consent.admin.pagination.label` (pagination `aria-label`, was hard-coded "Pagination") and `nowo_cookie_consent.admin.layout.title` (default admin layout `<title>`).

### Fixed

- Admin strings that rendered as raw keys in every locale now have translations: `cookie_definition.lead`, `form_lead`, `new_title`, `edit_title`. Catalan and Polish gain the full admin chrome set (tabs, settings sections, table, pagination).
- Row action "Edit" label now receives `%name%` (was rendered literally as "Edit %name%").
- `make validate-translations` (shell quoting + autoload).

### Changed

- Development: `composer.json` pins `config.platform.php` to 8.2.0 so the committed lock stays installable on the minimum PHP; CI overrides the platform per matrix cell.

## [1.12.0] - 2026-10-09

### Added

- **Performance (FrankenPHP worker):** `CookieConsentSchemaReadySubscriber` memoizes a positive "config table exists" probe per worker process for `schema_ready_cache_ttl` seconds (new option, default `60`, `0` = probe every request). The memo survives between requests (no `ResetInterface`), so anonymous public pages do no `information_schema` / `tablesExist()` probe after warm-up. A missing table is never memoized. Optional PSR-20 clock and `clearSchemaReadyMemo()`. Replaces host-side shims such as `CookieConsentSchemaReadyMemoSubscriber`.
- **CSP nonce (nowo-tech convention):** every `<script>` the bundle renders (modal / Tailwind modal loader, `_diagnostics_script.html.twig`, admin layout Bootstrap bundle, admin `nowo-ui-confirm.js`) carries `nonce` from the request attribute `csp_nonce` when present (`app.request` null-safe).
- **CSP nonce (JS):** the runtime-injected modal `<style>` copies the loader script nonce (`document.currentScript.nonce`, captured at evaluation), falling back to `<meta name="csp-nonce">` and then the first nonced `<script>`, so it survives `style-src-elem 'nonce-…'`. New `resolveCspNonce()` helper.

### Changed

- **CSP:** preferences bubble accent colour is emitted as `data-nowo-bubble-accent` and applied through CSSOM (`--nowo-cc-bubble-accent`) instead of an inline `style` attribute, so strict `style-src-attr` policies keep it.
- `_diagnostics_script.html.twig` tolerates a missing request (`app.request` null).

## [1.11.2] - 2026-10-09

### Dependencies

- `igor-php/igor-php` require-dev constraint bumped to `^0.10.0`; dev lock refreshed (`nowo-tech/ui-kit-bundle` 1.9.1, `nowo-tech/form-kit-bundle` 2.6.0, Symfony 7.4.20, PHPStan 2.3.1, Rector 2.7.0, `doctrine/orm` 3.7.3+).
- JS tooling: `vite` 8.3.2, `@types/node` 26.6.4 (rebuilt `nowo-consent-modal.js`, no behaviour change).
- Demos `symfony8` and `symfony8-tailwind`: Symfony 8.1.8, `doctrine/orm` 3.7.4, `doctrine/dbal` 4.5.0, Twig 3.30.0, `nowo-tech/ui-kit-bundle` 1.9.1, `nowo-tech/form-kit-bundle` 2.6.0.

[1.15.2]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.15.2
[1.15.1]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.15.1
[1.15.0]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.15.0
[1.14.0]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.14.0
[1.13.1]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.13.1
[1.13.0]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.13.0
[1.12.0]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.12.0
[1.11.2]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.11.2

## [1.11.1] - 2026-09-28

### Fixed

- **TLS / FrankenPHP:** `CookieHandler` now always emits consent cookies with the `Secure` flag and
  `SameSite=Lax` (and without the raw flag). Behind a TLS-terminating proxy, Symfony's `secure=null`
  ("auto") saw a plain HTTP request and omitted `Secure`, so browsers on `https://` dropped the cookies
  and the consent banner reappeared after refresh.
- Consent timestamp cookie value uses UTC ISO-8601 (`Y-m-d\TH:i:s\Z`) instead of locale-dependent `date('r')`.
- `CookieChecker` falls back to `RequestStack::getCurrentRequest()` when the main request is null
  (edge cases under worker / sub-request stacks).

[1.11.1]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.11.1

## [1.11.0] - 2026-09-28

### Security

- Empty `security.access_roles` is fail-closed (deny) unless `allow_unauthenticated` or a custom `access_checker` is set.

### Added

- **REQ-DEMO-013:** Playwright e2e under `demo/symfony8/e2e/` (`make test-e2e`), `demo-screenshots` target, and README gallery cropped to .nowo-cookie-consent (`docs/images/demo/overview.png`, `interaction.png`).

### Changed

- **Doctrine ORM SortDirection:** replace string `'ASC'`/`'DESC'` in `#[ORM\OrderBy]` and QueryBuilder `orderBy`/`addOrderBy` with `SortDirection::Ascending`/`Descending` (doctrine/orm deprecation, https://github.com/doctrine/orm/issues/11313); require `doctrine/orm` `^3.7` where applicable.

## [1.10.1] - 2026-09-27

### Added

- **REQ-CS-008:** `igor-php/igor-php` (require-dev only), root `igor.json`, Composer/`Makefile` `igor` target, and `release-check` wiring for FrankenPHP worker-state audit.

### Changed

- **Worker safety (Igor):** justified `// @igor-ignore` annotations and/or `ResetInterface` / request-scoped fixes so `make igor` passes on package `src/`.

[1.11.0]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.11.0
[1.10.1]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.10.1

## [1.10.0] - 2026-09-24

### Added

- **Twig:** `nowo_cookie_consent_trans(id, display_config = null)` returns the database profile copy of the current
  request for a consent message id and falls back to the `NowoCookieConsentBundle` translation domain.
- **FrankenPHP worker:** `CookieConsentRuntimeCacheResetSubscriber` (`kernel.request`, priority 4096, main requests only)
  clears the resolver, inventory and config repository memos at the start of every request, so they stay request-scoped
  even when the kernel is not reset between requests (`reset_kernel false` / scenario B).
- `LocaleResolver::normalize()` maps an arbitrary locale to an enabled locale.
- **Docs:** [FrankenPHP worker audit](FRANKENPHP-WORKER-AUDIT.md) (scenario A vs B; verdict viable under B).

### Fixed

- **FrankenPHP worker:** `CookieChecker` and `CookieLogger` no longer capture the main request in their constructor.
  In worker mode every visitor served by the same worker was evaluated against (and logged with the IP of) the first
  visitor's request. The granular preferences memo of `CookieChecker` was removed.
- **FrankenPHP worker:** database consent texts are no longer registered on the shared translator with
  `Translator::addResource()` on every request (unbounded memory growth, stale or ignored texts once the catalogue was
  loaded or cached). The bundled templates and the consent form buttons read them from the resolved profile instead.
- **FrankenPHP worker:** `CookieLogger` obtains the entity manager per call through `ManagerRegistry` (resetting it
  when a previous failure closed it) and detaches the consent log entities after flushing.
- The runtime cache listener also invalidates on `CookieConsentConfigTranslation`, `CookieDefinition` and
  `CookieDefinitionTranslation` changes and clears `CookieInventoryProvider`.
- `/cookie-consent/config` normalizes `?locale=` / `{_locale}` to an enabled locale, so arbitrary values no longer
  create translator catalogues.

[1.10.0]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.10.0

## [1.9.7] - 2026-08-29

### Added

- **FrankenPHP worker:** `CookieConsentConfigRepository`, `CookieConsentConfigResolver`, and
  `CookieInventoryProvider` implement `ResetInterface` so memoized lookups clear between worker
  requests (hosts no longer need a custom `CookieConsentWorkerCacheReset` shim).

[1.9.7]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.9.7

## [1.9.6] - 2026-08-24

### Changed

- Raise minimum PHP to **8.2** and sync README badge (REQ-SF-001).
- **Demos:** MySQL env policy in FrankenPHP stack (REQ-DEMO-011).
- **Docs:** PHP-FIG PSR evaluation (REQ-CS-007).

### Notes

- **No API or configuration changes** for integrators unless noted above.

[1.9.6]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.9.6

## [1.9.5] - 2026-08-21

### Added

- **`nowo_cookie_consent_render()`** Twig function and `CookieConsentModalRenderer`: embed the consent modal in the current request (no kernel sub-request). Prefer this over `{{ render(path('nowo_cookie_consent.show')) }}` in host layouts. Routes `nowo_cookie_consent.show` / `show_if_not_set` remain for ESI / legacy embeds.

[1.9.5]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.9.5

## [1.9.4] - 2026-08-20

### Security

- **Dev/demo frontend:** bump `vitest` to **^3.2.6**; pnpm overrides for `vite` ≥6.4.3, `postcss`, `nanoid`, `brace-expansion@>=4` (Dependabot High).

## [1.9.3] - 2026-08-19

### Security

- **CI:** run `composer audit --locked` after dependency install (REQ-SEC / P3).

## [1.9.2] - 2026-08-19

### Fixed

- **Consent profile lookups:** memoize `CookieConsentConfigRepository` enabled/default queries and `CookieConsentConfigResolver::resolve()` per request so the modal sub-request and Twig helpers do not repeat `findDefaultEnabled` against Doctrine. The consent fragment controller reuses `nowo_cookie_consent_config` from the main request when present; runtime caches are cleared on admin profile writes.

[1.9.2]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.9.2

## [1.9.1] - 2026-08-18

### Changed

- **Demos:** pin `nowo-tech/hot-reload-bundle` to `^1.4` with FrankenPHP Mercure/`hot_reload` (`dev`/`test` only).

[1.9.1]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.9.1
## [1.9.0] - 2026-08-15

### Added

- **Standalone modal CSS** `nowo-cookie-consent.css` in `src/Resources/public/` (emitted by Vite build). Hosts can `<link>` it via the `nowo_cookie_consent` asset package.
- **CSP-safe external CSS mode**: `nowo-consent-modal.js` skips injecting a runtime `<style>` block when `link[data-nowo-cookie-consent-css]` is present, or when `data-nowo-cookie-consent-external-css="true"` is set on `<html>`, or `data-nowo-external-css="true"` on `#cookieconsent`.
- Upstream Beacon skin polish in `cookie-consent.css`: category tint cards, ghost secondary actions, box layout side inset, preferences intro spacing, `html[data-theme]` light/dark token remaps, equal-weight button grid.

### Changed

- New `CookieConsentConfig` entity defaults for **new** DB profiles: consent/preferences modal **bottom + left**, **equal-weight buttons** on (existing rows unchanged).
- Config API payload fallbacks align with those layout defaults when no DB config is resolved.

### Documentation

- CONFIGURATION / USAGE / INSTALLATION: CSP `style-src` nonce guidance and recommended layout defaults.
- UPGRADING notes for linking kit CSS and optional removal of host-only consent SCSS.

## [1.8.0] - 2026-08-15

### Added
- **`CookieConsentSchemaReadySubscriber`**: when SiteBackup reports the MySQL schema exists but `dashboard_cookie_config` is missing (post-`database_create`, pre-migrations), sets `_nowo_cookie_consent_schema_ready=false` so Twig/listeners skip Doctrine-backed consent.
- `CookieConsentConfig::TABLE_NAME` constant for schema probes.

### Documentation
- UPGRADING / CHANGELOG notes for mid-migration cold-start.

## [1.7.0] - 2026-08-15

### Added

- Cold-start skip for database-backed consent: when the main request carries `_nowo_site_backup_schema_exists: false` (shared with Site Backup Bundle) or `_nowo_cookie_consent_schema_ready: false`, config resolution, form handling, and `nowo_cookie_consent_should_render()` skip Doctrine work. See `Nowo\CookieConsentBundle\Http\ColdStartRequestAttributes`.

### Documentation

- Site Backup interoperability and cold-start attributes documented in UPGRADING, CONFIGURATION, and USAGE.

## [1.6.3] - 2026-08-11

### Added

- `render_routes`: optional whitelist of route name patterns; when non-empty the consent fragment renders only on matches (ideal for public-only shells). Still respects `skip_render_routes` (deny wins).

### Documentation

- Documented public whitelist usage in CONFIGURATION and UPGRADING.

[1.9.0]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.9.0

[1.8.0]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.8.0

[1.7.0]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.7.0

[1.6.3]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.6.3

## [1.6.2] - 2026-08-11

### Fixed

- Cookie inventory Doctrine queries: memoize `listForLocale` / definition loads per request, use `existsByConfig` (COUNT) for presence checks instead of loading all rows twice, attach inventory once on the consent form view, and filter category tables from that list in form themes.

### Documentation

- Noted inventory query reduction in UPGRADING / RELEASE history.

[1.6.2]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.6.2

## [1.6.1] - 2026-08-11

### Added

- `skip_render_routes`: route name patterns (fnmatch wildcards) where `CookieConsentController::show` returns an empty private response and skips config/form/inventory work. Twig helper `nowo_cookie_consent_should_render()` lets hosts omit the ESI/`render()` sub-request on authenticated dashboards.

### Documentation

- Documented `skip_render_routes` in CONFIGURATION and UPGRADING.

[1.6.1]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.6.1

## [1.6.0] - 2026-08-04

### Changed

- **FormKitBundle:** depend on [`nowo-tech/form-kit-bundle`](https://github.com/nowo-tech/FormKitBundle) ^2.0. Admin form types use `FormOptionsTrait` + profile `cookie_consent` (`#[FormKitConfig]`). Extension prepends that profile when missing; form types are tagged `form.type` so `FormOptionsMerger` is injected.
- **UiKit:** Admin templates use `ui.btn` / `ui.row_actions` macros with `nowo_cookie_consent_css_framework` instead of hard-coded Bootstrap button classes.

### Added
- **REQ-TWIG-004:** require `twig/extra-bundle` + `twig/string-extra`; `make check-twig-extra` in `release-check`; demos register `TwigExtraBundle`.
- **Twig-CS-Fixer:** `vincentlanglet/twig-cs-fixer`, `.twig-cs-fixer.php`, `composer twig:lint` / `twig:fix`.

### Changed

- **REQ-UI-001-kit:** Admin `nowo-ui.css` now loads from UiKit (`asset('css/nowo-ui.css', 'nowo_ui_kit')`). Removed the forked `src/Resources/public/css/nowo-ui.css`. Requires `nowo-tech/ui-kit-bundle` `^1.4`. Cookie-consent modal assets (`cookieconsent.css`, `nowo-cc-themes.css`, `nowo-consent-modal.js`) stay on the `nowo_cookie_consent` package. Extension seeds `nowo_ui_kit` defaults from `web_ui.css_framework` / `icon_set` when the host has not configured UiKit. Admin `base.html.twig` imports `@NowoUiKitBundle/macros/ui.html.twig` (flashes via `ui.flash`, cookie-definition index create via `ui.btn`); duplicate inline flash loops removed from child templates.
- **REQ-UI-001 feedback:** Cookie inventory delete uses UiKit `_confirm.html.twig` + `nowo-ui-confirm.js` (POST+CSRF in dialog footer) instead of native `confirm()`.
- **REQ-TWIG-003:** Cookie definition admin form renders fields via `{% for child in form %}` / `form_row(child)`.

[1.6.0]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.6.0

## [1.5.2] - 2026-08-03

### Fixed

- Demo FrankenPHP stacks: install `nowo-tech/twig-inspector-bundle` from Packagist instead of a monorepo sibling path mount, so `make demo-smoke` / CI standalone checkouts no longer crash with `service "php" is not running` during entrypoint `composer install`.

### Documentation

- [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md) — Twig Inspector via Packagist; drop sibling-path troubleshooting tip
- [UPGRADING.md](UPGRADING.md) / [RELEASE.md](RELEASE.md) — 1.5.2 notes

## [1.5.1] - 2026-08-03

### Fixed

- **REQ-UI-001**: `admin/base.html.twig` stacks host `javascripts` with `{{ parent() }}` (stylesheets already did); legacy `admin/*/layout.html.twig` BC aliases extend `admin/base.html.twig` instead of hardcoding the demo layout.

### Changed

- Default `admin/layout.html.twig` keeps CDN Bootstrap in `stylesheets` / `javascripts` directly so `parent()` stacking from `admin/base.html.twig` does not drop host or demo assets.

### Documentation

- [CONFIGURATION.md](CONFIGURATION.md) / [USAGE.md](USAGE.md) — base shell stacking on both asset blocks; BC alias targets.

## [1.5.0] - 2026-08-01

### Added

- **Route-based settings sections** — `/cookie-consent-config/{id}/settings/{section}` (`nowo_cookie_consent_config_settings_section`) for Profile, Behavior, Appearance, Consent modal, Preferences modal, and Auto-show targeting. Each tab has its own FormType under `Form/Settings/` (resolved via `CookieConsentConfigSettingsSection::formType()`).
- Admin area nav partial `admin/_admin_area_nav.html.twig` (Settings | Cookie inventory) and section tabs `admin/config/_settings_section_tabs.html.twig`.
- Semantic `.nowo-ui-tabs` styles in `nowo-ui.css` for hosts using `web_ui.css_framework: custom` / `tailwind` / `none`.

### Changed

- `GET /cookie-consent-config/{id}/settings` (`nowo_cookie_consent_config_settings_edit`) now **redirects** to `/settings/profile` (BC for existing bookmarks and host links).
- Settings Twig uses a **single** form card with the save button inside; section navigation is outside the card (no nested section cards).
- `CookieConsentConfigSettingsType` is a **deprecated** alias of `CookieConsentConfigFullSettingsType` (all sections) for demos / legacy single-page editors. Full settings keep the form name `cookie_consent_config_settings` via `getBlockPrefix()`.

### Documentation

- [CONFIGURATION.md](CONFIGURATION.md) / [UPGRADING.md](UPGRADING.md) — canonical settings URLs and host upgrade notes.

## [1.4.9] - 2026-08-01

### Fixed

- Admin `base.html.twig` injects `nowo-ui.css` via host `stylesheets` / `parent()` when `web_ui.css_framework` is non-Bootstrap (host `layout_template` path).
### Added

- `src/Resources/public/css/nowo-ui.css` — standalone admin stylesheet activated when `web_ui.css_framework` is `custom`, `tailwind`, or `none`. Provides `--nowo-ui-*` CSS custom properties and semantic fallback styles for all Bootstrap class names used in admin templates (`.btn`, `.table`, `.card`, `.alert`, `.form-control`, `.badge`, `.container`, pagination, flex utilities) so the admin UI is usable without the Bootstrap CDN.
- Admin `layout.html.twig` now links `nowo-ui.css` (via the existing `nowo_cookie_consent` asset package) for any `css_framework` value that is not `bootstrap`, `bootstrap4`, `bootstrap5`, or `tabler`.

### Documentation

- [CONFIGURATION.md](CONFIGURATION.md) — new **`ui_theme` vs `web_ui.css_framework`** callout clarifying that these two options are independent (`ui_theme` controls the public modal markup; `web_ui.css_framework` controls the admin CSS stack). New **Using a custom CSS framework** section documents the `custom`/`tailwind`/`none` path, asset injection, and `--nowo-ui-*` token overrides.

## [1.4.8] - 2026-07-30

### Fixed

- Consent modal JS: prepare Symfony SameOrigin CSRF (double-submit cookie + `csrf-token` header) before XHR so hosts can keep `csrf_protection: true` without relying on native `submit` / Stimulus.

### Documentation

- [SECURITY.md](SECURITY.md), [USAGE.md](USAGE.md), [CONFIGURATION.md](CONFIGURATION.md) — document XHR CSRF double-submit path

## [1.4.7] - 2026-07-30

### Fixed

- Consent modal Twig: detect CSRF via `form.children._token` (Twig `form._token is defined` still accesses the missing field).

## [1.4.6] - 2026-07-30

### Fixed

- Consent modal Twig: render CSRF field only when `form._token` is defined (hosts with `csrf_protection: false`).

## [1.4.5] - 2026-07-30

### Changed

- **Twig overrides (REQ-TWIG-001)**: `TwigPathsPass` now `prependPath`s `templates/bundles/NowoCookieConsentBundle/` when present (AuthKit-compatible), so hosts no longer need a manual `twig.paths` entry for the namespace.
- **Tailwind modal / form theme**: dropped hard-coded indigo/slate/white utility colors; buttons and chrome use BEM + `--nowo-cc-*` tokens from the inlined `cookie-consent.css` (including `--nowo-cc-link` and panel/section borders).

### Documentation

- [USAGE.md](USAGE.md) — clarify `prependPath` behaviour for app overrides
- [UPGRADING.md](UPGRADING.md) — To 1.4.5 notes
- [RELEASE.md](RELEASE.md) — release history row

## [1.4.4] - 2026-07-30

### Changed

- **Dependencies**: Root and demo `composer.lock` refreshed (Symfony components **7.4.15** / demo apps **8.1.2**); demo `config/reference.php` regenerated.

### Documentation

- [UPGRADING.md](UPGRADING.md) — upgrade path from 1.4.3
- [RELEASE.md](RELEASE.md) — release history entry

[1.4.4]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.4.4

## [1.4.3] - 2026-07-30

### Added

- **Admin Twig (REQ-UI-001 / REQ-TWIG-001)**: `admin/base.html.twig` intermediate shell; admin pages extend it so host chrome stays on `web_ui.layout_template`.

### Changed

- Admin CRUD/settings templates extend `@NowoCookieConsentBundle/admin/base.html.twig` instead of inlining the layout global.
- README documentation links moved below Quick start / FrankenPHP demo.

### Documentation

- [USAGE.md](USAGE.md) — Overriding templates (REQ-TWIG-001) procedure, freeze rule, and overridable `<subpath>` table (includes `admin/base.html.twig`)
- [CONFIGURATION.md](CONFIGURATION.md) — Twig overrides section points to USAGE
- [UPGRADING.md](UPGRADING.md) — upgrade path from 1.4.2
- [RELEASE.md](RELEASE.md) — release history entry

[1.4.3]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.4.3

## [1.4.2] - 2026-07-29

### Changed

- **Tooling (REQ-MAKE-010)**: Root and demo Makefiles prefer `docker compose` (V2) and fall back to `docker-compose` (V1); demo recipes invoke Compose via a shell helper to avoid WSL Make `execve` EACCES on the Docker Desktop CLI symlink.
- **Tooling (REQ-MAKE-009)**: Monorepo `update-deps` Makefile includes are optional (`-include`) so standalone GitHub Actions checkouts do not fail.

### Documentation

- [UPGRADING.md](UPGRADING.md) — upgrade path from 1.4.1
- [RELEASE.md](RELEASE.md) — release history entry

[1.4.2]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.4.2

## [1.4.1] - 2026-07-28

### Added

- **Docs (REQ-DOCS-005 / REQ-TEST-003)**: Table of contents on long docs; `docs/COVERAGE.md` documents the PHPUnit coverage gate (100% lines on `src/`).
- **Spec Kit (REQ-SPECKIT-001 / REQ-SPECKIT-003)**: Full `src/` inventory (~107 files); `FR-SEC-001` / Twig admin / DI clock notes in baseline `spec.md`.
- **Tooling (REQ-REL-003 / REQ-MAKE-002 / REQ-TEST-003)**: `make check-open-prs`, `make coverage-check` (fail-under 99%), wired into `release-check`.
- **Tests**: Unit coverage for `CookieConsentSecurityPass`, access-checker DI branches, admin access subscriber events, definition pagination/count, admin index page clamp.

### Changed

- **CI / PHPUnit (REQ-SF-005)**: `SYMFONY_DEPRECATIONS_HELPER=max[direct]=0` in `phpunit.xml.dist` and CI test jobs.
- **Demos (REQ-DEMO-010 / REQ-GITIGNORE-003)**: `/.pnpm-store` in demo `.gitignore` files; demo locks pull `psr/clock`; `release-verify` follows redirects (`curl -sL`) for locale root; Tailwind migrations use `demo_tailwind_dashboard_*` table names; demo functional tests updated for DomCrawler checkboxes, playground copy, and Tailwind selectors.
- **DI**: Drop unused `_defaults.bind` `$httpOnly` (explicit on `CookieHandler`).
- **Tooling**: `check-open-prs` resolves `owner/repo` from `origin` when `gh` cannot map the git remote; `make coverage-check` reads `coverage-output.txt`.
- **Dependencies**: Closed Dependabot PRs #15 / #16 (conflicted happy-dom; TypeScript major) pending a deliberate upgrade path.
- README coverage badge and Tests section aligned to **100%** PHP lines on `src/` (`make coverage-check`).
- **Rector**: `CookieDefinitionAdminController::delete` return type narrowed to `RedirectResponse`.

### Fixed

- Tailwind consent modal no longer double-renders the `cookies` form field when granular selection is enabled (aligns with Bootstrap template + `_preference_sections`).

### Documentation

- [COVERAGE.md](COVERAGE.md) — PHPUnit coverage gate
- [UPGRADING.md](UPGRADING.md) — upgrade path from 1.4.0
- [RELEASE.md](RELEASE.md) — release history entry; TOC

[1.4.1]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.4.1

## [1.4.0] - 2026-07-27

### Added

- **Admin Web UI (REQ-UI-001 / REQ-UI-002)**: `web_ui` (`enabled`, `path_prefix`, `layout_template`, `css_framework`, `icon_set`, `list_page_size`), Twig globals, semantic `nowo-ui-*` hooks; `security.access_roles` + `CookieConsentAccessCheckerInterface` + admin access subscriber; host firewall guidance.
- **Pagination (REQ-PERF-001)**: Cookie definition admin index paginated via `web_ui.list_page_size` (default `20`) with translation eager-loading.
- **DI (REQ-DI-001)**: `psr/clock` + `SystemClock`; `CookieHandler` / `CookieLogger` use `ClockInterface`; optional PSR-3 logger on consent persist (no raw IP).
- **Dependencies**: `psr/log`, `symfony/security-core`, `symfony/security-csrf` declared in `require`.
- **CI**: Dedicated PHPStan job; `demo-smoke` workflow (REQ-TEST-011) wrapping `demo/Makefile` `release-verify`.

### Changed

- Configuration nodes `categories`, `disabled_routes`, `target_routes`, `enabled_locales`, and `preference_sections` are typed `arrayNode`s.
- Admin controllers are `final`; settings admin unchanged functionally.
- Demo configs set `security.allow_unauthenticated: true` (demo-only).
- `make release-check-demos` fails when demo release-check fails (no longer swallowed with `|| true`).
- Demo smoke (**REQ-TEST-011**) via `make demo-smoke` / `demo/Makefile` `release-verify` (HTTP 200) as part of `release-check`.

### Documentation

- [CONFIGURATION.md](CONFIGURATION.md) — Admin Web UI, security, Twig override table updates
- [USAGE.md](USAGE.md) — `layout_template` / access control pointers
- [UPGRADING.md](UPGRADING.md) — upgrade path from 1.3.6
- [SECURITY.md](SECURITY.md) — bundle-level admin access checker

[1.4.0]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.4.0

## [1.3.6] - 2026-07-27

### Added

- FrankenPHP Friendly Worker Mode banner in README + `docs/images/frankenphp-friendly.png` (**REQ-DOCS-017**)
- Demo `restart` targets; root `down-dev` alias; `demo/Makefile` aggregate `up` / `down` / `update-bundle` (**REQ-MAKE-003**, **REQ-MAKE-007**)

### Changed

- Split multi-class PHPUnit files so granular/marketing/translation tests are discovered (**211** tests)
- Expanded unit coverage to **≥99.95%** PHP line coverage (**REQ-TEST-003**)
- English-only sample strings in `docs/CONFIGURATION.md` translation override example (**REQ-DOCS-016**)

No configuration or public API breaking changes for bundle consumers.

### Documentation

- [UPGRADING.md](UPGRADING.md) — upgrade path from 1.3.5
- [RELEASE.md](RELEASE.md) — release history entry; coverage goals aligned to ≥99% PHP lines
- [README.md](../README.md) — coverage badge and Tests section percentages

[1.3.6]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.3.6

## [1.3.5] - 2026-07-24

### Added

- **REQ-CS-005** — `nowo-tech/phpstan-frankenphp` (classic + worker rulesets) in `phpstan.neon.dist`
- Empty `phpstan-baseline.neon` (`ignoreErrors: []`) included from PHPStan config

### Changed

- PHPStan Level max hygiene: typed PHPDoc/generics, FQCN → `use` imports, inventory YAML cache as instance property
- **DI** — `$useLogger` bound explicitly on `CookieConsentFormSubscriber` (survives host `setClass` overrides; avoids unused global bind)
- PHP-CS-Fixer `fully_qualified_strict_types.import_symbols` enabled
- Dev lock refresh (PHPStan ecosystem, php-cs-fixer, demo locks)

### Fixed

- CSRF deny on cookie definition delete throws `AccessDeniedHttpException` (consistent 403)

No configuration or public API breaking changes for bundle consumers.

### Documentation

- [UPGRADING.md](UPGRADING.md) — upgrade path from 1.3.4
- [RELEASE.md](RELEASE.md) — release history entry

[1.3.5]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.3.5

## [1.3.4] - 2026-07-22

### Changed

- **Dev tooling** — Vite **8**, happy-dom **20**, `@types/node` **26**; GitHub Actions `checkout`/`setup-node` **v7**, `pnpm/action-setup` **v6**
- **Demos** — FrankenPHP mode via `FRANKENPHP_MODE` (`classic` \| `worker`, default `worker`); shared `docker/entrypoint.sh` instead of inline Dockerfile script

### Documentation

- [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md) — `FRANKENPHP_MODE` switching (classic vs worker)
- [UPGRADING.md](UPGRADING.md) — upgrade path from 1.3.3

No configuration, public API, or runtime changes for bundle consumers.

[1.3.4]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.3.4

## [1.3.3] - 2026-07-20

### Added

- **REQ-GIT-001** — git hygiene: `.githooks/commit-msg`, `make setup-hooks`, `make check-no-cursor-coauthor`, CI job `git-hygiene`, and [GITHUB_CI.md](GITHUB_CI.md)
- **Code of Conduct** — [CODE_OF_CONDUCT.md](../CODE_OF_CONDUCT.md) (Contributor Covenant)
- Expanded PHPUnit coverage for admin controllers, forms, Twig CMP UX helpers, repositories, and cookie/config edge cases (**180 tests**)

### Changed

- `make release-check` runs `check-no-cursor-coauthor` before the rest of the pipeline
- [CONTRIBUTING.md](CONTRIBUTING.md) and [RELEASE.md](RELEASE.md) document hook setup and pre-push co-author verification

No configuration, public API, or runtime changes for bundle consumers.

[1.3.3]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.3.3

## [1.3.2] - 2026-07-13

### Added

- **`nowo_cookie_consent` asset package** — `NowoCookieConsentExtension` implements `PrependExtensionInterface` and registers `framework.assets.packages.nowo_cookie_consent` (`base_path: /bundles/nowocookieconsent`)

### Changed

- Twig consent templates load `nowo-consent-modal.js` via `asset('nowo-consent-modal.js', 'nowo_cookie_consent')` with `defer` instead of a hardcoded `/bundles/nowocookieconsent/` path

### Fixed

- Consent modal JavaScript resolves correctly in apps using Symfony AssetMapper and standard asset packages

### Documentation

- [INSTALLATION.md](INSTALLATION.md) — AssetMapper note and asset package loading
- [UPGRADING.md](UPGRADING.md) — upgrade path from 1.3.1

No configuration or API breaking changes.

[1.3.2]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.3.2

## [1.3.1] - 2026-07-09

### Added

- **GitHub Spec Kit** — baseline spec at `specs/001-baseline/`, Cursor Agent skills (`.cursor/skills/speckit-*`), and operator manual [SPEC-KIT.md](SPEC-KIT.md)

### Changed

- [SPEC-DRIVEN-DEVELOPMENT.md](SPEC-DRIVEN-DEVELOPMENT.md) — three-layer spec model with Spec Kit baseline and maintainer sync checklist
- Dev dependency lock refresh (`phpunit`, `php-cs-fixer` in `require-dev` only)

### Fixed

- Demo `make update-deps` — define `SERVICE_PHP := php` in `demo/symfony8` and `demo/symfony8-tailwind` Makefiles (fixes `no such service: sh`)

### Documentation

- README link to [SPEC-KIT.md](SPEC-KIT.md)
- [UPGRADING.md](UPGRADING.md) — upgrade path from 1.3.0

No consumer-facing bundle API, configuration, or runtime changes.

[1.3.1]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.3.1

## [1.3.0] - 2026-07-05

### Changed

- **Breaking:** Doctrine table names now use the `dashboard_cookie_*` prefix (e.g. `dashboard_cookie_log`, `dashboard_cookie_config`, `dashboard_cookie_definition`), aligned with [DashboardMenuBundle](https://github.com/nowo-tech/DashboardMenuBundle).
- **`doctrine.table_prefix`** — preferred config key (same structure as dashboard menu). Root `table_prefix` is deprecated but still supported.
- **Translations** — complete consent modal strings for `de`, `fr`, `it`, `nl`, and `pt` (previously partial English fallback).

### Migration

Rename existing tables or regenerate migrations. Examples:

| Old | New |
|-----|-----|
| `nowo_cookie_consent_log` | `dashboard_cookie_log` |
| `nowo_cookie_consent_config` | `dashboard_cookie_config` |
| `nowo_cookie_consent_config_translation` | `dashboard_cookie_config_translation` |
| `nowo_cookie_consent_cookie_definition` | `dashboard_cookie_definition` |
| `nowo_cookie_consent_cookie_definition_translation` | `dashboard_cookie_definition_translation` |

### Documentation

- [CONFIGURATION.md](CONFIGURATION.md) — `doctrine.table_prefix`, deprecated root `table_prefix`, updated default table names
- [INSTALLATION.md](INSTALLATION.md) — database table names and prefix configuration
- [UPGRADING.md](UPGRADING.md) — upgrade path from 1.2.0

[1.3.0]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.3.0

## [1.2.0] - 2026-06-15

### Added

- **`disable_page_interaction`** — global YAML option, Twig helper `nowo_cookie_consent_disable_page_interaction()`, and per-profile admin field for full-page overlay and scroll lock until consent
- **Profile settings admin in the bundle** — `CookieConsentConfigSettingsType`, `CookieConsentConfigSettingsAdminController`, and Bootstrap templates at `/cookie-consent-config/{id}/settings` (route `nowo_cookie_consent_config_settings_edit`)
- **Preferences bubble customization** — `preferences_bubble_border_color` (hex outline/icon color) and `preferences_bubble_icon` (custom HTML or SVG) via YAML or `CookieConsentConfig` when `use_database_config: true`
- **`PreferencesBubbleIconSanitizer`** — rejects dangerous markup (`<script>`, event handlers, etc.) before persisting custom bubble icons
- Twig helpers `nowo_cookie_consent_preferences_bubble_border_color()` and `nowo_cookie_consent_preferences_bubble_icon()`
- Default bubble SVG partial `_preferences_bubble_icon_default.html.twig`
- Symfony **8.1** and PHP **8.2–8.5** in CI; broader Symfony and Doctrine `composer.json` constraints

### Changed

- Preferences bubble: transparent background, configurable border color, larger icon area, flex-centered custom content
- Modal **vertical position** (`consent_modal_position_y: bottom`) fixed for box and cloud layouts (CSS flex column + Tailwind wrapper cleanup)
- Demo settings UI reuses bundle `CookieConsentConfigSettingsType` (removed duplicate demo form class)
- PHP test suite: **130 tests**
- Flex recipe documents commented `disable_page_interaction` example

### Fixed

- Tailwind consent modal no longer forces `items-center justify-center` on the overlay, which prevented bottom-aligned box modals from sitting at the viewport edge

### Documentation

- [CONFIGURATION.md](CONFIGURATION.md) — page overlay per profile, bubble border/icon, settings admin, Twig helpers
- [USAGE.md](USAGE.md) — profile settings admin and custom bubble icon
- [SECURITY.md](SECURITY.md) — settings admin surface and bubble icon sanitization
- [UPGRADING.md](UPGRADING.md) — upgrade path from 1.1.1

[1.2.0]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.2.0

## [1.1.1] - 2026-06-15

### Added

- TypeScript tests for `iframe-manager`, `apply-theme`, and expanded coverage for `apply-config`, `step-manager`, and `granular-cookie-toggles`
- Maintainer script `.scripts/complete-public-phpdoc.php` and Makefile target `validate-phpdoc` (included in `release-check`)

### Changed

- **Nowo bundle standards alignment** — REQ-MAKE-008 markers in Makefiles, REQ-DOCS-009 README CTA blockquote, expanded `docs/SECURITY.md`, full Twig/i18n override docs in [CONFIGURATION.md](CONFIGURATION.md)
- **PHPDoc (REQ-CS-001)** — English PHPDoc completed on public classes and methods across `src/`
- **TypeScript coverage** — Vitest thresholds at 90%; `.scripts/ts-coverage-percent.sh` fails below minimum; README reports **~94%** TS lines (PHP remains **~100%**)

### Fixed

- PHPUnit fixtures updated for `CookieInventoryProvider` and expanded `CookieConsentConfigPayloadFactory` / `CookieConsentType` constructors (`CookieConsentTwigExtensionTest`, `CookieConsentConfigPayloadFactoryTest`, `CookieConsentConfigApiControllerTest`, `CookieConsentTypeTest`)

### Documentation

- [SECURITY.md](SECURITY.md) — scope, attack surface, threat model, mitigations, logging, dependency policy
- [CONFIGURATION.md](CONFIGURATION.md) — Twig override procedure and full template table; translation override procedure with YAML example
- [UPGRADING.md](UPGRADING.md) — upgrade path from 1.1.0

[1.1.1]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.1.1

## [1.1.0] - 2026-06-15

### Added

- **Granular cookie selection** — optional per-cookie toggles inside each category block when `granular_cookie_selection` is enabled on the active profile (`CookieConsentConfig::granularCookieSelection` or bundle default)
- **Cookie inventory** — `use_cookie_inventory`, static `cookie_inventory` YAML, and Doctrine entities `CookieDefinition` / `CookieDefinitionTranslation` with translatable provider and purpose
- **`allowed_by_default`** on cookie definitions — controls pre-checked state for individual cookies and category toggles before consent is saved
- **Per-cookie consent storage** — optional cookie map persisted alongside category choices; `CookieChecker::isCookieAllowedByUser()` respects granular preferences
- **Preferences bubble** — floating cookie button (`preferences_bubble_enabled`, `preferences_bubble_position`) to reopen the modal after consent; Twig helper `nowo_cookie_consent_should_embed_modal()`
- **Cookie definition admin CRUD** — `CookieDefinitionAdminController` and Bootstrap admin views under `/cookie-consent-config/{id}/cookies` (wire routes in your app)
- **Two-step modal navigation** — close control on the preferences step returns to the consent banner (`data-nowo-hide-preferences`)
- **CookieConsent v3 alignment** — color themes, two-step banner, preference sections, iframe placeholders, diagnostics script, category/granular toggle TypeScript modules
- **Twig helpers** — `nowo_cookie_consent_granular_cookie_selection()`, `nowo_cookie_consent_preferences_bubble_*()`, `nowo_cookie_consent_two_step_modal()`, and related CMP UX helpers
- **Demo enhancements** — legal pages, cookie inventory CRUD with locale tabs, playground preset seed, shared demo catalog (`DemoCookieCatalog`)

### Changed

- Rebuilt `nowo-consent-modal.js` (granular toggle CSS, step manager, category sync)
- `CookieConsentType` initial state derives category and cookie checkboxes from inventory `allowed_by_default` when no consent cookie exists
- Database-backed config payload and CMP UX resolver expose new profile flags
- PHP test suite expanded (**123 tests**); TypeScript tests cover step manager, granular toggles, and category toggles

### Documentation

- [CONFIGURATION.md](CONFIGURATION.md) — cookie inventory, granular selection, preferences bubble, admin CRUD
- [USAGE.md](USAGE.md) — embed modal with bubble, per-cookie checks, inventory admin
- [UPGRADING.md](UPGRADING.md) — upgrade path from 1.0.0

[1.1.0]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.1.0

## [1.0.0] - 2026-06-15

First stable release of the modernized Nowo Cookie Consent Bundle.

### Added

- GDPR cookie consent modal with category toggles, AJAX form submission, and optional Doctrine consent logging
- TypeScript frontend (`nowo-consent-modal.js`) built with Vite; Bootstrap and Tailwind UI themes (`ui_theme`)
- Database-backed modal configuration (`use_database_config`) with per-locale translations and optional config API (`fetch_config_via_api`)
- Locales: `en`, `es`, `it`, `fr`, `de`, `pt`, `nl`, `pl`, `ca` with `Accept-Language` detection
- Symfony Flex recipe (`.symfony/recipe/nowo-tech/cookie-consent-bundle/1.0`)
- FrankenPHP demos: `demo/symfony8` (Bootstrap, port **8014**) and `demo/symfony8-tailwind` (Tailwind, port **8015**)
- Nowo bundle standards: GitHub workflows (CI, release, sync-releases), issue/PR templates, Dependabot, PR lint, stale bot, CodeRabbit, Scrutinizer config, Copilot instructions
- Cursor/Engram setup (`.cursor/`, `docs/ENGRAM.md`, `docs/SPEC-DRIVEN-DEVELOPMENT.md`)
- Docs: INSTALLATION, CONFIGURATION, USAGE, CONTRIBUTING, CHANGELOG, UPGRADING, RELEASE, SECURITY, DEMO-FRANKENPHP

### Changed

- PHP test suite: **114 tests**, **100%** line coverage on `src/`
- TypeScript tests: **~96%** line coverage (Vitest)
- Demo `make up` follows canonical Nowo port protocol (`REQ-DEMO-005`)
- Demo `release-check` syncs bundle code via `update-bundle` before tests (`REQ-DEMO-007`)
- English PHPDoc on all public classes and methods in `src/`

### Documentation

- README: canonical badges, `## Documentation`, `## Tests and coverage` with percentages
- `docs/CONFIGURATION.md` table of contents; Twig override and translation procedures documented

[1.0.0]: https://github.com/nowo-tech/CookieConsentBundle/releases/tag/v1.0.0
