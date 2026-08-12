<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieDefinition;
use Nowo\CookieConsentBundle\Entity\CookieDefinitionTranslation;
use Nowo\CookieConsentBundle\Form\CookieDefinitionType;
use Nowo\CookieConsentBundle\Form\DeleteCookieDefinitionType;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;
use Nowo\CookieConsentBundle\Repository\CookieDefinitionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

use function ceil;
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
     */
    public function __construct(
        private readonly CookieConsentConfigRepository $configRepository,
        private readonly CookieDefinitionRepository $definitionRepository,
        private readonly TranslatorInterface $translator,
        private readonly ParameterBagInterface $parameterBag,
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

        return $this->render('@NowoCookieConsentBundle/admin/cookie_definition/index.html.twig', [
            'config'       => $config,
            'definitions'  => $definitions,
            'delete_forms' => $this->createDeleteForms($configId, $definitions),
            'page'         => $page,
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
        $this->ensureTranslationForLocale($definition, $request->getLocale());

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

        if ($definition->getTranslations()->isEmpty()) {
            $this->ensureTranslationForLocale($definition, $request->getLocale());
        }

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

        if (!$this->isCsrfTokenValid('delete-cookie-definition-' . $definition->getId(), (string) $request->request->get('_token'))) {
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

        foreach ($definitions as $definition) {
            if (null === $definition->getId()) {
                continue;
            }

            $forms[$definition->getId()] = $this->createForm(DeleteCookieDefinitionType::class, null, [
                'action' => $this->generateUrl('nowo_cookie_consent_cookie_definitions_delete', [
                    'configId' => $configId,
                    'id' => $definition->getId(),
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

        return $this->render('@NowoCookieConsentBundle/admin/cookie_definition/form.html.twig', [
            'config'     => $config,
            'definition' => $definition,
            'form'       => $form,
            'isNew'      => $isNew,
        ]);
    }

    private function ensureTranslationForLocale(CookieDefinition $definition, string $locale): void
    {
        if ($definition->findTranslation($locale) instanceof CookieDefinitionTranslation) {
            return;
        }

        $definition->addTranslation(
            (new CookieDefinitionTranslation())->setLocale($locale !== '' ? $locale : 'en'),
        );
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
