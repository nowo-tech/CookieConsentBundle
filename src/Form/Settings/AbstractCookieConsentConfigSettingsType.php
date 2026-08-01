<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Form\Settings;

use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Shared options for per-section CookieConsentConfig settings forms.
 *
 * @extends AbstractType<CookieConsentConfig>
 */
abstract class AbstractCookieConsentConfigSettingsType extends AbstractType
{
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

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'                   => CookieConsentConfig::class,
            'translation_domain'           => 'NowoCookieConsentBundle',
            'label_prefix'                 => 'nowo_cookie_consent.admin.config.settings.fields.',
            'choice_label_prefix'          => 'nowo_cookie_consent.admin.config.settings.',
            'route_patterns_placeholder'   => "admin_*\ncookie_consent_*",
            'auto_show_routes_placeholder' => "home\nadmin_*",
        ]);

        $resolver->setAllowedTypes('route_patterns_placeholder', 'string');
        $resolver->setAllowedTypes('auto_show_routes_placeholder', 'string');
    }
}
