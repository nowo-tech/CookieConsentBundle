<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Cookie;

use Nowo\CookieConsentBundle\Cookie\CookieHandler;
use Nowo\CookieConsentBundle\Enum\CookieNameEnum;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

final class CookieHandlerTest extends TestCase
{
    public function testSavesConsentCookies(): void
    {
        $handler  = new CookieHandler(true);
        $response = new Response();

        $handler->save([
            'required'  => true,
            'analytics' => true,
            'marketing' => false,
        ], 'consent-key', $response);

        $cookies = $response->headers->getCookies();
        $names   = array_map(static fn (\Symfony\Component\HttpFoundation\Cookie $cookie): string => $cookie->getName(), $cookies);

        self::assertContains(CookieNameEnum::COOKIE_CONSENT_NAME, $names);
        self::assertContains(CookieNameEnum::COOKIE_CONSENT_KEY_NAME, $names);
        self::assertContains(CookieNameEnum::getCookieCategoryName('analytics'), $names);
        self::assertContains(CookieNameEnum::getCookieCategoryName('marketing'), $names);
    }
}
