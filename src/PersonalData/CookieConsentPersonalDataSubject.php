<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\PersonalData;

use DateTimeInterface;
use InvalidArgumentException;
use Nowo\CookieConsentBundle\Event\ConsentSavedEvent;

use function array_keys;
use function array_map;
use function array_values;
use function preg_match;
use function sprintf;
use function strlen;

/**
 * Whose consent log rows a GDPR erasure or export covers (1.29).
 *
 * The bundle stores no user id with a consent: a row is the person's when its `cookie_consent_key` (the
 * `Cookie_Consent_Key` cookie of the person's browser, also in {@see ConsentSavedEvent})
 * is one of {@see self::consentKeys()}, or when its anonymised IP and time fall into one of {@see self::ipWindows()}.
 * Keys are compared byte for byte; they are not trimmed or case-folded. Since 1.30.2 (REQ-SEC-006) a key with leading
 * or trailing whitespace (NBSP included), a control or format character (newline, zero-width space, BOM, bidi mark)
 * or invalid UTF-8 is refused: the bundle never writes one, and on a folding collation it would look like another key.
 */
final readonly class CookieConsentPersonalDataSubject
{
    /** Length of the `cookie_consent_key` column. */
    public const MAX_KEY_LENGTH = 255;

    /** Usable key (1.30.2): valid UTF-8, no edge whitespace, no control (Cc), format (Cf) or line / paragraph separator. */
    private const USABLE_KEY = '/\A(?![\s\p{Z}])[^\p{Cc}\p{Cf}\p{Zl}\p{Zp}]*(?<![\s\p{Z}])\z/u';

    /**
     * @param list<CookieConsentIpWindow> $ipWindows
     * @param list<string> $consentKeys
     */
    private function __construct(
        private array $ipWindows,
        private array $consentKeys,
    ) {
    }

    /**
     * A subject seen on one client IP between two instants (both inclusive).
     *
     * @throws InvalidArgumentException see {@see CookieConsentIpWindow::of()}
     */
    public static function fromIpWindow(string $ip, DateTimeInterface $from, DateTimeInterface $to): self
    {
        return new self([CookieConsentIpWindow::of($ip, $from, $to)], []);
    }

    /**
     * A subject known by the consent key of one of their browsers (`Cookie_Consent_Key`).
     *
     * @throws InvalidArgumentException when the key is empty, longer than the column, has whitespace at an edge, holds a
     *                                  control or format character or is not valid UTF-8
     */
    public static function fromConsentKey(string $consentKey): self
    {
        return new self([], [self::assertKey($consentKey)]);
    }

    /**
     * A subject built from several IP windows and / or consent keys (duplicates are dropped).
     *
     * @param iterable<CookieConsentIpWindow> $ipWindows
     * @param iterable<string> $consentKeys
     *
     * @throws InvalidArgumentException when both are empty or a key is invalid
     */
    public static function of(iterable $ipWindows = [], iterable $consentKeys = []): self
    {
        $windows = [];
        foreach ($ipWindows as $window) {
            $windows[self::windowId($window)] = $window;
        }

        $keys = [];
        foreach ($consentKeys as $key) {
            $keys[self::assertKey($key)] = true;
        }

        if ($windows === [] && $keys === []) {
            throw new InvalidArgumentException('A consent personal-data subject needs at least one IP window or consent key.');
        }

        return new self(array_values($windows), array_map(strval(...), array_keys($keys)));
    }

    /**
     * The union of this subject and another one.
     */
    public function merge(self $other): self
    {
        return self::of([...$this->ipWindows, ...$other->ipWindows], [...$this->consentKeys, ...$other->consentKeys]);
    }

    /**
     * The same subject reduced to its consent keys, or null when it has none (IP windows are opt-in for erasure and
     * never used for the export).
     */
    public function withoutIpWindows(): ?self
    {
        if ($this->consentKeys === []) {
            return null;
        }

        return $this->ipWindows === [] ? $this : new self([], $this->consentKeys);
    }

    /**
     * @return list<CookieConsentIpWindow>
     */
    public function ipWindows(): array
    {
        return $this->ipWindows;
    }

    /**
     * @return list<string>
     */
    public function consentKeys(): array
    {
        return $this->consentKeys;
    }

    /**
     * Whether a stored row belongs to this subject: its key is one of the keys, or its IP and time fall into a window.
     */
    public function matches(string $storedKey, string $storedIp, DateTimeInterface $at): bool
    {
        foreach ($this->consentKeys as $key) {
            if ($key === $storedKey) {
                return true;
            }
        }

        foreach ($this->ipWindows as $window) {
            if ($window->contains($storedIp, $at)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The subject as scalars, for events and logs.
     *
     * @return array{consent_keys: list<string>, ip_windows: list<array{ip: string, from: string, to: string}>}
     */
    public function toArray(): array
    {
        return [
            'consent_keys' => $this->consentKeys,
            'ip_windows'   => array_map(static fn (CookieConsentIpWindow $window): array => $window->toArray(), $this->ipWindows),
        ];
    }

    private static function assertKey(string $key): string
    {
        if ($key === '' || strlen($key) > self::MAX_KEY_LENGTH || preg_match(self::USABLE_KEY, $key) !== 1) {
            throw new InvalidArgumentException(sprintf('A consent key must be a non-empty UTF-8 string of at most %d bytes, without whitespace at either end and without control or format characters.', self::MAX_KEY_LENGTH));
        }

        return $key;
    }

    private static function windowId(CookieConsentIpWindow $window): string
    {
        return $window->ip . "\0" . $window->from->format('U.u') . "\0" . $window->to->format('U.u');
    }
}
