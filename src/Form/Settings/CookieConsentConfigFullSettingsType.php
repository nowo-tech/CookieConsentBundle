<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Form\Settings;

use Nowo\CookieConsentBundle\Admin\CookieConsentConfigSettingsSection;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Single-page settings form that merges every admin section (demo / legacy hosts).
 *
 * Prefer per-section types via CookieConsentConfigSettingsSection::formType() for tabbed admin.
 */
class CookieConsentConfigFullSettingsType extends AbstractCookieConsentConfigSettingsType
{
    /**
     * Keep the pre-1.5.0 form name for demos / hosts that submit field names under
     * `cookie_consent_config_settings[...]`.
     */
    public function getBlockPrefix(): string
    {
        return 'cookie_consent_config_settings';
    }

    /**
     * @param FormBuilderInterface<CookieConsentConfig|null> $builder
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->rememberTranslationDomain($options);

        foreach (CookieConsentConfigSettingsSection::cases() as $section) {
            $typeClass = $section->formType();
            /** @var AbstractCookieConsentConfigSettingsType $type */
            $type = new $typeClass();
            $this->withFormKit($type)->buildForm($builder, $options);
        }
    }
}
