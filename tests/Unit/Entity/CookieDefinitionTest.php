<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Entity;

use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieDefinition;
use Nowo\CookieConsentBundle\Entity\CookieDefinitionTranslation;
use PHPUnit\Framework\TestCase;

final class CookieDefinitionTest extends TestCase
{
    public function testGettersSettersAndTranslations(): void
    {
        $config      = (new CookieConsentConfig())->setName('Default');
        $definition  = new CookieDefinition();
        $translation = (new CookieDefinitionTranslation())->setLocale('en');

        $definition
            ->setName('_ga')
            ->setDuration('2 years')
            ->setCategory('analytics')
            ->setType(CookieDefinition::TYPE_THIRD_PARTY)
            ->setSortOrder(3)
            ->setAllowedByDefault(false)
            ->setConfig($config)
            ->addTranslation($translation);

        self::assertSame('_ga', $definition->getName());
        self::assertSame('2 years', $definition->getDuration());
        self::assertSame('analytics', $definition->getCategory());
        self::assertSame(CookieDefinition::TYPE_THIRD_PARTY, $definition->getType());
        self::assertSame(3, $definition->getSortOrder());
        self::assertFalse($definition->isAllowedByDefault());
        self::assertSame($config, $definition->getConfig());
        self::assertSame($translation, $definition->findTranslation('en'));
        self::assertCount(1, $definition->getTranslations());

        $definition->removeTranslation($translation);
        self::assertNull($definition->findTranslation('en'));
        self::assertNull($translation->getDefinition());
    }
}

final class CookieDefinitionTranslationTest extends TestCase
{
    public function testGettersSettersAndDefinitionRelation(): void
    {
        $definition  = new CookieDefinition();
        $translation = new CookieDefinitionTranslation();

        $translation
            ->setLocale('fr')
            ->setProvider('Provider')
            ->setPurpose('Purpose')
            ->setDefinition($definition);

        self::assertSame('fr', $translation->getLocale());
        self::assertSame('Provider', $translation->getProvider());
        self::assertSame('Purpose', $translation->getPurpose());
        self::assertSame($definition, $translation->getDefinition());
    }
}
