<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Form;

use Nowo\CookieConsentBundle\Admin\CookieConsentConfigSettingsSection;
use Nowo\CookieConsentBundle\Form\CookieConsentConfigSettingsType;
use InvalidArgumentException;
use Symfony\Component\Form\Test\TypeTestCase;

final class CookieConsentConfigSettingsTypeTest extends TypeTestCase
{
    public function testBuildsOnlyProfileFieldsByDefault(): void
    {
        $form = $this->factory->create(CookieConsentConfigSettingsType::class);
        $view = $form->createView();

        foreach (CookieConsentConfigSettingsSection::Profile->formFields() as $field) {
            self::assertTrue($form->has($field), sprintf('Expected field "%s"', $field));
        }

        self::assertFalse($form->has('autoShow'));
        self::assertFalse($form->has('colorTheme'));
        self::assertFalse($form->has('consentModalLayout'));
        self::assertSame('NowoCookieConsentBundle', $view->vars['translation_domain']);
    }

    public function testBuildsAppearanceSectionFields(): void
    {
        $form = $this->factory->create(CookieConsentConfigSettingsType::class, null, [
            'section' => CookieConsentConfigSettingsSection::Appearance,
        ]);

        foreach (CookieConsentConfigSettingsSection::Appearance->formFields() as $field) {
            self::assertTrue($form->has($field), sprintf('Expected field "%s"', $field));
        }

        self::assertFalse($form->has('enabled'));
        self::assertFalse($form->has('consentModalLayout'));
    }

    public function testAcceptsSectionSlugString(): void
    {
        $form = $this->factory->create(CookieConsentConfigSettingsType::class, null, [
            'section' => 'behavior',
        ]);

        self::assertTrue($form->has('autoShow'));
        self::assertFalse($form->has('enabled'));
    }

    public function testRejectsUnknownSection(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->factory->create(CookieConsentConfigSettingsType::class, null, [
            'section' => 'unknown',
        ]);
    }
}
