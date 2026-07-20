<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\EventSubscriber;

use Nowo\CookieConsentBundle\Cookie\CookieHandler;
use Nowo\CookieConsentBundle\Cookie\CookieLogger;
use Nowo\CookieConsentBundle\Enum\CookieNameEnum;
use Nowo\CookieConsentBundle\EventSubscriber\CookieConsentFormSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class CookieConsentFormSubscriberTest extends TestCase
{
    public function testSubscribedEvents(): void
    {
        self::assertSame(
            [KernelEvents::RESPONSE => ['onResponse']],
            CookieConsentFormSubscriber::getSubscribedEvents(),
        );
    }

    public function testIgnoresSubRequests(): void
    {
        $subscriber = new CookieConsentFormSubscriber(
            $this->createMock(\Symfony\Component\Form\FormFactoryInterface::class),
            $this->createMock(CookieLogger::class),
            $this->createMock(CookieHandler::class),
            true,
        );

        $event = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            Request::create('/'),
            HttpKernelInterface::SUB_REQUEST,
            new Response(),
        );

        $subscriber->onResponse($event);
        self::assertTrue($event->getResponse()->isSuccessful());
    }

    public function testIgnoresInvalidSubmissions(): void
    {
        $handler = $this->createMock(CookieHandler::class);
        $handler->expects(self::never())->method('save');

        $form = $this->createMock(\Symfony\Component\Form\FormInterface::class);
        $form->method('handleRequest');
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(false);

        $formFactory = $this->createMock(\Symfony\Component\Form\FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        $subscriber = new CookieConsentFormSubscriber($formFactory, $this->createMock(CookieLogger::class), $handler, true);
        $subscriber->onResponse(new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            Request::create('/', 'POST'),
            HttpKernelInterface::MAIN_REQUEST,
            new Response(),
        ));
    }

    public function testHandlesValidSubmissionWithLogger(): void
    {
        $handler = $this->createMock(CookieHandler::class);
        $handler->expects(self::once())->method('save');

        $logger = $this->createMock(CookieLogger::class);
        $logger->expects(self::once())->method('log');

        $form = $this->createMock(\Symfony\Component\Form\FormInterface::class);
        $form->method('handleRequest');
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn(['required' => true, 'analytics' => true]);

        $formFactory = $this->createMock(\Symfony\Component\Form\FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        $subscriber = new CookieConsentFormSubscriber($formFactory, $logger, $handler, true);

        $request = Request::create('/', 'POST');
        $request->cookies->set(CookieNameEnum::COOKIE_CONSENT_KEY_NAME, 'existing-key');

        $event = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new Response(),
        );

        $subscriber->onResponse($event);
    }

    public function testSkipsLoggerWhenDisabled(): void
    {
        $handler = $this->createMock(CookieHandler::class);
        $handler->expects(self::once())->method('save');

        $logger = $this->createMock(CookieLogger::class);
        $logger->expects(self::never())->method('log');

        $form = $this->createMock(\Symfony\Component\Form\FormInterface::class);
        $form->method('handleRequest');
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn(['required' => true, 'analytics' => true]);

        $formFactory = $this->createMock(\Symfony\Component\Form\FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        $subscriber = new CookieConsentFormSubscriber($formFactory, $logger, $handler, false);

        $request = Request::create('/', 'POST');

        $event = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new Response(),
        );

        $subscriber->onResponse($event);
    }

    public function testHandlesGranularCookieSubmission(): void
    {
        $handler = $this->createMock(CookieHandler::class);
        $handler->expects(self::once())->method('save')->with(
            self::anything(),
            self::anything(),
            self::anything(),
            ['_ga' => true],
        );

        $form = $this->createMock(\Symfony\Component\Form\FormInterface::class);
        $form->method('handleRequest');
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn([
            'required' => true,
            'cookies'  => ['_ga' => true],
        ]);

        $formFactory = $this->createMock(\Symfony\Component\Form\FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        $subscriber = new CookieConsentFormSubscriber($formFactory, $this->createMock(CookieLogger::class), $handler, false);
        $subscriber->onResponse(new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            Request::create('/', 'POST'),
            HttpKernelInterface::MAIN_REQUEST,
            new Response(),
        ));
    }
}
