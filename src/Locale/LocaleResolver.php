<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Locale;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

use function in_array;
use function is_string;

/**
 * Resolves the cookie consent UI locale from the HTTP request.
 */
final class LocaleResolver
{
    /**
     * Creates a new locale resolver.
     *
     * @param list<string> $enabledLocales
     */
    public function __construct(
        private readonly array $enabledLocales,
        private readonly string $defaultLocale,
        private readonly bool $detectLocaleFromAcceptLanguage,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * Returns the locales enabled for the cookie consent UI.
     *
     * @return list<string>
     */
    public function getEnabledLocales(): array
    {
        return $this->enabledLocales;
    }

    /**
     * Returns the default fallback locale.
     *
     * @return string The default locale code
     */
    public function getDefaultLocale(): string
    {
        return $this->defaultLocale;
    }

    /**
     * Returns whether the given locale is enabled.
     *
     * @param string $locale The locale code to check
     *
     * @return bool True when the locale is supported
     */
    public function isEnabled(string $locale): bool
    {
        return in_array($locale, $this->enabledLocales, true);
    }

    /**
     * Resolves the best matching enabled locale for the request.
     *
     * @param Request $request The current HTTP request
     *
     * @return string The resolved locale code
     */
    public function resolve(Request $request): string
    {
        $candidates = [
            $this->normalizeLocale($request->query->get('locale')),
            $this->normalizeLocale($request->attributes->get('_locale')),
            $this->normalizeLocale($request->attributes->get('locale')),
        ];

        $mainRequest = $this->requestStack->getMainRequest();
        if ($mainRequest instanceof Request && $mainRequest !== $request) {
            $candidates[] = $this->normalizeLocale($mainRequest->getLocale());
        }

        $candidates[] = $this->normalizeLocale($request->getLocale());

        if ($this->detectLocaleFromAcceptLanguage) {
            $candidates[] = $this->resolveFromAcceptLanguage($request->headers->get('Accept-Language'));
        }

        foreach ($candidates as $candidate) {
            if ($candidate !== null && $this->isEnabled($candidate)) {
                return $candidate;
            }
        }

        if ($this->isEnabled($this->defaultLocale)) {
            return $this->defaultLocale;
        }

        return $this->enabledLocales[0] ?? 'en';
    }

    private function normalizeLocale(mixed $locale): ?string
    {
        if (!is_string($locale) || $locale === '') {
            return null;
        }

        return strtolower($locale);
    }

    private function resolveFromAcceptLanguage(?string $header): ?string
    {
        if ($header === null || $header === '') {
            return null;
        }

        foreach (explode(',', $header) as $part) {
            $tag = strtolower(trim(explode(';', $part)[0]));

            if ($tag === '' || $tag === '*') {
                continue;
            }

            if ($this->isEnabled($tag)) {
                return $tag;
            }

            $primary = explode('-', $tag)[0];

            if ($this->isEnabled($primary)) {
                return $primary;
            }
        }

        return null;
    }
}
