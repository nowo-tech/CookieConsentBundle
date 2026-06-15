<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Entity;

use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfigTranslation;
use PHPUnit\Framework\TestCase;

final class CookieConsentConfigTranslationTest extends TestCase
{
    public function testToTranslationMessagesMapsBundleKeys(): void
    {
        $translation = (new CookieConsentConfigTranslation())
            ->setConsentModalTitle('Title')
            ->setConsentModalDescription('Intro')
            ->setConsentModalFooter('Read more')
            ->setConsentModalAcceptAllBtn('All')
            ->setConsentModalAcceptNecessaryBtn('Necessary')
            ->setPreferencesModalSavePreferencesBtn('Save')
            ->setPrivacyRoute('privacy');

        self::assertSame([
            'nowo_cookie_consent.title'                       => 'Title',
            'nowo_cookie_consent.intro'                       => 'Intro',
            'nowo_cookie_consent.use_all_cookies'             => 'All',
            'nowo_cookie_consent.use_only_functional_cookies' => 'Necessary',
            'nowo_cookie_consent.privacy_route'               => 'privacy',
            'nowo_cookie_consent.read_more'                   => 'Read more',
            'nowo_cookie_consent.save'                        => 'Save',
        ], $translation->toTranslationMessages());
    }

    public function testToTranslationMessagesOmitsOptionalEmptyFields(): void
    {
        $translation = (new CookieConsentConfigTranslation())
            ->setConsentModalTitle('Title')
            ->setConsentModalDescription('Intro')
            ->setConsentModalAcceptAllBtn('All')
            ->setConsentModalAcceptNecessaryBtn('Necessary');

        self::assertArrayNotHasKey('nowo_cookie_consent.read_more', $translation->toTranslationMessages());
        self::assertArrayNotHasKey('nowo_cookie_consent.save', $translation->toTranslationMessages());
    }

    public function testAccessorsRoundTrip(): void
    {
        $config      = new CookieConsentConfig();
        $translation = (new CookieConsentConfigTranslation())
            ->setLocale('fr')
            ->setConsentModalLabel('Label')
            ->setConsentModalTitle('Title')
            ->setConsentModalDescription('Intro')
            ->setConsentModalAcceptAllBtn('All')
            ->setConsentModalAcceptNecessaryBtn('Necessary')
            ->setConsentModalShowPreferencesBtn('Prefs')
            ->setConsentModalFooter('Footer')
            ->setPreferencesModalTitle('Prefs title')
            ->setPreferencesModalAcceptAllBtn('All prefs')
            ->setPreferencesModalAcceptNecessaryBtn('Necessary prefs')
            ->setPreferencesModalSavePreferencesBtn('Save')
            ->setPreferencesModalCloseIconLabel('Close')
            ->setPrivacyRoute('privacy')
            ->setConfig($config);

        self::assertNull($translation->getId());
        self::assertSame('fr', $translation->getLocale());
        self::assertSame('Label', $translation->getConsentModalLabel());
        self::assertSame('Title', $translation->getConsentModalTitle());
        self::assertSame('Intro', $translation->getConsentModalDescription());
        self::assertSame('All', $translation->getConsentModalAcceptAllBtn());
        self::assertSame('Necessary', $translation->getConsentModalAcceptNecessaryBtn());
        self::assertSame('Prefs', $translation->getConsentModalShowPreferencesBtn());
        self::assertSame('Footer', $translation->getConsentModalFooter());
        self::assertSame('Prefs title', $translation->getPreferencesModalTitle());
        self::assertSame('All prefs', $translation->getPreferencesModalAcceptAllBtn());
        self::assertSame('Necessary prefs', $translation->getPreferencesModalAcceptNecessaryBtn());
        self::assertSame('Save', $translation->getPreferencesModalSavePreferencesBtn());
        self::assertSame('Close', $translation->getPreferencesModalCloseIconLabel());
        self::assertSame('privacy', $translation->getPrivacyRoute());
        self::assertSame($config, $translation->getConfig());
    }
}
