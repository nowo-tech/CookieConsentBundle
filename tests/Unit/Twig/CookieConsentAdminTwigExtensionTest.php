<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Twig;

use Nowo\CookieConsentBundle\Twig\CookieConsentAdminTwigExtension;
use PHPUnit\Framework\TestCase;

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
    }
}
