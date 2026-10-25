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
        self::assertSelectorExists('a[href="/en/demo/admin/cookie-consent-config"]');
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
        $client  = static::createClient();
        $crawler = $client->request('GET', '/en/');
        // REQ-TWIG-005: the reset button posts a CSRF-only Form Type (FormKit CsrfOnlyType).
        $client->submit($crawler->filter('form[action="/en/demo/reset-consent"]')->form());

        self::assertResponseRedirects('/en/');
        self::assertStringContainsString('Cookie_Consent=', (string) $client->getResponse()->headers->get('Set-Cookie'));

        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[role="alert"]', 'Consent cookies cleared');
        self::assertSelectorExists('#cookieconsent');
    }
}
