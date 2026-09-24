<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Form\Settings;

use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Settings form for the Profile admin section.
 */
final class CookieConsentConfigProfileSettingsType extends AbstractCookieConsentConfigSettingsType
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

        $labelPrefix = $options['label_prefix'];

        $this->addCheckbox($builder, 'enabled', [
            'label'    => $labelPrefix . 'enabled',
            'help'     => $labelPrefix . 'enabled_help',
            'required' => false,
        ]);
        $this->addText($builder, 'name', [
            'label'    => $labelPrefix . 'name',
            'help'     => $labelPrefix . 'name_help',
            'required' => false,
        ]);
        $this->addTextarea($builder, 'routePatternsText', [
            'label'    => $labelPrefix . 'route_patterns',
            'help'     => $labelPrefix . 'route_patterns_help',
            'required' => false,
            'attr'     => [
                'rows'        => 4,
                'placeholder' => $options['route_patterns_placeholder'],
            ],
        ]);
        $this->addInteger($builder, 'priority', [
            'label' => $labelPrefix . 'priority',
            'help'  => $labelPrefix . 'priority_help',
        ]);
        $this->addCheckbox($builder, 'default', [
            'label'    => $labelPrefix . 'default_profile',
            'help'     => $labelPrefix . 'default_profile_help',
            'required' => false,
        ]);
    }
}
