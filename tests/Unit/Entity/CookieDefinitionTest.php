<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Entity;

use Doctrine\ORM\Mapping\Column;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieDefinition;
use Nowo\CookieConsentBundle\Entity\CookieDefinitionTranslation;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

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

    public function testAllowedByDefaultIsFalseUnlessSetExplicitly(): void
    {
        // Owner decision JD-L6: optional cookies start unticked; an explicit true is still honoured.
        self::assertFalse((new CookieDefinition())->isAllowedByDefault());
        self::assertTrue((new CookieDefinition())->setAllowedByDefault(true)->isAllowedByDefault());
    }

    public function testAllowedByDefaultColumnDefaultsToFalseForNewRows(): void
    {
        $attributes = (new ReflectionProperty(CookieDefinition::class, 'allowedByDefault'))->getAttributes(Column::class);

        self::assertCount(1, $attributes);
        self::assertSame(['default' => false], $attributes[0]->getArguments()['options'] ?? null);
    }

    public function testTranslationsAreKeyedByLocaleUnlessTheLocaleIsEmptyOrTaken(): void
    {
        $definition = new CookieDefinition();
        $es         = (new CookieDefinitionTranslation())->setLocale('es');
        $duplicate  = (new CookieDefinitionTranslation())->setLocale('es');
        $blank      = (new CookieDefinitionTranslation())->setLocale('');
        $definition->addTranslation($es)->addTranslation($duplicate)->addTranslation($blank)->addTranslation($es);

        self::assertSame(['es', 0, 1], array_keys($definition->getTranslations()->toArray()));
        self::assertSame($definition, $blank->getDefinition());
    }
}
