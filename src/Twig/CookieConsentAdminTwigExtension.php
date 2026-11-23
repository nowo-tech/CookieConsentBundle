<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Admin Web UI Twig global `nowo_cookie_consent_admin_chrome`.
 *
 * Since 1.16 the admin pages extend the UiKit page scaffolds (REQ-UI-003) and read the UiKit theme slots. 2.0 removed
 * `web_ui.chrome` and the deprecated `nowo_cookie_consent_layout_template`, `nowo_cookie_consent_css_framework` and
 * `nowo_cookie_consent_icon_set` globals; this global keeps the shape it had without `web_ui.chrome` (no classes, no
 * attributes, no partials) for host templates that still read it.
 */
final class CookieConsentAdminTwigExtension extends AbstractExtension implements GlobalsInterface
{
    public const GLOBAL_ADMIN_CHROME = 'nowo_cookie_consent_admin_chrome';

    /**
     * The value of {@see self::GLOBAL_ADMIN_CHROME}: what 1.x resolved when `web_ui.chrome` was not set.
     *
     * @var array{classes: array<string, string|null>, attributes: array<string, array<string, string>>, templates: array<string, string|null>, page_title_tag: string, row_actions_display: string|null}
     */
    public const ADMIN_CHROME = [
        'classes'    => [],
        'attributes' => [],
        'templates'  => [
            'tabs'         => null,
            'pagination'   => null,
            'form_theme'   => null,
            'form_fields'  => null,
            'styles_after' => null,
        ],
        'page_title_tag'      => 'h1',
        'row_actions_display' => null,
    ];

    /**
     * Returns the admin Twig globals.
     *
     * @return array<string, mixed>
     */
    public function getGlobals(): array
    {
        return [self::GLOBAL_ADMIN_CHROME => self::ADMIN_CHROME];
    }
}
