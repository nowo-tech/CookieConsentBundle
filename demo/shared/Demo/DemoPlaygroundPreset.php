<?php

declare(strict_types=1);

namespace App\Demo;

use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;

/**
 * CookieConsent v3 playground-style defaults for the demo profile.
 *
 * @see https://playground.cookieconsent.orestbida.com/
 */
final class DemoPlaygroundPreset
{
    public static function applyTo(CookieConsentConfig $config): void
    {
        $config
            ->setColorTheme('dark-turquoise')
            ->setDarkModeEnabled(false)
            ->setDisableTransitions(false)
            ->setTwoStepModal(true)
            ->setOpenPreferencesModal(false)
            ->setManageIframePlaceholders(true)
            ->setGranularCookieSelection(true)
            ->setPreferencesBubbleEnabled(true)
            ->setPreferencesBubblePosition(CookieConsentConfig::PREFERENCES_BUBBLE_POSITION_BOTTOM_RIGHT)
            ->setManageScriptTags(true)
            ->setAutoClearCookies(true)
            ->setDisablePageInteraction(true)
            ->setHideFromBots(false)
            ->setConsentModalLayout('box')
            ->setConsentModalVariant('wide')
            ->setConsentModalPositionY('bottom')
            ->setConsentModalPositionX('center')
            ->setConsentModalEqualWeightButtons(true)
            ->setConsentModalFlipButtons(false)
            ->setPreferencesModalLayout('box')
            ->setPreferencesModalVariant('wide')
            ->setPreferencesModalPositionY('middle')
            ->setPreferencesModalPositionX('center')
            ->setPreferencesModalEqualWeightButtons(true)
            ->setPreferencesModalFlipButtons(false);
    }
}
