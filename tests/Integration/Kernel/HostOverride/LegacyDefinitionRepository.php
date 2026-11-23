<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Integration\Kernel\HostOverride;

use Doctrine\Persistence\ManagerRegistry;
use Nowo\CookieConsentBundle\Repository\CookieDefinitionRepository;

/**
 * A host's 1.34-style replacement of the cookie definition repository: its constructor has no `$hostFlushReporter`
 * (audit M1).
 */
final class LegacyDefinitionRepository extends CookieDefinitionRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry);
    }
}
