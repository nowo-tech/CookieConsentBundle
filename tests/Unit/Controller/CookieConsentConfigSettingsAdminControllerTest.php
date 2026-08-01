<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\CookieConsentBundle\Admin\CookieConsentConfigSettingsSection;
use Nowo\CookieConsentBundle\Controller\CookieConsentConfigSettingsAdminController;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Form\Settings\CookieConsentConfigProfileSettingsType;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;
use Nowo\CookieConsentBundle\Tests\Unit\Support\AbstractControllerTestCase;
use ReflectionProperty;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

final class CookieConsentConfigSettingsAdminControllerTest extends AbstractControllerTestCase
{
    public function testEditRedirectsToProfileSection(): void
    {
        $controller = $this->createSettingsController();
        $response   = $controller->edit(1);

        self::assertSame('/redirect', $response->getTargetUrl());
    }

    public function testSectionRendersFormOnGet(): void
    {
        $controller = $this->createSettingsController();
        $response   = $controller->section(
            1,
            CookieConsentConfigSettingsSection::Profile->value,
            Request::create('/settings/profile', 'GET'),
            $this->createMock(EntityManagerInterface::class),
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('rendered', (string) $response->getContent());
    }

    public function testSectionRedirectsAfterValidSubmit(): void
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
        $formFactory->method('create')->with(
            CookieConsentConfigProfileSettingsType::class,
            $config,
        )->willReturn($form);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturn('saved');

        $controller = new CookieConsentConfigSettingsAdminController($configRepository, $translator);
        $this->configureController($controller, formFactory: $formFactory);

        $response = $controller->section(
            1,
            CookieConsentConfigSettingsSection::Profile->value,
            Request::create('/settings/profile', 'POST'),
            $entityManager,
        );

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertFalse($otherDefault->isDefault());
    }

    public function testSectionThrowsWhenConfigDisabled(): void
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

        $this->expectException(NotFoundHttpException::class);
        $controller->section(
            1,
            CookieConsentConfigSettingsSection::Profile->value,
            Request::create('/settings/profile'),
            $this->createMock(EntityManagerInterface::class),
        );
    }

    public function testSectionThrowsForUnknownSectionSlug(): void
    {
        $controller = $this->createSettingsController();

        $this->expectException(NotFoundHttpException::class);
        $controller->section(
            1,
            'not-a-section',
            Request::create('/settings/not-a-section'),
            $this->createMock(EntityManagerInterface::class),
        );
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
        $reflection = new ReflectionProperty($entity, 'id');
        $reflection->setValue($entity, $id);
    }
}
