<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\DependencyInjection\Compiler;

use Nowo\CookieConsentBundle\DependencyInjection\NowoCookieConsentExtension;
use Nowo\TransactionDoctrine\PrivateManager\DependencyInjection\PrivateEntityManagerPass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function is_string;

/**
 * 1.34 (`doctrine.private_entity_manager: true`, REQ-ARCH-001 rule 6).
 *
 * Resolves the application's manager the private one is created on — `doctrine.entity_manager`, else the application's
 * default entity manager (`doctrine.default_entity_manager`, so a default manager not named "default" works without
 * configuration) — then runs transaction-doctrine's {@see PrivateEntityManagerPass}, which checks that manager exists
 * and tags the bundle's PrivateEntityManagerMiddleware `doctrine.middleware` for its connection only.
 *
 * Does nothing in the default mode (no middleware service). Runs before DoctrineBundle's MiddlewaresPass (priority 0).
 */
final class CookieConsentPrivateEntityManagerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(NowoCookieConsentExtension::PRIVATE_MIDDLEWARE_SERVICE)) {
            return;
        }

        $named = $container->hasParameter(NowoCookieConsentExtension::PRIVATE_SOURCE_MANAGER_PARAMETER)
            ? $container->getParameter(NowoCookieConsentExtension::PRIVATE_SOURCE_MANAGER_PARAMETER)
            : null;
        if (!is_string($named) || $named === '') {
            $default = $container->hasParameter('doctrine.default_entity_manager') ? $container->getParameter('doctrine.default_entity_manager') : null;
            $named   = is_string($default) && $default !== '' ? $default : 'default';
        }

        $container->setParameter(NowoCookieConsentExtension::PRIVATE_SOURCE_MANAGER_PARAMETER, $named);

        (new PrivateEntityManagerPass(
            NowoCookieConsentExtension::PRIVATE_MIDDLEWARE_SERVICE,
            NowoCookieConsentExtension::PRIVATE_SOURCE_MANAGER_PARAMETER,
            'nowo_cookie_consent.doctrine.entity_manager',
        ))->process($container);
    }
}
