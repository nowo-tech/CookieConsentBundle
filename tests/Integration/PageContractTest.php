<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Integration;

use Nowo\CookieConsentBundle\Admin\CookieConsentConfigSettingsSection;
use Nowo\CookieConsentBundle\Tests\Support\AdminPageFixtures;
use Nowo\CookieConsentBundle\Twig\CookieConsentAdminTwigExtension;
use Nowo\UiKitBundle\Test\PageContractTestCase;
use Nowo\UiKitBundle\Test\PageFixture;
use Twig\Environment;

use function dirname;

/**
 * REQ-UI-003 page contract: every admin page extends a UiKit scaffold, fills the canonical blocks of the stub host
 * layout, emits no CDN / inline handler / confirm(), paints framework classes only through theme slots, reflects
 * the stub theme and prints no raw translation keys.
 */
final class PageContractTest extends PageContractTestCase
{
    private const VIEWS = '@NowoCookieConsentBundle/admin/';

    public static function pageFixtures(): iterable
    {
        yield 'cookie definitions' => new PageFixture(self::VIEWS . 'cookie_definition/index.html.twig', AdminPageFixtures::definitionList());
        yield 'cookie definitions (empty)' => new PageFixture(self::VIEWS . 'cookie_definition/index.html.twig', AdminPageFixtures::definitionList(true));
        yield 'cookie definition (edit)' => new PageFixture(self::VIEWS . 'cookie_definition/form.html.twig', AdminPageFixtures::definitionForm());
        yield 'cookie definition (new)' => new PageFixture(self::VIEWS . 'cookie_definition/form.html.twig', AdminPageFixtures::definitionForm(true));
        foreach (CookieConsentConfigSettingsSection::cases() as $section) {
            yield 'settings (' . $section->value . ')' => new PageFixture(self::VIEWS . 'config/settings.html.twig', AdminPageFixtures::settings($section));
        }
    }

    protected static function templatePaths(): array
    {
        return ['NowoCookieConsentBundle' => dirname(__DIR__, 2) . '/src/Resources/views'];
    }

    protected static function translationDirs(): array
    {
        return [dirname(__DIR__, 2) . '/src/Resources/translations'];
    }

    protected static function templatesDocument(): string
    {
        return dirname(__DIR__, 2) . '/docs/TEMPLATES.md';
    }

    protected function configureTwig(Environment $twig): void
    {
        $twig->addExtension(new CookieConsentAdminTwigExtension());
    }
}
