<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\EventSubscriber;

use Nowo\CookieConsentBundle\Config\CookieConsentConfigResolver;
use Nowo\CookieConsentBundle\Config\CookieConsentConfigSelector;
use Nowo\CookieConsentBundle\Config\CookieConsentRoutePatternMatcher;
use Nowo\CookieConsentBundle\Config\ResolvedCookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfigTranslation;
use Nowo\CookieConsentBundle\EventSubscriber\CookieConsentConfigTranslationSubscriber;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigTranslationRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

final class CookieConsentConfigTranslationSubscriberTest extends TestCase
{
    public function testSubscribedEvents(): void
    {
        self::assertSame(
            [KernelEvents::REQUEST => ['onKernelRequest', 19]],
            CookieConsentConfigTranslationSubscriber::getSubscribedEvents(),
        );
    }

    public function testDoesNothingWhenConfigNotResolved(): void
    {
        $configRepository = $this->createMock(CookieConsentConfigRepository::class);
        $configRepository->method('findDefaultEnabled')->willReturn(null);
        $configRepository->method('findAllEnabledNonDefault')->willReturn([]);

        $resolver = new CookieConsentConfigResolver(
            new CookieConsentConfigSelector($configRepository, new CookieConsentRoutePatternMatcher()),
            $this->createMock(CookieConsentConfigTranslationRepository::class),
            true,
        );

        $subscriber = new CookieConsentConfigTranslationSubscriber($resolver, $this->createTranslator());
        $request    = Request::create('/');
        $request->attributes->set('_route', 'home');

        $subscriber->onKernelRequest($this->createRequestEvent($request));

        self::assertNull($request->attributes->get('nowo_cookie_consent_config'));
    }

    public function testAddsTranslationsAndStoresResolvedConfig(): void
    {
        $config      = new CookieConsentConfig();
        $translation = (new CookieConsentConfigTranslation())
            ->setConsentModalTitle('Title')
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

        $translator = $this->createTranslator();
        $subscriber = new CookieConsentConfigTranslationSubscriber($resolver, $translator);
        $request    = Request::create('/');
        $request->setLocale('en');
        $request->attributes->set('_route', 'home');

        $subscriber->onKernelRequest($this->createRequestEvent($request));

        self::assertInstanceOf(ResolvedCookieConsentConfig::class, $request->attributes->get('nowo_cookie_consent_config'));
        self::assertSame('Title', $translator->trans('nowo_cookie_consent.title', [], 'NowoCookieConsentBundle', 'en'));
    }

    private function createTranslator(): Translator
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());

        return $translator;
    }

    private function createRequestEvent(Request $request): RequestEvent
    {
        return new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );
    }
}
