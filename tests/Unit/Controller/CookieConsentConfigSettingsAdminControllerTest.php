<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\CookieConsentBundle\Controller\CookieConsentConfigSettingsAdminController;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Form\CookieConsentConfigSettingsType;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;
use Nowo\CookieConsentBundle\Tests\Unit\Support\AbstractControllerTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

final class CookieConsentConfigSettingsAdminControllerTest extends AbstractControllerTestCase
{
    public function testEditRendersFormOnGet(): void
    {
        $controller = $this->createSettingsController();
        $response   = $controller->edit(1, Request::create('/settings', 'GET'), $this->createMock(EntityManagerInterface::class));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('rendered', (string) $response->getContent());
    }

    public function testEditRedirectsAfterValidSubmit(): void
    {
        $config = (new CookieConsentConfig())->setEnabled(true)->setDefault(true);
        $this->setEntityId($config, 1);

        $otherDefault = (new CookieConsentConfig())->setEnabled(true)->setDefault(true);
        $this->setEntityId($otherDefault, 2);

        $configRepository = $this->createMock(CookieConsentConfigRepository::class);
        $configRepository->method('find')->willReturn($config);
        $configRepository->method('findAllEnabled')->willReturn([$config, $otherDefault]);

        $form = $this->createMock(FormInterface::class);
        $form->method('createView')->willReturn(new FormView());
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('handleRequest');

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->method('create')->with(CookieConsentConfigSettingsType::class)->willReturn($form);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturn('saved');

        $controller = new CookieConsentConfigSettingsAdminController($configRepository, $translator);
        $this->configureController($controller, formFactory: $formFactory);

        $response = $controller->edit(1, Request::create('/settings', 'POST'), $entityManager);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertFalse($otherDefault->isDefault());
    }

    public function testEditRendersWhenConfigDisabledThrows(): void
    {
        $config = (new CookieConsentConfig())->setEnabled(false);
        $this->setEntityId($config, 1);

        $configRepository = $this->createMock(CookieConsentConfigRepository::class);
        $configRepository->method('find')->willReturn($config);

        $controller = new CookieConsentConfigSettingsAdminController(
            $configRepository,
            $this->createMock(TranslatorInterface::class),
        );
        $this->configureController($controller);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        $controller->edit(1, Request::create('/settings'), $this->createMock(EntityManagerInterface::class));
    }

    private function createSettingsController(): CookieConsentConfigSettingsAdminController
    {
        $config = (new CookieConsentConfig())->setEnabled(true);
        $this->setEntityId($config, 1);

        $configRepository = $this->createMock(CookieConsentConfigRepository::class);
        $configRepository->method('find')->willReturn($config);

        $translator = $this->createMock(TranslatorInterface::class);

        $controller = new CookieConsentConfigSettingsAdminController($configRepository, $translator);
        $this->configureController($controller);

        return $controller;
    }

    private function setEntityId(object $entity, int $id): void
    {
        $reflection = new \ReflectionProperty($entity, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($entity, $id);
    }
}
