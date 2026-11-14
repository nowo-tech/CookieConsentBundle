<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\DependencyInjection\Compiler;

use LogicException;
use Nowo\CookieConsentBundle\Cookie\ConsentCookieWriterInterface;
use Nowo\CookieConsentBundle\Cookie\CookieHandler;
use Nowo\CookieConsentBundle\Cookie\CookieHandlerInterface;
use Nowo\CookieConsentBundle\EventSubscriber\CookieConsentFormSubscriber;
use Nowo\CookieConsentBundle\EventSubscriber\CookieConsentOutdatedConsentSubscriber;
use ReflectionMethod;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

use function class_exists;
use function is_a;
use function is_string;
use function sprintf;
use function trigger_error;

use const E_USER_DEPRECATED;

/**
 * Chooses, once at container build, which consent cookie port the `kernel.response` listeners call (1.30,
 * REQ-ARCH-001 rule 3).
 *
 * Runs after decoration and alias resolution (`TYPE_BEFORE_REMOVING`), so it sees the class that really serves each
 * port:
 *
 * 1. `ConsentCookieWriterInterface` points at a host service (or the bundle writer is decorated): that writer.
 * 2. Otherwise the `CookieHandlerInterface` service is the bundle's own `CookieHandler`: the bundle writer.
 * 3. Otherwise the handler is a host service (alias, replaced class, `#[AsDecorator]`): when its class implements
 *    the new port and is not a `CookieHandler` subclass, that service; else no writer, so the listeners call the
 *    deprecated `save()` / `clear()` exactly as in 1.29 (an overridden `saveCookie()` still runs) and one deprecation
 *    is reported here, not on every request.
 */
final class ConsentCookieWriterPass implements CompilerPassInterface
{
    public const WRITER_SERVICE_ID = 'nowo_cookie_consent.cookie_writer';

    private const LISTENERS = [
        CookieConsentFormSubscriber::class,
        CookieConsentOutdatedConsentSubscriber::class,
    ];

    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(ConsentCookieWriterInterface::class) || !$container->has(CookieHandlerInterface::class)) {
            return;
        }

        $writer = $this->chooseWriter($container);

        foreach (self::LISTENERS as $listenerId) {
            if (!$container->hasDefinition($listenerId)) {
                continue;
            }

            $definition = $container->getDefinition($listenerId);
            $class      = $this->classOf($container, $listenerId);

            if ($class === null || !is_a($class, $listenerId, true)) {
                continue; // replaced by the host with an unrelated class
            }

            $definition->setArgument($this->writerArgumentIndex($listenerId), $writer);
        }
    }

    private function chooseWriter(ContainerBuilder $container): ?Reference
    {
        $writerId    = $this->resolveId($container, ConsentCookieWriterInterface::class);
        $writerClass = $this->classOf($container, $writerId);

        if ($writerId !== self::WRITER_SERVICE_ID || $writerClass !== CookieHandler::class) {
            return new Reference($writerId);
        }

        $handlerId    = $this->resolveId($container, CookieHandlerInterface::class);
        $handlerClass = $this->classOf($container, $handlerId);

        if ($handlerClass === CookieHandler::class) {
            return new Reference(self::WRITER_SERVICE_ID);
        }

        if ($handlerClass !== null
            && is_a($handlerClass, ConsentCookieWriterInterface::class, true)
            && !is_a($handlerClass, CookieHandler::class, true)) {
            return new Reference($handlerId);
        }

        @trigger_error(sprintf(
            'Since nowo-tech/cookie-consent-bundle 1.30: the consent cookies are written through the deprecated "%s" (service "%s", class "%s"); implement "%s" instead (removed in 2.0).',
            CookieHandlerInterface::class,
            $handlerId,
            $handlerClass ?? 'unknown',
            ConsentCookieWriterInterface::class,
        ), E_USER_DEPRECATED);

        return null;
    }

    private function resolveId(ContainerBuilder $container, string $id): string
    {
        while ($container->hasAlias($id)) {
            $id = (string) $container->getAlias($id);
        }

        return $id;
    }

    /**
     * @return class-string|null
     */
    private function classOf(ContainerBuilder $container, string $id): ?string
    {
        if (!$container->has($id)) {
            return null;
        }

        $class = $container->getParameterBag()->resolveValue($container->findDefinition($id)->getClass() ?? $id);

        return is_string($class) && class_exists($class) ? $class : null;
    }

    private function writerArgumentIndex(string $listenerClass): int
    {
        foreach ((new ReflectionMethod($listenerClass, '__construct'))->getParameters() as $parameter) {
            if ($parameter->getName() === 'cookieWriter') {
                return $parameter->getPosition();
            }
        }

        throw new LogicException(sprintf('"%s" has no $cookieWriter argument.', $listenerClass)); // @codeCoverageIgnore
    }
}
