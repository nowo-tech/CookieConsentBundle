<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Form;

use Nowo\CookieConsentBundle\Form\CookieConsentConfigSettingsType;
use Symfony\Component\Form\Test\TypeTestCase;

final class CookieConsentConfigSettingsTypeTest extends TypeTestCase
{
    public function testBuildsProfileSettingsFields(): void
    {
        $form = $this->factory->create(CookieConsentConfigSettingsType::class);
        $view = $form->createView();

        self::assertTrue($form->has('disablePageInteraction'));
        self::assertTrue($form->has('colorTheme'));
        self::assertTrue($form->has('consentModalPositionY'));
        self::assertTrue($form->has('preferencesBubbleBorderColor'));
        self::assertTrue($form->has('preferencesBubbleIcon'));
        self::assertSame('NowoCookieConsentBundle', $view->vars['translation_domain']);
    }
}
