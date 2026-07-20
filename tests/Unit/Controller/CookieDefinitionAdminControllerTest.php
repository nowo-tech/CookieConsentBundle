<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\CookieConsentBundle\Controller\CookieDefinitionAdminController;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieDefinition;
use Nowo\CookieConsentBundle\Form\CookieDefinitionType;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;
use Nowo\CookieConsentBundle\Repository\CookieDefinitionRepository;
use Nowo\CookieConsentBundle\Tests\Unit\Support\AbstractControllerTestCase;
use ReflectionProperty;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

/**
 * @internal test double overriding CSRF validation without security-bundle
 */
final class TestableCookieDefinitionAdminController extends CookieDefinitionAdminController
{
    public function __construct(
        CookieConsentConfigRepository $configRepository,
        CookieDefinitionRepository $definitionRepository,
        TranslatorInterface $translator,
        private readonly bool $csrfValid = true,
    ) {
        parent::__construct($configRepository, $definitionRepository, $translator);
    }

    protected function isCsrfTokenValid(string $tokenId, ?string $token): bool
    {
        return $this->csrfValid;
    }

    protected function createAccessDeniedException(string $message = 'Access Denied.', ?Throwable $previous = null): \Symfony\Component\Security\Core\Exception\AccessDeniedException
    {
        throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException($message, $previous);
    }
}

final class CookieDefinitionAdminControllerTest extends AbstractControllerTestCase
{
    public function testIndexRendersDefinitions(): void
    {
        $config = $this->createEnabledConfig(1);
        $repo   = $this->createMock(CookieDefinitionRepository::class);
        $repo->expects(self::once())
            ->method('findByConfigOrdered')
            ->with($config)
            ->willReturn([]);

        $controller = $this->createDefinitionController(config: $config, definitionRepository: $repo);
        $response   = $controller->index(1);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('rendered', (string) $response->getContent());
    }

    public function testNewRendersFormOnGet(): void
    {
        $controller = $this->createDefinitionController();
        $response   = $controller->new(1, Request::create('/new', 'GET'), $this->createMock(EntityManagerInterface::class));

        self::assertSame(200, $response->getStatusCode());
    }

    public function testEditRendersFormOnGet(): void
    {
        $config     = $this->createEnabledConfig(1);
        $definition = (new CookieDefinition())->setName('_ga')->setConfig($config);
        $this->setEntityId($definition, 5);

        $definitionRepository = $this->createMock(CookieDefinitionRepository::class);
        $definitionRepository->method('find')->willReturn($definition);

        $controller = $this->createDefinitionController(config: $config, definitionRepository: $definitionRepository);
        $response   = $controller->edit(1, 5, Request::create('/edit', 'GET'), $this->createMock(EntityManagerInterface::class));

        self::assertSame(200, $response->getStatusCode());
    }

