<?php

declare(strict_types=1);

namespace App\Controller;

use App\Demo\DemoLocale;
use Nowo\CookieConsentBundle\Enum\CookieNameEnum;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(
    '/{_locale}',
    name: 'demo_',
    requirements: ['_locale' => DemoLocale::REQUIREMENT],
    defaults: ['_locale' => DemoLocale::DEFAULT],
)]
class DemoController extends AbstractController
{
    /**
     * @param list<string> $cookieCategories
     */
    public function __construct(
        #[Autowire('%nowo_cookie_consent.categories%')]
        private readonly array $cookieCategories,
        #[Autowire('%nowo_cookie_consent.http_only%')]
        private readonly bool $httpOnly,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/', name: 'home')]
    public function home(): Response
    {
        return $this->render('demo/home.html.twig');
    }

    #[Route('/demo/reset-consent', name: 'reset_consent', methods: ['POST'])]
    public function resetConsent(): Response
    {
        $response = $this->redirectToRoute('demo_home');
        $expiredAt = new \DateTimeImmutable('1970-01-01');

        foreach ($this->getConsentCookieNames() as $cookieName) {
            $response->headers->setCookie(
                Cookie::create($cookieName)
                    ->withValue('')
                    ->withExpires($expiredAt)
                    ->withPath('/')
                    ->withHttpOnly($this->httpOnly)
                    ->withSameSite(Cookie::SAMESITE_LAX)
            );
        }

        $this->addFlash('success', $this->translator->trans('demo.flash.consent_cleared'));

        return $response;
    }

    /**
     * @return list<string>
     */
    private function getConsentCookieNames(): array
    {
        $names = [
            CookieNameEnum::COOKIE_CONSENT_NAME,
            CookieNameEnum::COOKIE_CONSENT_KEY_NAME,
        ];

        foreach ($this->cookieCategories as $category) {
            $names[] = CookieNameEnum::getCookieCategoryName((string) $category);
        }

        return $names;
    }
}
