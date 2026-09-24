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
use Nowo\CookieConsentBundle\Http\ColdStartRequestAttributes;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigTranslationRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

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

        $subscriber = new CookieConsentConfigTranslationSubscriber($resolver);
        $request    = Request::create('/');
        $request->attributes->set('_route', 'home');

        $subscriber->onKernelRequest($this->createRequestEvent($request));

        self::assertNull($request->attributes->get('nowo_cookie_consent_config'));
    }

    public function testStoresResolvedConfigWithTranslationMessages(): void
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

        $subscriber = new CookieConsentConfigTranslationSubscriber($resolver);
        $request    = Request::create('/');
        $request->setLocale('en');
        $request->attributes->set('_route', 'home');

        $subscriber->onKernelRequest($this->createRequestEvent($request));

        $resolved = $request->attributes->get('nowo_cookie_consent_config');
        self::assertInstanceOf(ResolvedCookieConsentConfig::class, $resolved);
        self::assertSame('Title', $resolved->getTranslationMessages()['nowo_cookie_consent.title']);
    }

    public function testConsecutiveRequestsGetTheirOwnLocaleCopy(): void
    {
        $config = new CookieConsentConfig();

        $configRepository = $this->createMock(CookieConsentConfigRepository::class);
        $configRepository->method('findAllEnabledNonDefault')->willReturn([]);
        $configRepository->method('findDefaultEnabled')->willReturn($config);

        $translationRepository = $this->createMock(CookieConsentConfigTranslationRepository::class);
        $translationRepository->method('findOneForConfigAndLocale')->willReturnCallback(
            static fn (CookieConsentConfig $config, string $locale): CookieConsentConfigTranslation => (new CookieConsentConfigTranslation())
                ->setConsentModalTitle('Title ' . $locale),
        );

        $subscriber = new CookieConsentConfigTranslationSubscriber(new CookieConsentConfigResolver(
            new CookieConsentConfigSelector($configRepository, new CookieConsentRoutePatternMatcher()),
            $translationRepository,
            true,
        ));

        $titles = [];

        foreach (['en', 'es'] as $locale) {
            $request = Request::create('/');
            $request->setLocale($locale);
            $request->attributes->set('_route', 'home');

            $subscriber->onKernelRequest($this->createRequestEvent($request));

            $resolved = $request->attributes->get('nowo_cookie_consent_config');
            self::assertInstanceOf(ResolvedCookieConsentConfig::class, $resolved);
            $titles[] = $resolved->getTranslationMessages()['nowo_cookie_consent.title'];
        }

        self::assertSame(['Title en', 'Title es'], $titles);
    }

    public function testSkipsWhenSiteBackupSchemaDoesNotExist(): void
    {
        $configRepository = $this->createMock(CookieConsentConfigRepository::class);
        $configRepository->expects(self::never())->method('findDefaultEnabled');

        $resolver = new CookieConsentConfigResolver(
            new CookieConsentConfigSelector($configRepository, new CookieConsentRoutePatternMatcher()),
            $this->createMock(CookieConsentConfigTranslationRepository::class),
            true,
        );

        $subscriber = new CookieConsentConfigTranslationSubscriber($resolver);
        $request    = Request::create('/');
        $request->attributes->set(ColdStartRequestAttributes::SITE_BACKUP_SCHEMA_EXISTS, false);

        $subscriber->onKernelRequest($this->createRequestEvent($request));

        self::assertNull($request->attributes->get('nowo_cookie_consent_config'));
    }

    public function testSkipsWhenCookieConsentSchemaReadyIsFalse(): void
    {
        $configRepository = $this->createMock(CookieConsentConfigRepository::class);
        $configRepository->expects(self::never())->method('findDefaultEnabled');

        $resolver = new CookieConsentConfigResolver(
            new CookieConsentConfigSelector($configRepository, new CookieConsentRoutePatternMatcher()),
            $this->createMock(CookieConsentConfigTranslationRepository::class),
            true,
        );

        $subscriber = new CookieConsentConfigTranslationSubscriber($resolver);
        $request    = Request::create('/');
        $request->attributes->set(ColdStartRequestAttributes::COOKIE_CONSENT_SCHEMA_READY, false);

        $subscriber->onKernelRequest($this->createRequestEvent($request));

        self::assertNull($request->attributes->get('nowo_cookie_consent_config'));
    }

    public function testIgnoresSubRequests(): void
    {
        $configRepository = $this->createMock(CookieConsentConfigRepository::class);
        $configRepository->expects(self::never())->method('findDefaultEnabled');

        $resolver = new CookieConsentConfigResolver(
            new CookieConsentConfigSelector($configRepository, new CookieConsentRoutePatternMatcher()),
            $this->createMock(CookieConsentConfigTranslationRepository::class),
            true,
        );

        $subscriber = new CookieConsentConfigTranslationSubscriber($resolver);
        $request    = Request::create('/');
        $request->attributes->set(ColdStartRequestAttributes::SITE_BACKUP_SCHEMA_EXISTS, false);

        $subscriber->onKernelRequest(new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::SUB_REQUEST,
        ));

        self::assertNull($request->attributes->get('nowo_cookie_consent_config'));
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
