<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\CookieConsentBundle\Controller\CookieDefinitionAdminController;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieDefinition;
use Nowo\CookieConsentBundle\Entity\CookieDefinitionTranslation;
use Nowo\CookieConsentBundle\Form\CookieDefinitionType;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;
use Nowo\CookieConsentBundle\Repository\CookieDefinitionRepository;
use Nowo\CookieConsentBundle\Tests\Unit\Support\AbstractControllerTestCase;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final class CookieDefinitionAdminControllerTest extends AbstractControllerTestCase
{
    public function testIndexRendersDefinitions(): void
    {
        $config = $this->createEnabledConfig(1);
        $repo   = $this->createMock(CookieDefinitionRepository::class);
        $repo->expects(self::once())
            ->method('countByConfig')
            ->with($config)
            ->willReturn(0);
        $repo->expects(self::once())
            ->method('findByConfigOrderedPaginated')
            ->with($config, 1, 20)
            ->willReturn([]);

        $controller = $this->createDefinitionController(config: $config, definitionRepository: $repo);
        $response   = $controller->index(1, Request::create('/'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('rendered', (string) $response->getContent());
    }

    public function testIndexClampsPageBeyondLast(): void
    {
        $config = $this->createEnabledConfig(1);
        $repo   = $this->createMock(CookieDefinitionRepository::class);
        $repo->method('countByConfig')->willReturn(5);
        $repo->expects(self::once())
            ->method('findByConfigOrderedPaginated')
            ->with($config, 1, 20)
            ->willReturn([]);

        $controller = $this->createDefinitionController(config: $config, definitionRepository: $repo);
        $response   = $controller->index(1, Request::create('/', 'GET', ['page' => 99]));

        self::assertSame(200, $response->getStatusCode());
    }

    public function testIndexProvidesDeleteFormsForDefinitions(): void
    {
        $config     = $this->createEnabledConfig(1);
        $definition = (new CookieDefinition())->setName('_ga')->setConfig($config);
        $this->setEntityId($definition, 5);

        $repo = $this->createMock(CookieDefinitionRepository::class);
        $repo->method('countByConfig')->willReturn(1);
        $repo->expects(self::once())
            ->method('findByConfigOrderedPaginated')
            ->with($config, 1, 20)
            ->willReturn([$definition]);

        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())
            ->method('render')
            ->with(
                '@NowoCookieConsentBundle/admin/cookie_definition/index.html.twig',
                self::callback(static function (array $context): bool {
                    return isset($context['delete_forms'][5]);
                }),
            )
            ->willReturn('rendered');

        $controller = $this->createDefinitionController(config: $config, definitionRepository: $repo, twig: $twig);
        $response   = $controller->index(1, Request::create('/'));

        self::assertSame(200, $response->getStatusCode());
    }

    public function testIndexSkipsDeleteFormsForDefinitionsWithoutId(): void
    {
        $config            = $this->createEnabledConfig(1);
        $persisted         = (new CookieDefinition())->setName('_ga')->setConfig($config);
        $unsavedDefinition = (new CookieDefinition())->setName('_gid')->setConfig($config);
        $this->setEntityId($persisted, 5);

        $repo = $this->createMock(CookieDefinitionRepository::class);
        $repo->method('countByConfig')->willReturn(2);
        $repo->expects(self::once())
            ->method('findByConfigOrderedPaginated')
            ->with($config, 1, 20)
            ->willReturn([$persisted, $unsavedDefinition]);

        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())
            ->method('render')
            ->with(
                '@NowoCookieConsentBundle/admin/cookie_definition/index.html.twig',
                self::callback(static function (array $context): bool {
                    return isset($context['delete_forms'][5])
                        && count($context['delete_forms']) === 1;
                }),
            )
            ->willReturn('rendered');

        $controller = $this->createDefinitionController(config: $config, definitionRepository: $repo, twig: $twig);
        $response   = $controller->index(1, Request::create('/'));

        self::assertSame(200, $response->getStatusCode());
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

        self::assertSame(302, $response->getStatusCode());
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

        $this->expectException(AccessDeniedHttpException::class);

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
            $this->createParameterBag(),
        );
        $this->configureController($controller);

        $this->expectException(NotFoundHttpException::class);
        $controller->index(99, Request::create('/'));
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

    public function testEditAddsEnglishTranslationWhenLocaleEmpty(): void
    {
        $config     = $this->createEnabledConfig(1);
        $definition = (new CookieDefinition())->setName('_ga')->setConfig($config);
        $this->setEntityId($definition, 5);

        $controller = $this->createDefinitionController(config: $config);
        $method     = new ReflectionMethod(CookieDefinitionAdminController::class, 'ensureTranslationForLocale');
        $method->invoke($controller, $definition, '');

        self::assertNotNull($definition->findTranslation('en'));
    }

    public function testEnsureTranslationIsIdempotentWhenLocaleExists(): void
    {
        $config     = $this->createEnabledConfig(1);
        $definition = (new CookieDefinition())->setName('_ga')->setConfig($config);
        $definition->addTranslation((new CookieDefinitionTranslation())->setLocale('en'));
        $this->setEntityId($definition, 5);

        $controller = $this->createDefinitionController(config: $config);
        $method     = new ReflectionMethod(CookieDefinitionAdminController::class, 'ensureTranslationForLocale');
        $method->invoke($controller, $definition, 'en');

        self::assertCount(1, $definition->getTranslations());
    }

    public function testEditThrowsWhenDefinitionBelongsToAnotherConfig(): void
    {
        $config      = $this->createEnabledConfig(1);
        $otherConfig = $this->createEnabledConfig(2);
        $definition  = (new CookieDefinition())->setName('_ga')->setConfig($otherConfig);
        $this->setEntityId($definition, 5);

        $definitionRepository = $this->createMock(CookieDefinitionRepository::class);
        $definitionRepository->method('find')->willReturn($definition);

        $controller = $this->createDefinitionController(config: $config, definitionRepository: $definitionRepository);

        $this->expectException(NotFoundHttpException::class);
        $controller->edit(1, 5, Request::create('/edit', 'GET'), $this->createMock(EntityManagerInterface::class));
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
        ?Environment $twig = null,
    ): CookieDefinitionAdminController {
        $config ??= $this->createEnabledConfig(1);

        $configRepository = $this->createMock(CookieConsentConfigRepository::class);
        $configRepository->method('find')->willReturn($config);

        $definitionRepository ??= $this->createMock(CookieDefinitionRepository::class);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturn('translated');

        $controller = new CookieDefinitionAdminController(
            $configRepository,
            $definitionRepository,
            $translator,
            $this->createParameterBag(),
        );
        $this->configureController(
            $controller,
            twig: $twig,
            formFactory: $formFactory,
            csrfTokenManager: $this->createCsrfTokenManager($csrfValid),
        );

        return $controller;
    }

    private function createParameterBag(int $listPageSize = 20): ParameterBagInterface
    {
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')
            ->with('nowo_cookie_consent.web_ui.list_page_size')
            ->willReturn($listPageSize);

        return $parameterBag;
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
        $reflection->setValue($entity, $id);
    }
}
