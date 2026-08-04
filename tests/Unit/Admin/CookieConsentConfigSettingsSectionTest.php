<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Admin;

use Nowo\CookieConsentBundle\Admin\CookieConsentConfigSettingsSection;
use Nowo\CookieConsentBundle\Form\Settings\AbstractCookieConsentConfigSettingsType;
use PHPUnit\Framework\TestCase;

final class CookieConsentConfigSettingsSectionTest extends TestCase
{
    public function testRouteRequirementListsAllSlugs(): void
    {
        $requirement = CookieConsentConfigSettingsSection::routeRequirement();

        foreach (CookieConsentConfigSettingsSection::cases() as $section) {
            self::assertStringContainsString($section->value, $requirement);
            self::assertNotSame('', $section->translationSuffix());
            self::assertTrue(class_exists($section->formType()));
            self::assertTrue(is_subclass_of($section->formType(), AbstractCookieConsentConfigSettingsType::class));
        }
    }
}
