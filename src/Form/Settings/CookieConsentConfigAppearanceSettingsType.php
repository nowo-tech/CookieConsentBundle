<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Form\Settings;

use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Settings form for the Appearance admin section.
 */
final class CookieConsentConfigAppearanceSettingsType extends AbstractCookieConsentConfigSettingsType
{
    /**
     * @param FormBuilderInterface<CookieConsentConfig|null> $builder
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $labelPrefix  = $options['label_prefix'];
        $choicePrefix = $options['choice_label_prefix'];

        $colorThemeChoices = [];

        foreach (CookieConsentConfig::COLOR_THEMES as $theme) {
            $colorThemeChoices[$choicePrefix . 'color_theme.' . $theme] = $theme;
        }

        $builder
            ->add('colorTheme', ChoiceType::class, [
                'label'   => $labelPrefix . 'color_theme',
                'help'    => $labelPrefix . 'color_theme_help',
                'choices' => $colorThemeChoices,
            ])
            ->add('disablePageInteraction', CheckboxType::class, [
                'label'    => $labelPrefix . 'disable_page_interaction',
                'help'     => $labelPrefix . 'disable_page_interaction_help',
                'required' => false,
                'attr'     => ['id' => 'nowo-cookie-consent-overlay-setting'],
            ])
            ->add('darkModeEnabled', CheckboxType::class, [
                'label'    => $labelPrefix . 'dark_mode_enabled',
                'help'     => $labelPrefix . 'dark_mode_enabled_help',
                'required' => false,
            ])
            ->add('disableTransitions', CheckboxType::class, [
                'label'    => $labelPrefix . 'disable_transitions',
                'help'     => $labelPrefix . 'disable_transitions_help',
                'required' => false,
            ])
            ->add('twoStepModal', CheckboxType::class, [
                'label'    => $labelPrefix . 'two_step_modal',
                'help'     => $labelPrefix . 'two_step_modal_help',
                'required' => false,
            ])
            ->add('openPreferencesModal', CheckboxType::class, [
                'label'    => $labelPrefix . 'open_preferences_modal',
                'help'     => $labelPrefix . 'open_preferences_modal_help',
                'required' => false,
            ]);
    }
}
