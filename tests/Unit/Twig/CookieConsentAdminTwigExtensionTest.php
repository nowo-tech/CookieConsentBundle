<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Twig;

use Nowo\CookieConsentBundle\Admin\AdminChrome;
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

    public function testDefaultGlobalsPointAtTheUiKitLayout(): void
    {
        $globals = (new CookieConsentAdminTwigExtension())->getGlobals();

        self::assertSame(
            '@NowoUiKit/layout/admin.html.twig',
            $globals[CookieConsentAdminTwigExtension::GLOBAL_LAYOUT_TEMPLATE],
        );
        self::assertSame('bootstrap5', $globals[CookieConsentAdminTwigExtension::GLOBAL_CSS_FRAMEWORK]);
        self::assertSame('bootstrap-icons', $globals[CookieConsentAdminTwigExtension::GLOBAL_ICON_SET]);
        self::assertSame(AdminChrome::resolve(), $globals[CookieConsentAdminTwigExtension::GLOBAL_ADMIN_CHROME]);
    }

    public function testExposesResolvedChrome(): void
    {
        $chrome  = AdminChrome::resolve(['page_title_tag' => 'h2']);
        $globals = (new CookieConsentAdminTwigExtension('base.html.twig', 'tailwind', 'ux_icon', $chrome))->getGlobals();

        self::assertSame($chrome, $globals[CookieConsentAdminTwigExtension::GLOBAL_ADMIN_CHROME]);
    }
}
