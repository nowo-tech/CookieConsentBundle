<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\DependencyInjection\Compiler;

use LogicException;
use Nowo\CookieConsentBundle\Cookie\ConsentCookieWriterInterface;
use Nowo\CookieConsentBundle\Cookie\CookieHandler;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function is_string;
use function sprintf;

/**
 * The consent cookie port of the `kernel.response` listeners (1.30, REQ-ARCH-001 rule 3; the only one since 2.0).
 *
 * Since 2.0 the listeners always call `ConsentCookieWriterInterface` (by default `nowo_cookie_consent.cookie_writer`).
 * In 1.x a host that decorated or replaced the `CookieHandler` service had its class called through the removed
 * `CookieHandlerInterface`; after 2.0 it would silently stop being called. Runs after decoration and alias resolution
 * (`TYPE_BEFORE_REMOVING`): when the port is still the bundle's own writer and the `CookieHandler` service is no
 * longer the bundle's class, the build fails with what to do instead.
 */
final class ConsentCookieWriterPass implements CompilerPassInterface
{
    public const WRITER_SERVICE_ID = 'nowo_cookie_consent.cookie_writer';

    /**
     * Fails the build when a host customisation of the `CookieHandler` service would no longer be called.
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(ConsentCookieWriterInterface::class) || !$container->has(CookieHandler::class)) {
            return;
        }

        if ($this->resolveId($container, ConsentCookieWriterInterface::class) !== self::WRITER_SERVICE_ID) {
            return; // the host aliased the port to its own writer: CookieHandler is not on the consent path
        }

        $handlerId = $this->resolveId($container, CookieHandler::class);
        $class     = $container->getParameterBag()->resolveValue($container->findDefinition($handlerId)->getClass() ?? $handlerId);
        if ($handlerId === CookieHandler::class && $class === CookieHandler::class) {
            return;
        }

        throw new LogicException(sprintf('The "%s" service is decorated or replaced (service "%s", class "%s"). Since 2.0 the consent cookies are written only through "%s" ("%s" was removed): implement that interface and alias it to your service, or decorate "%s". See UPGRADING.md "To 2.0.0".', CookieHandler::class, $handlerId, is_string($class) ? $class : 'unknown', ConsentCookieWriterInterface::class, 'Nowo\\CookieConsentBundle\\Cookie\\CookieHandlerInterface', self::WRITER_SERVICE_ID));
    }

    private function resolveId(ContainerBuilder $container, string $id): string
    {
        while ($container->hasAlias($id)) {
            $id = (string) $container->getAlias($id);
        }

        return $id;
    }
}
