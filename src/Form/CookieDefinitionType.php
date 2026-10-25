<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Form;

use Nowo\CookieConsentBundle\Entity\CookieDefinition;
use Nowo\CookieConsentBundle\Entity\CookieDefinitionTranslation;
use Nowo\CookieConsentBundle\Enum\CategoryEnum;
use Nowo\CookieConsentBundle\Locale\LocaleResolver;
use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function array_values;
use function in_array;
use function is_string;
use function trim;

/**
 * Symfony form type for creating and editing cookie inventory definitions.
 *
 * Translations (1.18): one fixed entry per enabled locale (`translations[<locale>]`, rendered as locale tabs). The
 * field is unmapped: on submit a filled entry is attached to the definition, an emptied one (no provider, no purpose)
 * is removed, and translations of locales that are not enabled are never touched.
 *
 * @extends AbstractType<CookieDefinition>
 *
 * @final since 1.19, will be final in 2.0 (not an extension point; decorate or replace the service instead)
 */
#[FormKitConfig('cookie_consent')]
class CookieDefinitionType extends AbstractType
{
    use FormOptionsTrait {
        resolveFieldOptions as private resolveFieldOptionsFromFormKit;
    }

    /**
     * Uses the locale resolver, when available, to list the translation locales.
     */
    public function __construct(
        private readonly ?LocaleResolver $localeResolver = null,
    ) {
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
        /** @var list<string> $locales */
        $locales = $options['enabled_locales'];
        $this->addWithDefaults($builder, 'translations', CollectionType::class, [
            'entry_type'    => CookieDefinitionTranslationType::class,
            'mapped'        => false,
            'allow_add'     => false,
            'allow_delete'  => false,
            'label'         => $options['label_prefix'] . 'translations',
            'entry_options' => [
                'label_prefix'       => $options['label_prefix'],
                'translation_domain' => $options['translation_domain'],
                'error_bubbling'     => false,
            ],
        ]);

        $builder->addEventListener(FormEvents::POST_SET_DATA, static function (FormEvent $event) use ($locales): void {
            $definition = $event->getData();
            if (!$definition instanceof CookieDefinition) {
                return;
            }
            $entries = [];
            foreach ($locales as $locale) {
                $entries[$locale] = $definition->findTranslation($locale) ?? (new CookieDefinitionTranslation())->setLocale($locale);
            }
            $event->getForm()->get('translations')->setData($entries);
        });

        $builder->addEventListener(FormEvents::SUBMIT, static function (FormEvent $event) use ($locales): void {
            $definition = $event->getData();
            if (!$definition instanceof CookieDefinition) {
                return;
            }
            $translations = $event->getForm()->get('translations');
            foreach ($locales as $locale) {
                if (!$translations->has($locale)) {
                    continue;
                }
                $translation = $translations->get($locale)->getData();
                if (!$translation instanceof CookieDefinitionTranslation) {
                    continue;
                }
                // The locale is the tab, never the submitted value.
                $translation->setLocale($locale);
                $blank    = trim($translation->getProvider()) === '' && trim($translation->getPurpose()) === '';
                $attached = $definition->getTranslations()->contains($translation);
                if ($blank && $attached) {
                    $definition->removeTranslation($translation);
                } elseif (!$blank && !$attached) {
                    $definition->addTranslation($translation);
                }
            }
        });
    }

    /**
     * Exposes the locale tabs settings and the translations of locales that are not enabled (kept, read-only).
     *
     * @param array<string, mixed> $options
     */
    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        /** @var list<string> $locales */
        $locales                          = $options['enabled_locales'];
        $view->vars['enabled_locales']    = $locales;
        $view->vars['default_locale']     = $options['default_locale'];
        $view->vars['other_translations'] = [];
        /** @var mixed $definition null on a form created without data */
        $definition = $form->getData();
        if (!$definition instanceof CookieDefinition) {
            return;
        }
        foreach ($definition->getTranslations() as $translation) {
            if (!in_array($translation->getLocale(), $locales, true)) {
                $view->vars['other_translations'][$translation->getLocale()] = [
                    'provider' => $translation->getProvider(),
                    'purpose'  => $translation->getPurpose(),
                ];
            }
        }
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
            'enabled_locales'       => $this->localeResolver?->getEnabledLocales() ?? ['en'],
            'default_locale'        => null,
        ]);
        $resolver->setAllowedTypes('enabled_locales', 'string[]');
        $resolver->setAllowedTypes('default_locale', ['null', 'string']);
        $resolver->setNormalizer('enabled_locales', static fn (OptionsResolver $resolver, array $locales): array => array_values($locales));
        $resolver->setNormalizer('default_locale', function (OptionsResolver $resolver, ?string $locale): string {
            /** @var list<string> $locales */
            $locales = $resolver['enabled_locales'];
            $locale ??= $this->localeResolver?->getDefaultLocale();

            return is_string($locale) && in_array($locale, $locales, true) ? $locale : ($locales[0] ?? 'en');
        });
    }
}
