<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CookieConsentConfigApiControllerTest extends WebTestCase
{
    public function testConfigEndpointReturnsJsonPayload(): void
    {
        $client = static::createClient();
        $client->request('GET', '/cookie-consent/config', ['locale' => 'en', 'route' => 'demo_home']);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');

        $payload = json_decode($client->getResponse()->getContent() ?: '', true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(200, $payload['code']);
        self::assertSame('Cookie settings', $payload['data']['language']['translations']['en']['consentModal']['title']);
        self::assertArrayHasKey('guiOptions', $payload['data']);
        self::assertArrayHasKey('categories', $payload['data']);
    }
}
