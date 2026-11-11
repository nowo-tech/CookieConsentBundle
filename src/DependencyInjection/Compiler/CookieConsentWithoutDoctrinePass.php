<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\DependencyInjection\Compiler;

use Nowo\CookieConsentBundle\Config\CookieConsentConfigResolver;
use Nowo\CookieConsentBundle\Config\CookieConsentConfigSelector;
use Nowo\CookieConsentBundle\Config\CookieInventoryProvider;
use Nowo\CookieConsentBundle\Controller\CookieConsentConfigSettingsAdminController;
use Nowo\CookieConsentBundle\Controller\CookieDefinitionAdminController;
use Nowo\CookieConsentBundle\Cookie\CookieLogger;
use Nowo\CookieConsentBundle\Cookie\CookieLoggerInterface;
use Nowo\CookieConsentBundle\Cookie\NullCookieLogger;
use Nowo\CookieConsentBundle\DependencyInjection\Configuration;
use Nowo\CookieConsentBundle\EventListener\CookieConsentConfigRuntimeCacheListener;
use Nowo\CookieConsentBundle\EventSubscriber\CookieConsentConfigTranslationSubscriber;
use Nowo\CookieConsentBundle\EventSubscriber\CookieConsentRuntimeCacheResetSubscriber;
use Nowo\CookieConsentBundle\EventSubscriber\CookieConsentSchemaReadySubscriber;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigTranslationRepository;
use Nowo\CookieConsentBundle\Repository\CookieDefinitionRepository;
use Nowo\CookieConsentBundle\Repository\CookieDefinitionTranslationRepository;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * A host without Doctrine (no `doctrine` service: DoctrineBundle not registered) uses the YAML path (1.28,
 * architecture bar rule 8): configuration, copy and cookie inventory from the bundle configuration, consents logged
 * into the host's own {@see CookieLoggerInterface}. The services that only exist for the bundle tables (repositories,
 * profile selector, schema probe, Doctrine listeners, admin controllers, the DBAL logger) are removed and the admin
 * routes answer 404. Standard hosts (DoctrineBundle registered) are left untouched.
 *
 * Fails the build with an explicit message when the configuration needs the tables: `use_database_config: true`, or
 * `use_logger: true` with the bundle's own logger.
 */
final class CookieConsentWithoutDoctrinePass implements CompilerPassInterface
{
    /** Services that need the bundle tables. */
    private const DOCTRINE_ONLY = [
        CookieConsentConfigRepository::class,
        CookieConsentConfigTranslationRepository::class,
        CookieDefinitionRepository::class,
        CookieDefinitionTranslationRepository::class,
        CookieConsentConfigSelector::class,
        CookieConsentSchemaReadySubscriber::class,
        CookieConsentConfigRuntimeCacheListener::class,
        CookieConsentConfigTranslationSubscriber::class,
        CookieConsentConfigSettingsAdminController::class,
        CookieDefinitionAdminController::class,
        CookieLogger::class,
    ];

    /**
     * Switches a host without Doctrine to the YAML path.
     */
    public function process(ContainerBuilder $container): void
    {
        if ($container->has('doctrine') || !$container->hasParameter(Configuration::ALIAS . '.use_logger')) {
            return;
        }

        if ($container->getParameter(Configuration::ALIAS . '.use_database_config') === true) {
            throw new InvalidConfigurationException('nowo_cookie_consent.use_database_config: true needs Doctrine ORM (DoctrineBundle): register it, or keep the configuration in YAML (use_database_config: false).');
        }

        $bundleLogger = !$container->hasAlias(CookieLoggerInterface::class) || (string) $container->getAlias(CookieLoggerInterface::class) === CookieLogger::class;
        if ($bundleLogger) {
            if ($container->getParameter(Configuration::ALIAS . '.use_logger') === true) {
                throw new InvalidConfigurationException('nowo_cookie_consent.use_logger: true writes the consent log with Doctrine, which is not registered: alias Nowo\CookieConsentBundle\Cookie\CookieLoggerInterface to your own consent store, or set use_logger: false.');
            }
            $container->register(NullCookieLogger::class, NullCookieLogger::class);
            $container->setAlias(CookieLoggerInterface::class, NullCookieLogger::class);
        }

        foreach (self::DOCTRINE_ONLY as $id) {
            $container->removeDefinition($id);
        }

        $container->getDefinition(CookieConsentConfigResolver::class)
            ->setArgument('$configSelector', null)
            ->setArgument('$translationRepository', null);
        $container->getDefinition(CookieInventoryProvider::class)->setArgument('$definitionRepository', null);
        $container->getDefinition(CookieConsentRuntimeCacheResetSubscriber::class)->setArgument('$configRepository', null);

        // The admin pages edit the tables: their routes answer 404 (CookieConsentSecurityPass).
        $container->setParameter(Configuration::ALIAS . '.web_ui.enabled', false);
    }
}
