<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Integration\Kernel\HostEntity;

use Doctrine\ORM\Mapping as ORM;
use Nowo\CookieConsentBundle\Tests\Integration\Kernel\DedicatedEntityManagerKernelTest;

/**
 * Host entity found by the catch-all default driver of the host manager in {@see DedicatedEntityManagerKernelTest};
 * `$title` lets the private-manager tests leave a changed field pending in the application's manager.
 */
#[ORM\Entity]
#[ORM\Table(name: 'host_note')]
class HostNote
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column(nullable: true)]
    public ?string $title = null;
}
