<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Enum;

use Nowo\CookieConsentBundle\Enum\CookieNameEnum;
use PHPUnit\Framework\TestCase;

final class CookieNameEnumTest extends TestCase
{
    public function testBuildsCategoryCookieName(): void
    {
        self::assertSame(
            'Cookie_Category_analytics',
            CookieNameEnum::getCookieCategoryName('analytics'),
        );
    }
}
