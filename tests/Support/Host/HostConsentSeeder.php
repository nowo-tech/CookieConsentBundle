<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Support\Host;

use Nowo\CookieConsentBundle\Provisioning\CookieConsentProvisionerInterface;
use Nowo\CookieConsentBundle\Provisioning\CookieDefinitionSpecs;
use Nowo\CookieConsentBundle\Provisioning\DefaultProfileSpec;
use Nowo\CookieConsentBundle\Provisioning\ProvisioningResult;

/**
 * A DDD host's application service (stands for a Beacon-like demo / platform seeder): autowired with the provisioning
 * port only, it never sees an entity, a repository or an entity manager of the bundle.
 */
final readonly class HostConsentSeeder
{
    public function __construct(private CookieConsentProvisionerInterface $provisioner)
    {
    }

    /**
     * @param array<mixed> $profile decoded fixture of the profile
     * @param array<mixed> $cookies decoded fixture of the cookie inventory
     *
     * @return array{0: ProvisioningResult, 1: ProvisioningResult}
     */
    public function seed(array $profile, array $cookies): array
    {
        return [
            $this->provisioner->ensureDefaultProfile(DefaultProfileSpec::fromArray($profile)),
            $this->provisioner->upsertCookieDefinitions(CookieDefinitionSpecs::fromArray($cookies)),
        ];
    }
}
