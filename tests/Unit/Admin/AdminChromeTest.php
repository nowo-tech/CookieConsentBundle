<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Admin;

use InvalidArgumentException;
use Nowo\CookieConsentBundle\Admin\AdminChrome;
use PHPUnit\Framework\TestCase;

final class AdminChromeTest extends TestCase
{
    public function testDefaultsReproduceShippedChrome(): void
    {
        $chrome = AdminChrome::resolve();

        self::assertSame(AdminChrome::DEFAULT_CLASSES, $chrome['classes']);
        self::assertSame(AdminChrome::DEFAULT_TEMPLATES, $chrome['templates']);
        self::assertSame([], $chrome['attributes']);
        self::assertSame('h1', $chrome['page_title_tag']);
        self::assertSame('text', $chrome['row_actions_display']);
    }

    public function testOverridesAreMerged(): void
    {
        $chrome = AdminChrome::resolve([
            'page_title_tag'      => 'h2',
            'row_actions_display' => 'inherit',
            'classes'             => ['table' => 'data-table', 'button_primary' => 'btn-primary', 'pagination' => null],
            'attributes'          => ['table_panel' => ['data-testid' => 'x', 'data-n' => 3, 'data-bad' => ['nested']]],
            'templates'           => ['pagination' => 'kit/_pagination.html.twig', 'form_theme' => '', 'tabs' => null, 'styles_after' => 'kit/_styles.html.twig'],
        ]);

        self::assertSame('data-table', $chrome['classes']['table']);
        self::assertSame('btn-primary', $chrome['classes']['button_primary']);
        self::assertNull($chrome['classes']['pagination']);
        self::assertSame('table-responsive', $chrome['classes']['table_wrap']);
        self::assertSame(['table_panel' => ['data-testid' => 'x', 'data-n' => '3', 'data-bad' => '']], $chrome['attributes']);
        self::assertSame('kit/_pagination.html.twig', $chrome['templates']['pagination']);
        self::assertNull($chrome['templates']['form_theme']);
        self::assertSame(AdminChrome::DEFAULT_TEMPLATES['tabs'], $chrome['templates']['tabs'], 'tabs/pagination cannot be disabled');
        self::assertSame('kit/_styles.html.twig', $chrome['templates']['styles_after']);
        self::assertSame('h2', $chrome['page_title_tag']);
        self::assertNull($chrome['row_actions_display']);
    }

    public function testInvalidScalarsFallBackToDefaults(): void
    {
        $chrome = AdminChrome::resolve(['page_title_tag' => 'div', 'row_actions_display' => 'banner', 'classes' => 'nope']);

        self::assertSame('h1', $chrome['page_title_tag']);
        self::assertSame('text', $chrome['row_actions_display']);
        self::assertSame(AdminChrome::DEFAULT_CLASSES, $chrome['classes']);
    }

    public function testUnknownClassKeyIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('web_ui.chrome.classes key "tabel"');

        AdminChrome::resolve(['classes' => ['tabel' => 'x']]);
    }

    public function testUnknownTemplateKeyIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('web_ui.chrome.templates key "layout"');

        AdminChrome::resolve(['templates' => ['layout' => 'x']]);
    }

    public function testUnknownAttributeKeyIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AdminChrome::resolve(['attributes' => ['nope' => ['a' => 'b']]]);
    }
}
