<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Form\Settings;

use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
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
        $this->rememberTranslationDomain($options);

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

        $this->addChoice($builder, 'consentModalLayout', [
            'label'   => $labelPrefix . 'consent_modal_layout',
            'help'    => $labelPrefix . 'consent_modal_layout_help',
            'choices' => $layoutChoices,
        ]);
        $this->addChoice($builder, 'consentModalVariant', [
            'label'   => $labelPrefix . 'consent_modal_variant',
            'help'    => $labelPrefix . 'consent_modal_variant_help',
            'choices' => $variantChoices,
        ]);
        $this->addChoice($builder, 'consentModalPositionY', [
            'label'   => $labelPrefix . 'consent_modal_position_y',
            'help'    => $labelPrefix . 'consent_modal_position_y_help',
            'choices' => $positionYChoices,
        ]);
        $this->addChoice($builder, 'consentModalPositionX', [
            'label'       => $labelPrefix . 'consent_modal_position_x',
            'help'        => $labelPrefix . 'consent_modal_position_x_help',
            'choices'     => $positionXChoices,
            'required'    => false,
            'placeholder' => $choicePrefix . 'position_x.none',
        ]);
        $this->addCheckbox($builder, 'consentModalEqualWeightButtons', [
            'label'    => $labelPrefix . 'consent_modal_equal_weight_buttons',
            'help'     => $labelPrefix . 'consent_modal_equal_weight_buttons_help',
            'required' => false,
        ]);
        $this->addCheckbox($builder, 'consentModalFlipButtons', [
            'label'    => $labelPrefix . 'consent_modal_flip_buttons',
            'help'     => $labelPrefix . 'consent_modal_flip_buttons_help',
            'required' => false,
        ]);
    }
}
