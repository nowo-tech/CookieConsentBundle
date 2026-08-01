<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Form\Settings;

use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Settings form for the Auto-show route targeting admin section.
 */
final class CookieConsentConfigRouteTargetingSettingsType extends AbstractCookieConsentConfigSettingsType
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

        $routeModeChoices = [];

        foreach (CookieConsentConfig::AUTO_SHOW_ROUTE_MODES as $mode) {
            $routeModeChoices[$choicePrefix . 'route_mode.' . $mode] = $mode;
        }

        $builder
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
}
