<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\EventSubscriber;

use Nowo\TransactionDoctrine\PrivateManager\PrivateEntityManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Service\ResetInterface;

/**
 * 1.34 (`doctrine.private_entity_manager: true`): drops the bundle's private entity manager, and clears the memos that
 * hold its entities, at every outermost unit-of-work boundary, so nothing loaded or left unflushed in one request,
 * Messenger message or console command survives into the next one (FrankenPHP worker mode with the kernel reset off
 * included; REQ-RUNTIME-002). `kernel.reset` resets the same services too, but is never relied on alone.
 *
 * Boundaries:
 * - HTTP: the start (`kernel.request`, priority 4096) and the end (`kernel.terminate`, priority -4096) of the HTTP
 *   kernel's own main request. A main request handled in-process inside another one (an `HttpKernel::handle()` from a
 *   controller: the request stack already holds the outer request) is not a boundary, so it never drops the manager
 *   of the unit that runs it; sub-requests (fragments, ESI) never are;
 * - Messenger: when a message is received (priority 4096) and after it is handled or failed (priority -4096). The
 *   events are subscribed by name, so symfony/messenger stays optional;
 * - console: the start and the end of the OUTERMOST command (a nesting depth; a command run in-process from another
 *   one is not a boundary).
 *
 * Only services the unit of work already built are reset (IGNORE_ON_UNINITIALIZED references): a boundary never builds
 * the private manager. A {@see PrivateEntityManager} reset drops the wrapped manager (pending changes are logged and
 * discarded, never written later); the next use creates a fresh one. The application's managers are never touched.
 *
 * When the host also registers transaction-doctrine's Symfony bundle, its boundary listener resets the same manager
 * through the {@see PrivateEntityManager::TAG} tag; a second reset is a no-op.
 *
 * 1.35: the same class also resets the host-flush reporter (service
 * `nowo_cookie_consent.host_flush_reporter.boundary_subscriber`, registered by CookieConsentHostFlushNoticePass when
 * the bundle writes through the application's default manager): its resettables are then only that reporter.
 *
 * @internal registered by the bundle (private mode; host mode for the host-flush reporter); not an extension point
 */
final class CookieConsentPrivateManagerBoundarySubscriber implements EventSubscriberInterface
{
    /** Messenger event names (class names), subscribed without requiring symfony/messenger. */
    public const MESSENGER_WORKER_MESSAGE_RECEIVED = 'Symfony\\Component\\Messenger\\Event\\WorkerMessageReceivedEvent';
    public const MESSENGER_WORKER_MESSAGE_HANDLED  = 'Symfony\\Component\\Messenger\\Event\\WorkerMessageHandledEvent';
    public const MESSENGER_WORKER_MESSAGE_FAILED   = 'Symfony\\Component\\Messenger\\Event\\WorkerMessageFailedEvent';

    /** Console event names (ConsoleEvents::COMMAND / TERMINATE). */
    public const CONSOLE_COMMAND   = 'console.command';
    public const CONSOLE_TERMINATE = 'console.terminate';

    /** Nesting depth of the running console commands (process lifetime: it describes the commands still running). */
    private int $commandDepth = 0;

    /**
     * @param iterable<ResetInterface> $resettables the private manager and the memos holding its entities (host mode: the host-flush reporter)
     * @param RequestStack|null $requestStack the HTTP kernel's request stack (read only): tells a nested main request
     */
    public function __construct(
        private readonly iterable $resettables = [],
        private readonly ?RequestStack $requestStack = null,
    ) {
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST                   => ['onRequest', 4096],
            KernelEvents::TERMINATE                 => ['onTerminate', -4096],
            self::MESSENGER_WORKER_MESSAGE_RECEIVED => ['onMessage', 4096],
            self::MESSENGER_WORKER_MESSAGE_HANDLED  => ['onMessage', -4096],
            self::MESSENGER_WORKER_MESSAGE_FAILED   => ['onMessage', -4096],
            self::CONSOLE_COMMAND                   => ['onCommand', 4096],
            self::CONSOLE_TERMINATE                 => ['onCommandTerminate', -4096],
        ];
    }

    /** Start of the HTTP kernel's own main request. */
    public function onRequest(RequestEvent $event): void
    {
        if ($this->isOutermostMainRequest($event)) {
            $this->reset();
        }
    }

    /** End of the main request (after the response was sent). */
    public function onTerminate(TerminateEvent $event): void
    {
        $this->reset();
    }

    /** A Messenger message is received, handled or failed. */
    public function onMessage(object $event): void
    {
        $this->reset();
    }

    /** console.command: only the outermost command starts a unit of work. */
    public function onCommand(object $event): void
    {
        if ($this->commandDepth++ === 0) { // @igor-ignore — nesting depth of the running commands (process lifetime)
            $this->reset();
        }
    }

    /** console.terminate: the outermost command ends its unit of work. */
    public function onCommandTerminate(object $event): void
    {
        if ($this->commandDepth > 0 && --$this->commandDepth === 0) { // @igor-ignore — nesting depth of the running commands
            $this->reset();
        }
    }

    /** Resets the private manager and the memos (only the ones already built). */
    public function reset(): void
    {
        foreach ($this->resettables as $resettable) {
            $resettable->reset();
        }
    }

    /**
     * A main request with no other request below it on the request stack (the stack already holds the request at
     * `kernel.request`): an in-process main request inside another one is not a unit of its own.
     */
    private function isOutermostMainRequest(KernelEvent $event): bool
    {
        if (!$event->isMainRequest()) {
            return false;
        }

        $main = $this->requestStack?->getMainRequest();

        return !$main instanceof Request || $main === $event->getRequest();
    }
}
