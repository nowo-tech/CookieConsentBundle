<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Admin;

use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\UiKitBundle\Navigation\AreaNavProviderInterface;
use Nowo\UiKitBundle\View\AreaNav;

use function ctype_digit;
use function in_array;
use function is_int;
use function is_string;

/**
 * Publishes the admin area navigation of a consent profile (Settings | Cookie inventory) to the UiKit area-nav
 * registry (area {@see self::AREA}), so other bundles (DashboardMenu, a host menu) can reuse it:
 * `nowo_ui_area_nav('cookie_consent', {configId: 1})`.
 *
 * Context: `configId` (int or numeric string) or `config` ({@see CookieConsentConfig}) — without one the area is
 * unknown (null); optional `section` (`settings` | `cookies`) marks that tab active (`current`, an href, is handled
 * by the registry). Stateless (FrankenPHP worker).
 */
final readonly class CookieConsentAreaNavProvider implements AreaNavProviderInterface
{
    public const AREA = 'cookie_consent';

    public function __construct(
        private AdminPageFactory $pages,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return ?AreaNav
     */
    public function areaNav(string $area, array $context = []): ?AreaNav
    {
        if ($area !== self::AREA) {
            return null;
        }

        $config   = $context['config'] ?? $context['configId'] ?? null;
        $configId = match (true) {
            $config instanceof CookieConsentConfig     => $config->getId(),
            is_int($config)                            => $config,
            is_string($config) && ctype_digit($config) => (int) $config,
            default                                    => null,
        };
        if ($configId === null) {
            return null;
        }

        $section = $context['section'] ?? null;

        return $this->pages->areaNav(
            $configId,
            in_array($section, [AdminPageFactory::AREA_SETTINGS, AdminPageFactory::AREA_COOKIES], true) ? $section : null,
        );
    }
}
