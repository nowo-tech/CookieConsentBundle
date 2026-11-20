<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\CookieConsentBundle\Admin\CookieConsentConfigSettingsSection;
use Nowo\CookieConsentBundle\Concurrency\StaleVersionException;
use Nowo\CookieConsentBundle\Controller\CookieConsentConfigSettingsAdminController;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Form\RowVersionField;
use Nowo\CookieConsentBundle\Form\Settings\CookieConsentConfigProfileSettingsType;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;
use Nowo\CookieConsentBundle\Tests\Support\AdminPageFixtures;
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
        // 1.31: the repository saves in one transaction and clears the other defaults atomically.
        $configRepository->expects(self::once())->method('saveSettings')->willReturnCallback(static function (CookieConsentConfig $saved) use ($config, $otherDefault): void {
            self::assertSame($config, $saved);
            $otherDefault->setDefault(false);
        });

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

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturn('saved');

        $controller = new CookieConsentConfigSettingsAdminController($configRepository, $translator, AdminPageFixtures::factory());
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
            AdminPageFixtures::factory(),
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

    public function testStaleSaveReRendersTheFormWith409AndSavesNothing(): void
    {
        $config = (new CookieConsentConfig())->setEnabled(true);
        $this->setEntityId($config, 1);

        $configRepository = $this->createMock(CookieConsentConfigRepository::class);
        $configRepository->method('find')->willReturn($config);
        // 1.33: the page's version token reaches the save; a refused save is a domain answer.
        $configRepository->expects(self::once())->method('saveSettings')
            ->with($config, self::anything(), 4)
            ->willThrowException(new StaleVersionException(CookieConsentConfig::class, 1, 4));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        // The form's unsaved changes are dropped from the managed profile (never clear() or reset the manager).
        $entityManager->expects(self::once())->method('refresh')->with($config);
        $entityManager->expects(self::never())->method('clear');
        $entityManager->expects(self::never())->method('flush');

        $controller = $this->staleController($configRepository, $this->submittedFormFactory($config, 4));

        $response = $controller->section(1, CookieConsentConfigSettingsSection::Profile->value, Request::create('/settings/profile', 'POST'), $entityManager);

        self::assertSame(409, $response->getStatusCode());
        self::assertSame(StaleVersionException::ERROR_CODE, $response->headers->get('X-Error-Code'));
        self::assertFalse($response->headers->has('X-Conflict-Code'), 'a domain answer, not CONFLICT_001 / CONFLICT_002');
        self::assertFalse($response->headers->has('Retry-After'));
        self::assertSame('rendered', (string) $response->getContent());
    }

    private function staleController(CookieConsentConfigRepository $configRepository, FormFactoryInterface $formFactory): CookieConsentConfigSettingsAdminController
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturn('stale message');

        $controller = new CookieConsentConfigSettingsAdminController($configRepository, $translator, AdminPageFixtures::factory());
        $this->configureController($controller, formFactory: $formFactory);

        return $controller;
    }

    private function submittedFormFactory(CookieConsentConfig $config, int $token): FormFactoryInterface
    {
        $field = $this->createMock(FormInterface::class);
        $field->method('getData')->willReturn((string) $token);

        $form = $this->createMock(FormInterface::class);
        $form->method('createView')->willReturn(new FormView());
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('handleRequest');
        $form->method('has')->with(RowVersionField::NAME)->willReturn(true);
        $form->method('get')->with(RowVersionField::NAME)->willReturn($field);

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->method('create')->with(CookieConsentConfigProfileSettingsType::class, $config)->willReturn($form);

        return $formFactory;
    }

    private function createSettingsController(): CookieConsentConfigSettingsAdminController
    {
        $config = (new CookieConsentConfig())->setEnabled(true);
        $this->setEntityId($config, 1);

        $configRepository = $this->createMock(CookieConsentConfigRepository::class);
        $configRepository->method('find')->willReturn($config);

        $translator = $this->createMock(TranslatorInterface::class);

        $controller = new CookieConsentConfigSettingsAdminController($configRepository, $translator, AdminPageFixtures::factory());
        $this->configureController($controller);

        return $controller;
    }

    private function setEntityId(object $entity, int $id): void
    {
        $reflection = new ReflectionProperty($entity, 'id');
        $reflection->setValue($entity, $id);
    }
}
