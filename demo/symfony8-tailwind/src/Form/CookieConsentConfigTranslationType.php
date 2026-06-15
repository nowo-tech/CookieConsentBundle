<?php

declare(strict_types=1);

namespace App\Form;

use Nowo\CookieConsentBundle\Entity\CookieConsentConfigTranslation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CookieConsentConfigTranslationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('locale', TextType::class, [
                'label' => 'demo.config.form.fields.locale',
                'help' => 'demo.config.form.fields.locale_help',
                'attr' => ['placeholder' => 'en'],
            ])
            ->add('consentModalTitle', TextType::class, [
                'label' => 'demo.config.form.fields.modal_title',
            ])
            ->add('consentModalDescription', TextareaType::class, [
                'label' => 'demo.config.form.fields.intro',
                'attr' => ['rows' => 4],
            ])
            ->add('consentModalFooter', TextType::class, [
                'label' => 'demo.config.form.fields.read_more',
                'required' => false,
            ])
            ->add('consentModalAcceptAllBtn', TextType::class, [
                'label' => 'demo.config.form.fields.accept_all',
            ])
            ->add('consentModalAcceptNecessaryBtn', TextType::class, [
                'label' => 'demo.config.form.fields.accept_necessary',
            ])
            ->add('preferencesModalSavePreferencesBtn', TextType::class, [
                'label' => 'demo.config.form.fields.save',
                'required' => false,
            ])
            ->add('privacyRoute', TextType::class, [
                'label' => 'demo.config.form.fields.privacy_route',
                'required' => false,
                'help' => 'demo.config.form.fields.privacy_route_help',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CookieConsentConfigTranslation::class,
            'translation_domain' => 'messages',
        ]);
    }
}
