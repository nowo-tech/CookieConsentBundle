<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Form;

use Nowo\CookieConsentBundle\Entity\CookieDefinition;
use Nowo\CookieConsentBundle\Enum\CategoryEnum;
use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Symfony form type for creating and editing cookie inventory definitions.
 *
 * @extends AbstractType<CookieDefinition>
 */
#[FormKitConfig('cookie_consent')]
class CookieDefinitionType extends AbstractType
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
     * Builds the cookie definition form fields.
     *
     * @param FormBuilderInterface<CookieDefinition|null> $builder The form builder
     * @param array<string, mixed> $options Resolved form options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $categoryChoices = [
            $options['category_label_prefix'] . 'required'      => 'required',
            $options['category_label_prefix'] . 'functionality' => 'functionality',
            $options['category_label_prefix'] . 'preferences'   => CategoryEnum::CATEGORY_PREFERENCES,
            $options['category_label_prefix'] . 'analytics'     => CategoryEnum::CATEGORY_ANALYTICS,
            $options['category_label_prefix'] . 'marketing'     => CategoryEnum::CATEGORY_MARKETING,
        ];

        $typeChoices = [
            $options['type_label_prefix'] . 'first_party' => CookieDefinition::TYPE_FIRST_PARTY,
            $options['type_label_prefix'] . 'third_party' => CookieDefinition::TYPE_THIRD_PARTY,
        ];

        // The entity setters take non-null strings / ints: empty submissions map to '' / 0 (empty_data) and the
        // entity constraints (NotBlank / Length) turn them into validation errors (422) instead of a TypeError.
        $this->addText($builder, 'name', [
            'label'      => $options['label_prefix'] . 'name',
            'empty_data' => '',
        ]);
        $this->addText($builder, 'duration', [
            'label'      => $options['label_prefix'] . 'duration',
            'help'       => $options['label_prefix'] . 'duration_help',
            'empty_data' => '',
        ]);
        $this->addChoice($builder, 'category', [
            'label'   => $options['label_prefix'] . 'category',
            'choices' => $categoryChoices,
        ]);
        $this->addChoice($builder, 'type', [
            'label'   => $options['label_prefix'] . 'type',
            'choices' => $typeChoices,
        ]);
        // ChoiceType maps an empty submission to null whatever empty_data says: keep the model a string.
        foreach (['category', 'type'] as $choice) {
            $builder->get($choice)->addModelTransformer(new CallbackTransformer(
                static fn (mixed $value): mixed => $value,
                static fn (mixed $value): mixed => $value ?? '',
            ));
        }
        $this->addInteger($builder, 'sortOrder', [
            'label'      => $options['label_prefix'] . 'sort_order',
            'empty_data' => '0',
        ]);
        $this->addCheckbox($builder, 'allowedByDefault', [
            'label'    => $options['label_prefix'] . 'allowed_by_default',
            'help'     => $options['label_prefix'] . 'allowed_by_default_help',
            'required' => false,
        ]);
        $this->addWithDefaults($builder, 'translations', CollectionType::class, [
            'entry_type'    => CookieDefinitionTranslationType::class,
            'allow_add'     => true,
            'allow_delete'  => true,
            'by_reference'  => false,
            'label'         => $options['label_prefix'] . 'translations',
            'entry_options' => [
                'label_prefix'       => $options['label_prefix'],
                'translation_domain' => $options['translation_domain'],
            ],
        ]);
    }

    /**
     * Configures default options for the cookie definition form.
     *
     * @param OptionsResolver $resolver The options resolver
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'            => CookieDefinition::class,
            'translation_domain'    => 'NowoCookieConsentBundle',
            'label_prefix'          => 'nowo_cookie_consent.admin.cookie_definition.fields.',
            'category_label_prefix' => 'nowo_cookie_consent.admin.cookie_definition.category.',
            'type_label_prefix'     => 'nowo_cookie_consent.admin.cookie_definition.type.',
        ]);
    }
}
