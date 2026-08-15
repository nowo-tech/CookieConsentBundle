# Usage

## Table of contents

- [Embed the modal](#embed-the-modal)
- [Conditional scripts](#conditional-scripts)
- [Cookie inventory in the modal](#cookie-inventory-in-the-modal)
- [Cookie inventory admin](#cookie-inventory-admin)
- [Profile settings admin (overlay, theme, layout)](#profile-settings-admin-overlay-theme-layout)
- [Preferences bubble icon](#preferences-bubble-icon)
- [AJAX submission](#ajax-submission)
- [Consent logging](#consent-logging)
- [Overriding templates (REQ-TWIG-001)](#overriding-templates-req-twig-001)
- [Demo](#demo)

## Embed the modal

Render the consent fragment when the user has not saved preferences yet, or when the floating bubble is enabled (so the modal can be reopened):

```twig
{% if nowo_cookie_consent_should_embed_modal() %}
    {{ render(path('nowo_cookie_consent.show_if_not_set')) }}
{% endif %}
```

When the bubble is disabled, the legacy check is equivalent to `not nowo_cookie_consent_is_saved()`.

To omit the consent fragment entirely on authenticated dashboards (no ESI sub-request, no inventory queries), configure `skip_render_routes` and optionally guard the embed:

```twig
{% if nowo_cookie_consent_should_render() %}
    {{ render(path('nowo_cookie_consent.show')) }}
{% endif %}
```

`CookieConsentController::show` also returns an empty response when the main request route is outside `render_routes` (whitelist) or matches `skip_render_routes`, even if the host forgets the Twig guard.

During cold start (before the database schema exists), Site Backup Bundle may set `_nowo_site_backup_schema_exists: false` on the main request. Cookie Consent Bundle then skips consent rendering and Doctrine work; `nowo_cookie_consent_should_render()` returns `false`. See [CONFIGURATION — Cold start / Site Backup](CONFIGURATION.md#cold-start--site-backup).

The bundle detects the locale from the current request (and `Accept-Language` when enabled). You do not need to pass `locale` manually unless you want to force a specific language.

The modal uses Bootstrap 5 markup (or Tailwind when `ui_theme: tailwind`). Include the matching CSS/JS in your layout, or rely on the bundle fallback that toggles `.show` on the modal element.

After upgrading the bundle, run `php bin/console assets:install` so `nowo-consent-modal.js` includes granular toggles and step navigation.

## Conditional scripts

Load analytics only when the category is allowed:

```twig
{% if nowo_cookie_consent_is_category_allowed('analytics') %}
    {# analytics snippet #}
{% endif %}
```

When granular selection and cookie inventory are enabled, gate third-party scripts by cookie name in PHP:

```php
if ($cookieChecker->isCookieAllowedByUser('_ga', 'analytics')) {
    // load analytics
}
```

## Cookie inventory in the modal

Enable `use_cookie_inventory: true` and populate definitions via YAML or Doctrine (`CookieDefinition` entities). The preferences modal lists cookies per category with provider, purpose, and duration.

With `granular_cookie_selection`, each optional cookie shows an **Allow** toggle. Set `allowed_by_default` on each definition to control the initial state before the visitor saves consent.

## Cookie inventory admin

The bundle provides `CookieDefinitionAdminController`, `CookieDefinitionType`, and Bootstrap admin templates. Register the routes in your application and link from your config admin UI.

The Symfony 8 demo implements a full CRUD at `/demo/admin/cookie-consent-config/{id}/cookies` with locale tabs for translations.

## Profile settings admin (overlay, theme, layout)

When `use_database_config: true`, edit `CookieConsentConfig` profiles with:

- **Forms:** one class per tab under `Nowo\CookieConsentBundle\Form\Settings\` (e.g. `CookieConsentConfigProfileSettingsType`); resolve with `CookieConsentConfigSettingsSection::formType()`
- **Controller:** `CookieConsentConfigSettingsAdminController` — `GET/POST /cookie-consent-config/{configId}/settings/{section}`
- **Template:** `@NowoCookieConsentBundle/admin/config/settings.html.twig`

Set `nowo_cookie_consent.web_ui.layout_template` to your project layout (or a one-file bridge) so admin pages inherit host chrome without copying list/form templates. See [CONFIGURATION.md — Admin Web UI](CONFIGURATION.md#admin-web-ui). Protect admin routes with `security.access_roles` / host `access_control` ([Admin security](CONFIGURATION.md#admin-security)).

Import bundle routes (`config/routes/nowo_cookie_consent.yaml` → `@NowoCookieConsentBundle/Resources/config/routing.yaml`). Labels use the `NowoCookieConsentBundle` translation domain (`en` and `es` shipped; other locales fall back to English).

Example in your controller:

```php
use Nowo\CookieConsentBundle\Admin\CookieConsentConfigSettingsSection;

$section = CookieConsentConfigSettingsSection::Appearance;
$form = $this->createForm($section->formType(), $config);
```

For a single-page editor (all sections at once), use `CookieConsentConfigFullSettingsType` (or the deprecated `CookieConsentConfigSettingsType` alias). The demos do that with demo-specific label prefixes and route placeholders.

## Preferences bubble icon

When the bubble is enabled, set custom markup in the admin **Bubble icon (HTML/SVG)** field or via `CookieConsentConfig::setPreferencesBubbleIcon()`. SVG and simple HTML (e.g. an emoji in a `<span class="nowo-cookie-consent__preferences-bubble-emoji">`) are supported; dangerous markup is rejected server-side. Leave empty for the default cookie SVG.

## AJAX submission

The bundled `nowo-consent-modal.js` (built from TypeScript via Vite) submits the form via `XMLHttpRequest` and dispatches `nowo-cookie-consent-form-submit-successful` on success.

Before each XHR POST it prepares Symfony CSRF the same way as the Flex `csrf-protection` Stimulus controller (native `submit` never fires on button click): SameOrigin double-submit cookie when the field still holds the cookie-name placeholder, plus a `csrf-token` request header when `framework.csrf_protection.check_header` is enabled. Keep `nowo_cookie_consent.csrf_protection: true`.

## Consent logging

When `use_logger: true`, each submission creates rows in `CookieConsentLog` with anonymized IP addresses (GDPR-friendly).

## Overriding templates (REQ-TWIG-001)

The bundle registers the Twig namespace **`@NowoCookieConsentBundle/`**. Application files under **`templates/bundles/NowoCookieConsentBundle/`** **always win** over the copies inside the package (`TwigPathsPass` prepends that directory when it exists, then adds the bundle views path).

**Freeze rule:** a full-file override hides vendor updates for that `<subpath>` until you delete or manually merge it. Prefer surgical overrides (a single partial) or config such as **`nowo_cookie_consent.web_ui.layout_template`** for upgrade-safe customisation — see [CONFIGURATION.md — Admin Web UI](CONFIGURATION.md#admin-web-ui).

**Procedure**

1. Identify the `<subpath>` from the table below (path relative to `src/Resources/views/`).
2. Create in your application: `templates/bundles/NowoCookieConsentBundle/<subpath>` (same relative path and filename).
3. Clear the cache in dev if needed: `php bin/console cache:clear`.

Example — override the Bootstrap modal shell:

```text
templates/bundles/NowoCookieConsentBundle/cookie_consent.html.twig
```

Controllers and Twig use logical names such as `@NowoCookieConsentBundle/cookie_consent.html.twig`, never absolute filesystem paths.

**Overridable templates**

| Subpath | Purpose |
| --- | --- |
| `cookie_consent.html.twig` | Main consent modal (Bootstrap theme) |
| `cookie_consent.tailwind.html.twig` | Main consent modal (Tailwind theme) |
| `form/cookie_consent_theme.html.twig` | Symfony form theme for consent fields (Bootstrap) |
| `form/cookie_consent_theme.tailwind.html.twig` | Form theme (Tailwind) |
| `cookie_consent_preferences_bubble.html.twig` | Floating “cookie settings” bubble button |
| `_preferences_bubble_icon_default.html.twig` | Default cookie SVG for the preferences bubble |
| `cookie_consent_manage_link.html.twig` | Inline link to reopen preferences |
| `_category_cookie_table.html.twig` | Per-category cookie inventory table (granular mode) |
| `_preference_sections.html.twig` | Preferences step category blocks |
| `_preferences_intro.html.twig` | Intro text on the preferences step |
| `_diagnostics_script.html.twig` | Optional diagnostics script partial |
| `admin/base.html.twig` | Intermediate shell admin pages extend (points at `web_ui.layout_template`; stacks `stylesheets` / `javascripts` with `parent()`) |
| `admin/layout.html.twig` | Default admin demo full HTML root (`web_ui.layout_template` default); preferred override target via config |
| `admin/_pagination.html.twig` | Cookie definition list pagination partial |
| `admin/cookie_definition/layout.html.twig` | Admin CRUD layout shell (BC alias of `admin/base.html.twig`) |
| `admin/cookie_definition/index.html.twig` | Cookie definition list |
| `admin/cookie_definition/form.html.twig` | Create/edit cookie definition form |
| `admin/cookie_definition/_table.html.twig` | Admin list table partial |
| `admin/config/layout.html.twig` | Profile settings admin layout shell (BC alias of `admin/base.html.twig`) |
| `admin/config/settings.html.twig` | Profile settings form (overlay, theme, bubble, layout) |

Theme selection follows `ui_theme` (`bootstrap` or `tailwind`); override the modal and form theme rows that match your active theme. See also [CONFIGURATION.md — UI theme](CONFIGURATION.md#ui-theme).

## Demo

See `demo/symfony8/` for a FrankenPHP demo application (port **8014**). Tailwind variant: `demo/symfony8-tailwind/` (port **8015**).

Seed sample inventory: `php bin/console demo:seed-cookie-definitions --if-empty`
