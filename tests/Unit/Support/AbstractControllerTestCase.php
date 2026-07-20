<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

abstract class AbstractControllerTestCase extends TestCase
{
    protected function configureController(
        AbstractController $controller,
        ?Environment $twig = null,
        ?FormFactoryInterface $formFactory = null,
        ?RouterInterface $router = null,
        ?object $csrfTokenManager = null,
        ?RequestStack $requestStack = null,
    ): void {
        $container = new Container();

        $twig ??= $this->createTwig();
        $container->set('twig', $twig);

        $formFactory ??= $this->createFormFactory();
        $container->set('form.factory', $formFactory);

        $router ??= $this->createRouter();
        $container->set('router', $router);

        $csrfTokenManager ??= $this->createCsrfTokenManager();
        $container->set('security.csrf.token_manager', $csrfTokenManager);

        $requestStack ??= $this->createRequestStackWithSession();
        $container->set('request_stack', $requestStack);

        $controller->setContainer($container);
    }

    protected function createTwig(): Environment
    {
        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturn('rendered');

        return $twig;
    }

    protected function createFormFactory(): FormFactoryInterface
    {
        $form = $this->createMock(FormInterface::class);
        $form->method('createView')->willReturn(new FormView());
        $form->method('isSubmitted')->willReturn(false);
        $form->method('isValid')->willReturn(false);

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        return $formFactory;
    }

    protected function createRouter(): RouterInterface
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturn('/redirect');

        return $router;
    }

    protected function createCsrfTokenManager(bool $valid = true): object
    {
        return new class($valid) {
            public function __construct(private readonly bool $valid)
            {
            }

            public function isTokenValid(mixed $token): bool
            {
                return $this->valid;
            }
        };
    }

    protected function createRequestStackWithSession(): RequestStack
    {
        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/');
        $request->setSession($session);

        $stack = new RequestStack();
        $stack->push($request);

        return $stack;
    }
}
