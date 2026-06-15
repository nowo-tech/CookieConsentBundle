<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nowo\CookieConsentBundle\Repository\CookieDefinitionRepository;

#[ORM\Entity(repositoryClass: CookieDefinitionRepository::class)]
#[ORM\Table(name: 'nowo_cookie_consent_cookie_definition')]
#[ORM\UniqueConstraint(name: 'uniq_cookie_definition_config_name', columns: ['config_id', 'name'])]
class CookieDefinition
{
    public const TYPE_FIRST_PARTY = 'first_party';

    public const TYPE_THIRD_PARTY = 'third_party';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    /** @phpstan-ignore property.unusedType */
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $name = '';

    #[ORM\Column(length: 60)]
    private string $duration = '';

    #[ORM\Column(length: 40)]
    private string $category = 'required';

    #[ORM\Column(length: 20)]
    private string $type = self::TYPE_FIRST_PARTY;

    #[ORM\Column(name: 'sort_order', options: ['default' => 0])]
    private int $sortOrder = 0;

    #[ORM\Column(name: 'allowed_by_default', options: ['default' => true])]
    private bool $allowedByDefault = true;

    #[ORM\ManyToOne(targetEntity: CookieConsentConfig::class, inversedBy: 'cookieDefinitions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?CookieConsentConfig $config = null;

    /** @var Collection<int, CookieDefinitionTranslation> */
    #[ORM\OneToMany(targetEntity: CookieDefinitionTranslation::class, mappedBy: 'definition', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $translations;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getDuration(): string
    {
        return $this->duration;
    }

    public function setDuration(string $duration): self
    {
        $this->duration = $duration;

        return $this;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): self
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    public function isAllowedByDefault(): bool
    {
        return $this->allowedByDefault;
    }

    public function setAllowedByDefault(bool $allowedByDefault): self
    {
        $this->allowedByDefault = $allowedByDefault;

        return $this;
    }

    public function getConfig(): ?CookieConsentConfig
    {
        return $this->config;
    }

    public function setConfig(?CookieConsentConfig $config): self
    {
        $this->config = $config;

        return $this;
    }

    /** @return Collection<int, CookieDefinitionTranslation> */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function findTranslation(string $locale): ?CookieDefinitionTranslation
    {
        foreach ($this->translations as $translation) {
            if ($translation->getLocale() === $locale) {
                return $translation;
            }
        }

        return null;
    }

    public function addTranslation(CookieDefinitionTranslation $translation): self
    {
        if (!$this->translations->contains($translation)) {
            $this->translations->add($translation);
            $translation->setDefinition($this);
        }

        return $this;
    }

    public function removeTranslation(CookieDefinitionTranslation $translation): self
    {
        if ($this->translations->removeElement($translation) && $translation->getDefinition() === $this) {
            $translation->setDefinition(null);
        }

        return $this;
    }
}
