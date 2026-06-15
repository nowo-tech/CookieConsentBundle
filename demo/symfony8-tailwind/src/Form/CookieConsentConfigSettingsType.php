<?php

declare(strict_types=1);

namespace App\Form;

use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CookieConsentConfigSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $layoutChoices = [];

        foreach (CookieConsentConfig::CONSENT_MODAL_LAYOUT_TYPES as $layout) {
            $layoutChoices['demo.config.settings.layout.' . $layout] = $layout;
        }

        $routeModeChoices = [];

        foreach (CookieConsentConfig::AUTO_SHOW_ROUTE_MODES as $mode) {
            $routeModeChoices['demo.config.settings.route_mode.' . $mode] = $mode;
        }

        $variantChoices = $this->buildChoicesFromGroups(
            CookieConsentConfig::CONSENT_MODAL_VARIANT_TYPES,
            'demo.config.settings.variant.',
        );
        $positionYChoices = $this->buildChoicesFromGroups(
            CookieConsentConfig::CONSENT_MODAL_POSITION_Y_TYPES,
            'demo.config.settings.position_y.',
        );
        $positionXChoices = $this->buildChoicesFromGroups(
            CookieConsentConfig::CONSENT_MODAL_POSITION_X_TYPES,
            'demo.config.settings.position_x.',
        );

        $colorThemeChoices = [];

        foreach (CookieConsentConfig::COLOR_THEMES as $theme) {
            $colorThemeChoices['demo.config.settings.color_theme.' . $theme] = $theme;
        }

        $builder
            ->add('enabled', CheckboxType::class, [
                'label' => 'demo.config.settings.fields.enabled',
                'help' => 'demo.config.settings.fields.enabled_help',
                'required' => false,
            ])
            ->add('name', TextType::class, [
                'label' => 'demo.config.settings.fields.name',
                'help' => 'demo.config.settings.fields.name_help',
                'required' => false,
            ])
            ->add('routePatternsText', TextareaType::class, [
                'label' => 'demo.config.settings.fields.route_patterns',
                'help' => 'demo.config.settings.fields.route_patterns_help',
                'required' => false,
                'attr' => [
                    'rows' => 4,
                    'placeholder' => "demo_admin_*\ndemo_cookie_consent_config_*",
                ],
            ])
            ->add('priority', IntegerType::class, [
                'label' => 'demo.config.settings.fields.priority',
                'help' => 'demo.config.settings.fields.priority_help',
            ])
            ->add('default', CheckboxType::class, [
                'label' => 'demo.config.settings.fields.default_profile',
                'help' => 'demo.config.settings.fields.default_profile_help',
                'required' => false,
            ])
            ->add('autoShow', CheckboxType::class, [
                'label' => 'demo.config.settings.fields.auto_show',
                'help' => 'demo.config.settings.fields.auto_show_help',
                'required' => false,
            ])
            ->add('revision', IntegerType::class, [
                'label' => 'demo.config.settings.fields.revision',
                'help' => 'demo.config.settings.fields.revision_help',
            ])
            ->add('manageScriptTags', CheckboxType::class, [
                'label' => 'demo.config.settings.fields.manage_script_tags',
                'help' => 'demo.config.settings.fields.manage_script_tags_help',
                'required' => false,
            ])
            ->add('autoClearCookies', CheckboxType::class, [
                'label' => 'demo.config.settings.fields.auto_clear_cookies',
                'help' => 'demo.config.settings.fields.auto_clear_cookies_help',
                'required' => false,
            ])
            ->add('hideFromBots', CheckboxType::class, [
                'label' => 'demo.config.settings.fields.hide_from_bots',
                'help' => 'demo.config.settings.fields.hide_from_bots_help',
                'required' => false,
            ])
            ->add('disablePageInteraction', CheckboxType::class, [
                'label' => 'demo.config.settings.fields.disable_page_interaction',
                'help' => 'demo.config.settings.fields.disable_page_interaction_help',
                'required' => false,
            ])
            ->add('lazyHtmlGeneration', CheckboxType::class, [
                'label' => 'demo.config.settings.fields.lazy_html_generation',
                'help' => 'demo.config.settings.fields.lazy_html_generation_help',
                'required' => false,
            ])
            ->add('colorTheme', ChoiceType::class, [
                'label' => 'demo.config.settings.fields.color_theme',
                'help' => 'demo.config.settings.fields.color_theme_help',
                'choices' => $colorThemeChoices,
            ])
            ->add('darkModeEnabled', CheckboxType::class, [
                'label' => 'demo.config.settings.fields.dark_mode_enabled',
                'help' => 'demo.config.settings.fields.dark_mode_enabled_help',
                'required' => false,
            ])
            ->add('disableTransitions', CheckboxType::class, [
                'label' => 'demo.config.settings.fields.disable_transitions',
                'help' => 'demo.config.settings.fields.disable_transitions_help',
                'required' => false,
            ])
            ->add('twoStepModal', CheckboxType::class, [
                'label' => 'demo.config.settings.fields.two_step_modal',
                'help' => 'demo.config.settings.fields.two_step_modal_help',
                'required' => false,
            ])
            ->add('openPreferencesModal', CheckboxType::class, [
                'label' => 'demo.config.settings.fields.open_preferences_modal',
                'help' => 'demo.config.settings.fields.open_preferences_modal_help',
                'required' => false,
            ])
            ->add('manageIframePlaceholders', CheckboxType::class, [
                'label' => 'demo.config.settings.fields.manage_iframe_placeholders',
                'help' => 'demo.config.settings.fields.manage_iframe_placeholders_help',
                'required' => false,
            ])
            ->add('granularCookieSelection', CheckboxType::class, [
                'label' => 'demo.config.settings.fields.granular_cookie_selection',
                'help' => 'demo.config.settings.fields.granular_cookie_selection_help',
                'required' => false,
            ])
            ->add('preferencesBubbleEnabled', CheckboxType::class, [
                'label' => 'demo.config.settings.fields.preferences_bubble_enabled',
                'help' => 'demo.config.settings.fields.preferences_bubble_enabled_help',
                'required' => false,
            ])
            ->add('preferencesBubblePosition', ChoiceType::class, [
                'label' => 'demo.config.settings.fields.preferences_bubble_position',
                'help' => 'demo.config.settings.fields.preferences_bubble_position_help',
                'choices' => [
                    'demo.config.settings.bubble_position.bottom_right' => CookieConsentConfig::PREFERENCES_BUBBLE_POSITION_BOTTOM_RIGHT,
                    'demo.config.settings.bubble_position.bottom_left' => CookieConsentConfig::PREFERENCES_BUBBLE_POSITION_BOTTOM_LEFT,
                    'demo.config.settings.bubble_position.top_right' => CookieConsentConfig::PREFERENCES_BUBBLE_POSITION_TOP_RIGHT,
                    'demo.config.settings.bubble_position.top_left' => CookieConsentConfig::PREFERENCES_BUBBLE_POSITION_TOP_LEFT,
                ],
            ])
            ->add('consentModalLayout', ChoiceType::class, [
                'label' => 'demo.config.settings.fields.consent_modal_layout',
                'help' => 'demo.config.settings.fields.consent_modal_layout_help',
                'choices' => $layoutChoices,
            ])
            ->add('consentModalVariant', ChoiceType::class, [
                'label' => 'demo.config.settings.fields.consent_modal_variant',
                'help' => 'demo.config.settings.fields.consent_modal_variant_help',
                'choices' => $variantChoices,
            ])
            ->add('consentModalPositionY', ChoiceType::class, [
                'label' => 'demo.config.settings.fields.consent_modal_position_y',
                'help' => 'demo.config.settings.fields.consent_modal_position_y_help',
                'choices' => $positionYChoices,
            ])
            ->add('consentModalPositionX', ChoiceType::class, [
                'label' => 'demo.config.settings.fields.consent_modal_position_x',
                'help' => 'demo.config.settings.fields.consent_modal_position_x_help',
                'choices' => $positionXChoices,
                'required' => false,
                'placeholder' => 'demo.config.settings.position_x.none',
            ])
            ->add('consentModalEqualWeightButtons', CheckboxType::class, [
                'label' => 'demo.config.settings.fields.consent_modal_equal_weight_buttons',
                'help' => 'demo.config.settings.fields.consent_modal_equal_weight_buttons_help',
                'required' => false,
            ])
            ->add('consentModalFlipButtons', CheckboxType::class, [
                'label' => 'demo.config.settings.fields.consent_modal_flip_buttons',
                'help' => 'demo.config.settings.fields.consent_modal_flip_buttons_help',
                'required' => false,
            ])
            ->add('preferencesModalLayout', ChoiceType::class, [
                'label' => 'demo.config.settings.fields.preferences_modal_layout',
                'help' => 'demo.config.settings.fields.preferences_modal_layout_help',
                'choices' => $layoutChoices,
            ])
            ->add('preferencesModalVariant', ChoiceType::class, [
                'label' => 'demo.config.settings.fields.preferences_modal_variant',
                'help' => 'demo.config.settings.fields.preferences_modal_variant_help',
                'choices' => $variantChoices,
            ])
            ->add('preferencesModalPositionY', ChoiceType::class, [
                'label' => 'demo.config.settings.fields.preferences_modal_position_y',
                'help' => 'demo.config.settings.fields.preferences_modal_position_y_help',
                'choices' => $positionYChoices,
            ])
            ->add('preferencesModalPositionX', ChoiceType::class, [
                'label' => 'demo.config.settings.fields.preferences_modal_position_x',
                'help' => 'demo.config.settings.fields.preferences_modal_position_x_help',
                'choices' => $positionXChoices,
                'required' => false,
                'placeholder' => 'demo.config.settings.position_x.none',
            ])
            ->add('preferencesModalEqualWeightButtons', CheckboxType::class, [
                'label' => 'demo.config.settings.fields.preferences_modal_equal_weight_buttons',
                'help' => 'demo.config.settings.fields.preferences_modal_equal_weight_buttons_help',
                'required' => false,
            ])
            ->add('preferencesModalFlipButtons', CheckboxType::class, [
                'label' => 'demo.config.settings.fields.preferences_modal_flip_buttons',
                'help' => 'demo.config.settings.fields.preferences_modal_flip_buttons_help',
                'required' => false,
            ])
            ->add('autoShowRouteMode', ChoiceType::class, [
                'label' => 'demo.config.settings.fields.auto_show_route_mode',
                'help' => 'demo.config.settings.fields.auto_show_route_mode_help',
                'choices' => $routeModeChoices,
            ])
            ->add('autoShowRoutesText', TextareaType::class, [
                'label' => 'demo.config.settings.fields.auto_show_routes',
                'help' => 'demo.config.settings.fields.auto_show_routes_help',
                'required' => false,
                'attr' => [
                    'rows' => 5,
                    'placeholder' => "demo_home\ndemo_admin_*",
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

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CookieConsentConfig::class,
            'translation_domain' => 'messages',
        ]);
    }
}
