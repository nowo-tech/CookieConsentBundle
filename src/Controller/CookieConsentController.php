<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Controller;

use Nowo\CookieConsentBundle\Render\CookieConsentModalRenderer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * HTTP entry points for the consent modal (ESI / legacy {@code render(path(...))} embeds).
 *
 * Prefer {@code {{ nowo_cookie_consent_render() }}} in host layouts to avoid a kernel sub-request.
 */
class CookieConsentController
{
    public function __construct(
        private readonly CookieConsentModalRenderer $modalRenderer,
    ) {
    }

    /**
     * Renders the cookie consent modal page.
     *
     * @param Request $request The current HTTP request
     *
     * @return Response The rendered consent modal response
     */
    #[Route('/cookie_consent', name: 'nowo_cookie_consent.show')]
    public function show(Request $request): Response
    {
        $response = new Response($this->modalRenderer->renderHtml($request));
        $response->setPrivate();
        $response->setMaxAge(0);

        return $response;
    }

    /**
     * Renders the consent modal only when the user has not saved preferences yet.
     *
     * @param Request $request The current HTTP request
     *
     * @return Response The modal response or an empty response
     */
    #[Route('/cookie_consent_alt', name: 'nowo_cookie_consent.show_if_not_set')]
    public function showIfCookieConsentNotSet(Request $request): Response
    {
        return $this->show($request);
    }
}
