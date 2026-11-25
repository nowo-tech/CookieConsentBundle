<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\DependencyInjection\Compiler;

use Nowo\CookieConsentBundle\DependencyInjection\NowoCookieConsentExtension;
use Nowo\TransactionDoctrine\PrivateManager\DependencyInjection\PrivateEntityManagerPass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function is_string;
use function trigger_deprecation;

/**
 * The bundle's private entity manager (1.34, REQ-ARCH-001 rule 6; the only mode on the application's default manager
 * since 2.0).
 *
 * Decides the mode once the application's default manager is known (`doctrine.default_entity_manager`, so a default
 * manager not named "default" works without configuration):
 * - `doctrine.entity_manager` unset or naming the default manager: the private manager is created on the default
 *   manager. `doctrine.private_entity_manager: true` then has no effect and triggers one deprecation at build time;
 * - `doctrine.entity_manager` naming a dedicated manager (the DDD option) with `private_entity_manager` unset: the
 *   bundle keeps that manager as is, exactly as in 1.x, without a private manager
 *   ({@see NowoCookieConsentExtension::useDedicatedEntityManager()});
 * - a dedicated manager with `private_entity_manager: true`: the private manager is created on that manager (1.34).
 *
 * Then resolves the manager the private one is created on and runs transaction-doctrine's
 * {@see PrivateEntityManagerPass}, which checks that manager exists and tags the bundle's PrivateEntityManagerMiddleware
 * `doctrine.middleware` for its connection only.
 *
 * Does nothing without DoctrineBundle (no middleware service). Runs before DoctrineBundle's MiddlewaresPass (priority 0).
 */
final class CookieConsentPrivateEntityManagerPass implements CompilerPassInterface
{
    /**
     * Adjusts the container definitions of this bundle during compilation.
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(NowoCookieConsentExtension::PRIVATE_MIDDLEWARE_SERVICE)) {
            return;
        }

        $default = $container->hasParameter('doctrine.default_entity_manager') ? $container->getParameter('doctrine.default_entity_manager') : null;
        $default = is_string($default) && $default !== '' ? $default : 'default';
        $named   = $container->hasParameter(NowoCookieConsentExtension::ENTITY_MANAGER_PARAMETER)
            ? $container->getParameter(NowoCookieConsentExtension::ENTITY_MANAGER_PARAMETER)
            : null;
        $named   = is_string($named) && $named !== '' ? $named : null;
        $setting = $container->hasParameter(NowoCookieConsentExtension::PRIVATE_ENTITY_MANAGER_SETTING_PARAMETER)
            ? $container->getParameter(NowoCookieConsentExtension::PRIVATE_ENTITY_MANAGER_SETTING_PARAMETER)
            : null;

        if ($named !== null && $named !== $default) {
            if ($setting !== true) {
                NowoCookieConsentExtension::useDedicatedEntityManager($container, $named);

                return;
            }
        } elseif ($setting === true) {
            trigger_deprecation('nowo-tech/cookie-consent-bundle', '2.0', NowoCookieConsentExtension::PRIVATE_ENTITY_MANAGER_TRUE_DEPRECATION);
        }

        $container->setParameter(NowoCookieConsentExtension::PRIVATE_SOURCE_MANAGER_PARAMETER, $named ?? $default);

        (new PrivateEntityManagerPass(
            NowoCookieConsentExtension::PRIVATE_MIDDLEWARE_SERVICE,
            NowoCookieConsentExtension::PRIVATE_SOURCE_MANAGER_PARAMETER,
            'nowo_cookie_consent.doctrine.entity_manager',
        ))->process($container);
    }
}
