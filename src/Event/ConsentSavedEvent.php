<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Event;

use DateTimeImmutable;

/**
 * Dispatched once a visitor's consent has been saved (1.26, DDD P2).
 *
 * Timing: after the consent cookies were set on the response and, with `use_logger: true`, after the consent log rows
 * were flushed. When logging fails, the event is not dispatched. The event name is the class name.
 *
 * The payload holds only scalars: the anonymous consent key (the id of the visitor's log rows), the category choices
 * and the per-cookie choices. No IP address, no request, no entity.
 */
final readonly class ConsentSavedEvent
{
    /**
     * @param string $consentKey The anonymous consent key (`Cookie_Consent_Key`)
     * @param array<string, bool> $categories Category slug => allowed (the `required` category is not included)
     * @param array<string, bool> $granularCookies Cookie name => allowed (empty without granular selection)
     * @param bool $logged Whether the consent log rows were written (`use_logger`)
     * @param DateTimeImmutable $occurredAt When the consent was saved
     */
    public function __construct(
        public string $consentKey,
        public array $categories,
        public array $granularCookies,
        public bool $logged,
        public DateTimeImmutable $occurredAt,
    ) {
    }

    /**
     * Returns whether the visitor allowed the given category.
     *
     * @param string $category The category slug
     *
     * @return bool True when the category was allowed (`required` is always allowed)
     */
    public function isCategoryAllowed(string $category): bool
    {
        return $category === 'required' || ($this->categories[$category] ?? false);
    }
}
