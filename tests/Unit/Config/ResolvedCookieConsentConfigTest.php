<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Config;

use Nowo\CookieConsentBundle\Config\ResolvedCookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfigTranslation;
use PHPUnit\Framework\TestCase;

final class ResolvedCookieConsentConfigTest extends TestCase
{
    public function testShouldAutoShowReflectsConfig(): void
    {
        $config   = (new CookieConsentConfig())->setAutoShow(false);
        $resolved = new ResolvedCookieConsentConfig($config, null);

        self::assertFalse($resolved->shouldAutoShow());
    }

    public function testTranslationMessagesEmptyWithoutTranslation(): void
    {
        $resolved = new ResolvedCookieConsentConfig(new CookieConsentConfig(), null);

        self::assertSame([], $resolved->getTranslationMessages());
    }

    public function testViewCarriesScalarsOnly(): void
    {
        $config = (new CookieConsentConfig())
            ->setName('checkout')
            ->setRevision(3)
            ->setDefault(false)
            ->setPriority(7)
            ->setAutoShow(true)
            ->setRoutePatterns(['app_checkout_*']);
        $translation = (new CookieConsentConfigTranslation())->setLocale('es')->setConsentModalTitle('Título');

        $view = (new ResolvedCookieConsentConfig($config, $translation))->view();

        self::assertNull($view->id);
        self::assertNull((new ResolvedCookieConsentConfig($config, null))->getConfigId());
        self::assertSame(['checkout', 3, false, 7, true, ['app_checkout_*'], 'es'], [$view->name, $view->revision, $view->isDefault, $view->priority, $view->autoShow, $view->routePatterns, $view->locale]);
        self::assertSame('Título', $view->translationMessages['nowo_cookie_consent.title']);
        self::assertNull($view->reconsentRequiredAt);
        self::assertNull((new ResolvedCookieConsentConfig($config, null))->view()->locale);
    }

    public function testTranslationMessagesFromTranslationEntity(): void
    {
        $translation = (new CookieConsentConfigTranslation())
            ->setConsentModalTitle('Title')
            ->setConsentModalDescription('Intro')
            ->setConsentModalAcceptAllBtn('All')
            ->setConsentModalAcceptNecessaryBtn('Necessary');

        $resolved = new ResolvedCookieConsentConfig(new CookieConsentConfig(), $translation);

        self::assertArrayHasKey('nowo_cookie_consent.title', $resolved->getTranslationMessages());
        self::assertSame('Title', $resolved->getTranslationMessages()['nowo_cookie_consent.title']);
    }
}
