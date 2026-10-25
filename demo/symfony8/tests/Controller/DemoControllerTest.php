<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * @return iterable<string, array{string}>
     */
    public static function consentButtonProvider(): iterable
    {
        yield 'accept' => ['use_all_cookies'];
        yield 'reject' => ['use_only_functional_cookies'];
        yield 'save preferences' => ['save'];
    }

    #[DataProvider('consentButtonProvider')]
    public function testConsentSubmitSetsConsentCookie(string $button): void
    {
        $client  = static::createClient();
        $crawler = $client->request('GET', '/en/');
        self::assertResponseIsSuccessful();

        // Same-origin XHR post, as the consent script sends it (stateless CSRF validates the Origin header).
        $form = $crawler->filter(sprintf('#cookieconsent button[name="cookie_consent[%s]"]', $button))->form();
        $client->submit($form, [], ['HTTP_ORIGIN' => 'http://localhost']);

        self::assertResponseIsSuccessful();
        self::assertBrowserHasCookie('Cookie_Consent');
        self::assertBrowserHasCookie('Cookie_Consent_Key');
    }
}
