<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Config;

use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;

/**
 * Default {@see CookieConsentConfigReaderInterface} (1.34): maps the repository's enabled profiles to
 * {@see ConsentProfileView} value objects.
 *
 * Stateless (FrankenPHP worker mode with the kernel reset off): the profiles come from the repository's per-unit memo,
 * which is cleared at every request boundary; nothing is kept here.
 */
final readonly class CookieConsentConfigReader implements CookieConsentConfigReaderInterface
{
    /**
     * @param CookieConsentConfigRepository|null $configRepository null on a host without Doctrine (YAML path)
     */
    public function __construct(
        private ?CookieConsentConfigRepository $configRepository = null,
    ) {
    }

    public function defaultProfileId(): ?int
    {
        return $this->configRepository?->findDefaultEnabled()?->getId();
    }

    public function defaultProfile(?string $locale = null): ?ConsentProfileView
    {
        $config = $this->configRepository?->findDefaultEnabled();

        return $config instanceof CookieConsentConfig ? $this->view($config, $locale) : null;
    }

    public function enabledProfile(int $profileId, ?string $locale = null): ?ConsentProfileView
    {
        foreach ($this->configRepository?->findAllEnabled() ?? [] as $config) {
            if ($config->getId() === $profileId) {
                return $this->view($config, $locale);
            }
        }

        return null;
    }

    public function enabledProfiles(?string $locale = null): array
    {
        $views = [];
        foreach ($this->configRepository?->findAllEnabled() ?? [] as $config) {
            $views[] = $this->view($config, $locale);
        }

        return $views;
    }

    private function view(CookieConsentConfig $config, ?string $locale): ConsentProfileView
    {
        $translation = $locale !== null && $locale !== '' ? $config->findTranslation($locale) : null;

        return (new ResolvedCookieConsentConfig($config, $translation))->view();
    }
}
