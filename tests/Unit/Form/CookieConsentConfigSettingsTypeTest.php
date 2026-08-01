<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Form;

use Nowo\CookieConsentBundle\Admin\CookieConsentConfigSettingsSection;
use Nowo\CookieConsentBundle\Form\CookieConsentConfigSettingsType;
use Nowo\CookieConsentBundle\Form\Settings\CookieConsentConfigAppearanceSettingsType;
use Nowo\CookieConsentBundle\Form\Settings\CookieConsentConfigProfileSettingsType;
use Symfony\Component\Form\Test\TypeTestCase;

use function count;
use function sprintf;

final class CookieConsentConfigSettingsTypeTest extends TypeTestCase
{
    public function testDeprecatedAliasBuildsFullSettingsForm(): void
    {
        $form = $this->factory->create(CookieConsentConfigSettingsType::class);
        $view = $form->createView();

        self::assertTrue($form->has('enabled'));
        self::assertTrue($form->has('autoShow'));
        self::assertTrue($form->has('colorTheme'));
        self::assertTrue($form->has('consentModalLayout'));
        self::assertTrue($form->has('preferencesModalLayout'));
        self::assertTrue($form->has('autoShowRouteMode'));
        self::assertSame('NowoCookieConsentBundle', $view->vars['translation_domain']);
    }

    public function testProfileSectionTypeBuildsOnlyProfileFields(): void
    {
        $form = $this->factory->create(CookieConsentConfigProfileSettingsType::class);

        self::assertTrue($form->has('enabled'));
        self::assertTrue($form->has('name'));
        self::assertFalse($form->has('autoShow'));
        self::assertFalse($form->has('colorTheme'));
    }

    public function testAppearanceSectionTypeBuildsOnlyAppearanceFields(): void
    {
        $form = $this->factory->create(CookieConsentConfigAppearanceSettingsType::class);

        self::assertTrue($form->has('colorTheme'));
        self::assertTrue($form->has('disablePageInteraction'));
        self::assertFalse($form->has('enabled'));
        self::assertFalse($form->has('consentModalLayout'));
    }

    public function testSectionEnumMapsToDedicatedFormTypes(): void
    {
        foreach (CookieConsentConfigSettingsSection::cases() as $section) {
            $form = $this->factory->create($section->formType());
            self::assertGreaterThan(0, count($form->all()), sprintf('Section "%s" form is empty', $section->value));
        }

        self::assertSame(
            CookieConsentConfigProfileSettingsType::class,
            CookieConsentConfigSettingsSection::Profile->formType(),
        );
        self::assertSame(
            CookieConsentConfigAppearanceSettingsType::class,
            CookieConsentConfigSettingsSection::Appearance->formType(),
        );
    }
}
