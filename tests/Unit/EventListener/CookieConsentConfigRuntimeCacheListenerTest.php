<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Nowo\CookieConsentBundle\Config\CookieConsentConfigResolver;
use Nowo\CookieConsentBundle\Config\CookieConsentConfigSelector;
use Nowo\CookieConsentBundle\Config\CookieConsentRoutePatternMatcher;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\EventListener\CookieConsentConfigRuntimeCacheListener;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigTranslationRepository;
use PHPUnit\Framework\TestCase;
use stdClass;

final class CookieConsentConfigRuntimeCacheListenerTest extends TestCase
{
    public function testInvalidateWhenConfigChangedClearsRuntimeCaches(): void
    {
        $config = (new CookieConsentConfig())->setEnabled(true)->setDefault(true);

        $configRepository = $this->createMock(CookieConsentConfigRepository::class);
        $configRepository->expects(self::once())->method('clearRuntimeCache');
        $configRepository->expects(self::exactly(2))->method('findDefaultEnabled')->willReturn($config);
        $configRepository->method('findAllEnabledNonDefault')->willReturn([]);

        $translationRepository = $this->createMock(CookieConsentConfigTranslationRepository::class);
        $translationRepository->method('findOneForConfigAndLocale')->willReturn(null);

        $resolver = new CookieConsentConfigResolver(
            new CookieConsentConfigSelector($configRepository, new CookieConsentRoutePatternMatcher()),
            $translationRepository,
            true,
        );

        $listener = new CookieConsentConfigRuntimeCacheListener($configRepository, $resolver);

        $resolver->resolve('en', 'home');
        $listener->invalidateWhenConfigChanged($config);
        $resolver->resolve('en', 'home');
    }

    public function testInvalidateWhenConfigChangedIgnoresOtherEntities(): void
    {
        $config = (new CookieConsentConfig())->setEnabled(true)->setDefault(true);

        $configRepository = $this->createMock(CookieConsentConfigRepository::class);
        $configRepository->expects(self::never())->method('clearRuntimeCache');
        $configRepository->expects(self::once())->method('findDefaultEnabled')->willReturn($config);
        $configRepository->method('findAllEnabledNonDefault')->willReturn([]);

        $translationRepository = $this->createMock(CookieConsentConfigTranslationRepository::class);
        $translationRepository->method('findOneForConfigAndLocale')->willReturn(null);

        $resolver = new CookieConsentConfigResolver(
            new CookieConsentConfigSelector($configRepository, new CookieConsentRoutePatternMatcher()),
            $translationRepository,
            true,
        );

        $listener = new CookieConsentConfigRuntimeCacheListener($configRepository, $resolver);

        $resolver->resolve('en', 'home');
        $listener->invalidateWhenConfigChanged(new stdClass());
        $resolver->resolve('en', 'home');
    }

    public function testDoctrineLifecycleCallbacksDelegateToInvalidation(): void
    {
        $configRepository = $this->createMock(CookieConsentConfigRepository::class);
        $configRepository->expects(self::exactly(3))->method('clearRuntimeCache');

        $resolver = new CookieConsentConfigResolver(
            new CookieConsentConfigSelector(
                $this->createMock(CookieConsentConfigRepository::class),
                new CookieConsentRoutePatternMatcher(),
            ),
            $this->createMock(CookieConsentConfigTranslationRepository::class),
            true,
        );

        $listener      = new CookieConsentConfigRuntimeCacheListener($configRepository, $resolver);
        $config        = new CookieConsentConfig();
        $objectManager = $this->createMock(EntityManagerInterface::class);

        $listener->postPersist(new PostPersistEventArgs($config, $objectManager));
        $listener->postUpdate(new PostUpdateEventArgs($config, $objectManager));
        $listener->postRemove(new PostRemoveEventArgs($config, $objectManager));
    }
}
