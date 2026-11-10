<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\EventSubscriber;

use DateTimeImmutable;
use Doctrine\DBAL\Exception as DBALException;
use Nowo\CookieConsentBundle\Cookie\CookieHandlerInterface;
use Nowo\CookieConsentBundle\Cookie\CookieLoggerInterface;
use Nowo\CookieConsentBundle\Enum\CookieNameEnum;
use Nowo\CookieConsentBundle\Event\ConsentSavedEvent;
use Nowo\CookieConsentBundle\EventSubscriber\CookieConsentFormSubscriber;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

use const DATE_ATOM;

/**
 * 1.26 (DDD P2): ConsentSavedEvent after the save, and host implementations of the cookie ports.
 */
final class CookieConsentFormSubscriberEventTest extends TestCase
{
    /** @var list<string> */
    private array $calls = [];

    public function testDispatchesConsentSavedAfterTheLogWithHostPortImplementations(): void
    {
        $dispatcher = new EventDispatcher();
        $events     = [];
        $dispatcher->addListener(ConsentSavedEvent::class, function (ConsentSavedEvent $event) use (&$events): void {
            $this->calls[] = 'event';
            $events[]      = $event;
        });

        $subscriber = new CookieConsentFormSubscriber(
            $this->formFactory(['required' => true, 'analytics' => 'true', 'marketing' => false, 'cookies' => ['_ga' => true, '_fbp' => '0']]),
            $this->hostLogger(),
            $this->hostHandler(),
            true,
            null,
            null,
            $dispatcher,
            $this->clock(),
        );

        $request = Request::create('/', 'POST');
        $request->cookies->set(CookieNameEnum::COOKIE_CONSENT_KEY_NAME, 'visitor-key');
        $subscriber->onResponse($this->responseEvent($request));

        self::assertSame(['save', 'log', 'event'], $this->calls);
        self::assertCount(1, $events);
        self::assertSame('visitor-key', $events[0]->consentKey);
        self::assertSame(['analytics' => true, 'marketing' => false], $events[0]->categories);
        self::assertSame(['_ga' => true, '_fbp' => false], $events[0]->granularCookies);
        self::assertTrue($events[0]->logged);
        self::assertSame('2026-11-10T10:00:00+00:00', $events[0]->occurredAt->format(DATE_ATOM));
    }

    public function testDispatchesWithoutLoggerAndWithoutClock(): void
    {
        $dispatcher = new EventDispatcher();
        $events     = [];
        $dispatcher->addListener(ConsentSavedEvent::class, static function (ConsentSavedEvent $event) use (&$events): void {
            $events[] = $event;
        });

        $subscriber = new CookieConsentFormSubscriber(
            $this->formFactory(['required' => true]),
            $this->hostLogger(),
            $this->hostHandler(),
            false,
            null,
            null,
            $dispatcher,
        );
        $subscriber->onResponse($this->responseEvent(Request::create('/', 'POST')));

        self::assertSame(['save'], $this->calls);
        self::assertCount(1, $events);
        self::assertFalse($events[0]->logged);
        self::assertSame([], $events[0]->categories);
        self::assertSame([], $events[0]->granularCookies);
    }

    public function testNoEventWhenTheLogFailsOnTheDatabase(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatched = false;
        $dispatcher->addListener(ConsentSavedEvent::class, static function () use (&$dispatched): void {
            $dispatched = true;
        });

        $logger = $this->createMock(CookieLoggerInterface::class);
        $logger->method('log')->willThrowException(new class('down') extends RuntimeException implements DBALException {});

        $subscriber = new CookieConsentFormSubscriber(
            $this->formFactory(['required' => true, 'analytics' => true]),
            $logger,
            $this->hostHandler(),
            true,
            null,
            null,
            $dispatcher,
        );
        $subscriber->onResponse($this->responseEvent(Request::create('/', 'POST')));

        self::assertFalse($dispatched);
    }

    public function testNoEventWhenTheLogThrows(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatched = false;
        $dispatcher->addListener(ConsentSavedEvent::class, static function () use (&$dispatched): void {
            $dispatched = true;
        });

        $logger = $this->createMock(CookieLoggerInterface::class);
        $logger->method('log')->willThrowException(new RuntimeException('No request found'));

        $subscriber = new CookieConsentFormSubscriber(
            $this->formFactory(['required' => true]),
            $logger,
            $this->hostHandler(),
            true,
            null,
            null,
            $dispatcher,
        );

        try {
            $subscriber->onResponse($this->responseEvent(Request::create('/', 'POST')));
            self::fail('The log exception must propagate.');
        } catch (RuntimeException $exception) {
            self::assertSame('No request found', $exception->getMessage());
        }

        self::assertFalse($dispatched);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function formFactory(array $data): FormFactoryInterface
    {
        $form = $this->createMock(FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn($data);
        $form->method('has')->willReturn(false);

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        return $formFactory;
    }

    private function hostLogger(): CookieLoggerInterface
    {
        $test = $this;

        return new class($test) implements CookieLoggerInterface {
            public function __construct(private readonly CookieConsentFormSubscriberEventTest $test)
            {
            }

            public function log(array $categories, string $key): void
            {
                $this->test->record('log');
            }
        };
    }

    private function hostHandler(): CookieHandlerInterface
    {
        $test = $this;

        return new class($test) implements CookieHandlerInterface {
            public function __construct(private readonly CookieConsentFormSubscriberEventTest $test)
            {
            }

            public function save(array $categories, string $key, Response $response, array $granularCookies = []): void
            {
                $this->test->record('save');
            }

            public function clear(Request $request, Response $response): void
            {
            }
        };
    }

    public function record(string $call): void
    {
        $this->calls[] = $call;
    }

    private function clock(): ClockInterface
    {
        return new class implements ClockInterface {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-11-10T10:00:00+00:00');
            }
        };
    }

    private function responseEvent(Request $request): ResponseEvent
    {
        return new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new Response(),
        );
    }
}
