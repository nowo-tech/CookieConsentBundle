<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\DependencyInjection\Compiler;

use Nowo\CookieConsentBundle\DependencyInjection\Configuration;
use Nowo\CookieConsentBundle\EventSubscriber\CookieConsentAdminAccessSubscriber;
use Nowo\CookieConsentBundle\Security\AllowAllCookieConsentAccessChecker;
use Nowo\CookieConsentBundle\Security\CookieConsentAccessCheckerInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Enforces SecurityBundle for admin UI unless allow_unauthenticated is true (REQ-UI-002).
 *
 * The admin access subscriber is always registered because the bundle routing imports every
 * controller: with web_ui.enabled false it answers admin routes with 404 instead of leaving them open.
 */
final class CookieConsentSecurityPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter(Configuration::ALIAS . '.web_ui.enabled')) {
            return;
        }

        $webUiEnabled = (bool) $container->getParameter(Configuration::ALIAS . '.web_ui.enabled');
        if (!$webUiEnabled) {
            // Admin routes are still loaded by routing.yaml: 404 them (the access checker is never consulted).
            $this->registerSubscriber($container, new Definition(AllowAllCookieConsentAccessChecker::class), false);

            return;
        }

        $allowUnauthenticated = (bool) $container->getParameter(Configuration::ALIAS . '.security.allow_unauthenticated');
        $hasSecurity          = $container->has('security.authorization_checker');

        if (!$hasSecurity && !$allowUnauthenticated) {
            throw new InvalidConfigurationException('nowo_cookie_consent admin UI requires symfony/security-bundle (security.authorization_checker), or set nowo_cookie_consent.security.allow_unauthenticated: true (dev/demo only — never in production).');
        }

        if ($allowUnauthenticated) {
            $this->registerSubscriber($container, new Definition(AllowAllCookieConsentAccessChecker::class), true);

            return;
        }

        // Empty access_roles is fail-closed via the checker.
        $this->registerSubscriber($container, new Reference(CookieConsentAccessCheckerInterface::class), true);
    }

    private function registerSubscriber(ContainerBuilder $container, Definition|Reference $accessChecker, bool $webUiEnabled): void
    {
        if ($container->hasDefinition(CookieConsentAdminAccessSubscriber::class)) {
            return;
        }

        $container->register(CookieConsentAdminAccessSubscriber::class)
            ->setArgument('$accessChecker', $accessChecker)
            ->setArgument('$webUiEnabled', $webUiEnabled)
            ->addTag('kernel.event_subscriber');
    }
}
