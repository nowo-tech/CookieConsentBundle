<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Form\Settings;

use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Form\FormKitPlaceholders;
use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsMerger;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Translation\TranslatorInterface;

use function array_key_exists;
use function is_string;

/**
 * Shared options for per-section CookieConsentConfig settings forms.
 *
 * @extends AbstractType<CookieConsentConfig>
 */
#[FormKitConfig('cookie_consent')]
abstract class AbstractCookieConsentConfigSettingsType extends AbstractType
{
    use FormOptionsTrait {
        setFormOptionsMerger as private setFormOptionsMergerTrait;
        resolveFieldOptions as private resolveFieldOptionsFromTrait;
    }

    private ?FormOptionsMerger $cookieConsentFormOptionsMerger = null;

    private ?TranslatorInterface $cookieConsentTranslator = null;

    /**
     * Injects the translator used for messages the form adds itself (1.25: the re-consent question).
     *
     * @param TranslatorInterface $translator The translator
     */
    #[Required]
    public function setCookieConsentTranslator(TranslatorInterface $translator): void
    {
        // @igor-ignore - Container-wired translator; same instance for every request.
        $this->cookieConsentTranslator = $translator;
    }

    /**
     * Translates a message, or returns the fallback when no translator is wired (bare form factories).
     *
     * @param string $id The message key
     * @param string $domain The translation domain
     * @param string $fallback The English text used without a translator
     *
     * @return string The translated message
     */
    protected function translate(string $id, string $domain, string $fallback): string
    {
        if (!$this->cookieConsentTranslator instanceof TranslatorInterface) {
            return $fallback;
        }

        $translated = $this->cookieConsentTranslator->trans($id, [], $domain);

        return $translated !== $id ? $translated : $fallback;
    }

    /**
     * Injects the FormKit options merger used to resolve per-field options.
     */
    public function setFormOptionsMerger(FormOptionsMerger $formOptionsMerger): void
    {
        // @igor-ignore - Request-scoped Twig/Form wiring; not a shared worker singleton.
        $this->cookieConsentFormOptionsMerger = $formOptionsMerger;
        $this->setFormOptionsMergerTrait($formOptionsMerger);
    }

    /**
     * Propagate Form Kit merger to section types instantiated outside the form factory
     * (e.g. {@see CookieConsentConfigFullSettingsType}).
     */
    protected function withFormKit(self $type): self
    {
        if ($this->cookieConsentFormOptionsMerger instanceof FormOptionsMerger) {
            $type->setFormOptionsMerger($this->cookieConsentFormOptionsMerger);
        }

        if ($this->cookieConsentTranslator instanceof TranslatorInterface) {
            $type->setCookieConsentTranslator($this->cookieConsentTranslator);
        }

        return $type;
    }

    /**
     * @param array<string, list<string>> $groups
     *
     * @return array<string, string>
     */
    protected function buildChoicesFromGroups(array $groups, string $translationPrefix): array
    {
        $choices = [];

        foreach (array_unique(array_merge(...array_values($groups))) as $value) {
            $choices[$translationPrefix . $value] = $value;
        }

        return $choices;
    }

    /**
     * Declares the options shared by every consent-config settings form.
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'          => CookieConsentConfig::class,
            'translation_domain'  => 'NowoCookieConsentBundle',
            'label_prefix'        => 'nowo_cookie_consent.admin.config.settings.fields.',
            'choice_label_prefix' => 'nowo_cookie_consent.admin.config.settings.',
            // Translation keys of the form domain (the form theme translates the placeholder once).
            'route_patterns_placeholder'   => 'nowo_cookie_consent.admin.config.settings.fields.route_patterns_placeholder',
            'auto_show_routes_placeholder' => 'nowo_cookie_consent.admin.config.settings.fields.auto_show_routes_placeholder',
        ]);

        $resolver->setAllowedTypes('route_patterns_placeholder', 'string');
        $resolver->setAllowedTypes('auto_show_routes_placeholder', 'string');
    }

    /**
     * Prefer the form-level translation_domain (e.g. demos using {@code messages}) over the FormKit profile default,
     * and only render placeholders the form type sets explicitly ({@see FormKitPlaceholders}).
     *
     * @param class-string<FormTypeInterface<mixed>> $type
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    protected function resolveFieldOptions(string $name, string $type, array $options = []): array
    {
        if (!array_key_exists('translation_domain', $options) && $this->activeTranslationDomain !== null) {
            $options['translation_domain'] = $this->activeTranslationDomain;
        }

        return FormKitPlaceholders::resolve($type, $options, fn (array $resolvedOptions): array => $this->resolveFieldOptionsFromTrait($name, $type, $resolvedOptions));
    }

    /**
     * Remember the form translation_domain for subsequent FormKit {@see addWithDefaults()} calls.
     *
     * @param array<string, mixed> $options
     */
    protected function rememberTranslationDomain(array $options): void
    {
        $domain = $options['translation_domain'] ?? null;
        // @igor-ignore - Request-scoped Twig/Form wiring; not a shared worker singleton.
        $this->activeTranslationDomain = is_string($domain) ? $domain : null;
    }

    private ?string $activeTranslationDomain = null;
}
