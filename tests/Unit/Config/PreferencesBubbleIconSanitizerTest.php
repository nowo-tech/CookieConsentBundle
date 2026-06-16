<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Config;

use InvalidArgumentException;
use Nowo\CookieConsentBundle\Config\PreferencesBubbleIconSanitizer;
use PHPUnit\Framework\TestCase;

final class PreferencesBubbleIconSanitizerTest extends TestCase
{
    public function testEmptyValuesReturnNull(): void
    {
        self::assertNull(PreferencesBubbleIconSanitizer::sanitize(null));
        self::assertNull(PreferencesBubbleIconSanitizer::sanitize(''));
        self::assertNull(PreferencesBubbleIconSanitizer::sanitize('   '));
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

    public function testRejectsDangerousMarkup(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PreferencesBubbleIconSanitizer::sanitize('<script>alert(1)</script>');
    }
}
