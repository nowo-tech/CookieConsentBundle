<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Form\Settings;

use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Settings form for the Profile admin section.
 */
final class CookieConsentConfigProfileSettingsType extends AbstractCookieConsentConfigSettingsType
{
    /**
     * @param FormBuilderInterface<CookieConsentConfig|null> $builder
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $labelPrefix = $options['label_prefix'];

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
            ]);
    }
}
