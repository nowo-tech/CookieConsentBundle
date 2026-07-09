# Code inventory — 100% traceability

**Baseline spec**: [`spec.md`](spec.md)  
**Package**: `nowo-tech/cookie-consent-bundle`  
**Last audited**: 2026-07-07

## TypeScript production (`src/Resources/assets/src/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/assets/src/apply-config.ts` | Runtime config merge | FR-UI-001 |
| `Resources/assets/src/apply-theme.ts` | Theme application | FR-UI-002 |
| `Resources/assets/src/apply-visual-config.ts` | Visual config | FR-UI-003 |
| `Resources/assets/src/category-toggles.ts` | Category toggles | FR-UI-004 |
| `Resources/assets/src/cookie-consent.css` | Modal styles | FR-UI-005 |
| `Resources/assets/src/cookie-consent.ts` | Modal entrypoint | FR-UI-006 |
| `Resources/assets/src/custom-event-polyfill.ts` | CustomEvent polyfill | FR-UI-007 |
| `Resources/assets/src/diagnostics.ts` | Diagnostics hooks | FR-UI-008 |
| `Resources/assets/src/form-serializer.ts` | Form serialization | FR-UI-009 |
| `Resources/assets/src/granular-cookie-toggles.ts` | Per-cookie toggles | FR-UI-010 |
| `Resources/assets/src/iframe-manager.ts` | Blocked iframe manager | FR-UI-011 |
| `Resources/assets/src/logger.ts` | Client logger | FR-UI-012 |
| `Resources/assets/src/step-manager.ts` | Step wizard UI | FR-UI-013 |

## TypeScript tests (`src/Resources/assets/src/*.test.ts`)

