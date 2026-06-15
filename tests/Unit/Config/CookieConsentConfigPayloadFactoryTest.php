<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Config;

use Nowo\CookieConsentBundle\Config\CookieConsentConfigPayloadFactory;
use Nowo\CookieConsentBundle\Config\CookieConsentConfigResolver;
use Nowo\CookieConsentBundle\Config\CookieConsentConfigSelector;
use Nowo\CookieConsentBundle\Config\CookieConsentRoutePatternMatcher;
use Nowo\CookieConsentBundle\Config\CookieInventoryProvider;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfigTranslation;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigTranslationRepository;
use Nowo\CookieConsentBundle\Repository\CookieDefinitionRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class CookieConsentConfigPayloadFactoryTest extends TestCase
{
    public function testBuildUsesDatabaseTranslationWhenAvailable(): void
    {
        $config = (new CookieConsentConfig())
            ->setAutoShow(false)
            ->setConsentModalLayout('bar')
            ->setConsentModalPositionY('bottom')
            ->setConsentModalPositionX(null);

        $translation = (new CookieConsentConfigTranslation())
            ->setConsentModalTitle('DB title')
            ->setConsentModalDescription('DB intro')
            ->setConsentModalAcceptAllBtn('All')
            ->setConsentModalAcceptNecessaryBtn('Necessary')
            ->setConsentModalFooter('Read more')
            ->setPreferencesModalSavePreferencesBtn('Save');

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

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $factory = new CookieConsentConfigPayloadFactory($resolver, $translator, $this->createInventoryProvider(), ['analytics']);
        $payload = $factory->build('en');

        self::assertSame(200, $payload['code']);
        self::assertFalse($payload['data']['autoShow']);
        self::assertSame('bar', $payload['data']['guiOptions']['consentModal']['layout']);
        self::assertSame('DB title', $payload['data']['language']['translations']['en']['consentModal']['title']);
        self::assertSame('analytics', array_key_last($payload['data']['categories']));
    }

    public function testBuildFallsBackToTranslatorWhenNoDatabaseConfig(): void
    {
        $resolver = new CookieConsentConfigResolver(
            new CookieConsentConfigSelector(
                $this->createMock(CookieConsentConfigRepository::class),
                new CookieConsentRoutePatternMatcher(),
            ),
            $this->createMock(CookieConsentConfigTranslationRepository::class),
            false,
        );

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturn('fallback');

        $factory = new CookieConsentConfigPayloadFactory($resolver, $translator, $this->createInventoryProvider(), ['analytics', 'marketing']);
        $payload = $factory->build('en', 'home');

        self::assertTrue($payload['data']['autoShow']);
        self::assertSame('fallback', $payload['data']['language']['translations']['en']['consentModal']['title']);
        self::assertArrayHasKey('marketing', $payload['data']['categories']);
    }

    private function createInventoryProvider(): CookieInventoryProvider
    {
        return new CookieInventoryProvider($this->createMock(CookieDefinitionRepository::class), false, []);
    }
}
