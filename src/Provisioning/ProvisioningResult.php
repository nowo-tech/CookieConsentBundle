<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Provisioning;

/**
 * What a provisioning call changed (1.36).
 *
 * The counts are per top-level item: the default profile for {@see CookieConsentProvisionerInterface::ensureDefaultProfile()}
 * (exactly one of the three is 1), one per cookie definition for
 * {@see CookieConsentProvisionerInterface::upsertCookieDefinitions()}. A change of a translation counts as an update
 * of its profile or definition. A second run with the same specification reports everything unchanged.
 */
final readonly class ProvisioningResult
{
    /**
     * @param int $created Items inserted
     * @param int $updated Items whose stored values (or translations) changed
     * @param int $unchanged Items already as specified (nothing written)
     * @param int $profileId Id of the enabled default profile written or checked
     */
    public function __construct(
        public int $created,
        public int $updated,
        public int $unchanged,
        public int $profileId,
    ) {
    }

    /**
     * Whether the call wrote anything.
     */
    public function hasChanges(): bool
    {
        return $this->created + $this->updated > 0;
    }
}
