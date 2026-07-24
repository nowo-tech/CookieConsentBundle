<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Twig;

use Nowo\CookieConsentBundle\Config\CmpUxOptionsResolver;
use Nowo\CookieConsentBundle\Config\PreferencesBubbleIconSanitizer;
use Nowo\CookieConsentBundle\Config\ResolvedCookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Twig\CmpUxTwigExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\TwigFunction;

final class CmpUxTwigExtensionTest extends TestCase
{
    public function testGetFunctionsRegistersAllUxCallbacks(): void
    {
        $extension = $this->createExtension();
        $names     = array_map(static fn (TwigFunction $function): string => $function->getName(), $extension->getFunctions());

        self::assertContains('nowo_cookie_consent_two_step_modal', $names);
        self::assertContains('nowo_cookie_consent_preferences_bubble_icon', $names);
        self::assertContains('nowo_cookie_consent_preference_sections', $names);
    }

    public function testDelegatesToUxOptionsResolver(): void
    {
        $config = (new CookieConsentConfig())
            ->setColorTheme('elegant-black')
            ->setTwoStepModal(false)
            ->setPreferencesBubbleEnabled(true)
            ->setPreferencesBubblePosition('top-left')
            ->setPreferencesBubbleBorderColor('#60fed2')
            ->setPreferencesBubbleIcon(PreferencesBubbleIconSanitizer::DEMO_EMOJI_ICON_HTML)
            ->setGranularCookieSelection(true);

        $request = Request::create('/');
        $request->attributes->set('nowo_cookie_consent_config', new ResolvedCookieConsentConfig($config, null));

        $stack = new RequestStack();
        $stack->push($request);

        $resolver  = $this->createResolver($stack, useDatabaseConfig: true);
        $extension = new CmpUxTwigExtension($resolver);

        self::assertSame('elegant-black', $extension->getColorTheme());
        self::assertFalse($extension->isTwoStepModal());
        self::assertTrue($extension->isPreferencesBubbleEnabled());
        self::assertSame('top-left', $extension->getPreferencesBubblePosition());
        self::assertSame('#60fed2', $extension->getPreferencesBubbleBorderColor());
        self::assertSame(PreferencesBubbleIconSanitizer::DEMO_EMOJI_ICON_HTML, $extension->getPreferencesBubbleIcon());
        self::assertTrue($extension->isGranularCookieSelection());
    }

    public function testAllPublicMethodsDelegateToResolver(): void
    {
        $extension = $this->createExtension();

        self::assertFalse($extension->isOpenPreferencesModal());
        self::assertFalse($extension->isDisableTransitions());
        self::assertFalse($extension->isDisablePageInteraction());
        self::assertFalse($extension->isManageIframePlaceholders());
        self::assertFalse($extension->isDarkModeEnabled());
        self::assertSame('bottom-right', $extension->getPreferencesBubblePosition());
        self::assertSame([['title' => 'Analytics', 'categories' => ['analytics']]], $extension->getPreferenceSections());
    }

    private function createExtension(): CmpUxTwigExtension
    {
        return new CmpUxTwigExtension($this->createResolver());
    }

    private function createResolver(?RequestStack $stack = null, bool $useDatabaseConfig = false): CmpUxOptionsResolver
    {
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
            [['title' => 'Analytics', 'categories' => ['analytics']]],
            $useDatabaseConfig,
        );
    }
}
