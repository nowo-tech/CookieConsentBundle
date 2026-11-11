<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use Nowo\CookieConsentBundle\DependencyInjection\Compiler\CookieConsentEntityManagerPass;
use Nowo\CookieConsentBundle\DependencyInjection\Compiler\CookieConsentSecurityPass;
use Nowo\CookieConsentBundle\DependencyInjection\Compiler\CookieConsentWithoutDoctrinePass;
use Nowo\CookieConsentBundle\DependencyInjection\Compiler\TwigPathsPass;
use Nowo\CookieConsentBundle\DependencyInjection\NowoCookieConsentExtension;
use Nowo\CookieConsentBundle\Doctrine\UtcDateTimeImmutableType;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Symfony bundle entry point for cookie consent management.
 */
class NowoCookieConsentBundle extends Bundle
{
    /**
     * Registers compiler passes for Twig paths and Doctrine entity mappings (on `doctrine.entity_manager`, else the default manager).
     *
     * @param ContainerBuilder $container The service container builder
     */
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new TwigPathsPass());
        // 1.28: before the security pass, which reads web_ui.enabled (rule 8, host without Doctrine).
        $container->addCompilerPass(new CookieConsentWithoutDoctrinePass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 10);
        $container->addCompilerPass(new CookieConsentSecurityPass());

        $entityDir = __DIR__ . '/Entity';
        if (is_dir($entityDir)) {
            $container->addCompilerPass(DoctrineOrmMappingsPass::createAttributeMappingDriver(
                ['Nowo\\CookieConsentBundle\\Entity'],
                [$entityDir],
                [NowoCookieConsentExtension::ENTITY_MANAGER_PARAMETER],
                // 1.28: only with DoctrineBundle (a host without it boots on the YAML path).
                NowoCookieConsentExtension::MAPPING_ENABLED_PARAMETER,
            ));
        }

        // After the mapping passes above (and DoctrineBundle's auto_mapping): keep the entities on one manager.
        $container->addCompilerPass(new CookieConsentEntityManagerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, -10);
    }

    /**
     * Registers the bundle's DBAL types (1.25: UTC re-consent time, audit JR-L2).
     */
    public function boot(): void
    {
        UtcDateTimeImmutableType::register();
    }

    /**
     * Returns the bundle dependency injection extension.
     *
     * @return ExtensionInterface|null The bundle extension instance
     */
    public function getContainerExtension(): ?ExtensionInterface
    {
        if (!$this->extension instanceof ExtensionInterface) {
            // @igor-ignore - Boot-time Symfony Bundle extension cache (not request state).
            $this->extension = new NowoCookieConsentExtension();
        }

        $extension = $this->extension;

        /* @phpstan-ignore identical.alwaysFalse */
        return $extension === false ? null : $extension;
    }
}
