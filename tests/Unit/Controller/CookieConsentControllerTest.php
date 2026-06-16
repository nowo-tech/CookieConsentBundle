<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Controller;

use Nowo\CookieConsentBundle\Config\CookieConsentConfigResolver;
use Nowo\CookieConsentBundle\Config\CookieConsentConfigSelector;
use Nowo\CookieConsentBundle\Config\CookieConsentRoutePatternMatcher;
use Nowo\CookieConsentBundle\Controller\CookieConsentController;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfigTranslation;
use Nowo\CookieConsentBundle\Form\CookieConsentType;
use Nowo\CookieConsentBundle\Locale\LocaleResolver;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigTranslationRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final class CookieConsentControllerTest extends TestCase
{
    public function testShowRendersBootstrapTemplate(): void
    {
        $controller = $this->createController();
        $request    = Request::create('/cookie_consent');
        $request->headers->set('Accept-Language', 'es');

        $response = $controller->show($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('rendered-bootstrap', (string) $response->getContent());
        self::assertTrue($response->headers->has('Cache-Control'));
    }

    public function testShowUsesTailwindTemplate(): void
    {
        $controller = $this->createController(uiTheme: 'tailwind');
        $response   = $controller->show(Request::create('/cookie_consent'));

        self::assertStringContainsString('rendered-tailwind', (string) $response->getContent());
    }

    public function testShowIfNotSetDelegatesToShow(): void
    {
        $controller = $this->createController();
        $response   = $controller->showIfCookieConsentNotSet(Request::create('/cookie_consent_alt'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('rendered-bootstrap', (string) $response->getContent());
    }

    public function testShowIfNotSetDelegatesToShowWhenConsentMissing(): void
    {
        $controller = $this->createController();
        $response   = $controller->showIfCookieConsentNotSet(Request::create('/cookie_consent_alt'));

        self::assertStringContainsString('rendered-bootstrap', (string) $response->getContent());
    }

    public function testShowAppliesDatabaseTranslationsAndConfigApiUrl(): void
    {
        $config      = new CookieConsentConfig();
        $translation = (new CookieConsentConfigTranslation())
            ->setConsentModalTitle('DB')
            ->setConsentModalDescription('Intro')
            ->setConsentModalAcceptAllBtn('All')
            ->setConsentModalAcceptNecessaryBtn('Necessary');

        $configRepository = $this->createMock(CookieConsentConfigRepository::class);
        $configRepository->method('findAllEnabledNonDefault')->willReturn([]);
        $configRepository->method('findDefaultEnabled')->willReturn($config);

        $translationRepository = $this->createMock(CookieConsentConfigTranslationRepository::class);
        $translationRepository->method('findOneForConfigAndLocale')->willReturn($translation);

        $resolver = new CookieConsentConfigResolver(
            new CookieConsentConfigSelector($configRepository, new CookieConsentRoutePatternMatcher()),
            $translationRepository,
            true,
        );

        $translator = new \Symfony\Component\Translation\Translator('en');
        $translator->addLoader('array', new \Symfony\Component\Translation\Loader\ArrayLoader());

        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturn('/en/cookie-consent/config');

        $mainRequest = Request::create('/page');
        $mainRequest->attributes->set('_route', 'home');
        $mainRequest->setLocale('en');

        $stack = new RequestStack();
        $stack->push($mainRequest);

        $controller = $this->createController(
            router: $router,
            requestStack: $stack,
            configResolver: $resolver,
            translator: $translator,
            fetchConfigViaApi: true,
        );

        $response = $controller->show(Request::create('/cookie_consent'));

        self::assertStringContainsString('rendered-bootstrap', (string) $response->getContent());
        self::assertSame('DB', $translator->trans('nowo_cookie_consent.title', [], 'NowoCookieConsentBundle', 'en'));
    }

    public function testConfigApiUrlFallsBackWhenLocalizedRouteMissing(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')
            ->willReturnCallback(static function (string $name): string {
                if ($name === 'nowo_cookie_consent.config_localized') {
                    throw new RouteNotFoundException();
                }

                return '/cookie-consent/config?locale=en';
            });

        $mainRequest = Request::create('/page');
        $mainRequest->attributes->set('_route', 'home');

        $stack = new RequestStack();
        $stack->push($mainRequest);

        $controller = $this->createController(
            router: $router,
            requestStack: $stack,
            fetchConfigViaApi: true,
        );

        $response = $controller->show(Request::create('/cookie_consent'));

        self::assertStringContainsString('rendered-bootstrap', (string) $response->getContent());
    }

    public function testFormUsesCustomActionWhenConfigured(): void
    {
        $form = $this->createMock(FormInterface::class);
        $form->method('createView')->willReturn(new FormView());

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->expects(self::once())
            ->method('create')
            ->with(CookieConsentType::class, null, ['action' => '/custom-action'])
            ->willReturn($form);

        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->with('custom_submit')->willReturn('/custom-action');

        $controller = $this->createController(formFactory: $formFactory, router: $router, formAction: 'custom_submit');
        $controller->show(Request::create('/cookie_consent'));
    }

    private function createController(
        ?Environment $twig = null,
        ?FormFactoryInterface $formFactory = null,
        ?RouterInterface $router = null,
        ?RequestStack $requestStack = null,
        ?CookieConsentConfigResolver $configResolver = null,
        ?TranslatorInterface $translator = null,
        bool $fetchConfigViaApi = false,
        string $uiTheme = 'bootstrap',
        ?string $formAction = null,
    ): CookieConsentController {
        $twig ??= $this->createTwig();
        $formFactory ??= $this->createFormFactory();
        $router ??= $this->createMock(RouterInterface::class);
        $requestStack ??= new RequestStack();
        $configResolver ??= new CookieConsentConfigResolver(
            new CookieConsentConfigSelector(
                $this->createMock(CookieConsentConfigRepository::class),
                new CookieConsentRoutePatternMatcher(),
            ),
            $this->createMock(CookieConsentConfigTranslationRepository::class),
            false,
        );
        $translator ??= $this->createMock(TranslatorInterface::class);

        return new CookieConsentController(
            $twig,
            $formFactory,
            $router,
            new LocaleResolver(['en', 'es'], 'en', true, $requestStack),
            $requestStack,
            $configResolver,
            $translator,
            $fetchConfigViaApi,
            $uiTheme,
            $formAction,
            ['privacy'],
        );
    }

    private function createTwig(): Environment
    {
        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturnCallback(
            static fn (string $template): string => str_contains($template, 'tailwind')
                ? 'rendered-tailwind'
                : 'rendered-bootstrap',
        );

        return $twig;
    }

    private function createFormFactory(): FormFactoryInterface
    {
        $form = $this->createMock(FormInterface::class);
        $form->method('createView')->willReturn(new FormView());

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        return $formFactory;
    }
}
