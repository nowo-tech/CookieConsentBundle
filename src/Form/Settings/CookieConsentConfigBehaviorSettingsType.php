<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Form\Settings;

use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Settings form for the Behavior admin section.
 */
final class CookieConsentConfigBehaviorSettingsType extends AbstractCookieConsentConfigSettingsType
{
    /**
     * @param FormBuilderInterface<CookieConsentConfig|null> $builder
     * @param array<string, mixed> $options
     *
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $labelPrefix  = $options['label_prefix'];
        $choicePrefix = $options['choice_label_prefix'];

        $builder
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
            ->add('lazyHtmlGeneration', CheckboxType::class, [
                'label'    => $labelPrefix . 'lazy_html_generation',
                'help'     => $labelPrefix . 'lazy_html_generation_help',
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
            ]);
    }
}
