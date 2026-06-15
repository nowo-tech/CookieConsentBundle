<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nowo\CookieConsentBundle\Repository\CookieDefinitionTranslationRepository;

#[ORM\Entity(repositoryClass: CookieDefinitionTranslationRepository::class)]
#[ORM\Table(name: 'nowo_cookie_consent_cookie_definition_translation')]
#[ORM\UniqueConstraint(name: 'uniq_cookie_definition_translation_locale', columns: ['definition_id', 'locale'])]
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

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): self
    {
        $this->provider = $provider;

        return $this;
    }

    public function getPurpose(): string
    {
        return $this->purpose;
    }

    public function setPurpose(string $purpose): self
    {
        $this->purpose = $purpose;

        return $this;
    }

    public function getDefinition(): ?CookieDefinition
    {
        return $this->definition;
    }

    public function setDefinition(?CookieDefinition $definition): self
    {
        $this->definition = $definition;

        return $this;
    }
}
