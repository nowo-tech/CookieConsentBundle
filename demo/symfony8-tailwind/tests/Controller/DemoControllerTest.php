<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DemoControllerTest extends WebTestCase
{
    public function testHomePageIsAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/en/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Cookie Consent Bundle');
        self::assertSelectorExists('a.nav-link[href="/en/demo/admin/cookie-consent-config"]');
    }

    public function testCookieConsentFragmentIsRenderedWhenConsentMissing(): void
    {
        $client = static::createClient();
        $client->request('GET', '/en/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#cookieconsent');
    }

    public function testResetConsentClearsCookiesAndRedirectsHome(): void
    {
        $client = static::createClient();
        $client->request('POST', '/en/demo/reset-consent');

        self::assertResponseRedirects('/en/');
        self::assertStringContainsString('Cookie_Consent=', (string) $client->getResponse()->headers->get('Set-Cookie'));

        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.alert-success', 'Consent cookies cleared');
        self::assertSelectorExists('#cookieconsent');
    }
}
