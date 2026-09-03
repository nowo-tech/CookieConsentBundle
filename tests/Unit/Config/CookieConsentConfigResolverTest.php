<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Config;

use Nowo\CookieConsentBundle\Config\CookieConsentConfigResolver;
use Nowo\CookieConsentBundle\Config\CookieConsentConfigSelector;
use Nowo\CookieConsentBundle\Config\CookieConsentRoutePatternMatcher;
use Nowo\CookieConsentBundle\Config\ResolvedCookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfigTranslation;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigTranslationRepository;
use PHPUnit\Framework\TestCase;

final class CookieConsentConfigResolverTest extends TestCase
{
    public function testReturnsNullWhenDatabaseConfigDisabled(): void
    {
        $resolver = new CookieConsentConfigResolver(
            new CookieConsentConfigSelector(
                $this->createMock(CookieConsentConfigRepository::class),
                new CookieConsentRoutePatternMatcher(),
            ),
            $this->createMock(CookieConsentConfigTranslationRepository::class),
            false,
        );

        self::assertNull($resolver->resolve('en', 'home'));
    }

    public function testReturnsNullWhenNoConfigMatches(): void
    {
        $configRepository = $this->createMock(CookieConsentConfigRepository::class);
        $configRepository->method('findAllEnabledNonDefault')->willReturn([]);
        $configRepository->method('findDefaultEnabled')->willReturn(null);

        $resolver = new CookieConsentConfigResolver(
            new CookieConsentConfigSelector($configRepository, new CookieConsentRoutePatternMatcher()),
            $this->createMock(CookieConsentConfigTranslationRepository::class),
            true,
        );

        self::assertNull($resolver->resolve('en', 'home'));
    }

    public function testResolvesConfigAndTranslation(): void
    {
        $config      = (new CookieConsentConfig())->setRoutePatterns(['admin']);
        $translation = (new CookieConsentConfigTranslation())->setLocale('es');

        $configRepository = $this->createMock(CookieConsentConfigRepository::class);
        $configRepository->method('findAllEnabledNonDefault')->willReturn([$config]);

        $translationRepository = $this->createMock(CookieConsentConfigTranslationRepository::class);
        $translationRepository->method('findOneForConfigAndLocale')->with($config, 'es')->willReturn($translation);

        $resolver = new CookieConsentConfigResolver(
            new CookieConsentConfigSelector($configRepository, new CookieConsentRoutePatternMatcher()),
            $translationRepository,
            true,
        );

        $resolved = $resolver->resolve('es', 'admin');

        self::assertInstanceOf(ResolvedCookieConsentConfig::class, $resolved);
        self::assertSame($config, $resolved->getConfig());
        self::assertSame($translation, $resolved->getTranslation());
    }

    public function testResolveIsMemoizedPerLocaleAndRoute(): void
    {
        $config = (new CookieConsentConfig())
            ->setEnabled(true)
            ->setDefault(true);

        $translation = (new CookieConsentConfigTranslation())->setLocale('es');

        $configRepository = $this->createMock(CookieConsentConfigRepository::class);
        $configRepository->expects(self::exactly(2))->method('findAllEnabledNonDefault')->willReturn([]);
        $configRepository->expects(self::exactly(2))->method('findDefaultEnabled')->willReturn($config);

        $translationRepository = $this->createMock(CookieConsentConfigTranslationRepository::class);
        $translationRepository->expects(self::exactly(2))
            ->method('findOneForConfigAndLocale')
            ->with($config, 'es')
            ->willReturn($translation);

        $resolver = new CookieConsentConfigResolver(
            new CookieConsentConfigSelector($configRepository, new CookieConsentRoutePatternMatcher()),
            $translationRepository,
            true,
        );

        $first = $resolver->resolve('es', 'home');
        self::assertNotNull($first);
        self::assertSame($first, $resolver->resolve('es', 'home'));

        $resolver->clearRuntimeCache();

        self::assertNotNull($resolver->resolve('es', 'home'));
    }

    public function testResetClearsMemoizedResolve(): void
    {
        $config = (new CookieConsentConfig())
            ->setEnabled(true)
            ->setDefault(true);

        $translation = (new CookieConsentConfigTranslation())->setLocale('es');

        $configRepository = $this->createMock(CookieConsentConfigRepository::class);
        $configRepository->expects(self::exactly(2))->method('findAllEnabledNonDefault')->willReturn([]);
        $configRepository->expects(self::exactly(2))->method('findDefaultEnabled')->willReturn($config);

        $translationRepository = $this->createMock(CookieConsentConfigTranslationRepository::class);
        $translationRepository->expects(self::exactly(2))
            ->method('findOneForConfigAndLocale')
            ->with($config, 'es')
            ->willReturn($translation);

        $resolver = new CookieConsentConfigResolver(
            new CookieConsentConfigSelector($configRepository, new CookieConsentRoutePatternMatcher()),
            $translationRepository,
            true,
        );

        self::assertNotNull($resolver->resolve('es', 'home'));
        $resolver->reset();
        self::assertNotNull($resolver->resolve('es', 'home'));
    }
}
