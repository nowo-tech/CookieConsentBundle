<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Form;

use Nowo\CookieConsentBundle\Entity\CookieDefinitionTranslation;
use Nowo\CookieConsentBundle\Form\CookieDefinitionTranslationType;
use Symfony\Component\Form\Test\TypeTestCase;

final class CookieDefinitionTranslationTypeTest extends TypeTestCase
{
    public function testBuildsTranslationFields(): void
    {
        $translation = (new CookieDefinitionTranslation())
            ->setLocale('en')
            ->setProvider('Google')
            ->setPurpose('Analytics');

        $form = $this->factory->create(CookieDefinitionTranslationType::class, $translation);

        self::assertTrue($form->has('locale'));
        self::assertTrue($form->has('provider'));
        self::assertTrue($form->has('purpose'));
    }

    public function testSubmitsTranslationData(): void
    {
        $form = $this->factory->create(CookieDefinitionTranslationType::class);
        $form->submit([
            'locale'   => 'es',
            'provider' => 'Meta',
            'purpose'  => 'Marketing pixel',
        ]);

        self::assertTrue($form->isSynchronized());
        /** @var CookieDefinitionTranslation $data */
        $data = $form->getData();
        self::assertSame('es', $data->getLocale());
        self::assertSame('Meta', $data->getProvider());
    }
}
