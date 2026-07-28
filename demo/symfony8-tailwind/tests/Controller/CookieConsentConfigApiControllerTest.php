<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CookieConsentConfigApiControllerTest extends WebTestCase
{
    public function testConfigEndpointReturnsJsonPayload(): void
    {
        $client = static::createClient();
        $this->ensureDatabaseSchema($client);
        $client->request('GET', '/cookie-consent/config', ['locale' => 'en', 'route' => 'demo_home']);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');

        $payload = json_decode($client->getResponse()->getContent() ?: '', true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(200, $payload['code']);
        self::assertSame("Hello traveller, it's cookie time!", $payload['data']['language']['translations']['en']['consentModal']['title']);
        self::assertArrayHasKey('guiOptions', $payload['data']);
        self::assertArrayHasKey('categories', $payload['data']);
    }

    private function ensureDatabaseSchema(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client): void
    {
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
    }
}
