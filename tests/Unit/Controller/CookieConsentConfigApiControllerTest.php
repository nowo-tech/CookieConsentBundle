<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Controller;

use Nowo\CookieConsentBundle\Config\CookieConsentConfigPayloadFactory;
use Nowo\CookieConsentBundle\Config\CookieConsentConfigResolver;
use Nowo\CookieConsentBundle\Config\CookieConsentConfigSelector;
use Nowo\CookieConsentBundle\Config\CookieConsentRoutePatternMatcher;
use Nowo\CookieConsentBundle\Config\CookieInventoryProvider;
use Nowo\CookieConsentBundle\Controller\CookieConsentConfigApiController;
use Nowo\CookieConsentBundle\Locale\LocaleResolver;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigTranslationRepository;
use Nowo\CookieConsentBundle\Repository\CookieDefinitionRepository;
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
            new CookieConsentConfigPayloadFactory($resolver, $this->createTranslator(), $this->createInventoryProvider(), ['analytics']),
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

    public function testUnknownQueryLocalesAreNormalizedBeforeReachingTheTranslator(): void
    {
        $requestedLocales = [];
        $translator       = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static function (string $id, array $parameters, ?string $domain, ?string $locale) use (&$requestedLocales): string {
                $requestedLocales[(string) $locale] = true;

                return $id;
            },
        );

        $controller = new CookieConsentConfigApiController(
            $this->createPayloadFactory($translator),
            new LocaleResolver(['en', 'fr'], 'en', false, new RequestStack()),
        );

        foreach (['zz', 'qq-QQ', 'fr-CA'] as $locale) {
            $response = $controller->getConfig(Request::create('/cookie-consent/config?locale=' . $locale));
            $default  = json_decode((string) $response->getContent(), true)['data']['language']['default'];

            self::assertContains($default, ['en', 'fr']);
        }

        self::assertSame(['en', 'fr'], array_keys($requestedLocales));
    }

    public function testUnknownRequestLocaleIsNormalized(): void
    {
        $controller = new CookieConsentConfigApiController(
            $this->createPayloadFactory(),
            new LocaleResolver(['en', 'es'], 'en', false, new RequestStack()),
        );

        $request = Request::create('/cookie-consent/config');
        $request->setLocale('xx');

        $response = $controller->getConfig($request);

        self::assertSame('en', json_decode((string) $response->getContent(), true)['data']['language']['default']);
    }

    public function testGetLocalizedConfigNormalizesDisabledLocale(): void
    {
        $controller = new CookieConsentConfigApiController(
            $this->createPayloadFactory(),
            new LocaleResolver(['en', 'de'], 'en', false, new RequestStack()),
        );

        $request  = Request::create('/zz/cookie-consent/config');
        $response = $controller->getLocalizedConfig('zz', $request);

        self::assertSame('en', $request->getLocale());
        self::assertSame('en', json_decode((string) $response->getContent(), true)['data']['language']['default']);
    }

    private function createPayloadFactory(?TranslatorInterface $translator = null): CookieConsentConfigPayloadFactory
    {
        $resolver = new CookieConsentConfigResolver(
            new CookieConsentConfigSelector(
                $this->createMock(CookieConsentConfigRepository::class),
                new CookieConsentRoutePatternMatcher(),
            ),
            $this->createMock(CookieConsentConfigTranslationRepository::class),
            false,
        );

        return new CookieConsentConfigPayloadFactory($resolver, $translator ?? $this->createTranslator(), $this->createInventoryProvider(), ['analytics']);
    }

    private function createInventoryProvider(): CookieInventoryProvider
    {
        return new CookieInventoryProvider($this->createMock(CookieDefinitionRepository::class), false, []);
    }

    private function createTranslator(): TranslatorInterface
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return $translator;
    }
}
