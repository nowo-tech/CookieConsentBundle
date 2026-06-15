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
