<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Form;

use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Symfony form type for editing CookieConsentConfig profile settings.
 */
class CookieConsentConfigSettingsType extends AbstractType
{
    /**
     * Builds the consent profile settings form fields.
     *
     * @param FormBuilderInterface $builder The form builder
     * @param array<string, mixed> $options Resolved form options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $labelPrefix  = $options['label_prefix'];
        $choicePrefix = $options['choice_label_prefix'];

        $layoutChoices = [];

        foreach (CookieConsentConfig::CONSENT_MODAL_LAYOUT_TYPES as $layout) {
            $layoutChoices[$choicePrefix . 'layout.' . $layout] = $layout;
        }

        $routeModeChoices = [];

        foreach (CookieConsentConfig::AUTO_SHOW_ROUTE_MODES as $mode) {
            $routeModeChoices[$choicePrefix . 'route_mode.' . $mode] = $mode;
        }

        $variantChoices = $this->buildChoicesFromGroups(
            CookieConsentConfig::CONSENT_MODAL_VARIANT_TYPES,
            $choicePrefix . 'variant.',
        );
        $positionYChoices = $this->buildChoicesFromGroups(
            CookieConsentConfig::CONSENT_MODAL_POSITION_Y_TYPES,
            $choicePrefix . 'position_y.',
        );
        $positionXChoices = $this->buildChoicesFromGroups(
            CookieConsentConfig::CONSENT_MODAL_POSITION_X_TYPES,
            $choicePrefix . 'position_x.',
        );

        $colorThemeChoices = [];

        foreach (CookieConsentConfig::COLOR_THEMES as $theme) {
            $colorThemeChoices[$choicePrefix . 'color_theme.' . $theme] = $theme;
        }

        $builder
            ->add('enabled', CheckboxType::class, [
                'label'    => $labelPrefix . 'enabled',
                'help'     => $labelPrefix . 'enabled_help',
                'required' => false,
            ])
            ->add('name', TextType::class, [
                'label'    => $labelPrefix . 'name',
                'help'     => $labelPrefix . 'name_help',
                'required' => false,
            ])
            ->add('routePatternsText', TextareaType::class, [
                'label'    => $labelPrefix . 'route_patterns',
                'help'     => $labelPrefix . 'route_patterns_help',
                'required' => false,
                'attr'     => [
                    'rows'        => 4,
                    'placeholder' => $options['route_patterns_placeholder'],
                ],
            ])
            ->add('priority', IntegerType::class, [
                'label' => $labelPrefix . 'priority',
                'help'  => $labelPrefix . 'priority_help',
            ])
            ->add('default', CheckboxType::class, [
                'label'    => $labelPrefix . 'default_profile',
                'help'     => $labelPrefix . 'default_profile_help',
                'required' => false,
            ])
            ->add('autoShow', CheckboxType::class, [
                'label'    => $labelPrefix . 'auto_show',
                'help'     => $labelPrefix . 'auto_show_help',
                'required' => false,
            ])
            ->add('revision', IntegerType::class, [
                'label' => $labelPrefix . 'revision',
                'help'  => $labelPrefix . 'revision_help',
            ])
            ->add('manageScriptTags', CheckboxType::class, [
                'label'    => $labelPrefix . 'manage_script_tags',
                'help'     => $labelPrefix . 'manage_script_tags_help',
                'required' => false,
            ])
            ->add('autoClearCookies', CheckboxType::class, [
                'label'    => $labelPrefix . 'auto_clear_cookies',
                'help'     => $labelPrefix . 'auto_clear_cookies_help',
                'required' => false,
            ])
            ->add('hideFromBots', CheckboxType::class, [
                'label'    => $labelPrefix . 'hide_from_bots',
                'help'     => $labelPrefix . 'hide_from_bots_help',
                'required' => false,
            ])
            ->add('disablePageInteraction', CheckboxType::class, [
                'label'    => $labelPrefix . 'disable_page_interaction',
                'help'     => $labelPrefix . 'disable_page_interaction_help',
                'required' => false,
                'attr'     => ['id' => 'nowo-cookie-consent-overlay-setting'],
            ])
            ->add('lazyHtmlGeneration', CheckboxType::class, [
                'label'    => $labelPrefix . 'lazy_html_generation',
                'help'     => $labelPrefix . 'lazy_html_generation_help',
                'required' => false,
            ])
            ->add('colorTheme', ChoiceType::class, [
                'label'   => $labelPrefix . 'color_theme',
                'help'    => $labelPrefix . 'color_theme_help',
                'choices' => $colorThemeChoices,
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
            ])
            ->add('manageIframePlaceholders', CheckboxType::class, [
                'label'    => $labelPrefix . 'manage_iframe_placeholders',
                'help'     => $labelPrefix . 'manage_iframe_placeholders_help',
                'required' => false,
            ])
            ->add('granularCookieSelection', CheckboxType::class, [
                'label'    => $labelPrefix . 'granular_cookie_selection',
                'help'     => $labelPrefix . 'granular_cookie_selection_help',
                'required' => false,
            ])
            ->add('preferencesBubbleEnabled', CheckboxType::class, [
                'label'    => $labelPrefix . 'preferences_bubble_enabled',
                'help'     => $labelPrefix . 'preferences_bubble_enabled_help',
                'required' => false,
            ])
            ->add('preferencesBubblePosition', ChoiceType::class, [
                'label'   => $labelPrefix . 'preferences_bubble_position',
                'help'    => $labelPrefix . 'preferences_bubble_position_help',
                'choices' => [
                    $choicePrefix . 'bubble_position.bottom_right' => CookieConsentConfig::PREFERENCES_BUBBLE_POSITION_BOTTOM_RIGHT,
                    $choicePrefix . 'bubble_position.bottom_left'  => CookieConsentConfig::PREFERENCES_BUBBLE_POSITION_BOTTOM_LEFT,
                    $choicePrefix . 'bubble_position.top_right'    => CookieConsentConfig::PREFERENCES_BUBBLE_POSITION_TOP_RIGHT,
                    $choicePrefix . 'bubble_position.top_left'     => CookieConsentConfig::PREFERENCES_BUBBLE_POSITION_TOP_LEFT,
                ],
            ])
            ->add('preferencesBubbleBorderColor', ColorType::class, [
                'label'    => $labelPrefix . 'preferences_bubble_border_color',
                'help'     => $labelPrefix . 'preferences_bubble_border_color_help',
                'required' => false,
            ])
            ->add('preferencesBubbleIcon', TextareaType::class, [
                'label'    => $labelPrefix . 'preferences_bubble_icon',
                'help'     => $labelPrefix . 'preferences_bubble_icon_help',
                'required' => false,
                'attr'     => [
                    'rows'  => 4,
                    'class' => 'font-monospace',
                ],
            ])
            ->add('consentModalLayout', ChoiceType::class, [
                'label'   => $labelPrefix . 'consent_modal_layout',
                'help'    => $labelPrefix . 'consent_modal_layout_help',
                'choices' => $layoutChoices,
            ])
            ->add('consentModalVariant', ChoiceType::class, [
                'label'   => $labelPrefix . 'consent_modal_variant',
                'help'    => $labelPrefix . 'consent_modal_variant_help',
                'choices' => $variantChoices,
            ])
            ->add('consentModalPositionY', ChoiceType::class, [
                'label'   => $labelPrefix . 'consent_modal_position_y',
                'help'    => $labelPrefix . 'consent_modal_position_y_help',
                'choices' => $positionYChoices,
            ])
            ->add('consentModalPositionX', ChoiceType::class, [
                'label'       => $labelPrefix . 'consent_modal_position_x',
                'help'        => $labelPrefix . 'consent_modal_position_x_help',
                'choices'     => $positionXChoices,
                'required'    => false,
                'placeholder' => $choicePrefix . 'position_x.none',
            ])
            ->add('consentModalEqualWeightButtons', CheckboxType::class, [
                'label'    => $labelPrefix . 'consent_modal_equal_weight_buttons',
                'help'     => $labelPrefix . 'consent_modal_equal_weight_buttons_help',
                'required' => false,
            ])
            ->add('consentModalFlipButtons', CheckboxType::class, [
                'label'    => $labelPrefix . 'consent_modal_flip_buttons',
                'help'     => $labelPrefix . 'consent_modal_flip_buttons_help',
                'required' => false,
            ])
            ->add('preferencesModalLayout', ChoiceType::class, [
                'label'   => $labelPrefix . 'preferences_modal_layout',
                'help'    => $labelPrefix . 'preferences_modal_layout_help',
                'choices' => $layoutChoices,
            ])
            ->add('preferencesModalVariant', ChoiceType::class, [
                'label'   => $labelPrefix . 'preferences_modal_variant',
                'help'    => $labelPrefix . 'preferences_modal_variant_help',
                'choices' => $variantChoices,
            ])
            ->add('preferencesModalPositionY', ChoiceType::class, [
                'label'   => $labelPrefix . 'preferences_modal_position_y',
                'help'    => $labelPrefix . 'preferences_modal_position_y_help',
                'choices' => $positionYChoices,
            ])
            ->add('preferencesModalPositionX', ChoiceType::class, [
                'label'       => $labelPrefix . 'preferences_modal_position_x',
                'help'        => $labelPrefix . 'preferences_modal_position_x_help',
                'choices'     => $positionXChoices,
                'required'    => false,
                'placeholder' => $choicePrefix . 'position_x.none',
            ])
            ->add('preferencesModalEqualWeightButtons', CheckboxType::class, [
                'label'    => $labelPrefix . 'preferences_modal_equal_weight_buttons',
                'help'     => $labelPrefix . 'preferences_modal_equal_weight_buttons_help',
                'required' => false,
            ])
            ->add('preferencesModalFlipButtons', CheckboxType::class, [
                'label'    => $labelPrefix . 'preferences_modal_flip_buttons',
                'help'     => $labelPrefix . 'preferences_modal_flip_buttons_help',
                'required' => false,
            ])
            ->add('autoShowRouteMode', ChoiceType::class, [
                'label'   => $labelPrefix . 'auto_show_route_mode',
                'help'    => $labelPrefix . 'auto_show_route_mode_help',
                'choices' => $routeModeChoices,
            ])
            ->add('autoShowRoutesText', TextareaType::class, [
                'label'    => $labelPrefix . 'auto_show_routes',
                'help'     => $labelPrefix . 'auto_show_routes_help',
                'required' => false,
                'attr'     => [
                    'rows'        => 5,
                    'placeholder' => $options['auto_show_routes_placeholder'],
                ],
            ]);
    }

    /**
     * @param array<string, list<string>> $groups
     *
     * @return array<string, string>
     */
    private function buildChoicesFromGroups(array $groups, string $translationPrefix): array
    {
        $choices = [];

        foreach (array_unique(array_merge(...array_values($groups))) as $value) {
            $choices[$translationPrefix . $value] = $value;
        }

        return $choices;
    }

    /**
     * Configures default options for the consent profile settings form.
     *
     * @param OptionsResolver $resolver The options resolver
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'                   => CookieConsentConfig::class,
            'translation_domain'           => 'NowoCookieConsentBundle',
            'label_prefix'                 => 'nowo_cookie_consent.admin.config.settings.fields.',
            'choice_label_prefix'          => 'nowo_cookie_consent.admin.config.settings.',
            'route_patterns_placeholder'   => "admin_*\ncookie_consent_*",
            'auto_show_routes_placeholder' => "home\nadmin_*",
        ]);

        $resolver->setAllowedTypes('route_patterns_placeholder', 'string');
        $resolver->setAllowedTypes('auto_show_routes_placeholder', 'string');
    }
}
