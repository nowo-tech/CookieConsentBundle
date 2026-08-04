<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Form\Settings;

use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Settings form for the Appearance admin section.
 */
final class CookieConsentConfigAppearanceSettingsType extends AbstractCookieConsentConfigSettingsType
{
    /**
     * @param FormBuilderInterface<CookieConsentConfig|null> $builder
     * @param array<string, mixed> $options
     *
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->rememberTranslationDomain($options);

        $labelPrefix  = $options['label_prefix'];
        $choicePrefix = $options['choice_label_prefix'];

        $colorThemeChoices = [];

        foreach (CookieConsentConfig::COLOR_THEMES as $theme) {
            $colorThemeChoices[$choicePrefix . 'color_theme.' . $theme] = $theme;
        }

        $this->addChoice($builder, 'colorTheme', [
            'label'   => $labelPrefix . 'color_theme',
            'help'    => $labelPrefix . 'color_theme_help',
            'choices' => $colorThemeChoices,
        ]);
        $this->addCheckbox($builder, 'disablePageInteraction', [
            'label'    => $labelPrefix . 'disable_page_interaction',
            'help'     => $labelPrefix . 'disable_page_interaction_help',
            'required' => false,
            'attr'     => ['id' => 'nowo-cookie-consent-overlay-setting'],
        ]);
        $this->addCheckbox($builder, 'darkModeEnabled', [
            'label'    => $labelPrefix . 'dark_mode_enabled',
            'help'     => $labelPrefix . 'dark_mode_enabled_help',
            'required' => false,
        ]);
        $this->addCheckbox($builder, 'disableTransitions', [
            'label'    => $labelPrefix . 'disable_transitions',
            'help'     => $labelPrefix . 'disable_transitions_help',
            'required' => false,
        ]);
        $this->addCheckbox($builder, 'twoStepModal', [
            'label'    => $labelPrefix . 'two_step_modal',
            'help'     => $labelPrefix . 'two_step_modal_help',
            'required' => false,
        ]);
        $this->addCheckbox($builder, 'openPreferencesModal', [
            'label'    => $labelPrefix . 'open_preferences_modal',
            'help'     => $labelPrefix . 'open_preferences_modal_help',
            'required' => false,
        ]);
    }
}
