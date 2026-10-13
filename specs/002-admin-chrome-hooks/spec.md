# Feature Specification: Admin chrome hooks (blocks, class map, partial paths)

**Feature Branch**: `002-admin-chrome-hooks`
**Status**: Implemented (1.13.0)
**Package**: `nowo-tech/cookie-consent-bundle`
**Upstream driver**: symfony-beacon spec 134 (kit upstream improvements) — FR-010 (CookieConsent panel)
**Baseline**: [`001-baseline`](../001-baseline/spec.md)

---

## Summary

Hosts re-skin the admin Web UI (cookie inventory list / form, profile settings) by **config and blocks** instead of copying whole pages. Defaults reproduce the 1.12.0 markup exactly, so existing apps see no change.

## Problem

Beacon forked eight admin templates (~249 lines) only to repaint chrome: Beacon tab partial, panel wrappers instead of Bootstrap cards, Beacon button classes, UiKit table classes, FormKit theme instead of `bootstrap_5_layout`, host pagination partial, extra stylesheet after `nowo-ui.css`, `data-testid` hooks. Each fork froze vendor behaviour (e.g. the Beacon `_table` still built its own delete form after the bundle moved to controller `delete_forms`; the Beacon `base` lost the 1.12 CSP nonce).

## Requirements

- **FR-CHROME-001 — Class / attribute map.** `web_ui.chrome.classes` maps element keys (`AdminChrome::DEFAULT_CLASSES`) to class strings; `null` keeps the UiKit macro for `web_ui.css_framework` (buttons, pagination). `web_ui.chrome.attributes` adds HTML attributes per element (hyphenated names preserved). Unknown keys fail at compile time. Empty `form_panel_body` / `settings_panel_body` drops the inner wrapper.
- **FR-CHROME-002 — Partial paths.** `web_ui.chrome.templates`: `tabs` (area + section tabs; contract `variant, aria_label, items[{label, href, current}], nav_class, list_class, item_class, active_class, attr, framework`, rendered `only`), `pagination` (receives `page, pages, total, page_size, item_count, framework` + page context), `form_theme` (`null` = app default theme), `form_fields` (host partial receiving `{form}`), `styles_after` (included after the UiKit stylesheet). `tabs` / `pagination` cannot be disabled (null → default).
- **FR-CHROME-003 — Twig blocks.** Pages expose `nowo_cookie_consent_admin_*` blocks: `area_nav`, `page_header`, `page_title`, `page_intro`, `page_actions`, `table_wrapper`, `pagination`, `section_tabs`, `settings_section`, `section_header`, `form_wrapper`, `form_body`, `form_actions`; `_table.html.twig`: `table_head`, `row_actions`, `empty_state`; `base.html.twig`: `styles_after`. Prefixed to avoid collisions with host layout blocks.
- **FR-CHROME-004 — Original namespace.** `TwigPathsPass` registers `@!NowoCookieConsentBundle` → shipped views, so a fork can `{% extends '@!NowoCookieConsentBundle/…' %}` and override one block.
- **FR-CHROME-005 — Options.** `page_title_tag` (`h1`…`h6`, `none`; default `h1`) and `row_actions_display` (`text` default, `icon`, `icon_text`, `inherit` = `nowo_ui_kit.row_actions_display`).
- **FR-CHROME-006 — i18n.** New keys `admin.pagination.label` (was hard-coded "Pagination") and `admin.layout.title`; previously missing admin keys (`cookie_definition.lead`, `form_lead`, `new_title`, `edit_title`) in all 9 locales; Catalan and Polish get the full admin chrome set. Row "Edit" label receives `%name%`.
- **FR-CHROME-007 — BC.** Without `web_ui.chrome` the rendered admin pages equal the 1.12.0 output (DOM-normalised goldens in `tests/Fixtures/admin_chrome/`, rendered from the v1.12.0 templates with the 1.13 catalog).

## Acceptance (tests)

| Test | Covers |
| --- | --- |
| `tests/Unit/Template/AdminChromeTemplateTest.php` | Goldens (index, empty index, form, settings) = 1.12.0; class + attribute map; `page_title_tag` h2/none; `row_actions_display: inherit`; empty state + confirm buttons; host tabs / pagination partials and their contracts; `form_theme: ~` + `form_fields`; `styles_after` order; `@!` fork overriding one block |
| `tests/Unit/Template/AdminChromeTranslationsTest.php` | Chrome keys exist in all 9 locales |
| `tests/Unit/Admin/AdminChromeTest.php` | Resolver defaults, merge, fallbacks, unknown keys |
| `tests/Unit/DependencyInjection/AdminChromeConfigurationTest.php` | Config tree defaults, hyphenated attributes, invalid values, extension → Twig global |
| `tests/Unit/DependencyInjection/Compiler/TwigPathsPassTest.php` | `!NowoCookieConsentBundle` path |

## Beacon fork verdicts (symfony-beacon `templates/bundles/NowoCookieConsentBundle/admin/`)

Diffed against v1.12.0. "Chrome" = classes / wrappers / partial choice; "behaviour" = routes, forms, CSRF, ids.