Co-located Vitest sources under `src/` (compiled/tested in CI; not shipped to Packagist as runtime API).

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/assets/src/apply-config.test.ts` | Vitest: config apply | FR-TEST-TS-001 |
| `Resources/assets/src/apply-theme.test.ts` | Vitest: theme apply | FR-TEST-TS-001 |
| `Resources/assets/src/apply-visual-config.test.ts` | Vitest: visual config | FR-TEST-TS-001 |
| `Resources/assets/src/category-toggles.test.ts` | Vitest: category toggles | FR-TEST-TS-001 |
| `Resources/assets/src/cookie-consent.test.ts` | Vitest: modal core | FR-TEST-TS-001 |
| `Resources/assets/src/custom-event-polyfill.test.ts` | Vitest: polyfill | FR-TEST-TS-001 |
| `Resources/assets/src/form-serializer.test.ts` | Vitest: form serializer | FR-TEST-TS-001 |
| `Resources/assets/src/granular-cookie-toggles.test.ts` | Vitest: granular toggles | FR-TEST-TS-001 |
| `Resources/assets/src/iframe-manager.test.ts` | Vitest: iframe manager | FR-TEST-TS-001 |
| `Resources/assets/src/logger.test.ts` | Vitest: logger | FR-TEST-TS-001 |
| `Resources/assets/src/step-manager.test.ts` | Vitest: step manager | FR-TEST-TS-001 |

## Symfony config, assets, translations, Twig (`src/Resources/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/config/routing.yaml` | Routes | FR-ROUT-001 |
| `Resources/config/services.yaml` | Service wiring | FR-DI-001 |
| `Resources/public/cookieconsent.css` | Built/legacy CSS | FR-ASSET-001 |
| `Resources/public/nowo-cc-themes.css` | Theme CSS | FR-ASSET-001 |
| `Resources/public/nowo-consent-modal.js` | Vite build output | FR-BUILD-001 |
| `Resources/translations/NowoCookieConsentBundle.ca.yaml` | Translations | FR-I18N-001 |
| `Resources/translations/NowoCookieConsentBundle.de.yaml` | Translations | FR-I18N-001 |
| `Resources/translations/NowoCookieConsentBundle.en.yaml` | Translations | FR-I18N-001 |
| `Resources/translations/NowoCookieConsentBundle.es.yaml` | Translations | FR-I18N-001 |
| `Resources/translations/NowoCookieConsentBundle.fr.yaml` | Translations | FR-I18N-001 |
| `Resources/translations/NowoCookieConsentBundle.it.yaml` | Translations | FR-I18N-001 |
| `Resources/translations/NowoCookieConsentBundle.nl.yaml` | Translations | FR-I18N-001 |
| `Resources/translations/NowoCookieConsentBundle.pl.yaml` | Translations | FR-I18N-001 |
| `Resources/translations/NowoCookieConsentBundle.pt.yaml` | Translations | FR-I18N-001 |
| `Resources/views/_category_cookie_table.html.twig` | Modal partial | FR-TWIG-003 |
| `Resources/views/_diagnostics_script.html.twig` | Diagnostics partial | FR-TWIG-003 |
| `Resources/views/_preference_sections.html.twig` | Preferences partial | FR-TWIG-003 |
| `Resources/views/_preferences_bubble_icon_default.html.twig` | Bubble icon partial | FR-TWIG-003 |
| `Resources/views/_preferences_intro.html.twig` | Intro partial | FR-TWIG-003 |
| `Resources/views/admin/config/layout.html.twig` | Admin layout | FR-TWIG-003 |
| `Resources/views/admin/config/settings.html.twig` | Admin settings | FR-TWIG-003 |
| `Resources/views/admin/cookie_definition/_table.html.twig` | Admin table | FR-TWIG-003 |
| `Resources/views/admin/cookie_definition/form.html.twig` | Admin form | FR-TWIG-003 |
| `Resources/views/admin/cookie_definition/index.html.twig` | Admin index | FR-TWIG-003 |
| `Resources/views/admin/cookie_definition/layout.html.twig` | Admin layout | FR-TWIG-003 |
| `Resources/views/cookie_consent.html.twig` | Bootstrap modal | FR-TWIG-003 |
| `Resources/views/cookie_consent.tailwind.html.twig` | Tailwind modal | FR-TWIG-003 |
| `Resources/views/cookie_consent_manage_link.html.twig` | Manage link | FR-TWIG-003 |
| `Resources/views/cookie_consent_preferences_bubble.html.twig` | Preferences bubble | FR-TWIG-003 |
| `Resources/views/form/cookie_consent_theme.html.twig` | Form theme Bootstrap | FR-TWIG-003 |
| `Resources/views/form/cookie_consent_theme.tailwind.html.twig` | Form theme Tailwind | FR-TWIG-003 |

## PHP classes (`src/**/*.php`)
| `Config/CmpUxOptionsResolver.php` | Config helper | FR-CFG-003 |
| `Config/CookieConsentConfigPayloadFactory.php` | Config helper | FR-CFG-003 |
| `Config/CookieConsentConfigResolver.php` | Config helper | FR-CFG-003 |
| `Config/CookieConsentConfigSelector.php` | Config helper | FR-CFG-003 |
| `Config/CookieConsentRoutePatternMatcher.php` | Config helper | FR-CFG-003 |
| `Config/CookieConsentRouteTargeting.php` | Config helper | FR-CFG-003 |
| `Config/CookieInventoryNormalizer.php` | Config helper | FR-CFG-003 |
| `Config/CookieInventoryProvider.php` | Config helper | FR-CFG-003 |
| `Config/PreferencesBubbleIconSanitizer.php` | Config helper | FR-CFG-003 |
| `Config/ResolvedCookieConsentConfig.php` | Config helper | FR-CFG-003 |
| `Controller/CookieConsentConfigApiController.php` | HTTP controller | FR-CTRL-001 |
| `Controller/CookieConsentConfigSettingsAdminController.php` | HTTP controller | FR-CTRL-001 |
| `Controller/CookieConsentController.php` | HTTP controller | FR-CTRL-001 |
| `Controller/CookieDefinitionAdminController.php` | HTTP controller | FR-CTRL-001 |
| `Cookie/CookieChecker.php` | Cookie handling | FR-COOKIE-001 |
| `Cookie/CookieHandler.php` | Cookie handling | FR-COOKIE-001 |
| `Cookie/CookieLogger.php` | Cookie handling | FR-COOKIE-001 |
| `DependencyInjection/Compiler/TwigPathsPass.php` | Compiler pass | FR-TWIG-001 |
| `DependencyInjection/Configuration.php` | Config tree | FR-CFG-001 |
| `DependencyInjection/NowoCookieConsentExtension.php` | DI extension | FR-CFG-002 |
| `DependencyInjection/TablePrefixListener.php` | PHP class | FR-PHP-001 |
| `Entity/CookieConsentConfig.php` | Doctrine entity | FR-ORM-001 |
| `Entity/CookieConsentConfigTranslation.php` | Doctrine entity | FR-ORM-001 |
| `Entity/CookieConsentLog.php` | Doctrine entity | FR-ORM-001 |
| `Entity/CookieDefinition.php` | Doctrine entity | FR-ORM-001 |
| `Entity/CookieDefinitionTranslation.php` | Doctrine entity | FR-ORM-001 |
| `Enum/CategoryEnum.php` | Enum | FR-ENUM-001 |
| `Enum/CookieNameEnum.php` | Enum | FR-ENUM-001 |
| `Enum/DisabledRoutesEnum.php` | Enum | FR-ENUM-001 |
| `EventSubscriber/CookieConsentConfigTranslationSubscriber.php` | Events | FR-EVT-001 |
| `EventSubscriber/CookieConsentFormSubscriber.php` | Events | FR-EVT-001 |
| `Form/CookieConsentConfigSettingsType.php` | Form type | FR-FORM-001 |
| `Form/CookieConsentType.php` | Form type | FR-FORM-001 |
| `Form/CookieDefinitionTranslationType.php` | Form type | FR-FORM-001 |
| `Form/CookieDefinitionType.php` | Form type | FR-FORM-001 |
| `Locale/LocaleResolver.php` | Locale | FR-LOCALE-001 |
| `NowoCookieConsentBundle.php` | Bundle entry | FR-BUNDLE-001 |
| `Repository/CookieConsentConfigRepository.php` | Repository | FR-ORM-002 |
| `Repository/CookieConsentConfigTranslationRepository.php` | Repository | FR-ORM-002 |
| `Repository/CookieDefinitionRepository.php` | Repository | FR-ORM-002 |
| `Repository/CookieDefinitionTranslationRepository.php` | Repository | FR-ORM-002 |
| `Twig/CmpUxTwigExtension.php` | Twig extension | FR-TWIG-002 |
| `Twig/CookieConsentTwigExtension.php` | Twig extension | FR-TWIG-002 |

## Coverage summary

| Category | Files | Mapped |
| --- | ---: | ---: |
| TypeScript production | 13 | 13 |
| TypeScript tests (Vitest) | 11 | 11 |
| Resources (YAML/CSS/JS/i18n/Twig) | 31 | 31 |
| PHP classes | 43 | 43 |
| **Total production sources** | **98** | **98** |
