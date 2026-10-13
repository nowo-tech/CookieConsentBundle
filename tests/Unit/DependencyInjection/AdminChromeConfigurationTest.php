<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\DependencyInjection;

use Nowo\CookieConsentBundle\Admin\AdminChrome;
use Nowo\CookieConsentBundle\DependencyInjection\Configuration;
use Nowo\CookieConsentBundle\DependencyInjection\NowoCookieConsentExtension;
use Nowo\CookieConsentBundle\Twig\CookieConsentAdminTwigExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class AdminChromeConfigurationTest extends TestCase
{
    public function testChromeDefaults(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[]]);

        self::assertSame([
            'page_title_tag'      => 'h1',
            'row_actions_display' => 'text',
            'classes'             => [],
            'attributes'          => [],
            'templates'           => [],
        ], $config['web_ui']['chrome']);
    }

    public function testChromeKeepsHyphenatedAttributeNames(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'web_ui' => ['chrome' => [
                'page_title_tag' => 'none',
                'classes'        => ['table_panel' => 'panel', 'button_primary' => null],
                'attributes'     => ['table_panel' => ['data-testid' => 'cookie-consent-definitions-table']],
                'templates'      => ['pagination' => 'kit/_pagination.html.twig', 'form_theme' => null],
            ]],
        ]]);

        $chrome = $config['web_ui']['chrome'];
        self::assertSame(['data-testid' => 'cookie-consent-definitions-table'], $chrome['attributes']['table_panel']);
        self::assertSame('panel', $chrome['classes']['table_panel']);
        self::assertArrayHasKey('button_primary', $chrome['classes']);
        self::assertNull($chrome['templates']['form_theme']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidChrome(): iterable
    {
        yield 'unknown class' => [['classes' => ['tabel' => 'x']]];
        yield 'unknown attribute element' => [['attributes' => ['tabel' => ['a' => 'b']]]];
        yield 'unknown template' => [['templates' => ['layout' => 'x']]];
        yield 'bad title tag' => [['page_title_tag' => 'div']];
        yield 'bad row actions display' => [['row_actions_display' => 'banner']];
    }

    /**
     * @param array<string, mixed> $chrome
     */
    #[DataProvider('invalidChrome')]
    public function testInvalidChromeIsRejected(array $chrome): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new Processor())->processConfiguration(new Configuration(), [['web_ui' => ['chrome' => $chrome]]]);
    }

    public function testExtensionPassesResolvedChromeToTwigGlobals(): void
    {
        $container = new ContainerBuilder();
        (new NowoCookieConsentExtension())->load([[
            'web_ui' => ['chrome' => ['classes' => ['table' => 'data-table'], 'templates' => ['styles_after' => 'kit/_styles.html.twig']]],
        ]], $container);

        $chrome = $container->getDefinition(CookieConsentAdminTwigExtension::class)->getArgument('$chrome');
        self::assertIsArray($chrome);
        self::assertSame('data-table', $chrome['classes']['table']);
        self::assertSame('kit/_styles.html.twig', $chrome['templates']['styles_after']);
        self::assertSame(AdminChrome::DEFAULT_TEMPLATES['pagination'], $chrome['templates']['pagination']);
    }
}
