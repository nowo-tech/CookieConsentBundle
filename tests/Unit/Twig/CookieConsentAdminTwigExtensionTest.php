<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Twig;

use Nowo\CookieConsentBundle\Twig\CookieConsentAdminTwigExtension;
use PHPUnit\Framework\TestCase;

final class CookieConsentAdminTwigExtensionTest extends TestCase
{
    public function testExposesLayoutGlobals(): void
    {
        $extension = new CookieConsentAdminTwigExtension(
            'base.html.twig',
            'tailwind',
            'svg_inline',
        );

        $globals = $extension->getGlobals();

        self::assertSame('base.html.twig', $globals[CookieConsentAdminTwigExtension::GLOBAL_LAYOUT_TEMPLATE]);
        self::assertSame('tailwind', $globals[CookieConsentAdminTwigExtension::GLOBAL_CSS_FRAMEWORK]);
        self::assertSame('svg_inline', $globals[CookieConsentAdminTwigExtension::GLOBAL_ICON_SET]);
    }

    public function testDefaultGlobalsMatchBundleLayout(): void
    {
        $globals = (new CookieConsentAdminTwigExtension())->getGlobals();

        self::assertSame(
            '@NowoCookieConsentBundle/admin/layout.html.twig',
            $globals[CookieConsentAdminTwigExtension::GLOBAL_LAYOUT_TEMPLATE],
        );
        self::assertSame('bootstrap5', $globals[CookieConsentAdminTwigExtension::GLOBAL_CSS_FRAMEWORK]);
        self::assertSame('bootstrap-icons', $globals[CookieConsentAdminTwigExtension::GLOBAL_ICON_SET]);
    }
}
