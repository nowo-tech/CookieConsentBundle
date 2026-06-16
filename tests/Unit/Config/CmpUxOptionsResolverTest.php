<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Config;

use Nowo\CookieConsentBundle\Config\CmpUxOptionsResolver;
use Nowo\CookieConsentBundle\Config\PreferencesBubbleIconSanitizer;
use Nowo\CookieConsentBundle\Config\ResolvedCookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfigTranslation;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class CmpUxOptionsResolverTest extends TestCase
{
    public function testYamlDefaultsWhenDatabaseConfigDisabled(): void
    {
        $resolver = $this->createResolver(useDatabaseConfig: false);

        self::assertSame('dark-turquoise', $resolver->getColorTheme());
        self::assertTrue($resolver->isTwoStepModal());
        self::assertFalse($resolver->isDarkModeEnabled());
        self::assertSame([['title' => 'Analytics', 'categories' => ['analytics']]], $resolver->getPreferenceSections());
    }

    public function testYamlDisablePageInteractionWhenDatabaseConfigDisabled(): void
    {
        $resolver = new CmpUxOptionsResolver(
            new RequestStack(),
            'light',
            false,
            false,
            true,
            false,
            false,
            false,
            false,
            false,
            'bottom-right',
            null,
            null,
            [],
            false,
        );

        self::assertTrue($resolver->isDisablePageInteraction());
    }

    public function testDatabaseValuesOverrideYamlWhenResolvedConfigPresent(): void
    {
        $config = (new CookieConsentConfig())
            ->setColorTheme('elegant-black')
            ->setDarkModeEnabled(true)
            ->setTwoStepModal(false)
            ->setOpenPreferencesModal(true)
            ->setDisableTransitions(true)
            ->setDisablePageInteraction(true)
            ->setManageIframePlaceholders(true);

        $translation = (new CookieConsentConfigTranslation())
            ->setPreferenceSections([
                ['title' => 'DB section', 'categories' => ['marketing']],
            ]);

        $request = Request::create('/');
        $request->attributes->set('nowo_cookie_consent_config', new ResolvedCookieConsentConfig($config, $translation));

        $stack = new RequestStack();
        $stack->push($request);

        $resolver = $this->createResolver($stack, useDatabaseConfig: true);

        self::assertSame('elegant-black', $resolver->getColorTheme());
        self::assertTrue($resolver->isDarkModeEnabled());
        self::assertFalse($resolver->isTwoStepModal());
        self::assertTrue($resolver->isOpenPreferencesModal());
        self::assertTrue($resolver->isDisableTransitions());
        self::assertTrue($resolver->isDisablePageInteraction());
        self::assertTrue($resolver->isManageIframePlaceholders());
        self::assertSame([['title' => 'DB section', 'categories' => ['marketing']]], $resolver->getPreferenceSections());
    }

    public function testPreferencesBubbleBorderColorComesFromDatabaseProfile(): void
    {
        $config = (new CookieConsentConfig())->setPreferencesBubbleBorderColor('#60fed2');

        $request = Request::create('/');
        $request->attributes->set('nowo_cookie_consent_config', new ResolvedCookieConsentConfig($config, null));

        $stack = new RequestStack();
        $stack->push($request);

        $resolver = $this->createResolver($stack, useDatabaseConfig: true);

        self::assertSame('#60fed2', $resolver->getPreferencesBubbleBorderColor());
    }

    public function testPreferencesBubbleIconComesFromDatabaseProfile(): void
    {
        $icon   = PreferencesBubbleIconSanitizer::DEMO_EMOJI_ICON_HTML;
        $config = (new CookieConsentConfig())->setPreferencesBubbleIcon($icon);

        $request = Request::create('/');
        $request->attributes->set('nowo_cookie_consent_config', new ResolvedCookieConsentConfig($config, null));

        $stack = new RequestStack();
        $stack->push($request);

        $resolver = $this->createResolver($stack, useDatabaseConfig: true);

        self::assertSame($icon, $resolver->getPreferencesBubbleIcon());
    }

    /**
     * @param list<array<string, mixed>> $yamlPreferenceSections
     */
    private function createResolver(
        ?RequestStack $stack = null,
        bool $useDatabaseConfig = false,
        array $yamlPreferenceSections = [['title' => 'Analytics', 'categories' => ['analytics']]],
    ): CmpUxOptionsResolver {
        return new CmpUxOptionsResolver(
            $stack ?? new RequestStack(),
            'dark-turquoise',
            false,
            false,
            false,
            true,
            false,
            false,
            false,
            false,
            'bottom-right',
            null,
            null,
            $yamlPreferenceSections,
            $useDatabaseConfig,
        );
    }
}
