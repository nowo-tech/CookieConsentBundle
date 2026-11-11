<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\PersonalData;

use DateTimeImmutable;

/**
 * Dispatched after a consent-log erasure committed (1.29). The event name is the class name.
 *
 * Only when {@see CookieConsentPersonalDataEraserInterface::erase()} ran its own transactions: inside a transaction the
 * caller opened it is not dispatched (the bundle cannot know whether that transaction commits). The payload holds only
 * scalars: the subject (consent keys, anonymised IP windows with ATOM dates) and the counts.
 */
final readonly class CookieConsentPersonalDataErasedEvent
{
    /**
     * @param list<string> $consentKeys the subject's consent keys
     * @param list<array{ip: string, from: string, to: string}> $ipWindows the subject's IP windows
     * @param array<string, int> $counts rows deleted per kind ({@see CookieConsentErasureResult::toArray()})
     * @param DateTimeImmutable $occurredAt when the erasure finished (bundle clock)
     */
    public function __construct(
        public array $consentKeys,
        public array $ipWindows,
        public array $counts,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
