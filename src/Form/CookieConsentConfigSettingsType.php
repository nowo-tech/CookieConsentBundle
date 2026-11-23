<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Form;

use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Form\Settings\CookieConsentConfigFullSettingsType;
use Symfony\Component\Form\FormBuilderInterface;

use function trigger_deprecation;

/**
 * @deprecated since 1.5.0, use CookieConsentConfigSettingsSection::formType() for tabbed admin,
 *             or CookieConsentConfigFullSettingsType for a single-page editor; removed in 2.0 (runtime notice since
 *             1.35 when the form is built)
 *
 * BC alias: full (all sections) settings form, matching pre-1.5.0 field set.
 *
 * @final since 1.19, will be final in 2.0 (not an extension point; decorate or replace the service instead)
 */
class CookieConsentConfigSettingsType extends CookieConsentConfigFullSettingsType
{
    /** 1.35: the runtime notice of the deprecated alias. */
    public const DEPRECATION = 'The "Nowo\CookieConsentBundle\Form\CookieConsentConfigSettingsType" form type is deprecated since 1.5.0 and removed in 2.0: use "Nowo\CookieConsentBundle\Form\Settings\CookieConsentConfigFullSettingsType" (same fields) or the per-section types of CookieConsentConfigSettingsSection::formType().';

    /**
     * @param FormBuilderInterface<CookieConsentConfig|null> $builder
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        trigger_deprecation('nowo-tech/cookie-consent-bundle', '1.35', self::DEPRECATION);

        parent::buildForm($builder, $options);
    }
}
