<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'dashboard_cookie_log')]
/**
 * Doctrine entity storing an anonymized record of a consent category choice.
 */
class CookieConsentLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    /** @phpstan-ignore property.unusedType */
    private ?int $id = null;

    #[ORM\Column(name: 'ip_address', type: Types::STRING, length: 255)]
    private string $ipAddress = '';

    #[ORM\Column(name: 'cookie_consent_key', type: Types::STRING, length: 255)]
    private string $cookieConsentKey = '';

    #[ORM\Column(name: 'cookie_name', type: Types::STRING, length: 255)]
    private string $cookieName = '';

    #[ORM\Column(name: 'cookie_value', type: Types::BOOLEAN)]
    private bool $cookieValue = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $timestamp;

    /**
     * Initializes the consent log timestamp.
     */
    public function __construct()
    {
        $this->timestamp = new DateTimeImmutable();
    }

    /**
     * Returns the consent log primary key.
     *
     * @return int|null The entity identifier
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Sets the anonymized IP address.
     *
     * @param string $ipAddress The anonymized client IP address
     *
     * @return self Fluent interface
     */
    public function setIpAddress(string $ipAddress): self
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    /**
     * Returns the anonymized IP address.
     *
     * @return string The anonymized client IP address
     */
    public function getIpAddress(): string
    {
        return $this->ipAddress;
    }

    /**
     * Sets the anonymous consent key.
     *
     * @param string $cookieConsentKey The consent key cookie value
     *
     * @return self Fluent interface
     */
    public function setCookieConsentKey(string $cookieConsentKey): self
    {
        $this->cookieConsentKey = $cookieConsentKey;

        return $this;
    }

    /**
     * Returns the anonymous consent key.
     *
     * @return string The consent key cookie value
     */
    public function getCookieConsentKey(): string
    {
        return $this->cookieConsentKey;
    }

    /**
     * Sets the logged cookie category name.
     *
     * @param string $cookieName The consent category name
     *
     * @return self Fluent interface
     */
    public function setCookieName(string $cookieName): self
    {
        $this->cookieName = $cookieName;

        return $this;
    }

    /**
     * Returns the logged cookie category name.
     *
     * @return string The consent category name
     */
    public function getCookieName(): string
    {
        return $this->cookieName;
    }

    /**
     * Sets whether the category was accepted.
     *
     * @param bool $cookieValue True when the category was allowed
     *
     * @return self Fluent interface
     */
    public function setCookieValue(bool $cookieValue): self
    {
        $this->cookieValue = $cookieValue;

        return $this;
    }

    /**
     * Returns whether the category was accepted.
     *
     * @return bool True when the category was allowed
     */
    public function getCookieValue(): bool
    {
        return $this->cookieValue;
    }

    /**
     * Sets the consent log timestamp.
     *
     * @param DateTimeImmutable $timestamp The log creation time
     *
     * @return self Fluent interface
     */
    public function setTimestamp(DateTimeImmutable $timestamp): self
    {
        $this->timestamp = $timestamp;

        return $this;
    }

    /**
     * Returns the consent log timestamp.
     *
     * @return DateTimeImmutable The log creation time
     */
    public function getTimestamp(): DateTimeImmutable
    {
        return $this->timestamp;
    }
}
