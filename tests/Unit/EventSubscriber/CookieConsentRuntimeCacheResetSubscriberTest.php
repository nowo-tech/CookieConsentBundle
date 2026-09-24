<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\EventSubscriber;

use Nowo\CookieConsentBundle\Config\CookieConsentConfigResolver;
use Nowo\CookieConsentBundle\Config\CookieConsentConfigSelector;
use Nowo\CookieConsentBundle\Config\CookieConsentRoutePatternMatcher;
use Nowo\CookieConsentBundle\Config\CookieInventoryProvider;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfigTranslation;
use Nowo\CookieConsentBundle\EventSubscriber\CookieConsentRuntimeCacheResetSubscriber;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigTranslationRepository;
use Nowo\CookieConsentBundle\Repository\CookieDefinitionRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class CookieConsentRuntimeCacheResetSubscriberTest extends TestCase
{
    public function testSubscribesEarlyToKernelRequest(): void
    {
        self::assertSame(
            [KernelEvents::REQUEST => ['onKernelRequest', 4096]],
            CookieConsentRuntimeCacheResetSubscriber::getSubscribedEvents(),
        );
    }

    public function testConsecutiveMainRequestsSeeFreshDatabaseCopyWithoutKernelReset(): void
    {
        $config = (new CookieConsentConfig())->setEnabled(true)->setDefault(true);

        $configRepository = $this->createMock(CookieConsentConfigRepository::class);
        $configRepository->method('findDefaultEnabled')->willReturn($config);
        $configRepository->method('findAllEnabledNonDefault')->willReturn([]);
        $configRepository->expects(self::exactly(2))->method('reset');

        $translationRepository = $this->createMock(CookieConsentConfigTranslationRepository::class);
        $translationRepository->method('findOneForConfigAndLocale')->willReturnOnConsecutiveCalls(
            (new CookieConsentConfigTranslation())->setConsentModalTitle('Before admin edit'),
            (new CookieConsentConfigTranslation())->setConsentModalTitle('After admin edit'),
        );

        $definitionRepository = $this->createMock(CookieDefinitionRepository::class);
        $definitionRepository->expects(self::exactly(2))->method('findByConfigOrdered')->willReturn([]);

        $resolver = new CookieConsentConfigResolver(
            new CookieConsentConfigSelector($configRepository, new CookieConsentRoutePatternMatcher()),
            $translationRepository,
            true,
        );
        $inventoryProvider = new CookieInventoryProvider($definitionRepository, true, []);
        $subscriber        = new CookieConsentRuntimeCacheResetSubscriber($resolver, $inventoryProvider, $configRepository);

        $subscriber->onKernelRequest($this->createEvent(HttpKernelInterface::MAIN_REQUEST));
        self::assertSame('Before admin edit', $this->resolveTitle($resolver));
        self::assertSame('Before admin edit', $this->resolveTitle($resolver));
        $inventoryProvider->listForLocale($config, 'en');
        $inventoryProvider->listForLocale($config, 'en');

        $subscriber->onKernelRequest($this->createEvent(HttpKernelInterface::MAIN_REQUEST));
        self::assertSame('After admin edit', $this->resolveTitle($resolver));
        $inventoryProvider->listForLocale($config, 'en');
    }

    public function testSubRequestsKeepTheCachesOfTheirMainRequest(): void
    {
        $configRepository = $this->createMock(CookieConsentConfigRepository::class);
        $configRepository->expects(self::never())->method('reset');

        $subscriber = new CookieConsentRuntimeCacheResetSubscriber(
            new CookieConsentConfigResolver(
                new CookieConsentConfigSelector($configRepository, new CookieConsentRoutePatternMatcher()),
                $this->createMock(CookieConsentConfigTranslationRepository::class),
                true,
            ),
            new CookieInventoryProvider($this->createMock(CookieDefinitionRepository::class), true, []),
            $configRepository,
        );

        $subscriber->onKernelRequest($this->createEvent(HttpKernelInterface::SUB_REQUEST));
    }

    private function resolveTitle(CookieConsentConfigResolver $resolver): ?string
    {
        return $resolver->resolve('en', 'home')?->getTranslation()?->getConsentModalTitle();
    }

    private function createEvent(int $requestType): RequestEvent
    {
        return new RequestEvent($this->createMock(HttpKernelInterface::class), Request::create('/'), $requestType);
    }
}
