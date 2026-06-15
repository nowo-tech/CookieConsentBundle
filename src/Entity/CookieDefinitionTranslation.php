<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nowo\CookieConsentBundle\Repository\CookieDefinitionTranslationRepository;

#[ORM\Entity(repositoryClass: CookieDefinitionTranslationRepository::class)]
#[ORM\Table(name: 'nowo_cookie_consent_cookie_definition_translation')]
#[ORM\UniqueConstraint(name: 'uniq_cookie_definition_translation_locale', columns: ['definition_id', 'locale'])]
/**
 * Locale-specific provider and purpose text for a cookie definition.
 */
class CookieDefinitionTranslation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    /** @phpstan-ignore property.unusedType */
    private ?int $id = null;

    #[ORM\Column(length: 10)]
    private string $locale = 'en';

    #[ORM\Column(length: 120)]
    private string $provider = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $purpose = '';

    #[ORM\ManyToOne(targetEntity: CookieDefinition::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?CookieDefinition $definition = null;

    /**
     * Returns the translation primary key.
     *
     * @return int|null The entity identifier
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Returns the BCP 47 locale code.
     *
     * @return string Locale code (e.g. en, es)
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * Sets the BCP 47 locale code.
     *
     * @param string $locale Locale code
     *
     * @return self Fluent interface
     */
    public function setLocale(string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * Returns the cookie provider or vendor label.
     *
     * @return string Provider name shown in the inventory
     */
    public function getProvider(): string
    {
        return $this->provider;
    }

    /**
     * Sets the cookie provider or vendor label.
     *
     * @param string $provider Provider name
     *
     * @return self Fluent interface
     */
    public function setProvider(string $provider): self
    {
        $this->provider = $provider;

        return $this;
    }

    /**
     * Returns the GDPR purpose description.
     *
     * @return string Purpose text shown in the inventory
     */
    public function getPurpose(): string
    {
        return $this->purpose;
    }

    /**
     * Sets the GDPR purpose description.
     *
     * @param string $purpose Purpose text
     *
     * @return self Fluent interface
     */
    public function setPurpose(string $purpose): self
    {
        $this->purpose = $purpose;

        return $this;
    }

    /**
     * Returns the parent cookie definition.
     *
     * @return CookieDefinition|null The owning definition entity
     */
    public function getDefinition(): ?CookieDefinition
    {
        return $this->definition;
    }

    /**
     * Sets the parent cookie definition.
     *
     * @param CookieDefinition|null $definition The owning definition entity
     *
     * @return self Fluent interface
     */
    public function setDefinition(?CookieDefinition $definition): self
    {
        $this->definition = $definition;

        return $this;
    }
}
