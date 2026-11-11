<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\PersonalData;

use function array_sum;

/**
 * Rows deleted by {@see CookieConsentPersonalDataEraserInterface::erase()} (1.29). A second run on the same subject
 * gives zero.
 */
final readonly class CookieConsentErasureResult
{
    /**
     * @param int $consentLogs consent log rows (`dashboard_cookie_log`) deleted
     */
    public function __construct(
        public int $consentLogs = 0,
    ) {
    }

    /**
     * Counts keyed by kind (event payload and logs).
     *
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return ['consent_logs' => $this->consentLogs];
    }

    /**
     * Total rows deleted.
     */
    public function total(): int
    {
        return array_sum($this->toArray());
    }
}
