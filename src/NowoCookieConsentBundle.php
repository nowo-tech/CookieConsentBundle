<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use Nowo\CookieConsentBundle\DependencyInjection\Compiler\CookieConsentSecurityPass;
use Nowo\CookieConsentBundle\DependencyInjection\Compiler\TwigPathsPass;
use Nowo\CookieConsentBundle\DependencyInjection\NowoCookieConsentExtension;
use Nowo\CookieConsentBundle\Doctrine\UtcDateTimeImmutableType;
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
        $container->addCompilerPass(new CookieConsentSecurityPass());

        $entityDir = __DIR__ . '/Entity';
        if (is_dir($entityDir)) {
            $container->addCompilerPass(DoctrineOrmMappingsPass::createAttributeMappingDriver(
                ['Nowo\\CookieConsentBundle\\Entity'],
                [$entityDir],
                [NowoCookieConsentExtension::ENTITY_MANAGER_PARAMETER],
            ));
        }
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
