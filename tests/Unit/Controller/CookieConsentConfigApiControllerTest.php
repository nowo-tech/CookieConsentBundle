<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Controller;

use Nowo\CookieConsentBundle\Config\CookieConsentConfigPayloadFactory;
use Nowo\CookieConsentBundle\Config\CookieConsentConfigResolver;
use Nowo\CookieConsentBundle\Config\CookieConsentConfigSelector;
use Nowo\CookieConsentBundle\Config\CookieConsentRoutePatternMatcher;
use Nowo\CookieConsentBundle\Controller\CookieConsentConfigApiController;
use Nowo\CookieConsentBundle\Locale\LocaleResolver;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigTranslationRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

final class CookieConsentConfigApiControllerTest extends TestCase
{
    public function testGetConfigUsesQueryLocaleAndRoute(): void
    {
        $controller = new CookieConsentConfigApiController(
            $this->createPayloadFactory(),
            new LocaleResolver(['en', 'fr'], 'en', false, new RequestStack()),
        );

        $request  = Request::create('/cookie-consent/config?locale=fr&route=admin');
        $response = $controller->getConfig($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(300, $response->getMaxAge());
        self::assertSame('fr', json_decode((string) $response->getContent(), true)['data']['language']['default']);
    }

    public function testGetLocalizedConfigSetsRequestLocale(): void
    {
        $configRepository = $this->createMock(CookieConsentConfigRepository::class);
        $configRepository->method('findDefaultEnabled')->willReturn(null);
        $configRepository->method('findAllEnabledNonDefault')->willReturn([]);

        $resolver = new CookieConsentConfigResolver(
            new CookieConsentConfigSelector($configRepository, new CookieConsentRoutePatternMatcher()),
            $this->createMock(CookieConsentConfigTranslationRepository::class),
            true,
        );

        $controller = new CookieConsentConfigApiController(
            new CookieConsentConfigPayloadFactory($resolver, $this->createTranslator(), ['analytics']),
            new LocaleResolver(['en', 'de'], 'en', false, new RequestStack()),
        );

        $request  = Request::create('/de/cookie-consent/config');
        $response = $controller->getLocalizedConfig('de', $request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('de', $request->getLocale());
    }

    public function testResolveLocaleFromRequestWhenQueryEmpty(): void
    {
        $controller = new CookieConsentConfigApiController(
            $this->createPayloadFactory(),
            new LocaleResolver(['en', 'es'], 'en', false, new RequestStack()),
        );

        $request = Request::create('/cookie-consent/config');
        $request->setLocale('es');

        $response = $controller->getConfig($request);

        self::assertSame('es', json_decode((string) $response->getContent(), true)['data']['language']['default']);
    }

    public function testResolveLocaleUsesLocaleResolverWhenRequestLocaleEmpty(): void
    {
        $factory = $this->createPayloadFactory();
        $stack   = new RequestStack();
        $main    = Request::create('/');
        $main->setLocale('fr');
        $stack->push($main);
        $subRequest = $main->duplicate();
        $subRequest->setLocale('');
        $stack->push($subRequest);

        $controller = new CookieConsentConfigApiController(
            $factory,
            new LocaleResolver(['en', 'fr'], 'en', false, $stack),
        );

        $response = $controller->getConfig($subRequest);

        self::assertSame('fr', json_decode((string) $response->getContent(), true)['data']['language']['default']);
    }

    private function createPayloadFactory(): CookieConsentConfigPayloadFactory
    {
        $resolver = new CookieConsentConfigResolver(
            new CookieConsentConfigSelector(
                $this->createMock(CookieConsentConfigRepository::class),
                new CookieConsentRoutePatternMatcher(),
            ),
            $this->createMock(CookieConsentConfigTranslationRepository::class),
            false,
        );

        return new CookieConsentConfigPayloadFactory($resolver, $this->createTranslator(), ['analytics']);
    }

    private function createTranslator(): TranslatorInterface
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return $translator;
    }
}
