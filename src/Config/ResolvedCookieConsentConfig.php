<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Config;

use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfigTranslation;

/**
 * Wraps a resolved consent configuration and its locale-specific translation.
 */
final class ResolvedCookieConsentConfig
{
    /**
     * Creates a resolved configuration wrapper.
     *
     * @param CookieConsentConfig $config The resolved configuration entity
     * @param CookieConsentConfigTranslation|null $translation The locale translation, if any
     */
    public function __construct(
        private readonly CookieConsentConfig $config,
        private readonly ?CookieConsentConfigTranslation $translation,
    ) {
    }

    /**
     * Returns the underlying consent configuration entity.
     *
     * @return CookieConsentConfig The resolved configuration
     */
    public function getConfig(): CookieConsentConfig
    {
        return $this->config;
    }

    /**
     * Returns the translation for the resolved locale, if available.
     *
     * @return CookieConsentConfigTranslation|null The locale translation or null
     */
    public function getTranslation(): ?CookieConsentConfigTranslation
    {
        return $this->translation;
    }

    /**
     * Returns whether the consent modal should auto-show for this configuration.
     *
     * @return bool True when auto-show is enabled
     */
    public function shouldAutoShow(): bool
    {
        return $this->config->isAutoShow();
    }

    /**
     * Returns translation messages keyed by translation identifier.
     *
     * @return array<string, string>
     */
    public function getTranslationMessages(): array
    {
        if (!$this->translation instanceof CookieConsentConfigTranslation) {
            return [];
        }

        return $this->translation->toTranslationMessages();
    }
}