| Fork | Lines | Diff classification | Verdict | Replacement |
| --- | ---: | --- | --- | --- |
| `base.html.twig` | 30 | Chrome: re-include `kit/_kit_admin_styles.html.twig` after `nowo-ui.css`. Regression: drops the 1.12 CSP nonce on `nowo-ui-confirm.js`. | **Delete** | `templates.styles_after: 'kit/_kit_admin_styles.html.twig'` |
| `_admin_area_nav.html.twig` | 24 | Chrome: Beacon `shared/_tabs.html.twig`, `mb-4`, `data-testid`. Same routes / labels. | **Delete** | `templates.tabs: 'shared/_tabs.html.twig'` (contract-compatible), `classes.area_nav`, `attributes.area_nav` |
| `config/_settings_section_tabs.html.twig` | 16 | Chrome: same as above, `beacon-tabs--sections`. | **Delete** | same `templates.tabs`, `classes.section_tabs`, `attributes.section_tabs` |
| `_pagination.html.twig` | 10 | Chrome: delegate to `kit/_pagination.html.twig`. | **Delete** | `templates.pagination: 'kit/_pagination.html.twig'` (receives `page_size`, `item_count`, `framework`) |
| `cookie_definition/_table.html.twig` | 74 | Chrome: UiKit table classes, `row_actions_display` from UiKit, non-sm confirm buttons. Stale behaviour: own `csrf_action_form` delete form instead of controller `delete_forms`. | **Delete** | `classes.table_wrap/table/confirm_cancel/confirm_delete`, `row_actions_display: inherit`; vendor delete forms win |
| `cookie_definition/index.html.twig` | 24 | Chrome: no in-page h1, toolbar header, `btn-primary`, panel + `data-testid`, pagination inside the panel. | **Delete** | `page_title_tag: h2` (or `none`), `classes.page_header/page_title/page_intro/button_primary/table_panel`, `attributes.table_panel`. Only nuance: pagination renders after (not inside) the panel; if that matters, shrink to an `@!` fork overriding `nowo_cookie_consent_admin_table_wrapper`. |
| `cookie_definition/form.html.twig` | 37 | Chrome: FormKit theme via `form/_fields.html.twig`, panel, Beacon buttons, title inside panel, "← Cookie inventory" back link. | **Delete** | `templates.form_theme: ~`, `templates.form_fields: 'form/_fields.html.twig'`, `classes.form_panel/form_panel_body/form/form_actions/button_primary/button_secondary`, `attributes.form_panel`. The back link is dropped (area tab + Cancel already navigate back); if required, shrink to an `@!` fork overriding `nowo_cookie_consent_admin_page_header`. |
| `config/settings.html.twig` | 34 | Chrome: FormKit fields, panel + `data-testid` (E2E), section title/lead classes, Beacon button. | **Delete** | `classes.settings_panel/settings_panel_body/section_title/section_lead/settings_actions/form/button_primary`, `attributes.settings_panel`, `templates.form_theme/form_fields` |

Nothing must stay: every diff is chrome. After adoption Beacon keeps only `templates/kit/cookie_consent_admin_layout.html.twig` (layout bridge) plus the config below (Beacon FR-024).

### Suggested Beacon config

```yaml
nowo_cookie_consent:
    web_ui:
        layout_template: 'kit/cookie_consent_admin_layout.html.twig'
        css_framework: tailwind
        chrome:
            page_title_tag: h2
            row_actions_display: inherit
            classes:
                page_header: 'nowo-ui-toolbar flex flex-wrap items-center justify-between gap-2 mb-2'
                page_header_form: 'mb-4'
                page_title: 'nowo-ui-section-title'
                page_intro: 'nowo-ui-muted text-slate-500 mb-0'
                area_nav: 'mb-4 nowo-ui-tabs--area'
                section_tabs: 'mb-6 beacon-tabs--sections nowo-ui-tabs--sections'
                table_panel: 'panel nowo-ui-table-wrap'
                table_wrap: 'nowo-ui-table-wrap overflow-x-auto'
                table: 'nowo-ui-table nowo-ui-table--striped min-w-full text-sm text-left data-table'
                form_panel: 'panel nowo-ui-form-wrap mx-auto'
                form_panel_body: ''
                settings_panel: 'panel nowo-ui-form-wrap mx-auto'
                settings_panel_body: ''
                form: 'nowo-ui-form nowo-ui-form--stack'
                form_actions: 'nowo-ui-form-actions'
                settings_actions: 'nowo-ui-form-actions'
                section_title: 'nowo-ui-section-title'
                section_lead: 'nowo-ui-muted text-slate-500 mb-4'
                button_primary: 'btn-primary'
                button_secondary: 'btn-ghost no-underline'
                confirm_cancel: 'btn-ghost'
                confirm_delete: 'btn-danger'
            attributes:
                area_nav: { data-testid: cookie-consent-area-tabs }
                section_tabs: { data-testid: cookie-consent-section-tabs }
                table_panel: { data-testid: cookie-consent-definitions-table }
                form_panel: { data-testid: cookie-consent-definition-form }
                settings_panel: { data-testid: cookie-consent-settings-form }
            templates:
                tabs: 'shared/_tabs.html.twig'
                pagination: 'kit/_pagination.html.twig'
                form_theme: ~
                form_fields: 'form/_fields.html.twig'
                styles_after: 'kit/_kit_admin_styles.html.twig'
```

## Out of scope

- Public consent modal markup (stays vendor + host CSS).
- Translating the ~100 settings field labels for Catalan / Polish (they fall back to English as before).
