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

        $builder
            ->add('enabled', CheckboxType::class, [
                'label' => 'demo.config.settings.fields.enabled',
                'required' => false,
            ])
            ->add('name', TextType::class, [
                'label' => 'demo.config.settings.fields.name',
                'required' => false,
            ])
            ->add('routePatternsText', TextareaType::class, [
                'label' => 'demo.config.settings.fields.route_patterns',
                'required' => false,
                'help' => 'demo.config.settings.fields.route_patterns_help',
                'attr' => [
                    'rows' => 4,
                    'placeholder' => "demo_admin_*\ndemo_cookie_consent_config_*",
                ],
            ])
            ->add('priority', IntegerType::class, [
                'label' => 'demo.config.settings.fields.priority',
            ])
            ->add('default', CheckboxType::class, [
                'label' => 'demo.config.settings.fields.default_profile',
                'required' => false,
            ])
            ->add('autoShow', CheckboxType::class, [
                'label' => 'demo.config.settings.fields.auto_show',
                'required' => false,
            ])
            ->add('revision', IntegerType::class, [
                'label' => 'demo.config.settings.fields.revision',
            ])
            ->add('manageScriptTags', CheckboxType::class, [
                'label' => 'demo.config.settings.fields.manage_script_tags',
                'required' => false,
            ])
            ->add('autoClearCookies', CheckboxType::class, [
                'label' => 'demo.config.settings.fields.auto_clear_cookies',
                'required' => false,
            ])
            ->add('hideFromBots', CheckboxType::class, [
                'label' => 'demo.config.settings.fields.hide_from_bots',
                'required' => false,
            ])
            ->add('disablePageInteraction', CheckboxType::class, [
                'label' => 'demo.config.settings.fields.disable_page_interaction',
                'required' => false,
            ])
            ->add('lazyHtmlGeneration', CheckboxType::class, [
                'label' => 'demo.config.settings.fields.lazy_html_generation',
                'required' => false,
            ])
            ->add('consentModalLayout', ChoiceType::class, [
                'label' => 'demo.config.settings.fields.consent_modal_layout',
                'choices' => $layoutChoices,
            ])
            ->add('consentModalVariant', ChoiceType::class, [
                'label' => 'demo.config.settings.fields.consent_modal_variant',
                'choices' => $variantChoices,
            ])
            ->add('consentModalPositionY', ChoiceType::class, [
                'label' => 'demo.config.settings.fields.consent_modal_position_y',
                'choices' => $positionYChoices,
            ])
            ->add('consentModalPositionX', ChoiceType::class, [
                'label' => 'demo.config.settings.fields.consent_modal_position_x',
                'choices' => $positionXChoices,
                'required' => false,
                'placeholder' => 'demo.config.settings.position_x.none',
            ])
            ->add('consentModalEqualWeightButtons', CheckboxType::class, [
                'label' => 'demo.config.settings.fields.consent_modal_equal_weight_buttons',
                'required' => false,
            ])
            ->add('consentModalFlipButtons', CheckboxType::class, [
                'label' => 'demo.config.settings.fields.consent_modal_flip_buttons',
                'required' => false,
            ])
            ->add('autoShowRouteMode', ChoiceType::class, [
                'label' => 'demo.config.settings.fields.auto_show_route_mode',
                'choices' => $routeModeChoices,
            ])
            ->add('autoShowRoutesText', TextareaType::class, [
                'label' => 'demo.config.settings.fields.auto_show_routes',
                'required' => false,
                'help' => 'demo.config.settings.fields.auto_show_routes_help',
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
