<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Entity;

use InvalidArgumentException;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfigTranslation;
use PHPUnit\Framework\TestCase;

final class CookieConsentConfigTest extends TestCase
{
    public function testParseRouteListNormalizesInput(): void
    {
        self::assertSame(['home', 'admin'], CookieConsentConfig::parseRouteList("home\nadmin, home"));
    }

    public function testDisplayNameUsesNameDefaultOrId(): void
    {
        $named = (new CookieConsentConfig())->setName('Marketing');
        self::assertSame('Marketing', $named->getDisplayName());

        $default = (new CookieConsentConfig())->setDefault(true);
        self::assertSame('default', $default->getDisplayName());

        self::assertSame('config-0', (new CookieConsentConfig())->getDisplayName());
    }

    public function testRouteAndAutoShowTextHelpers(): void
    {
        $config = (new CookieConsentConfig())
            ->setAutoShowRoutes([' home ', 'admin'])
            ->setRoutePatterns(['/demo/*']);

        self::assertSame("home\nadmin", $config->getAutoShowRoutesText());
        self::assertSame('/demo/*', $config->getRoutePatternsText());

        $config->setAutoShowRoutesText("one\ntwo");
        $config->setRoutePatternsText("a\nb");

        self::assertSame(['one', 'two'], $config->getAutoShowRoutes());
        self::assertSame(['a', 'b'], $config->getRoutePatterns());
    }

    public function testInvalidLayoutThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new CookieConsentConfig())->setConsentModalLayout('invalid');
    }

    public function testInvalidAutoShowModeThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new CookieConsentConfig())->setAutoShowRouteMode('invalid');
    }

    public function testTranslationCollectionHelpers(): void
    {
        $config      = new CookieConsentConfig();
        $translation = (new CookieConsentConfigTranslation())->setLocale('en');

        $config->addTranslation($translation);
        self::assertTrue($config->getTranslations()->contains($translation));
        self::assertSame($config, $translation->getConfig());
        self::assertSame($translation, $config->findTranslation('en'));

        $config->removeTranslation($translation);
        self::assertFalse($config->getTranslations()->contains($translation));
        self::assertNull($translation->getConfig());
    }

    public function testModalPositionHelpers(): void
    {
        $config = (new CookieConsentConfig())
            ->setConsentModalPositionY('bottom')
            ->setConsentModalPositionX('center')
            ->setPreferencesModalPositionY('middle')
            ->setPreferencesModalPositionX(null);

        self::assertSame('bottom', $config->getConsentModalPositionY());
        self::assertSame('center', $config->getConsentModalPositionX());
        self::assertSame('bottom center', $config->getConsentModalPosition());
        self::assertSame('middle', $config->getPreferencesModalPositionY());
        self::assertNull($config->getPreferencesModalPositionX());
        self::assertSame('middle', $config->getPreferencesModalPosition());
    }

    public function testBooleanFlagsAndRevisionRoundTrip(): void
    {
        $config = (new CookieConsentConfig())
            ->setEnabled(false)
            ->setDefault(true)
            ->setAutoShow(false)
            ->setRevision(3)
            ->setManageScriptTags(true)
            ->setAutoClearCookies(true)
            ->setHideFromBots(false)
            ->setDisablePageInteraction(true)
            ->setLazyHtmlGeneration(true)
            ->setConsentModalEqualWeightButtons(true)
            ->setConsentModalFlipButtons(true)
            ->setPreferencesModalEqualWeightButtons(true)
            ->setPreferencesModalFlipButtons(true)
            ->setPriority(42)
            ->setName('  trimmed  ');

        self::assertNull($config->getId());
        self::assertFalse($config->isEnabled());
        self::assertTrue($config->isDefault());
        self::assertFalse($config->isAutoShow());
        self::assertSame(3, $config->getRevision());
        self::assertTrue($config->isManageScriptTags());
        self::assertTrue($config->isAutoClearCookies());
        self::assertFalse($config->isHideFromBots());
        self::assertTrue($config->isDisablePageInteraction());
        self::assertTrue($config->isLazyHtmlGeneration());
        self::assertTrue($config->isConsentModalEqualWeightButtons());
        self::assertTrue($config->isConsentModalFlipButtons());
        self::assertTrue($config->isPreferencesModalEqualWeightButtons());
        self::assertTrue($config->isPreferencesModalFlipButtons());
        self::assertSame(42, $config->getPriority());
        self::assertSame('trimmed', $config->getName());
    }

    public function testConsentModalVariantAndPreferencesLayout(): void
    {
        $config = (new CookieConsentConfig())
            ->setConsentModalLayout('bar')
            ->setConsentModalVariant('inline')
            ->setPreferencesModalLayout('cloud')
            ->setPreferencesModalVariant('inline')
            ->setPreferencesModalPositionY('top')
            ->setPreferencesModalPositionX('left');

        self::assertSame('bar', $config->getConsentModalLayout());
        self::assertSame('inline', $config->getConsentModalVariant());
        self::assertSame('cloud', $config->getPreferencesModalLayout());
        self::assertSame('inline', $config->getPreferencesModalVariant());
        self::assertSame('top left', $config->getPreferencesModalPosition());
    }

    public function testInvalidPreferencesLayoutThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new CookieConsentConfig())->setPreferencesModalLayout('invalid');
    }

    public function testSetNameNullClearsValue(): void
    {
        self::assertNull((new CookieConsentConfig())->setName(null)->getName());
        self::assertNull((new CookieConsentConfig())->setName('   ')->getName());
    }

    public function testFindTranslationReturnsNullWhenMissing(): void
    {
        self::assertNull((new CookieConsentConfig())->findTranslation('en'));
    }

    public function testAddTranslationIgnoresDuplicate(): void
    {
        $config      = new CookieConsentConfig();
        $translation = (new CookieConsentConfigTranslation())->setLocale('en');

        $config->addTranslation($translation);
        $config->addTranslation($translation);

        self::assertCount(1, $config->getTranslations());
    }

    public function testAutoShowRouteModeAcceptsValidValues(): void
    {
        $config = new CookieConsentConfig();

        $config->setAutoShowRouteMode('only');
        self::assertSame('only', $config->getAutoShowRouteMode());

        $config->setAutoShowRouteMode('except');
        self::assertSame('except', $config->getAutoShowRouteMode());
    }

    public function testEmptyRouteListsNormalizeToEmptyArrays(): void
    {
        $config = (new CookieConsentConfig())
            ->setAutoShowRoutesText(null)
            ->setRoutePatternsText(null);

        self::assertSame([], $config->getAutoShowRoutes());
        self::assertSame('', $config->getAutoShowRoutesText());
        self::assertSame([], $config->getRoutePatterns());
        self::assertSame('', $config->getRoutePatternsText());
    }

    public function testParseRouteListIgnoresBlankLines(): void
    {
        self::assertSame(['home'], CookieConsentConfig::parseRouteList("home\n\n  \n"));
    }
}
