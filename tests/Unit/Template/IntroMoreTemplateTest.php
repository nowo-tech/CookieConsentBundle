<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Template;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;

use function dirname;
use function file_get_contents;
use function substr_count;

/**
 * MUX-151 (1.23): the "Show more" toggle of the first-layer intro, in both modal templates.
 */
final class IntroMoreTemplateTest extends TestCase
{
    public function testTheToggleIsHiddenUntilScriptMeasuresTheIntroAndControlsIt(): void
    {
        $views  = dirname(__DIR__, 3) . '/src/Resources/views';
        $loader = new FilesystemLoader($views);
        $loader->addPath($views, 'NowoCookieConsentBundle');
        $twig = new Environment($loader, ['strict_variables' => true]);
        $twig->addFilter(new TwigFilter('trans', static fn (string $id): string => $id . '"<'));

        $html = $twig->render('_intro_more.html.twig');

        self::assertStringContainsString('type="button"', $html);
        self::assertStringContainsString('data-nowo-intro-more', $html);
        self::assertStringContainsString('aria-controls="nowoCookieConsentIntro"', $html);
        self::assertStringContainsString('aria-expanded="false"', $html);
        self::assertStringContainsString("\n        hidden>", $html);
        self::assertStringContainsString('data-nowo-label-less="nowo_cookie_consent.intro_less&quot;&lt;"', $html);
        self::assertStringContainsString('>nowo_cookie_consent.intro_more&quot;&lt;</button>', $html);
        self::assertStringNotContainsString('name=', $html, 'Not a submit button: it never records a choice');
    }

    public function testBothModalTemplatesPrintTheIntroWithItsToggle(): void
    {
        $views = dirname(__DIR__, 3) . '/src/Resources/views';
        foreach (['cookie_consent.html.twig', 'cookie_consent.tailwind.html.twig'] as $template) {
            $source = (string) file_get_contents($views . '/' . $template);
            // Banner step (two-step) and the one-step first layer.
            self::assertSame(2, substr_count($source, 'id="nowoCookieConsentIntro"'), $template);
            self::assertSame(2, substr_count($source, 'data-nowo-intro>'), $template);
            self::assertSame(2, substr_count($source, "include('@NowoCookieConsentBundle/_intro_more.html.twig')"), $template);
        }
    }
}
