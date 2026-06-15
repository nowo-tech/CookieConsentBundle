<?php

declare(strict_types=1);

namespace App\Controller;

use App\Demo\DemoLocale;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RedirectController extends AbstractController
{
    #[Route('/', name: 'demo_root')]
    public function root(): Response
    {
        return $this->redirectToRoute('demo_home', ['_locale' => DemoLocale::DEFAULT]);
    }
}
