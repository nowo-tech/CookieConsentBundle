<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Twig;

use Nowo\CookieConsentBundle\Twig\CookieConsentAdminTwigExtension;
use PHPUnit\Framework\TestCase;
use ReflectionClassConstant;

final class CookieConsentAdminTwigExtensionTest extends TestCase
{
    /**
     * 2.0: only `nowo_cookie_consent_admin_chrome` is left, with the 1.x value of a host without `web_ui.chrome`; the
     * deprecated layout / css framework / icon set globals are gone.
     */
    public function testOnlyTheAdminChromeGlobalIsLeft(): void
    {
        $globals = (new CookieConsentAdminTwigExtension())->getGlobals();

        self::assertSame([CookieConsentAdminTwigExtension::GLOBAL_ADMIN_CHROME => [
            'classes'             => [],
            'attributes'          => [],
            'templates'           => ['tabs' => null, 'pagination' => null, 'form_theme' => null, 'form_fields' => null, 'styles_after' => null],
            'page_title_tag'      => 'h1',
            'row_actions_display' => null,
        ]], $globals);
        // Kept (deprecated, removed in 3.0): the UiKit admin layout of the removed layout global.
        $constant = new ReflectionClassConstant(CookieConsentAdminTwigExtension::class, 'UI_KIT_ADMIN_LAYOUT');
        self::assertSame('@NowoUiKit/layout/admin.html.twig', $constant->getValue());
        self::assertStringContainsString('@deprecated since 2.0', (string) $constant->getDocComment());
    }
}
