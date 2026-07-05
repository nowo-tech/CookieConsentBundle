<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nowo\CookieConsentBundle\Repository\CookieDefinitionRepository;

#[ORM\Entity(repositoryClass: CookieDefinitionRepository::class)]
#[ORM\Table(name: 'dashboard_cookie_definition')]
#[ORM\UniqueConstraint(name: 'uniq_cookie_definition_config_name', columns: ['config_id', 'name'])]
/**
 * Doctrine entity describing a single cookie in the inventory for a consent profile.
 */
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

    /**
     * Initializes the translation collection.
     */
    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }

    /**
     * Returns the cookie definition primary key.
     *
     * @return int|null The entity identifier
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Returns the cookie name.
     *
     * @return string The cookie identifier (e.g. _ga)
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Sets the cookie name.
     *
     * @param string $name The cookie identifier
     *
     * @return self Fluent interface
     */
    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Returns the retention period label.
     *
     * @return string Human-readable duration (e.g. Session, 2 years)
     */
    public function getDuration(): string
    {
        return $this->duration;
    }

    /**
     * Sets the retention period label.
     *
     * @param string $duration Human-readable duration
     *
     * @return self Fluent interface
     */
    public function setDuration(string $duration): self
    {
        $this->duration = $duration;

        return $this;
    }

    /**
     * Returns the consent category key.
     *
     * @return string Category slug (required, analytics, marketing, …)
     */
    public function getCategory(): string
    {
        return $this->category;
    }

    /**
     * Sets the consent category key.
     *
     * @param string $category Category slug
     *
     * @return self Fluent interface
     */
    public function setCategory(string $category): self
    {
        $this->category = $category;

        return $this;
    }

    /**
     * Returns the cookie party type.
     *
     * @return string Either TYPE_FIRST_PARTY or TYPE_THIRD_PARTY
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Sets the cookie party type.
     *
     * @param string $type Either TYPE_FIRST_PARTY or TYPE_THIRD_PARTY
     *
     * @return self Fluent interface
     */
    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Returns the display sort order.
     *
     * @return int Zero-based sort index
     */
    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    /**
     * Sets the display sort order.
     *
     * @param int $sortOrder Zero-based sort index
     *
     * @return self Fluent interface
     */
    public function setSortOrder(int $sortOrder): self
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    /**
     * Returns whether the cookie is pre-checked before consent is saved.
     *
     * @return bool True when selected by default in granular mode
     */
    public function isAllowedByDefault(): bool
    {
        return $this->allowedByDefault;
    }

    /**
     * Sets whether the cookie is pre-checked before consent is saved.
     *
     * @param bool $allowedByDefault True to pre-check in granular mode
     *
     * @return self Fluent interface
     */
    public function setAllowedByDefault(bool $allowedByDefault): self
    {
        $this->allowedByDefault = $allowedByDefault;

        return $this;
    }

    /**
     * Returns the parent consent configuration profile.
     *
     * @return CookieConsentConfig|null The owning config entity
     */
    public function getConfig(): ?CookieConsentConfig
    {
        return $this->config;
    }

    /**
     * Sets the parent consent configuration profile.
     *
     * @param CookieConsentConfig|null $config The owning config entity
     *
     * @return self Fluent interface
     */
    public function setConfig(?CookieConsentConfig $config): self
    {
        $this->config = $config;

        return $this;
    }

    /**
     * Returns all locale-specific translations.
     *
     * @return Collection<int, CookieDefinitionTranslation> Translation entities
     */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    /**
     * Finds a translation for the given locale.
     *
     * @param string $locale BCP 47 locale code (e.g. en, es)
     *
     * @return CookieDefinitionTranslation|null The matching translation or null
     */
    public function findTranslation(string $locale): ?CookieDefinitionTranslation
    {
        foreach ($this->translations as $translation) {
            if ($translation->getLocale() === $locale) {
                return $translation;
            }
        }

        return null;
    }

    /**
     * Adds a locale-specific translation.
     *
     * @param CookieDefinitionTranslation $translation The translation to attach
     *
     * @return self Fluent interface
     */
    public function addTranslation(CookieDefinitionTranslation $translation): self
    {
        if (!$this->translations->contains($translation)) {
            $this->translations->add($translation);
            $translation->setDefinition($this);
        }

        return $this;
    }

    /**
     * Removes a locale-specific translation.
     *
     * @param CookieDefinitionTranslation $translation The translation to detach
     *
     * @return self Fluent interface
     */
    public function removeTranslation(CookieDefinitionTranslation $translation): self
    {
        if ($this->translations->removeElement($translation) && $translation->getDefinition() === $this) {
            $translation->setDefinition(null);
        }

        return $this;
    }
}
