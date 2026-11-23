<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\DependencyInjection\Compiler;

use Nowo\CookieConsentBundle\DependencyInjection\NowoCookieConsentExtension;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function is_string;

/**
 * 1.36: resolves the application's manager the provisioning port's private manager is created on —
 * `doctrine.entity_manager`, else the application's default entity manager (`doctrine.default_entity_manager`, so a
 * default manager not named "default" works without configuration). Does nothing without the provisioning port
 * (no DoctrineBundle).
 */
final class CookieConsentProvisionerPass implements CompilerPassInterface
{
    /**
     * Sets the source manager of the provisioning manager.
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter(NowoCookieConsentExtension::PROVISIONER_SOURCE_MANAGER_PARAMETER)) {
            return;
        }

        $named = $container->getParameter(NowoCookieConsentExtension::PROVISIONER_SOURCE_MANAGER_PARAMETER);
        if (is_string($named) && $named !== '') {
            return;
        }

        $default = $container->hasParameter('doctrine.default_entity_manager') ? $container->getParameter('doctrine.default_entity_manager') : null;
        $container->setParameter(NowoCookieConsentExtension::PROVISIONER_SOURCE_MANAGER_PARAMETER, is_string($default) && $default !== '' ? $default : 'default');
    }
}
