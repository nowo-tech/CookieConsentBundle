<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Form;

use Nowo\CookieConsentBundle\Entity\CookieDefinition;
use Nowo\CookieConsentBundle\Enum\CategoryEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Symfony form type for creating and editing cookie inventory definitions.
 *
 * @extends AbstractType<CookieDefinition>
 */
class CookieDefinitionType extends AbstractType
{
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

        $builder
            ->add('name', TextType::class, [
                'label' => $options['label_prefix'] . 'name',
            ])
            ->add('duration', TextType::class, [
                'label' => $options['label_prefix'] . 'duration',
                'help'  => $options['label_prefix'] . 'duration_help',
            ])
            ->add('category', ChoiceType::class, [
                'label'   => $options['label_prefix'] . 'category',
                'choices' => $categoryChoices,
            ])
            ->add('type', ChoiceType::class, [
                'label'   => $options['label_prefix'] . 'type',
                'choices' => $typeChoices,
            ])
            ->add('sortOrder', IntegerType::class, [
                'label' => $options['label_prefix'] . 'sort_order',
            ])
            ->add('allowedByDefault', CheckboxType::class, [
                'label'    => $options['label_prefix'] . 'allowed_by_default',
                'help'     => $options['label_prefix'] . 'allowed_by_default_help',
                'required' => false,
            ])
            ->add('translations', CollectionType::class, [
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
