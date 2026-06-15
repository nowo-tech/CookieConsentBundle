<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Config;

use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Repository\CookieDefinitionRepository;

/**
 * Builds cookie inventory tables for the consent modal and legal pages.
 *
 * Sources (in order):
 * 1. {@see CookieDefinition} entities linked to the active profile (database)
 * 2. Static entries from bundle YAML ({@see Configuration::cookie_inventory})
 */
final class CookieInventoryProvider
{
    /**
     * Creates a new cookie inventory provider.
     *
     * @param CookieDefinitionRepository $definitionRepository Repository for database definitions
     * @param bool $useCookieInventory Whether the inventory feature is enabled
     * @param list<array<string, mixed>> $yamlCookieInventory Static YAML inventory entries
     */
    public function __construct(
        private readonly CookieDefinitionRepository $definitionRepository,
        private readonly bool $useCookieInventory,
        private readonly array $yamlCookieInventory,
    ) {
    }

    /**
     * Returns whether the cookie inventory feature is enabled.
     *
     * @return bool True when inventory tables may be rendered
     */
    public function isEnabled(): bool
    {
        return $this->useCookieInventory;
    }

    /**
     * Returns cookie inventory rows for the given profile and locale.
     *
     * @param CookieConsentConfig|null $config The active consent profile
     * @param string $locale The requested locale code
     *
     * @return list<array{
     *     name: string,
     *     provider: string,
     *     purpose: string,
     *     duration: string,
     *     category: string,
     *     type: string,
     *     allowed_by_default: bool
     * }> Cookie inventory rows
     */
    public function listForLocale(?CookieConsentConfig $config, string $locale): array
    {
        if (!$this->useCookieInventory) {
            return [];
        }

        if ($config instanceof CookieConsentConfig && $this->hasDatabaseDefinitions($config)) {
            return $this->listFromDatabase($config, $locale);
        }

        return $this->listFromYaml($locale);
    }

    /**
     * Builds table rows for a single inventory category.
     *
     * @param CookieConsentConfig|null $config The active consent profile
     * @param string $locale The requested locale code
     * @param string $category The consent category slug
     *
     * @return list<array{name: string, domain: string, desc: string}> Table rows
     */
    public function buildCookieTableBody(?CookieConsentConfig $config, string $locale, string $category): array
    {
        $rows = [];

        foreach ($this->listForLocale($config, $locale) as $row) {
            if ($row['category'] !== $category) {
                continue;
            }

            $rows[] = [
                'name'   => $row['name'],
                'domain' => $row['provider'],
                'desc'   => $row['purpose'],
            ];
        }

        return $rows;
    }

    /**
     * Returns whether inventory data exists for the given profile or YAML config.
     *
     * @param CookieConsentConfig|null $config The active consent profile
     *
     * @return bool True when at least one definition is available
     */
    public function hasDefinitions(?CookieConsentConfig $config = null): bool
    {
        if (!$this->useCookieInventory) {
            return false;
        }

        if ($config instanceof CookieConsentConfig && $this->hasDatabaseDefinitions($config)) {
            return true;
        }

        return $this->getNormalizedYamlInventory() !== [];
    }

    /**
     * @return list<array{
     *     name: string,
     *     provider: string,
     *     purpose: string,
     *     duration: string,
     *     category: string,
     *     type: string,
     *     allowed_by_default: bool
     * }>
     */
    private function listFromDatabase(CookieConsentConfig $config, string $locale): array
    {
        $inventory = [];

        foreach ($this->definitionRepository->findByConfigOrdered($config) as $definition) {
            $translation = $definition->findTranslation($locale)
                ?? $definition->findTranslation('en');

            $inventory[] = [
                'name'               => $definition->getName(),
                'provider'           => $translation?->getProvider() ?? '',
                'purpose'            => $translation?->getPurpose() ?? '',
                'duration'           => $definition->getDuration(),
                'category'           => $definition->getCategory(),
                'type'               => $definition->getType(),
                'allowed_by_default' => $definition->isAllowedByDefault(),
            ];
        }

        return $inventory;
    }

    /**
     * @return list<array{
     *     name: string,
     *     provider: string,
     *     purpose: string,
     *     duration: string,
     *     category: string,
     *     type: string,
     *     allowed_by_default: bool
     * }>
     */
    private function listFromYaml(string $locale): array
    {
        $inventory = [];

        foreach ($this->getNormalizedYamlInventory() as $entry) {
            $translation = $entry['translations'][$locale]
                ?? $entry['translations']['en']
                ?? ['provider' => '', 'purpose' => ''];

            $inventory[] = [
                'name'               => $entry['name'],
                'provider'           => $translation['provider'] ?? '',
                'purpose'            => $translation['purpose'] ?? '',
                'duration'           => $entry['duration'],
                'category'           => $entry['category'],
                'type'               => $entry['type'],
                'allowed_by_default' => $entry['allowed_by_default'] ?? true,
            ];
        }

        return $inventory;
    }

    /**
     * @return list<array{
     *     name: string,
     *     duration: string,
     *     category: string,
     *     type: string,
     *     sort_order: int,
     *     translations: array<string, array{provider: string, purpose: string}>
     * }>
     */
    private function getNormalizedYamlInventory(): array
    {
        static $cache = null;

        if ($cache === null) {
            $cache = CookieInventoryNormalizer::normalize($this->yamlCookieInventory);
        }

        return $cache;
    }

    private function hasDatabaseDefinitions(CookieConsentConfig $config): bool
    {
        return $this->definitionRepository->findByConfigOrdered($config) !== [];
    }
}
