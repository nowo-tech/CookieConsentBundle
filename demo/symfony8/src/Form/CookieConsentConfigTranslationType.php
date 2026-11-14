<?php

declare(strict_types=1);

namespace App\Form;

use Nowo\CookieConsentBundle\Entity\CookieConsentConfigTranslation;
use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * REQ-DEMO-015: a FormKit form (profile `demo`) with CSRF: the translation texts of a consent profile.
 *
 * @extends AbstractType<CookieConsentConfigTranslation>
 */
#[FormKitConfig('demo')]
final class CookieConsentConfigTranslationType extends AbstractType
{
    use FormOptionsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addWithDefaults($builder, 'locale', TextType::class, [
            'label' => 'demo.config.form.fields.locale',
            'help' => 'demo.config.form.fields.locale_help',
            'attr' => ['placeholder' => 'en'],
        ]);
        $this->addWithDefaults($builder, 'consentModalTitle', TextType::class, [
            'label' => 'demo.config.form.fields.modal_title',
            'help' => 'demo.config.form.fields.modal_title_help',
            'placeholder' => false,
        ]);
        $this->addWithDefaults($builder, 'consentModalDescription', TextareaType::class, [
            'label' => 'demo.config.form.fields.intro',
            'help' => 'demo.config.form.fields.intro_help',
            'attr' => ['rows' => 4],
        ]);
        $this->addWithDefaults($builder, 'consentModalFooter', TextType::class, [
            'label' => 'demo.config.form.fields.read_more',
            'help' => 'demo.config.form.fields.read_more_help',
            'required' => false,
            'placeholder' => false,
        ]);
        $this->addWithDefaults($builder, 'consentModalAcceptAllBtn', TextType::class, [
            'label' => 'demo.config.form.fields.accept_all',
            'help' => 'demo.config.form.fields.accept_all_help',
            'placeholder' => false,
        ]);
        $this->addWithDefaults($builder, 'consentModalAcceptNecessaryBtn', TextType::class, [
            'label' => 'demo.config.form.fields.accept_necessary',
            'help' => 'demo.config.form.fields.accept_necessary_help',
            'placeholder' => false,
        ]);
        $this->addWithDefaults($builder, 'preferencesModalSavePreferencesBtn', TextType::class, [
            'label' => 'demo.config.form.fields.save',
            'help' => 'demo.config.form.fields.save_help',
            'required' => false,
            'placeholder' => false,
        ]);
        $this->addWithDefaults($builder, 'privacyRoute', TextType::class, [
            'label' => 'demo.config.form.fields.privacy_route',
            'help' => 'demo.config.form.fields.privacy_route_help',
            'required' => false,
            'placeholder' => false,
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
