<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Entity;

use Nowo\CookieConsentBundle\Entity\CookieDefinition;
use Nowo\CookieConsentBundle\Entity\CookieDefinitionTranslation;
use PHPUnit\Framework\TestCase;

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
        self::assertNull($translation->getId());
    }
}
