<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Admin;

use Nowo\CookieConsentBundle\Admin\CookieConsentConfigSettingsSection;
use PHPUnit\Framework\TestCase;

final class CookieConsentConfigSettingsSectionTest extends TestCase
{
    public function testRouteRequirementListsAllSlugs(): void
    {
        $requirement = CookieConsentConfigSettingsSection::routeRequirement();

        foreach (CookieConsentConfigSettingsSection::cases() as $section) {
            self::assertStringContainsString($section->value, $requirement);
            self::assertNotSame('', $section->translationSuffix());
            self::assertNotEmpty($section->formFields());
        }
    }
}
