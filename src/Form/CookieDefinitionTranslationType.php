<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Form;

use Nowo\CookieConsentBundle\Entity\CookieDefinitionTranslation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Symfony form type for a single cookie definition locale translation.
 *
 * @extends AbstractType<CookieDefinitionTranslation>
 */
class CookieDefinitionTranslationType extends AbstractType
{
    /**
     * Builds the translation form fields.
     *
     * @param FormBuilderInterface<CookieDefinitionTranslation|null> $builder The form builder
     * @param array<string, mixed> $options Resolved form options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('locale', TextType::class, [
                'label' => $options['label_prefix'] . 'locale',
                'help'  => $options['label_prefix'] . 'locale_help',
                'attr'  => ['placeholder' => 'en'],
            ])
            ->add('provider', TextType::class, [
                'label' => $options['label_prefix'] . 'provider',
            ])
            ->add('purpose', TextareaType::class, [
                'label' => $options['label_prefix'] . 'purpose',
                'attr'  => ['rows' => 3],
            ]);
    }

    /**
     * Configures default options for the translation form.
     *
     * @param OptionsResolver $resolver The options resolver
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'         => CookieDefinitionTranslation::class,
            'translation_domain' => 'NowoCookieConsentBundle',
            'label_prefix'       => 'nowo_cookie_consent.admin.cookie_definition.fields.',
        ]);
    }
}
