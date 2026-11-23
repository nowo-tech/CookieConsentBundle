<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Form;

use Nowo\CookieConsentBundle\Admin\CookieConsentConfigSettingsSection;
use Nowo\CookieConsentBundle\Form\CookieConsentConfigSettingsType;
use Nowo\CookieConsentBundle\Form\Settings\CookieConsentConfigAppearanceSettingsType;
use Nowo\CookieConsentBundle\Form\Settings\CookieConsentConfigBehaviorSettingsType;
use Nowo\CookieConsentBundle\Form\Settings\CookieConsentConfigConsentModalSettingsType;
use Nowo\CookieConsentBundle\Form\Settings\CookieConsentConfigPreferencesModalSettingsType;
use Nowo\CookieConsentBundle\Form\Settings\CookieConsentConfigProfileSettingsType;
use Nowo\CookieConsentBundle\Form\Settings\CookieConsentConfigRouteTargetingSettingsType;
use Nowo\CookieConsentBundle\Tests\Support\FormKitTestSupport;
use Symfony\Component\Form\Test\TypeTestCase;

use function count;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;

use const E_USER_DEPRECATED;

final class CookieConsentConfigSettingsTypeTest extends TypeTestCase
{
    /**
     * @return list<object>
     */
    protected function getTypes(): array
    {
        return [
            FormKitTestSupport::withMerger(new CookieConsentConfigSettingsType()),
            FormKitTestSupport::withMerger(new CookieConsentConfigProfileSettingsType()),
            FormKitTestSupport::withMerger(new CookieConsentConfigBehaviorSettingsType()),
            FormKitTestSupport::withMerger(new CookieConsentConfigAppearanceSettingsType()),
            FormKitTestSupport::withMerger(new CookieConsentConfigConsentModalSettingsType()),
            FormKitTestSupport::withMerger(new CookieConsentConfigPreferencesModalSettingsType()),
            FormKitTestSupport::withMerger(new CookieConsentConfigRouteTargetingSettingsType()),
        ];
    }

    public function testDeprecatedAliasBuildsFullSettingsForm(): void
    {
        $deprecations = [];
        // @phpstan-ignore frankenphp.worker.noSetErrorExceptionHandler (test only; restored below)
        set_error_handler(static function (int $level, string $message) use (&$deprecations): bool {
            $deprecations[] = $message;

            return true;
        }, E_USER_DEPRECATED);

        try {
            $form = $this->factory->create(CookieConsentConfigSettingsType::class);
        } finally {
            restore_error_handler();
        }
        $view = $form->createView();

        // 1.35: the deprecated alias announces its removal in 2.0 when it is built.
        self::assertSame(['Since nowo-tech/cookie-consent-bundle 1.35: ' . CookieConsentConfigSettingsType::DEPRECATION], $deprecations);

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
