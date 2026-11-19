<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\EventSubscriber;

use Doctrine\DBAL\Driver\AbstractException;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Exception\ConnectionLost;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Nowo\CookieConsentBundle\Cookie\CookieHandler;
use Nowo\CookieConsentBundle\Cookie\CookieLoggerInterface;
use Nowo\CookieConsentBundle\Event\ConsentSavedEvent;
use Nowo\CookieConsentBundle\EventListener\CookieConsentConflictListener;
use Nowo\CookieConsentBundle\EventSubscriber\CookieConsentFormSubscriber;
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\TransactionDoctrine\ConflictCode;
use Nowo\TransactionDoctrine\Symfony\Conflict\ConflictResponsePolicy;
use Nowo\TransactionDoctrine\WriteUnit\CommitLedger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use RuntimeException;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Throwable;

/**
 * 1.31 (REQ-RUNTIME-003 rules 6 and 8): the consent POST answers a lock conflict with 409 + CONFLICT_001 / CONFLICT_002,
 * sets no consent cookie without its log rows, and never swallows a failure of the consent write.
 */
final class CookieConsentFormSubscriberConflictTest extends TestCase
{
    private int $events = 0;

    public function testALockConflictOfTheLogAnswers409Conflict001WithoutCookieOrEvent(): void
    {
        $failure  = TransientStorageFailure::fromPrevious(new DeadlockException(self::driverError('Deadlock found', '40001', 1213), null));
        $request  = Request::create('/host/page', 'POST');
        $response = new Response('host page');

        $answer = $this->expectAnswer($this->subscriber($this->failingLogger($failure)), $request, $response);

        self::assertSame(409, $answer->getStatusCode());
        self::assertSame(['X-Conflict-Code' => ConflictCode::RETRY_SAFE, 'Retry-After' => '1'], $answer->getHeaders());
        self::assertSame(CookieConsentConflictListener::RETRY_SAFE_MESSAGE, $answer->getMessage());
        self::assertSame($failure, $answer->getPrevious());
        self::assertSame([], $response->headers->getCookies(), 'no consent cookie without its proof of consent');
        self::assertSame(0, $this->events);
        self::assertTrue($request->attributes->get(CookieConsentFormSubscriber::HANDLED_ATTRIBUTE));
    }

    public function testTheErrorResponseOfTheSameRequestIsNotHandledAgain(): void
    {
        $logger = $this->createMock(CookieLoggerInterface::class);
        $logger->expects(self::once())->method('log')->willThrowException(new DeadlockException(self::driverError('Deadlock found', '40001', 1213), null));
        $subscriber = $this->subscriber($logger);
        $request    = Request::create('/host/page', 'POST');

        $this->expectAnswer($subscriber, $request, new Response());
        // The kernel renders the 409 and runs kernel.response again on it.
        $subscriber->onResponse($this->responseEvent($request, new Response('error page', 409)));

        self::assertSame(0, $this->events);
    }

    public function testACommitEarlierInTheRequestAnswersConflict002WithoutRetryAfter(): void
    {
        $ledger    = new CommitLedger();
        $conflicts = new CookieConsentConflictListener(new ConflictResponsePolicy(503, 5), $ledger);
        $request   = Request::create('/host/page', 'POST');
        $conflicts->onRequest(new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));
        $ledger->record(); // the host's controller committed its own work

        $answer = $this->expectAnswer(
            $this->subscriber($this->failingLogger(new DeadlockException(self::driverError('deadlock detected', '40P01'), null)), $conflicts),
            $request,
            new Response(),
        );