    public function testDeleteRedirectsWhenCsrfValid(): void
    {
        $config     = $this->createEnabledConfig(1);
        $definition = (new CookieDefinition())->setName('_ga')->setConfig($config);
        $this->setEntityId($definition, 5);

        $definitionRepository = $this->createMock(CookieDefinitionRepository::class);
        $definitionRepository->method('find')->willReturn($definition);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('remove')->with($definition);
        $entityManager->expects(self::once())->method('flush');

        $controller = $this->createDefinitionController(
            config: $config,
            definitionRepository: $definitionRepository,
            csrfValid: true,
        );

        $request  = Request::create('/delete', 'POST', ['_token' => 'valid']);
        $response = $controller->delete(1, 5, $request, $entityManager);

        self::assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testDeleteRejectsInvalidCsrf(): void
    {
        $config     = $this->createEnabledConfig(1);
        $definition = (new CookieDefinition())->setName('_ga')->setConfig($config);
        $this->setEntityId($definition, 5);

        $definitionRepository = $this->createMock(CookieDefinitionRepository::class);
        $definitionRepository->method('find')->willReturn($definition);

        $controller = $this->createDefinitionController(
            config: $config,
            definitionRepository: $definitionRepository,
            csrfValid: false,
        );

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        $controller->delete(1, 5, Request::create('/delete', 'POST', ['_token' => 'bad']), $this->createMock(EntityManagerInterface::class));
    }

    public function testIndexThrowsWhenConfigMissing(): void
    {
        $configRepository = $this->createMock(CookieConsentConfigRepository::class);
        $configRepository->method('find')->willReturn(null);

        $controller = new CookieDefinitionAdminController(
            $configRepository,
            $this->createMock(CookieDefinitionRepository::class),
            $this->createMock(TranslatorInterface::class),
        );
        $this->configureController($controller);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        $controller->index(99);
    }

    public function testEditAddsTranslationWhenMissing(): void
    {
        $config     = $this->createEnabledConfig(1);
        $definition = (new CookieDefinition())->setName('_ga')->setConfig($config);
        $this->setEntityId($definition, 5);

        $definitionRepository = $this->createMock(CookieDefinitionRepository::class);
        $definitionRepository->method('find')->willReturn($definition);

        $request = Request::create('/edit', 'GET');
        $request->setLocale('fr');

        $controller = $this->createDefinitionController(config: $config, definitionRepository: $definitionRepository);
        $response   = $controller->edit(1, 5, $request, $this->createMock(EntityManagerInterface::class));

        self::assertSame(200, $response->getStatusCode());
        self::assertNotNull($definition->findTranslation('fr'));
    }

    public function testEditRedirectsAfterValidSubmit(): void
    {
        $config     = $this->createEnabledConfig(1);
        $definition = (new CookieDefinition())->setName('_ga')->setConfig($config);
        $this->setEntityId($definition, 5);

        $definitionRepository = $this->createMock(CookieDefinitionRepository::class);
        $definitionRepository->method('find')->willReturn($definition);

        $form = $this->createMock(FormInterface::class);
        $form->method('createView')->willReturn(new FormView());
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('handleRequest');

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->method('create')->with(CookieDefinitionType::class)->willReturn($form);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $controller = $this->createDefinitionController(
            config: $config,
            definitionRepository: $definitionRepository,
            formFactory: $formFactory,
        );

        $response = $controller->edit(1, 5, Request::create('/edit', 'POST'), $entityManager);

        self::assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testNewPersistsRequiredCategoryAsAllowedByDefault(): void
    {
        $config = $this->createEnabledConfig(1);

        $form = $this->createMock(FormInterface::class);
        $form->method('createView')->willReturn(new FormView());
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('handleRequest');

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->method('create')->with(CookieDefinitionType::class)->willReturn($form);

        $persisted     = null;
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted = $entity;
        });
        $entityManager->expects(self::once())->method('flush');

        $controller = $this->createDefinitionController(config: $config, formFactory: $formFactory);
        $response   = $controller->new(1, Request::create('/new', 'POST'), $entityManager);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertInstanceOf(CookieDefinition::class, $persisted);
        self::assertTrue($persisted->isAllowedByDefault());
    }

    private function createDefinitionController(
        ?CookieConsentConfig $config = null,
        ?CookieDefinitionRepository $definitionRepository = null,
        ?FormFactoryInterface $formFactory = null,
        bool $csrfValid = true,
    ): CookieDefinitionAdminController {
        $config ??= $this->createEnabledConfig(1);

        $configRepository = $this->createMock(CookieConsentConfigRepository::class);
        $configRepository->method('find')->willReturn($config);

        $definitionRepository ??= $this->createMock(CookieDefinitionRepository::class);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturn('translated');

        $controller = new TestableCookieDefinitionAdminController(
            $configRepository,
            $definitionRepository,
            $translator,
            $csrfValid,
        );
        $this->configureController(
            $controller,
            formFactory: $formFactory,
        );

        return $controller;
    }

    private function createEnabledConfig(int $id): CookieConsentConfig
    {
        $config = (new CookieConsentConfig())->setEnabled(true)->setName('Default');
        $this->setEntityId($config, $id);

        return $config;
    }

    private function setEntityId(object $entity, int $id): void
    {
        $reflection = new ReflectionProperty($entity, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($entity, $id);
    }
}
