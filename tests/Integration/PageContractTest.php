<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Integration;

use Nowo\CookieConsentBundle\Admin\CookieConsentConfigSettingsSection;
use Nowo\CookieConsentBundle\Tests\Support\AdminPageFixtures;
use Nowo\CookieConsentBundle\Twig\CookieConsentAdminTwigExtension;
use Nowo\FormKitBundle\Twig\LocaleTabsTwigExtension;
use Nowo\UiKitBundle\Test\PageContractTestCase;
use Nowo\UiKitBundle\Test\PageFixture;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Translation\DataCollectorTranslator;
use Twig\Environment;

use function array_filter;
use function array_map;
use function array_values;
use function assert;
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

    /**
     * @return iterable<string, array{PageFixture}>
     */
    public static function translationPages(): iterable
    {
        foreach (self::pageFixtures() as $name => $fixture) {
            yield $name => [$fixture];
        }
    }

    /**
     * The form theme translates placeholders and choice labels in the field domain: the locale example (`en`) is a
     * catalogue entry, not a literal looked up as a message. Every lookup of a page hits the catalogues (no profiler
     * missing message).
     */
    #[DataProvider('translationPages')]
    public function testPageLooksUpNoMissingMessage(PageFixture $fixture): void
    {
        $twig      = $this->createTwig();
        $collector = $twig->getExtension(TranslationExtension::class)->getTranslator();
        assert($collector instanceof DataCollectorTranslator);
        $html = $twig->render($fixture->template, $fixture->context);
        // 1.18: the default form renders the locale as a hidden input (one tab per locale); a visible locale field
        // (host form theme / override) shows the catalogue example
        if (preg_match('/<input type="text"[^>]*\[locale\]/', $html) === 1) {
            self::assertStringContainsString('placeholder="en"', $html, 'The locale example comes from the catalogue.');
        }

        $missing = array_filter(
            $collector->getCollectedMessages(),
            static fn (array $message): bool => $message['state'] === DataCollectorTranslator::MESSAGE_MISSING,
        );
        self::assertSame([], array_values(array_map(static fn (array $message): string => $message['domain'] . ': ' . $message['id'], $missing)));
    }

    protected static function templatePaths(): array
    {
        return [
            'NowoCookieConsentBundle' => dirname(__DIR__, 2) . '/src/Resources/views',
            'NowoFormKitBundle'       => dirname(__DIR__, 2) . '/vendor/nowo-tech/form-kit-bundle/src/Resources/views',
        ];
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
        $twig->addExtension(new LocaleTabsTwigExtension());
        $twig->addExtension(new CookieConsentAdminTwigExtension());
    }
}
