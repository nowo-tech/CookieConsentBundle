<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Exposes admin Web UI globals to Twig templates (REQ-UI-001).
 */
final class CookieConsentAdminTwigExtension extends AbstractExtension implements GlobalsInterface
{
    public const GLOBAL_LAYOUT_TEMPLATE = 'nowo_cookie_consent_layout_template';

    public const GLOBAL_CSS_FRAMEWORK = 'nowo_cookie_consent_css_framework';

    public const GLOBAL_ICON_SET = 'nowo_cookie_consent_icon_set';

    public function __construct(
        private readonly string $layoutTemplate = '@NowoCookieConsentBundle/admin/layout.html.twig',
        private readonly string $cssFramework = 'bootstrap5',
        private readonly string $iconSet = 'bootstrap-icons',
    ) {
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
        ];
    }
}
