<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\CookieConsentBundle\Admin\AdminPageFactory;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieDefinition;
use Nowo\CookieConsentBundle\Form\CookieDefinitionType;
use Nowo\CookieConsentBundle\Form\DeleteCookieDefinitionType;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;
use Nowo\CookieConsentBundle\Repository\CookieDefinitionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

use function ceil;
use function is_array;
use function is_string;
use function max;

#[Route(
    '/cookie-consent-config/{configId}/cookies',
    name: 'nowo_cookie_consent_cookie_definitions_',
    requirements: ['configId' => '\d+'],
)]
/**
 * Admin CRUD controller for cookie inventory definitions linked to a consent profile.
 */
final class CookieDefinitionAdminController extends AbstractController
{
    /**
     * Creates a new cookie definition admin controller.
     *
     * @param CookieConsentConfigRepository $configRepository Repository for consent profiles
     * @param CookieDefinitionRepository $definitionRepository Repository for cookie definitions
     * @param TranslatorInterface $translator Symfony translator for flash messages
     * @param ParameterBagInterface $parameterBag Container parameters (list page size)
     * @param AdminPageFactory $pages UiKit view models and templates of the admin pages (REQ-UI-003)
     * @param FormFactoryInterface|null $formFactory Form factory for the per-row delete forms (autowired)
     */
    public function __construct(
        private readonly CookieConsentConfigRepository $configRepository,
        private readonly CookieDefinitionRepository $definitionRepository,
        private readonly TranslatorInterface $translator,
        private readonly ParameterBagInterface $parameterBag,
        private readonly AdminPageFactory $pages,
        private readonly ?FormFactoryInterface $formFactory = null,
    ) {
    }

    /**
     * Lists cookie definitions for a consent profile.
     *
     * @param int $configId The consent profile identifier
     * @param Request $request The current HTTP request
     *
     * @return Response The rendered index page
     */
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(int $configId, Request $request): Response
    {
        $config   = $this->getConfig($configId);
        $pageSize = max(1, (int) $this->parameterBag->get('nowo_cookie_consent.web_ui.list_page_size'));
        $page     = max(1, $request->query->getInt('page', 1));
        $total    = $this->definitionRepository->countByConfig($config);
        $pages    = max(1, (int) ceil($total / $pageSize));
        if ($page > $pages) {
            $page = $pages;
        }

        $definitions = $this->definitionRepository->findByConfigOrderedPaginated($config, $page, $pageSize);
        $deleteForms = $this->createDeleteForms($configId, $definitions);

        return $this->render($this->pages->template('cookie_definition_index'), [
            'page' => $this->pages->definitionList($config, $definitions, $deleteForms, $page, $pageSize, $total, $request->getLocale()),
            // 1.x variables, for host templates (web_ui.templates.cookie_definition_index)
            'config'       => $config,
            'definitions'  => $definitions,
            'delete_forms' => $deleteForms,
            'pages'        => $pages,
            'total'        => $total,
            'pageSize'     => $pageSize,
        ]);
    }

    /**
     * Creates a new cookie definition for a consent profile.
     *
     * @param int $configId The consent profile identifier
     * @param Request $request The current HTTP request
     * @param EntityManagerInterface $entityManager Doctrine entity manager
     *
     * @return Response The rendered form or redirect after success
     */
    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(int $configId, Request $request, EntityManagerInterface $entityManager): Response
    {
        $config     = $this->getConfig($configId);
        $definition = (new CookieDefinition())->setConfig($config);

        return $this->handleForm($request, $entityManager, $config, $definition, true);
    }

    /**
     * Edits an existing cookie definition.
     *
     * @param int $configId The consent profile identifier
     * @param int $id The cookie definition identifier
     * @param Request $request The current HTTP request
     * @param EntityManagerInterface $entityManager Doctrine entity manager
     *
     * @return Response The rendered form or redirect after success
     */
    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(int $configId, int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $config     = $this->getConfig($configId);
        $definition = $this->getDefinition($config, $id);

        return $this->handleForm($request, $entityManager, $config, $definition, false);
    }

