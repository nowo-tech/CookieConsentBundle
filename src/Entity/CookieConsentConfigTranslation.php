<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigTranslationRepository;

#[ORM\Entity(repositoryClass: CookieConsentConfigTranslationRepository::class)]
#[ORM\Table(name: 'nowo_cookie_consent_config_translation')]
#[ORM\UniqueConstraint(name: 'uniq_cookie_consent_config_translation_locale', columns: ['config_id', 'locale'])]
/**
 * Doctrine entity storing locale-specific copy for a consent configuration.
 */
class CookieConsentConfigTranslation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    /** @phpstan-ignore property.unusedType */
    private ?int $id = null;

    #[ORM\Column(length: 10)]
    private string $locale = 'en';

    #[ORM\Column(name: 'consent_modal_label', length: 100, nullable: true)]
    private ?string $consentModalLabel = null;

    #[ORM\Column(name: 'consent_modal_title', length: 100)]
    private string $consentModalTitle = '';

    #[ORM\Column(name: 'consent_modal_description', type: Types::TEXT)]
    private string $consentModalDescription = '';

    #[ORM\Column(name: 'consent_modal_accept_all_btn', length: 30)]
    private string $consentModalAcceptAllBtn = '';

    #[ORM\Column(name: 'consent_modal_accept_necessary_btn', length: 30)]
    private string $consentModalAcceptNecessaryBtn = '';

    #[ORM\Column(name: 'consent_modal_show_preferences_btn', length: 30, nullable: true)]
    private ?string $consentModalShowPreferencesBtn = null;

    #[ORM\Column(name: 'consent_modal_footer', type: Types::TEXT, nullable: true)]
    private ?string $consentModalFooter = null;

    #[ORM\Column(name: 'preferences_modal_title', length: 100, nullable: true)]
    private ?string $preferencesModalTitle = null;

    #[ORM\Column(name: 'preferences_modal_accept_all_btn', length: 30, nullable: true)]
    private ?string $preferencesModalAcceptAllBtn = null;

    #[ORM\Column(name: 'preferences_modal_accept_necessary_btn', length: 30, nullable: true)]
    private ?string $preferencesModalAcceptNecessaryBtn = null;

    #[ORM\Column(name: 'preferences_modal_save_preferences_btn', length: 30, nullable: true)]
    private ?string $preferencesModalSavePreferencesBtn = null;

    #[ORM\Column(name: 'preferences_modal_close_icon_label', length: 30, nullable: true)]
    private ?string $preferencesModalCloseIconLabel = null;

    #[ORM\Column(name: 'privacy_route', length: 255, nullable: true)]
    private ?string $privacyRoute = null;

    /** @var list<array<string, mixed>>|null */
    #[ORM\Column(name: 'preference_sections', type: Types::JSON, nullable: true)]
    private ?array $preferenceSections = null;

    #[ORM\ManyToOne(targetEntity: CookieConsentConfig::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?CookieConsentConfig $config = null;

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
     * Returns the translation locale.
     *
     * @return string The locale code
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * Sets the translation locale.
     *
     * @param string $locale The locale code
     */
    public function setLocale(string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * Returns the consent modal label.
     *
     * @return string|null The optional modal label
     */
    public function getConsentModalLabel(): ?string
    {
        return $this->consentModalLabel;
    }

    /**
     * Sets the consent modal label.
     *
     * @param string|null $consentModalLabel The optional modal label
     */
    public function setConsentModalLabel(?string $consentModalLabel): self
    {
        $this->consentModalLabel = $consentModalLabel;

        return $this;
    }

    /**
     * Returns the consent modal title.
     *
     * @return string The modal title
     */
    public function getConsentModalTitle(): string
    {
        return $this->consentModalTitle;
    }

    /**
     * Sets the consent modal title.
     *
     * @param string $consentModalTitle The modal title
     */
    public function setConsentModalTitle(string $consentModalTitle): self
    {
        $this->consentModalTitle = $consentModalTitle;

        return $this;
    }

    /**
     * Returns the consent modal description.
     *
     * @return string The modal description
     */
    public function getConsentModalDescription(): string
    {
        return $this->consentModalDescription;
    }

    /**
     * Sets the consent modal description.
     *
     * @param string $consentModalDescription The modal description
     */
    public function setConsentModalDescription(string $consentModalDescription): self
    {
        $this->consentModalDescription = $consentModalDescription;

        return $this;
    }

    /**
     * Returns the accept-all button label.
     *
     * @return string The accept-all button text
     */
    public function getConsentModalAcceptAllBtn(): string
    {
        return $this->consentModalAcceptAllBtn;
    }

    /**
     * Sets the accept-all button label.
     *
     * @param string $consentModalAcceptAllBtn The accept-all button text
     */
    public function setConsentModalAcceptAllBtn(string $consentModalAcceptAllBtn): self
    {
        $this->consentModalAcceptAllBtn = $consentModalAcceptAllBtn;

        return $this;
    }

    /**
     * Returns the accept-necessary button label.
     *
     * @return string The accept-necessary button text
     */
    public function getConsentModalAcceptNecessaryBtn(): string
    {
        return $this->consentModalAcceptNecessaryBtn;
    }

    /**
     * Sets the accept-necessary button label.
     *
     * @param string $consentModalAcceptNecessaryBtn The accept-necessary button text
     */
    public function setConsentModalAcceptNecessaryBtn(string $consentModalAcceptNecessaryBtn): self
    {
        $this->consentModalAcceptNecessaryBtn = $consentModalAcceptNecessaryBtn;

        return $this;
    }

    /**
     * Returns the show-preferences button label.
     *
     * @return string|null The show-preferences button text
     */
    public function getConsentModalShowPreferencesBtn(): ?string
    {
        return $this->consentModalShowPreferencesBtn;
    }

    /**
     * Sets the show-preferences button label.
     *
     * @param string|null $consentModalShowPreferencesBtn The show-preferences button text
     */
    public function setConsentModalShowPreferencesBtn(?string $consentModalShowPreferencesBtn): self
    {
        $this->consentModalShowPreferencesBtn = $consentModalShowPreferencesBtn;

        return $this;
    }

    /**
     * Returns the consent modal footer text.
     *
     * @return string|null The modal footer text
     */
    public function getConsentModalFooter(): ?string
    {
        return $this->consentModalFooter;
    }

    /**
     * Sets the consent modal footer text.
     *
     * @param string|null $consentModalFooter The modal footer text
     */
    public function setConsentModalFooter(?string $consentModalFooter): self
    {
        $this->consentModalFooter = $consentModalFooter;

        return $this;
    }

    /**
     * Returns the preferences modal title.
     *
     * @return string|null The preferences modal title
     */
    public function getPreferencesModalTitle(): ?string
    {
        return $this->preferencesModalTitle;
    }

    /**
     * Sets the preferences modal title.
     *
     * @param string|null $preferencesModalTitle The preferences modal title
     */
    public function setPreferencesModalTitle(?string $preferencesModalTitle): self
    {
        $this->preferencesModalTitle = $preferencesModalTitle;

        return $this;
    }

    /**
     * Returns the preferences accept-all button label.
     *
     * @return string|null The accept-all button text
     */
    public function getPreferencesModalAcceptAllBtn(): ?string
    {
        return $this->preferencesModalAcceptAllBtn;
    }

    /**
     * Sets the preferences accept-all button label.
     *
     * @param string|null $preferencesModalAcceptAllBtn The accept-all button text
     */
    public function setPreferencesModalAcceptAllBtn(?string $preferencesModalAcceptAllBtn): self
    {
        $this->preferencesModalAcceptAllBtn = $preferencesModalAcceptAllBtn;

        return $this;
    }

    /**
     * Returns the preferences accept-necessary button label.
     *
     * @return string|null The accept-necessary button text
     */
    public function getPreferencesModalAcceptNecessaryBtn(): ?string
    {
        return $this->preferencesModalAcceptNecessaryBtn;
    }

    /**
     * Sets the preferences accept-necessary button label.
     *
     * @param string|null $preferencesModalAcceptNecessaryBtn The accept-necessary button text
     */
    public function setPreferencesModalAcceptNecessaryBtn(?string $preferencesModalAcceptNecessaryBtn): self
    {
        $this->preferencesModalAcceptNecessaryBtn = $preferencesModalAcceptNecessaryBtn;

        return $this;
    }

    /**
     * Returns the save-preferences button label.
     *
     * @return string|null The save-preferences button text
     */
    public function getPreferencesModalSavePreferencesBtn(): ?string
    {
        return $this->preferencesModalSavePreferencesBtn;
    }

    /**
     * Sets the save-preferences button label.
     *
     * @param string|null $preferencesModalSavePreferencesBtn The save-preferences button text
     */
    public function setPreferencesModalSavePreferencesBtn(?string $preferencesModalSavePreferencesBtn): self
    {
        $this->preferencesModalSavePreferencesBtn = $preferencesModalSavePreferencesBtn;

        return $this;
    }

    /**
     * Returns the preferences close icon label.
     *
     * @return string|null The close icon label
     */
    public function getPreferencesModalCloseIconLabel(): ?string
    {
        return $this->preferencesModalCloseIconLabel;
    }

    /**
     * Sets the preferences close icon label.
     *
     * @param string|null $preferencesModalCloseIconLabel The close icon label
     */
    public function setPreferencesModalCloseIconLabel(?string $preferencesModalCloseIconLabel): self
    {
        $this->preferencesModalCloseIconLabel = $preferencesModalCloseIconLabel;

        return $this;
    }

    /**
     * Returns the privacy policy route name.
     *
     * @return string|null The privacy route name
     */
    public function getPrivacyRoute(): ?string
    {
        return $this->privacyRoute;
    }

    /**
     * Sets the privacy policy route name.
     *
     * @param string|null $privacyRoute The privacy route name
     */
    public function setPrivacyRoute(?string $privacyRoute): self
    {
        $this->privacyRoute = $privacyRoute;

        return $this;
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    public function getPreferenceSections(): ?array
    {
        return $this->preferenceSections;
    }

    /**
     * @param list<array<string, mixed>>|null $preferenceSections
     */
    public function setPreferenceSections(?array $preferenceSections): self
    {
        $this->preferenceSections = $preferenceSections;

        return $this;
    }

    /**
     * Returns the parent consent configuration.
     *
     * @return CookieConsentConfig|null The parent configuration
     */
    public function getConfig(): ?CookieConsentConfig
    {
        return $this->config;
    }

    /**
     * Sets the parent consent configuration.
     *
     * @param CookieConsentConfig|null $config The parent configuration
     */
    public function setConfig(?CookieConsentConfig $config): self
    {
        $this->config = $config;

        return $this;
    }

    /**
     * Returns Symfony translation messages for this locale.
     *
     * @return array<string, string>
     */
    public function toTranslationMessages(): array
    {
        $messages = [
            'nowo_cookie_consent.title'                       => $this->consentModalTitle,
            'nowo_cookie_consent.intro'                       => $this->consentModalDescription,
            'nowo_cookie_consent.use_all_cookies'             => $this->consentModalAcceptAllBtn,
            'nowo_cookie_consent.use_only_functional_cookies' => $this->consentModalAcceptNecessaryBtn,
            'nowo_cookie_consent.privacy_route'               => $this->privacyRoute ?? '',
        ];

        if ($this->consentModalFooter !== null && $this->consentModalFooter !== '') {
            $messages['nowo_cookie_consent.read_more'] = $this->consentModalFooter;
        }

        if ($this->preferencesModalSavePreferencesBtn !== null && $this->preferencesModalSavePreferencesBtn !== '') {
            $messages['nowo_cookie_consent.save'] = $this->preferencesModalSavePreferencesBtn;
        }

        return $messages;
    }
}
