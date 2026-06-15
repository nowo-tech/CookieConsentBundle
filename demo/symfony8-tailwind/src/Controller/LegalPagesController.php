<?php

declare(strict_types=1);

namespace App\Controller;

use App\Demo\DemoLocale;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/{_locale}',
    name: 'demo_',
    requirements: ['_locale' => DemoLocale::REQUIREMENT],
    defaults: ['_locale' => DemoLocale::DEFAULT],
)]
final class LegalPagesController extends AbstractController
{
    #[Route('/legal/privacy-policy', name: 'privacy_policy')]
    public function privacyPolicy(): Response
    {
        return $this->render('demo/legal/privacy.html.twig');
    }

    #[Route('/legal/cookie-policy', name: 'cookie_policy')]
    public function cookiePolicy(): Response
    {
        return $this->render('demo/legal/cookies.html.twig');
    }

    #[Route('/legal/consent', name: 'consent_information')]
    public function consentInformation(): Response
    {
        return $this->render('demo/legal/consent.html.twig');
    }
}
