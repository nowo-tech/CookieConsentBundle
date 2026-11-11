<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Cookie;

use function array_keys;
use function array_map;
use function in_array;

/**
 * The names of the cookies the visitor's request carries, without any HTTP type (1.30, REQ-ARCH-001 rule 3).
 *
 * Only names: expiring the consent cookies does not need their values. Built per request at the HTTP edge
 * (`Http\ConsentCookieJarApplier::requestCookies()`).
 */
final readonly class ConsentRequestCookies
{
    /** @var list<string> */
    private array $names;

    /**
     * @param list<string> $names The cookie names sent with the request
     */
    public function __construct(array $names)
    {
        $unique = [];
        foreach ($names as $name) {
            $unique[$name] = true;
        }

        $this->names = array_map(strval(...), array_keys($unique));
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return $this->names;
    }

    public function has(string $name): bool
    {
        return in_array($name, $this->names, true);
    }
}
