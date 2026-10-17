<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Twig;

use Nowo\CookieConsentBundle\Admin\AdminChrome;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Admin Web UI Twig globals.
 *
 * Since 1.16 the admin pages extend the UiKit page scaffolds (REQ-UI-003) and only read
 * `nowo_cookie_consent_admin_chrome.templates` (`form_theme`, `form_fields`, `styles_after`: deprecated 1.x hooks that
 * have no theme slot). The layout / css framework / icon set globals are deprecated and kept for host templates
 * written for 1.x; they are removed in 2.0 together with `web_ui.chrome`.
 */
final class CookieConsentAdminTwigExtension extends AbstractExtension implements GlobalsInterface
{
    /** @deprecated since 1.16, the admin pages use the UiKit layout (`nowo_ui_layout.admin` or `web_ui.layout_template`) */
    public const GLOBAL_LAYOUT_TEMPLATE = 'nowo_cookie_consent_layout_template';

    /** @deprecated since 1.16, read `nowo_ui_kit_css_framework` */
    public const GLOBAL_CSS_FRAMEWORK = 'nowo_cookie_consent_css_framework';

    /** @deprecated since 1.16, read `nowo_ui_kit_icon_set` */
    public const GLOBAL_ICON_SET = 'nowo_cookie_consent_icon_set';

    public const GLOBAL_ADMIN_CHROME = 'nowo_cookie_consent_admin_chrome';

    /** Value of the deprecated layout global when `web_ui.layout_template` is null. */
    public const UI_KIT_ADMIN_LAYOUT = '@NowoUiKit/layout/admin.html.twig';

    /** @var array<string, mixed> */
    private readonly array $chrome;

    /**
     * @param array<string, mixed> $chrome Resolved admin chrome hooks ({@see AdminChrome::resolve()}); empty = defaults
     */
    public function __construct(
        private readonly string $layoutTemplate = self::UI_KIT_ADMIN_LAYOUT,
        private readonly string $cssFramework = 'bootstrap5',
        private readonly string $iconSet = 'bootstrap-icons',
        array $chrome = [],
    ) {
        $this->chrome = $chrome === [] ? AdminChrome::resolve() : $chrome;
    }

    /**
     * @return array<string, mixed>
     */
    public function getGlobals(): array
    {
        return [
            self::GLOBAL_LAYOUT_TEMPLATE => $this->layoutTemplate,
            self::GLOBAL_CSS_FRAMEWORK   => $this->cssFramework,
            self::GLOBAL_ICON_SET        => $this->iconSet,
            self::GLOBAL_ADMIN_CHROME    => $this->chrome,
        ];
    }
}
