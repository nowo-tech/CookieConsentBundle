<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\DependencyInjection\Compiler;

use Nowo\CookieConsentBundle\Controller\CookieDefinitionAdminController;
use Nowo\CookieConsentBundle\DependencyInjection\NowoCookieConsentExtension;
use Nowo\CookieConsentBundle\Doctrine\CookieConsentHostFlushReporter;
use Nowo\CookieConsentBundle\EventSubscriber\CookieConsentPrivateManagerBoundarySubscriber;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;
use Nowo\CookieConsentBundle\Repository\CookieDefinitionRepository;
use Nowo\TransactionDoctrine\HostFlushProbe;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

use function is_bool;
use function is_string;
use function trigger_deprecation;

/**
 * 1.35 (REQ-ARCH-001 rule 6 deprecation layer): the `doctrine.private_entity_manager` notice and the host-flush probe.
 *
 * The bundle writes its profiles and cookie definitions through the application's default manager (and flushes the
 * application's pending changes with them) when:
 * - DoctrineBundle is registered (the entities are mapped), and
 * - `doctrine.private_entity_manager` is unset (null) or `false`, and
 * - `doctrine.entity_manager` is unset, or names the application's default manager (`doctrine.default_entity_manager`,
 *   so `entity_manager: default` is the same host mode).
 *
 * Exactly then, the build triggers one deprecation ({@see NowoCookieConsentExtension::privateEntityManagerNotice()}),
 * and the {@see CookieConsentHostFlushReporter} is registered and handed to the admin write paths (the two repository
 * saves and the cookie definition admin controller), with its own boundary subscriber that resets it at every request,
 * message and command (FrankenPHP worker mode with the kernel reset off). Nothing else changes: the writes run exactly
 * as in 1.34.
 *
 * No notice and no reporter with `private_entity_manager: true`, with a dedicated `doctrine.entity_manager` (the DDD
 * option, kept in 2.0), or without DoctrineBundle.
 */
final class CookieConsentHostFlushNoticePass implements CompilerPassInterface
{
    /** 1.35: the subscriber that resets the reporter at every unit-of-work boundary (host mode only). */
    public const BOUNDARY_SUBSCRIBER_SERVICE = 'nowo_cookie_consent.host_flush_reporter.boundary_subscriber';

    /**
     * Triggers the notice and wires the host-flush probe in host mode; does nothing otherwise.
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter(NowoCookieConsentExtension::PRIVATE_ENTITY_MANAGER_SETTING_PARAMETER)) {
            return;
        }

        $setting = $container->getParameter(NowoCookieConsentExtension::PRIVATE_ENTITY_MANAGER_SETTING_PARAMETER);
        $notice  = NowoCookieConsentExtension::privateEntityManagerNotice(is_bool($setting) ? $setting : null);
        if ($notice === null || !$this->onTheDefaultManager($container)) {
            return;
        }

        trigger_deprecation('nowo-tech/cookie-consent-bundle', '1.35', $notice);

        $container->register(CookieConsentHostFlushReporter::class, CookieConsentHostFlushReporter::class)
            ->setAutowired(false)
            ->setAutoconfigured(false)
            ->setArguments([new Reference(HostFlushProbe::SERVICE_ID, ContainerInterface::NULL_ON_INVALID_REFERENCE)])
            ->addTag('kernel.reset', ['method' => 'reset']);

        $reporter = new Reference(CookieConsentHostFlushReporter::class);
        foreach ([CookieConsentConfigRepository::class, CookieDefinitionRepository::class, CookieDefinitionAdminController::class] as $id) {
            if ($container->hasDefinition($id)) {
                $container->getDefinition($id)->setArgument('$hostFlushReporter', $reporter);
            }
        }

        // Only a reporter the unit of work already built: a boundary never builds one (REQ-RUNTIME-002).
        $container->register(self::BOUNDARY_SUBSCRIBER_SERVICE, CookieConsentPrivateManagerBoundarySubscriber::class)
            ->setAutowired(false)
            ->setAutoconfigured(false)
            ->setArguments([
                new IteratorArgument([new Reference(CookieConsentHostFlushReporter::class, ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE)]),
                new Reference('request_stack', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->addTag('kernel.event_subscriber');
    }

    /**
     * `doctrine.entity_manager` unset, or naming the application's default manager (the bundle flushes it then).
     */
    private function onTheDefaultManager(ContainerBuilder $container): bool
    {
        $named = $container->hasParameter(NowoCookieConsentExtension::ENTITY_MANAGER_PARAMETER)
            ? $container->getParameter(NowoCookieConsentExtension::ENTITY_MANAGER_PARAMETER)
            : null;
        if (!is_string($named) || $named === '') {
            return true;
        }

        $default = $container->hasParameter('doctrine.default_entity_manager') ? $container->getParameter('doctrine.default_entity_manager') : null;

        return $named === (is_string($default) && $default !== '' ? $default : 'default');
    }
}
