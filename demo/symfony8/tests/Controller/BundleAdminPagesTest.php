<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Doctrine\ORM\Tools\SchemaTool;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieDefinition;
use Nowo\CookieConsentBundle\Entity\CookieDefinitionTranslation;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The bundle admin pages (REQ-UI-003 UiKit scaffolds) inside the demo host layout (nowo_ui_kit.layouts.admin).
 */
final class BundleAdminPagesTest extends WebTestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function pages(): iterable
    {
        yield 'cookie inventory' => ['/cookie-consent-config/%d/cookies', 'list'];
        yield 'new cookie' => ['/cookie-consent-config/%d/cookies/new', 'form'];
        yield 'settings (profile)' => ['/cookie-consent-config/%d/settings/profile', 'settings'];
        yield 'settings (appearance)' => ['/cookie-consent-config/%d/settings/appearance', 'settings'];
        yield 'settings (route targeting)' => ['/cookie-consent-config/%d/settings/route-targeting', 'settings'];
    }

    /**
     * @dataProvider pages
     */
    public function testPageRendersInTheHostLayout(string $path, string $pageType): void
    {
        $client = static::createClient();
        $configId = $this->seed($client);

        $client->request('GET', sprintf($path, $configId));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('main#nowo-ui-content[data-nowo-ui-bundle="NowoCookieConsentBundle"] [data-nowo-ui-page="' . $pageType . '"]');
        self::assertSelectorExists('[data-nowo-ui-region="area-nav"]');
        self::assertSelectorExists('html[data-nowo-ui-host-tokens]');
        self::assertSelectorNotExists('.nowo-cookie-consent', 'skip_render_routes: no consent banner on admin pages');
        $html = (string) $client->getResponse()->getContent();
        self::assertStringNotContainsString('.placeholder"', $html);
        self::assertStringNotContainsString('.help<', $html);
    }

    public function testInventoryCreateAndDeleteThroughTheConfirmDialog(): void
    {
        $client = static::createClient();
        $configId = $this->seed($client);

        $crawler = $client->request('GET', sprintf('/cookie-consent-config/%d/cookies', $configId));
        self::assertSelectorTextContains('[data-nowo-ui-region="table"]', '_ga');
        self::assertSelectorExists('table[data-demo-table="cookie-consent"]');
        self::assertCount(1, $crawler->filter('dialog[data-nowo-ui-confirm] form[name^="delete_cookie_definition_"]'));

        $client->request('GET', sprintf('/cookie-consent-config/%d/cookies/new', $configId));
        $client->submitForm('Save cookie', [
            'cookie_definition[name]' => '_gid',
            'cookie_definition[duration]' => '1 day',
            'cookie_definition[category]' => 'analytics',
            'cookie_definition[translations][en][provider]' => 'Google',
            'cookie_definition[translations][en][purpose]' => 'Distinguishes users.',
        ]);
        self::assertResponseRedirects(sprintf('/cookie-consent-config/%d/cookies', $configId));
        $crawler = $client->followRedirect();
        self::assertSelectorTextContains('.nowo-ui-flash', 'Cookie definition created.');
        self::assertSelectorTextContains('[data-nowo-ui-region="table"]', '_gid');

        $form = $crawler->filter('dialog[data-nowo-ui-confirm] form[name^="delete_cookie_definition_"]')->first()->form();
        $client->submit($form);
        self::assertResponseRedirects(sprintf('/cookie-consent-config/%d/cookies', $configId));
        $client->followRedirect();
        self::assertSelectorTextContains('.nowo-ui-flash', 'Cookie definition deleted.');
    }

    public function testSettingsSectionSaves(): void
    {
        $client = static::createClient();
        $configId = $this->seed($client);

        $crawler = $client->request('GET', sprintf('/cookie-consent-config/%d/settings/profile', $configId));
        self::assertSelectorExists('[data-nowo-ui-region="tabs"] a[aria-current="page"][href$="/settings/profile"]');
        $form = $crawler->filter('[data-nowo-ui-region="section"] form')->form();
        $form['cookie_consent_config_profile_settings[name]'] = 'Public site';
        $client->submit($form);

        self::assertResponseRedirects(sprintf('/cookie-consent-config/%d/settings/profile', $configId));
        $client->followRedirect();
        self::assertSelectorTextContains('.nowo-ui-flash', 'Profile settings saved.');
        self::assertInputValueSame('cookie_consent_config_profile_settings[name]', 'Public site');
    }

    public function testDemoProfilePageLinksTheBundleAdmin(): void
    {
        $client = static::createClient();
        $configId = $this->seed($client);

        $client->request('GET', sprintf('/en/demo/admin/cookie-consent-config/%d', $configId));

        self::assertSelectorExists(sprintf('a[data-testid="bundle-admin-link"][href="/cookie-consent-config/%d/cookies"]', $configId));
    }

    private function seed(KernelBrowser $client): int
    {
        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $config = (new CookieConsentConfig())->setDefault(true)->setEnabled(true)->setName('Default');
        $definition = (new CookieDefinition())->setName('_ga')->setCategory('analytics')->setDuration('2 years')
            ->addTranslation((new CookieDefinitionTranslation())->setLocale('en')->setProvider('Google')->setPurpose('Analytics'));
        $config->addCookieDefinition($definition);
        $entityManager->persist($config);
        $entityManager->persist($definition);
        $entityManager->flush();

        return (int) $config->getId();
    }
}
