<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Config;

use Nowo\CookieConsentBundle\Config\CookieInventoryNormalizer;
use Nowo\CookieConsentBundle\Config\CookieInventoryProvider;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieDefinition;
use Nowo\CookieConsentBundle\Entity\CookieDefinitionTranslation;
use Nowo\CookieConsentBundle\Repository\CookieDefinitionRepository;
use PHPUnit\Framework\TestCase;

final class CookieInventoryProviderTest extends TestCase
{
    public function testNormalizerSortsAndMapsLegacyProviderFields(): void
    {
        $entries = CookieInventoryNormalizer::normalize([
            [
                'name'         => '_ga',
                'duration'     => '2 years',
                'category'     => 'analytics',
                'sortOrder'    => 20,
                'translations' => [
                    'en' => ['provider' => 'Google', 'purpose' => 'Analytics'],
                ],
            ],
            [
                'name'     => 'PHPSESSID',
                'duration' => 'Session',
                'provider' => 'This site',
                'purpose'  => 'Session id',
            ],
        ]);

        self::assertSame('PHPSESSID', $entries[0]['name']);
        self::assertSame('_ga', $entries[1]['name']);
        self::assertTrue($entries[0]['allowed_by_default']);
    }

    public function testNormalizerMapsAllowedByDefaultFromYaml(): void
    {
        $entries = CookieInventoryNormalizer::normalize([
            [
                'name'               => '_ga',
                'category'           => 'analytics',
                'allowed_by_default' => false,
            ],
        ]);

        self::assertFalse($entries[0]['allowed_by_default']);
    }

    public function testReturnsEmptyWhenDisabled(): void
    {
        $provider = new CookieInventoryProvider(
            $this->createMock(CookieDefinitionRepository::class),
            false,
            [['name' => '_ga', 'duration' => '', 'category' => 'analytics', 'type' => 'first_party', 'sort_order' => 0, 'translations' => []]],
        );

        self::assertSame([], $provider->listForLocale(null, 'en'));
        self::assertFalse($provider->hasDefinitions());
    }

    public function testLoadsFromYamlWhenNoDatabaseDefinitions(): void
    {
        $repository = $this->createMock(CookieDefinitionRepository::class);
        $repository->method('findByConfigOrdered')->willReturn([]);

        $provider = new CookieInventoryProvider($repository, true, [
            [
                'name'         => '_ga',
                'duration'     => '2 years',
                'category'     => 'analytics',
                'type'         => CookieDefinition::TYPE_THIRD_PARTY,
                'sort_order'   => 0,
                'translations' => [
                    'en' => ['provider' => 'Google', 'purpose' => 'Analytics'],
                ],
            ],
        ]);

        $inventory = $provider->listForLocale(null, 'en');

        self::assertCount(1, $inventory);
        self::assertSame('_ga', $inventory[0]['name']);
        self::assertSame('Google', $inventory[0]['provider']);
        self::assertSame('analytics', $inventory[0]['category']);
    }

    public function testPrefersDatabaseDefinitionsOverYaml(): void
    {
        $config     = new CookieConsentConfig();
        $definition = (new CookieDefinition())
            ->setName('_pk_id')
            ->setDuration('13 months')
            ->setCategory('analytics')
            ->setType(CookieDefinition::TYPE_FIRST_PARTY)
            ->addTranslation(
                (new CookieDefinitionTranslation())
                    ->setLocale('en')
                    ->setProvider('Matomo')
                    ->setPurpose('Visitor id'),
            );

        $repository = $this->createMock(CookieDefinitionRepository::class);
        $repository->method('findByConfigOrdered')->willReturn([$definition]);

        $provider = new CookieInventoryProvider($repository, true, [
            [
                'name'         => '_ga',
                'duration'     => '2 years',
                'category'     => 'analytics',
                'type'         => CookieDefinition::TYPE_THIRD_PARTY,
                'sort_order'   => 0,
                'translations' => [
                    'en' => ['provider' => 'Google', 'purpose' => 'Analytics'],
                ],
            ],
        ]);

        $inventory = $provider->listForLocale($config, 'en');

        self::assertCount(1, $inventory);
        self::assertSame('_pk_id', $inventory[0]['name']);
        self::assertSame('Matomo', $inventory[0]['provider']);
    }
}
