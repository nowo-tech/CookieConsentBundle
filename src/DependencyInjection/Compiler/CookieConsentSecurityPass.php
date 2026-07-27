<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\DependencyInjection\Compiler;

use Nowo\CookieConsentBundle\DependencyInjection\Configuration;
use Nowo\CookieConsentBundle\EventSubscriber\CookieConsentAdminAccessSubscriber;
use Nowo\CookieConsentBundle\Security\CookieConsentAccessCheckerInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Enforces SecurityBundle for admin UI unless allow_unauthenticated is true (REQ-UI-002).
 */
final class CookieConsentSecurityPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter(Configuration::ALIAS . '.web_ui.enabled')) {
            return;
        }
        if (!(bool) $container->getParameter(Configuration::ALIAS . '.web_ui.enabled')) {
            return;
        }

        $allowUnauthenticated = (bool) $container->getParameter(Configuration::ALIAS . '.security.allow_unauthenticated');
        $hasSecurity          = $container->has('security.authorization_checker');

        if (!$hasSecurity && !$allowUnauthenticated) {
            throw new InvalidConfigurationException(
                'nowo_cookie_consent admin UI requires symfony/security-bundle (security.authorization_checker), '
                . 'or set nowo_cookie_consent.security.allow_unauthenticated: true '
                . '(dev/demo only — never in production).',
            );
        }

        if ($allowUnauthenticated) {
            return;
        }

        /** @var list<string> $accessRoles */
        $accessRoles = $container->getParameter(Configuration::ALIAS . '.security.access_roles');
        if ($accessRoles === []) {
            return;
        }

        if ($container->hasDefinition(CookieConsentAdminAccessSubscriber::class)) {
            return;
        }

        $container->register(CookieConsentAdminAccessSubscriber::class)
            ->setArgument('$accessChecker', new Reference(CookieConsentAccessCheckerInterface::class))
            ->addTag('kernel.event_subscriber');
    }
}
