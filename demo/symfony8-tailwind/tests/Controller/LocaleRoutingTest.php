<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LocaleRoutingTest extends WebTestCase
{
    public function testRootRedirectsToDefaultLocale(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseRedirects('/en/');
    }

    public function testLocalizedHomeShowsLocaleInUrlAndHtml(): void
    {
        $client = static::createClient();
        $client->request('GET', '/es/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('html[lang="es"]');
        self::assertSelectorTextContains('#demoLocaleDropdown', 'ES');
    }

    public function testLocaleSwitcherKeepsCurrentRoute(): void
    {
        $client = static::createClient();
        $client->request('GET', '/en/demo/admin/cookie-consent-config');

        self::assertResponseIsSuccessful();

        foreach (['en', 'es', 'it', 'fr', 'de', 'pt', 'nl', 'pl', 'ca'] as $locale) {
            self::assertSelectorExists(sprintf('a.dropdown-item[href="/%s/demo/admin/cookie-consent-config"]', $locale));
        }
    }

    public function testUnsupportedLocaleReturnsNotFound(): void
    {
        $client = static::createClient();
        $client->request('GET', '/ru/');

        self::assertResponseStatusCodeSame(404);
    }
}
