<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Integration\Kernel\HostOverride;

use Nowo\CookieConsentBundle\Repository\CookieDefinitionRepository;

/**
 * A host's own replacement of the cookie definition admin controller service: its constructor has no
 * `$hostFlushReporter` (audit M1).
 */
final readonly class HostCookieDefinitionAdminController
{
    public function __construct(public CookieDefinitionRepository $definitionRepository)
    {
    }
}
