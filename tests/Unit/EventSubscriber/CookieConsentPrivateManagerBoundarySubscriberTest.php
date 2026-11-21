<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\EventSubscriber;

use Nowo\CookieConsentBundle\EventSubscriber\CookieConsentPrivateManagerBoundarySubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Service\ResetInterface;

/**
 * 1.34: the private manager (and the memos holding its entities) is dropped at every outermost unit-of-work boundary,
 * never inside a nested unit.
 */
#[CoversClass(CookieConsentPrivateManagerBoundarySubscriber::class)]
final class CookieConsentPrivateManagerBoundarySubscriberTest extends TestCase
{
    private int $resets = 0;

    public function testSubscribesToEveryBoundary(): void
    {
        self::assertSame([
            KernelEvents::REQUEST                                              => ['onRequest', 4096],
            KernelEvents::TERMINATE                                            => ['onTerminate', -4096],
            'Symfony\\Component\\Messenger\\Event\\WorkerMessageReceivedEvent' => ['onMessage', 4096],
            'Symfony\\Component\\Messenger\\Event\\WorkerMessageHandledEvent'  => ['onMessage', -4096],
            'Symfony\\Component\\Messenger\\Event\\WorkerMessageFailedEvent'   => ['onMessage', -4096],
            'console.command'                                                  => ['onCommand', 4096],
            'console.terminate'                                                => ['onCommandTerminate', -4096],
        ], CookieConsentPrivateManagerBoundarySubscriber::getSubscribedEvents());
    }

    public function testTheMainRequestStartAndEndResetEveryService(): void
    {
        $stack      = new RequestStack();
        $subscriber = new CookieConsentPrivateManagerBoundarySubscriber([$this->resettable(), $this->resettable()], $stack);
        $request    = Request::create('/');
        $stack->push($request);

        $subscriber->onRequest(new RequestEvent($this->kernel(), $request, HttpKernelInterface::MAIN_REQUEST));
        self::assertSame(2, $this->resets);

        $subscriber->onTerminate(new TerminateEvent($this->kernel(), $request, new Response()));
        self::assertSame(4, $this->resets);
    }

    public function testSubRequestsAndNestedMainRequestsAreNotBoundaries(): void
    {
        $stack      = new RequestStack();
        $subscriber = new CookieConsentPrivateManagerBoundarySubscriber([$this->resettable()], $stack);
        $outer      = Request::create('/');
        $stack->push($outer);
        $inner = Request::create('/inner');
        $stack->push($inner);

        $subscriber->onRequest(new RequestEvent($this->kernel(), $inner, HttpKernelInterface::SUB_REQUEST));
        $subscriber->onRequest(new RequestEvent($this->kernel(), $inner, HttpKernelInterface::MAIN_REQUEST));

        self::assertSame(0, $this->resets, 'an in-process main request never drops the outer unit\'s manager');
    }

    public function testWithoutARequestStackEveryMainRequestIsABoundary(): void
    {
        $subscriber = new CookieConsentPrivateManagerBoundarySubscriber([$this->resettable()]);

        $subscriber->onRequest(new RequestEvent($this->kernel(), Request::create('/'), HttpKernelInterface::MAIN_REQUEST));

        self::assertSame(1, $this->resets);
    }

    public function testEveryMessengerBoundaryResets(): void
    {
        $subscriber = new CookieConsentPrivateManagerBoundarySubscriber([$this->resettable()]);

        $subscriber->onMessage(new stdClass());
        $subscriber->onMessage(new stdClass());

        self::assertSame(2, $this->resets);
    }

    public function testOnlyTheOutermostCommandIsABoundary(): void
    {
        $subscriber = new CookieConsentPrivateManagerBoundarySubscriber([$this->resettable()]);

        $subscriber->onCommand(new stdClass());
        self::assertSame(1, $this->resets);
        $subscriber->onCommand(new stdClass());
        $subscriber->onCommandTerminate(new stdClass());
        self::assertSame(1, $this->resets, 'a nested command neither starts nor ends the unit');
        $subscriber->onCommandTerminate(new stdClass());
        self::assertSame(2, $this->resets);
        $subscriber->onCommandTerminate(new stdClass());
        self::assertSame(2, $this->resets, 'a terminate without a running command changes nothing');
    }

    private function resettable(): ResetInterface
    {
        $test = $this;

        return new class($test) implements ResetInterface {
            public function __construct(private readonly CookieConsentPrivateManagerBoundarySubscriberTest $test)
            {
            }

            public function reset(): void
            {
                $this->test->countReset();
            }
        };
    }

    public function countReset(): void
    {
        ++$this->resets;
    }

    private function kernel(): HttpKernelInterface
    {
        return $this->createStub(HttpKernelInterface::class);
    }
}
