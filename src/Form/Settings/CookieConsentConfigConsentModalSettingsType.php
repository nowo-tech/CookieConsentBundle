<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Form\Settings;

use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Settings form for the Consent modal admin section.
 */
final class CookieConsentConfigConsentModalSettingsType extends AbstractCookieConsentConfigSettingsType
{
    /**
     * @param FormBuilderInterface<CookieConsentConfig|null> $builder
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $labelPrefix  = $options['label_prefix'];
        $choicePrefix = $options['choice_label_prefix'];

        $layoutChoices = [];

        foreach (CookieConsentConfig::CONSENT_MODAL_LAYOUT_TYPES as $layout) {
            $layoutChoices[$choicePrefix . 'layout.' . $layout] = $layout;
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

        $builder
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
            ]);
    }
}
