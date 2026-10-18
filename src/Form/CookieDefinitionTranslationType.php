<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Form;

use Nowo\CookieConsentBundle\Entity\CookieDefinitionTranslation;
use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Symfony form type for a single cookie definition locale translation.
 *
 * @extends AbstractType<CookieDefinitionTranslation>
 */
#[FormKitConfig('cookie_consent')]
class CookieDefinitionTranslationType extends AbstractType
{
    use FormOptionsTrait {
        resolveFieldOptions as private resolveFieldOptionsFromFormKit;
    }

    /**
     * Only render placeholders this form type sets explicitly ({@see FormKitPlaceholders}).
     *
     * @param class-string<FormTypeInterface<mixed>> $type
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    protected function resolveFieldOptions(string $name, string $type, array $options = []): array
    {
        return FormKitPlaceholders::resolve($type, $options, fn (array $resolvedOptions): array => $this->resolveFieldOptionsFromFormKit($name, $type, $resolvedOptions));
    }

    /**
     * Builds the translation form fields.
     *
     * @param FormBuilderInterface<CookieDefinitionTranslation|null> $builder The form builder
     * @param array<string, mixed> $options Resolved form options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addText($builder, 'locale', [
            'label'      => $options['label_prefix'] . 'locale',
            'help'       => $options['label_prefix'] . 'locale_help',
            'attr'       => ['placeholder' => 'nowo_cookie_consent.admin.cookie_definition.fields.locale_placeholder'],
            'empty_data' => '',
        ]);
        $this->addText($builder, 'provider', [
            'label'      => $options['label_prefix'] . 'provider',
            'empty_data' => '',
        ]);
        $this->addTextarea($builder, 'purpose', [
            'label'      => $options['label_prefix'] . 'purpose',
            'attr'       => ['rows' => 3],
            'empty_data' => '',
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
