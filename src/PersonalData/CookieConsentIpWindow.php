<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\PersonalData;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Nowo\CookieConsentBundle\Cookie\ConsentIpAnonymizer;

use function filter_var;
use function preg_match;
use function sprintf;
use function str_contains;
use function strlen;

use const FILTER_VALIDATE_IP;

/**
 * One client IP and the period in which the person used it (1.29, GDPR ports).
 *
 * Consent log rows carry no user id, only the anonymised client IP: an IP alone would also match everyone else behind
 * the same NAT / CGNAT (and IPv4 rows share a /24 once anonymised). A window bounds the match to the time the person was
 * seen on that IP, so only rows of that IP written between {@see self::$from} and {@see self::$to} (inclusive) match.
 *
 * The IP is kept in the form the bundle stores it: a raw IPv4 / IPv6 address is anonymised exactly as
 * {@see \Nowo\CookieConsentBundle\Cookie\CookieLogger} does before writing (`203.0.113.10` → `203.0.113.xx`,
 * IPv6 → its first 48 bits); an already anonymised IPv4 (`203.0.113.xx`) is kept as given. Nothing is trimmed or
 * case-folded. `unknown` (rows without a valid client IP) is rejected: it is shared by every such visitor.
 */
final readonly class CookieConsentIpWindow
{
    /** Length of the `ip_address` column. */
    public const MAX_IP_LENGTH = 255;

    /**
     * @param string $ip the IP in its stored (anonymised) form
     * @param DateTimeImmutable $from first instant of the window (inclusive)
     * @param DateTimeImmutable $to last instant of the window (inclusive)
     */
    private function __construct(
        public string $ip,
        public DateTimeImmutable $from,
        public DateTimeImmutable $to,
    ) {
    }

    /**
     * Builds a window from a raw or already anonymised client IP and its period.
     *
     * @throws InvalidArgumentException when the IP is empty, too long, holds a NUL byte, is neither an IP address nor an
     *                                  anonymised IPv4, is `unknown`, or when `$from` is after `$to`
     */
    public static function of(string $ip, DateTimeInterface $from, DateTimeInterface $to): self
    {
        if ($ip === '' || str_contains($ip, "\0") || strlen($ip) > self::MAX_IP_LENGTH) {
            throw new InvalidArgumentException(sprintf('A consent IP window needs a non-empty IP of at most %d bytes without NUL bytes.', self::MAX_IP_LENGTH));
        }

        if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
            $ip = ConsentIpAnonymizer::anonymize($ip);
        } elseif (preg_match('/^(?:[0-9A-Fa-f:]*:)?\d{1,3}\.\d{1,3}\.\d{1,3}\.x{1,3}$/D', $ip) !== 1) {
            throw new InvalidArgumentException('A consent IP window needs an IPv4 / IPv6 address or an anonymised IPv4 as stored (e.g. "203.0.113.xx").');
        }

        $from = DateTimeImmutable::createFromInterface($from);
        $to   = DateTimeImmutable::createFromInterface($to);
        if ($from > $to) {
            throw new InvalidArgumentException('A consent IP window must not start after it ends.');
        }

        return new self($ip, $from, $to);
    }

    /**
     * Returns the window widened by the margin on both sides (e.g. `P1D`: one day before the first and after the last
     * request seen on the IP).
     */
    public function widen(DateInterval $margin): self
    {
        return new self($this->ip, $this->from->sub($margin), $this->to->add($margin));
    }

    /**
     * Whether a stored row (its `ip_address` and `timestamp`) falls into this window: the IP must be byte-for-byte
     * equal, whatever the column collation returned.
     */
    public function contains(string $storedIp, DateTimeInterface $at): bool
    {
        return $storedIp === $this->ip && $at >= $this->from && $at <= $this->to;
    }

    /**
     * The window as scalars (ATOM dates), for events and logs.
     *
     * @return array{ip: string, from: string, to: string}
     */
    public function toArray(): array
    {
        return [
            'ip'   => $this->ip,
            'from' => $this->from->format(DateTimeInterface::ATOM),
            'to'   => $this->to->format(DateTimeInterface::ATOM),
        ];
    }
}
