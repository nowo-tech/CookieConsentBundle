<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Integration\Kernel\HostRelation;

use Doctrine\ORM\Mapping as ORM;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;

/**
 * A host entity related to a bundle entity (ManyToOne to the consent profile), written by the application's manager
 * through `getReference()` while the bundle uses its private manager (2.0, lesson W1-2).
 */
#[ORM\Entity]
#[ORM\Table(name: 'host_profile_link')]
class HostProfileLink
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CookieConsentConfig::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public ?CookieConsentConfig $profile = null;
}
