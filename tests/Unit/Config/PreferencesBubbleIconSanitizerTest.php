<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Config;

use InvalidArgumentException;
use Nowo\CookieConsentBundle\Config\PreferencesBubbleIconSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function dirname;
use function file_get_contents;
use function preg_match;
use function preg_quote;
use function preg_replace;
use function strpos;
use function substr;
use function trim;

final class PreferencesBubbleIconSanitizerTest extends TestCase
{
    public function testReturnsNullForEmptyValues(): void
    {
        self::assertNull(PreferencesBubbleIconSanitizer::sanitize(null));
        self::assertNull(PreferencesBubbleIconSanitizer::sanitize(''));
        self::assertNull(PreferencesBubbleIconSanitizer::sanitize('   '));
        self::assertNull(PreferencesBubbleIconSanitizer::sanitize('<!-- only a comment -->'));
    }

    public function testAllowsSvgAndEmojiMarkup(): void
    {
        $svg = '<svg viewBox="0 0 24 24"><path d="M0 0"/></svg>';

        self::assertSame($svg, PreferencesBubbleIconSanitizer::sanitize($svg));
        self::assertSame(
            PreferencesBubbleIconSanitizer::DEMO_EMOJI_ICON_HTML,
            PreferencesBubbleIconSanitizer::sanitize(PreferencesBubbleIconSanitizer::DEMO_EMOJI_ICON_HTML),
        );
    }

    public function testKeepsSvgNamespaceGradientsAndLocalReferences(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 24 24">'
            . '<defs><linearGradient id="g1"><stop offset="0" stop-color="#fff"/></linearGradient></defs>'
            . '<circle cx="12" cy="12" r="10" fill="url(#g1)"/><use xlink:href="#g1"/><use href="#g1"/>'
            . '<title>Cookie &amp; co</title></svg>';

        self::assertSame(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">'
            . '<defs><linearGradient id="g1"><stop offset="0" stop-color="#fff"/></linearGradient></defs>'
            . '<circle cx="12" cy="12" r="10" fill="url(#g1)"/><use xlink:href="#g1"/><use href="#g1"/>'
            . '<title>Cookie &amp; co</title></svg>',
            PreferencesBubbleIconSanitizer::sanitize($svg),
        );
    }

    public function testDropsUnknownAttributesCommentsAndEscapesCdata(): void
    {
        self::assertSame(
            '<span class="x">&lt;/span&gt;&lt;img src=x onerror=alert(1)&gt;</span>',
            PreferencesBubbleIconSanitizer::sanitize(
                '<span class="x" style="color:red" data-foo="1" xml:lang="en"><!-- c --><![CDATA[</span><img src=x onerror=alert(1)>]]></span>',
            ),
        );
    }

    public function testEmptyHtmlElementIsNotSelfClosed(): void
    {
        self::assertSame('<i class="icon"></i>', PreferencesBubbleIconSanitizer::sanitize('<i class="icon"/>'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function dangerousMarkupProvider(): iterable
    {
        yield 'script' => ['<script>alert(1)</script>'];
        yield 'svg onload no space' => ['<svg/onload=alert(1)>'];
        yield 'img onerror slash separated' => ['<img/src=x/onerror=alert(1)>'];
        yield 'svg onload quoted' => ['<svg onload="alert(1)"></svg>'];
        yield 'entity-encoded javascript href' => ['<svg><a href="&#106;avascript:alert(1)"><path d="M0 0"/></a></svg>'];
        yield 'entity-encoded javascript use href' => ['<svg><use href="&#106;avascript:alert(1)"/></svg>'];
        yield 'external use href' => ['<svg><use xlink:href="https://evil.example/x.svg#a"/></svg>'];
        yield 'data uri' => ['<svg><path fill="url(data:image/svg+xml;base64,AAAA)" d="M0 0"/></svg>'];
        yield 'external url reference' => ['<svg><path fill="url(https://evil.example/#a)" d="M0 0"/></svg>'];
        yield 'foreignObject' => ['<svg><foreignObject><span>x</span></foreignObject></svg>'];
        yield 'style element' => ['<svg><style>*{}</style></svg>'];
        yield 'iframe' => ['<iframe src="https://evil.example"></iframe>'];
        yield 'anchor' => ['<a href="#x">x</a>'];
        yield 'mathml namespace' => ['<span xmlns="http://www.w3.org/1998/Math/MathML">x</span>'];
        yield 'malformed html' => ['<span>unclosed'];
        yield 'doctype entity' => ['<!DOCTYPE x [<!ENTITY e SYSTEM "file:///etc/passwd">]><span>&e;</span>'];
    }

    #[DataProvider('dangerousMarkupProvider')]
    public function testRejectsDangerousMarkup(string $markup): void
    {
        $this->expectException(InvalidArgumentException::class);

        PreferencesBubbleIconSanitizer::sanitize($markup);
    }

    public function testSanitizeOrNullSwallowsInvalidMarkup(): void
    {
        self::assertNull(PreferencesBubbleIconSanitizer::sanitizeOrNull('<svg/onload=alert(1)>'));
        self::assertSame('<b>x</b>', PreferencesBubbleIconSanitizer::sanitizeOrNull('<b>x</b>'));
    }

    /**
     * docs/CONFIGURATION.md "Bubble icon allow-list" documents every allowed element and attribute; this keeps the
     * table in sync with the sanitizer constants and checks the documented rejections and the img -> inline SVG example.
     */
    public function testDocumentedAllowListMatchesTheSanitizer(): void
    {
        $docs = (string) file_get_contents(dirname(__DIR__, 3) . '/docs/CONFIGURATION.md');
        self::assertStringContainsString('### Bubble icon allow-list', $docs);
        $section = substr($docs, (int) strpos($docs, '### Bubble icon allow-list'));
        $section = substr($section, 0, (int) strpos($section, "\n## "));

        $class = new ReflectionClass(PreferencesBubbleIconSanitizer::class);
        foreach (['SVG_ELEMENTS', 'HTML_ELEMENTS', 'ATTRIBUTES'] as $constant) {
            $names = $class->getConstant($constant);
            self::assertIsArray($names);
            foreach ($names as $name) {
                self::assertIsString($name);
                self::assertMatchesRegularExpression('/`' . preg_quote($name, '/') . '`/i', $section, $constant . ': ' . $name);
            }
        }

        foreach (['<filter id="s"><feDropShadow dx="0" dy="1"/></filter>', '<feDropShadow/>', '<img src="/cookie.svg" alt=""/>', '<foreignObject/>', '<br>'] as $rejected) {
            self::assertNull(PreferencesBubbleIconSanitizer::sanitizeOrNull('<svg>' . $rejected . '</svg>'), $rejected);
        }
        self::assertSame('<svg><circle r="1"/></svg>', PreferencesBubbleIconSanitizer::sanitize('<svg><circle r="1" filter="url(#s)" style="opacity:.5"/></svg>'));

        self::assertSame(1, preg_match('/preferences_bubble_icon: >-\n((?: {8}.+\n)+)/', $section, $example));
        $icon      = (string) preg_replace('/\s*\n\s*/', ' ', trim($example[1]));
        $sanitized = PreferencesBubbleIconSanitizer::sanitize($icon);
        self::assertNotNull($sanitized);
        self::assertStringContainsString('<circle cx="12" cy="12" r="10" fill="currentColor"/>', $sanitized);
    }
}
