# Usage

## Embed the modal

Render the consent fragment only when the user has not saved preferences yet:

```twig
{% if not nowo_cookie_consent_is_saved() %}
    {{ render(path('nowo_cookie_consent.show_if_not_set')) }}
{% endif %}
```

The bundle detects the locale from the current request (and `Accept-Language` when enabled). You do not need to pass `locale` manually unless you want to force a specific language.

The modal uses Bootstrap 5 markup. Include Bootstrap JS/CSS in your layout, or rely on the bundle fallback that toggles `.show` on the modal element.

## Conditional scripts

Load analytics only when the category is allowed:

```twig
{% if nowo_cookie_consent_is_category_allowed('analytics') %}
    {# analytics snippet #}
{% endif %}
```

## AJAX submission

The bundled `nowo-consent-modal.js` (built from TypeScript via Vite) submits the form via `XMLHttpRequest` and dispatches `nowo-cookie-consent-form-submit-successful` on success.

## Consent logging

When `use_logger: true`, each submission creates rows in `CookieConsentLog` with anonymized IP addresses (GDPR-friendly).

## Demo

See `demo/symfony8/` for a FrankenPHP demo application.
