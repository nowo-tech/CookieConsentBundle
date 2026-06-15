<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CookieConsentConfigControllerTest extends WebTestCase
{
    private static bool $schemaReady = false;

    public function testIndexPageIsAccessible(): void
    {
        $client = static::createClient();
        $this->ensureDatabaseSchema($client);
        $client->request('GET', '/en/demo/admin/cookie-consent-config');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Cookie consent configuration');
        self::assertSelectorExists('a[href="/en/demo/admin/cookie-consent-config/new-profile"]');
    }

    public function testItalianHomePageShowsTranslatedDemoCopy(): void
    {
        $client = static::createClient();
        $this->ensureDatabaseSchema($client);
        $client->request('GET', '/it/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Cookie Consent Bundle');
        self::assertSelectorTextContains('.list-group-item', 'Consenso salvato');
        self::assertSelectorTextContains('.nowo-cookie-consent__title', 'Impostazioni cookie');
        self::assertSelectorTextContains('.nowo-cookie-consent__category-title', 'Analitici');
    }

    public function testCreateEditAndDeleteConfiguration(): void
    {
        $client = static::createClient();
        $this->ensureDatabaseSchema($client);
        $configId = $this->getDefaultConfigId($client);

        $client->request('GET', sprintf('/en/demo/admin/cookie-consent-config/%d/new', $configId));
        self::assertResponseIsSuccessful();

        $client->submitForm('Create translation', [
            'cookie_consent_config_translation[locale]' => 'fr',
            'cookie_consent_config_translation[consentModalTitle]' => 'Cookies',
            'cookie_consent_config_translation[consentModalDescription]' => 'Nous utilisons des cookies.',
            'cookie_consent_config_translation[consentModalFooter]' => 'En savoir plus',
            'cookie_consent_config_translation[consentModalAcceptAllBtn]' => 'Tout accepter',
            'cookie_consent_config_translation[consentModalAcceptNecessaryBtn]' => 'Fonctionnels',
            'cookie_consent_config_translation[privacyRoute]' => '',
        ]);

        self::assertResponseRedirects(sprintf('/en/demo/admin/cookie-consent-config/%d', $configId));
        $client->followRedirect();
        self::assertSelectorTextContains('table', 'fr');

        $client->clickLink('Edit');
        self::assertResponseIsSuccessful();

        $client->submitForm('Save changes', [
            'cookie_consent_config_translation[locale]' => 'fr',
            'cookie_consent_config_translation[consentModalTitle]' => 'Cookies FR',
            'cookie_consent_config_translation[consentModalDescription]' => 'Texte mis à jour.',
            'cookie_consent_config_translation[consentModalFooter]' => 'Politique',
            'cookie_consent_config_translation[consentModalAcceptAllBtn]' => 'Tout accepter',
            'cookie_consent_config_translation[consentModalAcceptNecessaryBtn]' => 'Fonctionnels',
            'cookie_consent_config_translation[privacyRoute]' => '',
        ]);

        self::assertResponseRedirects(sprintf('/en/demo/admin/cookie-consent-config/%d', $configId));
        $client->followRedirect();
        self::assertSelectorTextContains('table', 'Cookies FR');

        $client->submitForm('Delete');
        self::assertResponseRedirects(sprintf('/en/demo/admin/cookie-consent-config/%d', $configId));
        $client->followRedirect();
        self::assertSelectorTextNotContains('table', 'Cookies FR');
    }

    public function testNavigationLinksToConfigurationCrud(): void
    {
        $client = static::createClient();
        $this->ensureDatabaseSchema($client);
        $client->request('GET', '/en/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a.nav-link[href="/en/demo/admin/cookie-consent-config"]');

        $client->clickLink('Cookie consent config');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Cookie consent configuration');
    }

    public function testRouteTargetingCanExcludeAdminPagesFromAutoOpen(): void
    {
        $client = static::createClient();
        $this->ensureDatabaseSchema($client);
        $configId = $this->getDefaultConfigId($client);

        $client->request('GET', sprintf('/en/demo/admin/cookie-consent-config/%d/settings', $configId));
        self::assertResponseIsSuccessful();

        $client->submitForm('Save settings', $this->buildSettingsFormData([
            'cookie_consent_config_settings[consentModalLayout]' => 'box',
            'cookie_consent_config_settings[consentModalVariant]' => 'wide',
            'cookie_consent_config_settings[consentModalPositionY]' => 'bottom',
            'cookie_consent_config_settings[consentModalPositionX]' => 'center',
            'cookie_consent_config_settings[autoShowRouteMode]' => 'except',
            'cookie_consent_config_settings[autoShowRoutesText]' => 'demo_cookie_consent_config_*',
        ]));

        self::assertResponseRedirects(sprintf('/en/demo/admin/cookie-consent-config/%d', $configId));

        $client->request('GET', '/en/');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#cookieconsent[data-nowo-open="true"]');

        $client->request('GET', '/en/demo/admin/cookie-consent-config');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#cookieconsent[data-nowo-open="false"]');
    }

    public function testRouteProfileSelectsDifferentConsentConfigPerPattern(): void
    {
        $client = static::createClient();
        $this->ensureDatabaseSchema($client);
        $defaultId = $this->getDefaultConfigId($client);

        $client->request('GET', '/en/demo/admin/cookie-consent-config/new-profile');
        self::assertResponseIsSuccessful();

        $client->submitForm('Save settings', $this->buildSettingsFormData([
            'cookie_consent_config_settings[name]' => 'Admin profile',
            'cookie_consent_config_settings[routePatternsText]' => 'demo_cookie_consent_config_*',
            'cookie_consent_config_settings[priority]' => '10',
            'cookie_consent_config_settings[default]' => '',
            'cookie_consent_config_settings[consentModalLayout]' => 'bar',
            'cookie_consent_config_settings[consentModalPositionY]' => 'bottom',
        ]));

        self::assertResponseIsSuccessful();

        $client->request('GET', '/en/demo/admin/cookie-consent-config');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#cookieconsent[data-nowo-layout="bar"]');

        $client->request('GET', '/en/');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#cookieconsent[data-nowo-layout="box"]');
    }

    public function testItalianSettingsPageUsesTranslatedAdminLabels(): void
    {
        $client = static::createClient();
        $this->ensureDatabaseSchema($client);
        $this->getDefaultConfigId($client);

        $client->request('GET', '/it/demo/admin/cookie-consent-config/settings');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Impostazioni di visualizzazione');
        self::assertSelectorTextContains('label', 'Attivo');
        self::assertSelectorTextNotContains('body', 'nowo_cookie_consent.enabled.title');
    }

    public function testColorThemeCanBeChangedToLight(): void
    {
        $client = static::createClient();
        $this->ensureDatabaseSchema($client);
        $configId = $this->getDefaultConfigId($client);

        $client->request('GET', sprintf('/en/demo/admin/cookie-consent-config/%d/settings', $configId));
        self::assertResponseIsSuccessful();

        $client->submitForm('Save settings', $this->buildSettingsFormData([
            'cookie_consent_config_settings[colorTheme]' => 'light',
            'cookie_consent_config_settings[darkModeEnabled]' => '',
            'cookie_consent_config_settings[twoStepModal]' => '1',
        ]));

        self::assertResponseRedirects(sprintf('/en/demo/admin/cookie-consent-config/%d', $configId));

        $client->request('GET', '/en/');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#cookieconsent[data-nowo-color-theme="light"]');
    }

    /**
     * @param array<string, string> $overrides
     *
     * @return array<string, string>
     */
    private function buildSettingsFormData(array $overrides = []): array
    {
        return array_merge([
            'cookie_consent_config_settings[enabled]' => '1',
            'cookie_consent_config_settings[name]' => 'Default',
            'cookie_consent_config_settings[routePatternsText]' => '',
            'cookie_consent_config_settings[priority]' => '0',
            'cookie_consent_config_settings[default]' => '1',
            'cookie_consent_config_settings[autoShow]' => '1',
            'cookie_consent_config_settings[revision]' => '0',
            'cookie_consent_config_settings[manageScriptTags]' => '',
            'cookie_consent_config_settings[autoClearCookies]' => '',
            'cookie_consent_config_settings[hideFromBots]' => '1',
            'cookie_consent_config_settings[disablePageInteraction]' => '',
            'cookie_consent_config_settings[lazyHtmlGeneration]' => '',
            'cookie_consent_config_settings[colorTheme]' => 'dark-turquoise',
            'cookie_consent_config_settings[darkModeEnabled]' => '',
            'cookie_consent_config_settings[disableTransitions]' => '',
            'cookie_consent_config_settings[twoStepModal]' => '',
            'cookie_consent_config_settings[openPreferencesModal]' => '',
            'cookie_consent_config_settings[manageIframePlaceholders]' => '',
            'cookie_consent_config_settings[granularCookieSelection]' => '',
            'cookie_consent_config_settings[preferencesBubbleEnabled]' => '1',
            'cookie_consent_config_settings[preferencesBubblePosition]' => 'bottom-right',
            'cookie_consent_config_settings[consentModalLayout]' => 'box',
            'cookie_consent_config_settings[consentModalVariant]' => 'wide',
            'cookie_consent_config_settings[consentModalPositionY]' => 'bottom',
            'cookie_consent_config_settings[consentModalPositionX]' => 'center',
            'cookie_consent_config_settings[consentModalEqualWeightButtons]' => '',
            'cookie_consent_config_settings[consentModalFlipButtons]' => '',
            'cookie_consent_config_settings[preferencesModalLayout]' => 'box',
            'cookie_consent_config_settings[preferencesModalVariant]' => 'wide',
            'cookie_consent_config_settings[preferencesModalPositionY]' => 'middle',
            'cookie_consent_config_settings[preferencesModalPositionX]' => 'center',
            'cookie_consent_config_settings[preferencesModalEqualWeightButtons]' => '',
            'cookie_consent_config_settings[preferencesModalFlipButtons]' => '',
            'cookie_consent_config_settings[autoShowRouteMode]' => 'all',
            'cookie_consent_config_settings[autoShowRoutesText]' => '',
        ], $overrides);
    }

    private function ensureDatabaseSchema(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client): void
    {
        if (self::$schemaReady) {
            return;
        }

        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $default = (new \Nowo\CookieConsentBundle\Entity\CookieConsentConfig())
            ->setDefault(true)
            ->setEnabled(true)
            ->setName('Default');
        $entityManager->persist($default);
        $entityManager->flush();

        self::$schemaReady = true;
    }

    private function getDefaultConfigId(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client): int
    {
        $config = $client->getContainer()->get(\Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository::class)
            ->findDefaultEnabled();

        self::assertNotNull($config);

        return (int) $config->getId();
    }
}
