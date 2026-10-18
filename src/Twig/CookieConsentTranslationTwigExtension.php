<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Twig;

use Nowo\CookieConsentBundle\Config\ResolvedCookieConsentConfig;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

use function array_key_exists;
use function trim;

/**
 * Translates consent modal texts, preferring the database profile copy of the current request.
 *
 * Database texts are looked up per call instead of being registered on the shared translator,
 * so they never leak between requests or accumulate in a long-running worker.
 */
final class CookieConsentTranslationTwigExtension extends AbstractExtension
{
    public const TRANSLATION_DOMAIN = 'NowoCookieConsentBundle';

    /**
     * @param string|null $privacyRouteFallback Config `privacy_route`: used when the profile translation is empty
     */
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly TranslatorInterface $translator,
        private readonly ?string $privacyRouteFallback = null,
    ) {
    }

    /**
     * @return array<int, TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('nowo_cookie_consent_trans', $this->trans(...)),
            new TwigFunction('nowo_cookie_consent_privacy_route', $this->privacyRoute(...)),
        ];
    }

    /**
     * Returns the database text for the message id when the resolved profile defines it,
     * otherwise the translation from the NowoCookieConsentBundle domain.
     *
     * @param string $id The translation message id (e.g. nowo_cookie_consent.title)
     * @param mixed $displayConfig The resolved config passed to the template; defaults to the one stored on the request
     *
     * @return string
     */
    public function trans(string $id, mixed $displayConfig = null): string
    {
        $resolved = $displayConfig instanceof ResolvedCookieConsentConfig ? $displayConfig : $this->getRequestResolvedConfig();

        if ($resolved instanceof ResolvedCookieConsentConfig) {
            $messages = $resolved->getTranslationMessages();

            if (array_key_exists($id, $messages)) {
                return $messages[$id];
            }
        }

        return $this->translator->trans($id, [], self::TRANSLATION_DOMAIN);
    }

    /**
     * Route name of the privacy link of the modal: the profile / translation value
     * (`nowo_cookie_consent.privacy_route`), else the configured `privacy_route` fallback; null for no link.
     *
     * @param mixed $displayConfig The resolved config passed to the template; defaults to the one stored on the request
     *
     * @return ?string
     */
    public function privacyRoute(mixed $displayConfig = null): ?string
    {
        $route = trim($this->trans('nowo_cookie_consent.privacy_route', $displayConfig));
        if ($route !== '' && $route !== 'nowo_cookie_consent.privacy_route') {
            return $route;
        }

        return $this->privacyRouteFallback !== null && $this->privacyRouteFallback !== '' ? $this->privacyRouteFallback : null;
    }

    private function getRequestResolvedConfig(): ?ResolvedCookieConsentConfig
    {
        foreach ([$this->requestStack->getCurrentRequest(), $this->requestStack->getMainRequest()] as $request) {
            if (!$request instanceof Request) {
                continue;
            }

            $resolved = $request->attributes->get('nowo_cookie_consent_config');

            if ($resolved instanceof ResolvedCookieConsentConfig) {
                return $resolved;
            }
        }

        return null;
    }
}
