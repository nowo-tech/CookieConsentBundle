<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Form;

use Nowo\CookieConsentBundle\Entity\CookieDefinitionTranslation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CookieDefinitionTranslationType extends AbstractType
{
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

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'         => CookieDefinitionTranslation::class,
            'translation_domain' => 'NowoCookieConsentBundle',
            'label_prefix'       => 'nowo_cookie_consent.admin.cookie_definition.fields.',
        ]);
    }
}