        self::assertSame(503, $answer->getStatusCode(), 'the host status option');
        self::assertSame(['X-Conflict-Code' => ConflictCode::COMMITTED], $answer->getHeaders());
        self::assertSame(CookieConsentConflictListener::COMMITTED_MESSAGE, $answer->getMessage());
    }

    public function testAConflictAfterTheLogCommittedAnswersConflict002(): void
    {
        $ledger    = new CommitLedger();
        $conflicts = new CookieConsentConflictListener(null, $ledger);
        $request   = Request::create('/host/page', 'POST');
        $conflicts->onRequest(new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));
        $logger = new class($ledger) implements CookieLoggerInterface {
            public function __construct(private readonly CommitLedger $ledger)
            {
            }

            public function log(array $categories, string $key): void
            {
                $this->ledger->record(); // the log rows are committed
            }
        };
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ConsentSavedEvent::class, static function (): never {
            throw new DeadlockException(self::driverError('Deadlock found', '40001', 1213), null); // a host listener
        });

        $subscriber = new CookieConsentFormSubscriber($this->formFactory(), $logger, new CookieHandler(false, new MockClock()), true, null, null, $dispatcher, null, null, $conflicts);
        $answer     = $this->expectAnswer($subscriber, $request, new Response());

        self::assertSame(['X-Conflict-Code' => ConflictCode::COMMITTED], $answer->getHeaders());
    }

    /**
     * @return iterable<string, array{0: Throwable}>
     */
    public static function realErrors(): iterable
    {
        yield 'unique violation' => [new UniqueConstraintViolationException(self::driverError('Duplicate entry', '23000', 1062), null)];
        yield 'lost connection' => [new ConnectionLost(self::driverError('MySQL server has gone away', '08S01', 2006), null)];
        yield 'missing table' => [new class('Table not found') extends RuntimeException implements DBALException {}];
    }

    #[DataProvider('realErrors')]
    public function testOtherFailuresOfTheLogAreRealErrorsAndSetNoCookie(Throwable $failure): void
    {
        $response = new Response();

        try {
            $this->subscriber($this->failingLogger($failure))->onResponse($this->responseEvent(Request::create('/', 'POST'), $response));
            self::fail('The failure must propagate.');
        } catch (Throwable $thrown) {
            self::assertSame($failure, $thrown, 'not mapped to a conflict, not swallowed');
        }

        self::assertSame([], $response->headers->getCookies());
        self::assertSame(0, $this->events);
    }

    public function testAReadFailureBeforeTheSubmissionIsStillSkipped(): void
    {
        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->method('create')->willThrowException(new class('no schema') extends RuntimeException implements DBALException {});
        $logger = $this->createMock(CookieLoggerInterface::class);
        $logger->expects(self::never())->method('log');
        $request = Request::create('/', 'POST');

        (new CookieConsentFormSubscriber($formFactory, $logger, new CookieHandler(false, new MockClock()), true))
            ->onResponse($this->responseEvent($request, new Response()));

        self::assertFalse($request->attributes->has(CookieConsentFormSubscriber::HANDLED_ATTRIBUTE));
    }

    public function testASuccessfulConsentSetsTheCookiesAfterTheLog(): void
    {
        $response = new Response();
        $logger   = $this->createMock(CookieLoggerInterface::class);
        $logger->expects(self::once())->method('log')->willReturnCallback(static function () use ($response): void {
            self::assertSame([], $response->headers->getCookies(), 'the log runs before the cookies are set');
        });

        $this->subscriber($logger)->onResponse($this->responseEvent(Request::create('/', 'POST'), $response));

        self::assertNotSame([], $response->headers->getCookies());
        self::assertSame(1, $this->events);
    }

    private function expectAnswer(CookieConsentFormSubscriber $subscriber, Request $request, Response $response): HttpException
    {
        try {
            $subscriber->onResponse($this->responseEvent($request, $response));
        } catch (HttpException $answer) {
            return $answer;
        }

        self::fail('A lock conflict of the consent write must answer a conflict.');
    }

    private function subscriber(CookieLoggerInterface $logger, ?CookieConsentConflictListener $conflicts = null): CookieConsentFormSubscriber
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ConsentSavedEvent::class, function (): void {
            ++$this->events;
        });

        return new CookieConsentFormSubscriber(
            $this->formFactory(),
            $logger,
            new CookieHandler(false, new MockClock()),
            true,
            null,
            null,
            $dispatcher,
            $this->createStub(ClockInterface::class),
            null,
            $conflicts,
        );
    }

    private function failingLogger(Throwable $failure): CookieLoggerInterface
    {
        $logger = $this->createMock(CookieLoggerInterface::class);
        $logger->method('log')->willThrowException($failure);

        return $logger;
    }

    private function formFactory(): FormFactoryInterface
    {
        $form = $this->createMock(FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn(['required' => true, 'analytics' => 'true']);
        $form->method('has')->willReturn(false);

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        return $formFactory;
    }

    private function responseEvent(Request $request, Response $response): ResponseEvent
    {
        return new ResponseEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response);
    }

    private static function driverError(string $message, ?string $sqlState, int $code = 0): AbstractException
    {
        return new class($message, $sqlState, $code) extends AbstractException {
        };
    }
}
