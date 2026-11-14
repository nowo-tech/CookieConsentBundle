<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Integration\Kernel\HostEntity;

use Doctrine\ORM\Mapping as ORM;
use Nowo\CookieConsentBundle\Tests\Integration\Kernel\DedicatedEntityManagerKernelTest;

/**
 * Host entity found by the catch-all default driver of the host manager in {@see DedicatedEntityManagerKernelTest}.
 */
#[ORM\Entity]
#[ORM\Table(name: 'host_note')]
class HostNote
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;
}
