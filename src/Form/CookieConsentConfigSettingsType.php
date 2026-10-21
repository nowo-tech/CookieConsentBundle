<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Form;

use Nowo\CookieConsentBundle\Form\Settings\CookieConsentConfigFullSettingsType;

/**
 * @deprecated since 1.5.0, use CookieConsentConfigSettingsSection::formType() for tabbed admin,
 *             or CookieConsentConfigFullSettingsType for a single-page editor.
 *
 * BC alias: full (all sections) settings form, matching pre-1.5.0 field set.
 *
 * @final since 1.19, will be final in 2.0 (not an extension point; decorate or replace the service instead)
 */
class CookieConsentConfigSettingsType extends CookieConsentConfigFullSettingsType
{
}
