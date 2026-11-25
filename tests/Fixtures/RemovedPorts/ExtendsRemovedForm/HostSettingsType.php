<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Fixtures\RemovedPorts\ExtendsRemovedForm;

use Nowo\CookieConsentBundle\Form\CookieConsentConfigSettingsType;

/**
 * An application form type still extending the settings type removed in 2.0: it cannot be loaded (W1-1 fixture).
 */
final class HostSettingsType extends CookieConsentConfigSettingsType
{
}