    /**
     * Deletes a cookie definition after CSRF validation.
     *
     * @param int $configId The consent profile identifier
     * @param int $id The cookie definition identifier
     * @param Request $request The current HTTP request
     * @param EntityManagerInterface $entityManager Doctrine entity manager
     *
     * @return RedirectResponse Redirect to the index page
     */
    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(int $configId, int $id, Request $request, EntityManagerInterface $entityManager): RedirectResponse
    {
        $config     = $this->getConfig($configId);
        $definition = $this->getDefinition($config, $id);

        if (!$this->isCsrfTokenValid('delete-cookie-definition-' . $definition->getId(), $this->getDeleteCsrfToken($request, $id))) {
            throw new AccessDeniedHttpException();
        }

        $entityManager->remove($definition);
        $entityManager->flush();

        $this->addFlash('success', $this->translator->trans('nowo_cookie_consent.admin.cookie_definition.deleted', [], 'NowoCookieConsentBundle'));

        return $this->redirectToRoute('nowo_cookie_consent_cookie_definitions_index', [
            'configId' => $configId,
        ]);
    }

    /**
     * Reads the CSRF token posted by the row delete form (`delete_cookie_definition_<id>[_token]`), falling back to
     * the 1.x form name (`delete_cookie_definition[_token]`) and to a top-level `_token` field for custom templates.
     *
     * @param Request $request The current HTTP request
     * @param int $id The cookie definition identifier
     *
     * @return string The submitted token (empty when missing or not a string)
     */
    private function getDeleteCsrfToken(Request $request, int $id): string
    {
        $formData = $request->request->all();
        $payload  = $formData[DeleteCookieDefinitionType::formName($id)] ?? $formData[DeleteCookieDefinitionType::FORM_NAME] ?? null;
        $token    = is_array($payload) ? ($payload['_token'] ?? null) : ($formData['_token'] ?? null);

        return is_string($token) ? $token : '';
    }

    /**
     * Builds CSRF-protected delete forms for each cookie definition row.
     *
     * @param int $configId The consent profile identifier
     * @param list<CookieDefinition> $definitions The current page of cookie definitions
     *
     * @return array<int, mixed> Delete form views keyed by cookie definition id
     */
    private function createDeleteForms(int $configId, array $definitions): array
    {
        $forms = [];
        // REQ-SF-003: injected (autowired) instead of pulled from the service container.
        $formFactory = $this->formFactory;
        if (!$formFactory instanceof FormFactoryInterface) {
            return $forms;
        }

        foreach ($definitions as $definition) {
            if ($definition->getId() === null) {
                continue;
            }

            // One form name per row: unique ids for the hidden token inputs of the confirm dialogs.
            $forms[$definition->getId()] = $formFactory->createNamed(DeleteCookieDefinitionType::formName($definition->getId()), DeleteCookieDefinitionType::class, null, [
                'action' => $this->generateUrl('nowo_cookie_consent_cookie_definitions_delete', [
                    'configId' => $configId,
                    'id'       => $definition->getId(),
                ]),
                'csrf_token_id' => 'delete-cookie-definition-' . $definition->getId(),
            ])->createView();
        }

        return $forms;
    }

    private function handleForm(
        Request $request,
        EntityManagerInterface $entityManager,
        CookieConsentConfig $config,
        CookieDefinition $definition,
        bool $isNew,
    ): Response {
        $form = $this->createForm(CookieDefinitionType::class, $definition);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($definition->getCategory() === 'required') {
                $definition->setAllowedByDefault(true);
            }

            if ($isNew) {
                $config->addCookieDefinition($definition);
                $entityManager->persist($definition);
            }

            $entityManager->flush();

            $this->addFlash(
                'success',
                $this->translator->trans(
                    $isNew
                        ? 'nowo_cookie_consent.admin.cookie_definition.created'
                        : 'nowo_cookie_consent.admin.cookie_definition.updated',
                    [],
                    'NowoCookieConsentBundle',
                ),
            );

            return $this->redirectToRoute('nowo_cookie_consent_cookie_definitions_index', [
                'configId' => $config->getId(),
            ]);
        }

        $view = $form->createView();

        return $this->render($this->pages->template('cookie_definition_form'), [
            'page' => $this->pages->definitionForm($config, $definition, $view, $isNew),
            // 1.x variables, for host templates (web_ui.templates.cookie_definition_form)
            'config'     => $config,
            'definition' => $definition,
            'form'       => $view,
            'isNew'      => $isNew,
        ], new Response(null, $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    private function getConfig(int $configId): CookieConsentConfig
    {
        $config = $this->configRepository->find($configId);

        if (!$config instanceof CookieConsentConfig || !$config->isEnabled()) {
            throw $this->createNotFoundException();
        }

        return $config;
    }

    private function getDefinition(CookieConsentConfig $config, int $id): CookieDefinition
    {
        $definition = $this->definitionRepository->find($id);

        if (!$definition instanceof CookieDefinition || $definition->getConfig()?->getId() !== $config->getId()) {
            throw $this->createNotFoundException();
        }

        return $definition;
    }
}
