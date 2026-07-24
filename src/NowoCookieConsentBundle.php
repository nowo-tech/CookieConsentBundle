<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use Nowo\CookieConsentBundle\DependencyInjection\Compiler\TwigPathsPass;
use Nowo\CookieConsentBundle\DependencyInjection\NowoCookieConsentExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Symfony bundle entry point for cookie consent management.
 */
class NowoCookieConsentBundle extends Bundle
{
    /**
     * Registers compiler passes for Twig paths and Doctrine entity mappings.
     *
     * @param ContainerBuilder $container The service container builder
     *
     * @return void
     */
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new TwigPathsPass());

        $entityDir = __DIR__ . '/Entity';
        if (is_dir($entityDir)) {
            $container->addCompilerPass(DoctrineOrmMappingsPass::createAttributeMappingDriver(
                ['Nowo\\CookieConsentBundle\\Entity'],
                [$entityDir],
            ));
        }
    }

    /**
     * Returns the bundle dependency injection extension.
     *
     * @return ExtensionInterface|null The bundle extension instance
     */
    public function getContainerExtension(): ?ExtensionInterface
    {
        if (!$this->extension instanceof ExtensionInterface) {
            $this->extension = new NowoCookieConsentExtension();
        }

        $extension = $this->extension;

        /* @phpstan-ignore identical.alwaysFalse */
        return $extension === false ? null : $extension;
    }
}
