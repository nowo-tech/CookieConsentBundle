<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Support\Host;

use Nowo\CookieConsentBundle\Cookie\CookieLoggerInterface;

/**
 * A host's own consent log (stands for its own repository: no Doctrine, kept across requests).
 */
final class HostConsentStore implements CookieLoggerInterface
{
    /** @var array<string, array<string, mixed>> consent key => last choices */
    public array $byKey = [];

    public int $writes = 0;

    public function log(array $categories, string $key): void
    {
        ++$this->writes;
        $this->byKey[$key] = $categories;
    }
}
