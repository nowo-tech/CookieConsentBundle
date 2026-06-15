<?php

declare(strict_types=1);

namespace App\Controller;

use App\Demo\DemoLocale;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieDefinition;
use Nowo\CookieConsentBundle\Entity\CookieDefinitionTranslation;
use Nowo\CookieConsentBundle\Form\CookieDefinitionType;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;
use Nowo\CookieConsentBundle\Repository\CookieDefinitionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(
    '/{_locale}/demo/admin/cookie-consent-config/{configId}/cookies',
    name: 'demo_cookie_consent_cookies_',
    requirements: ['_locale' => DemoLocale::REQUIREMENT, 'configId' => '\d+'],
    defaults: ['_locale' => DemoLocale::DEFAULT],
)]
class CookieDefinitionController extends AbstractController
{
    public function __construct(
        private readonly CookieConsentConfigRepository $configRepository,
        private readonly CookieDefinitionRepository $definitionRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(int $configId): Response
    {
        $config = $this->getConfig($configId);

        return $this->render('demo/cookies/index.html.twig', [
            'config'      => $config,
            'definitions' => $this->definitionRepository->findByConfigOrdered($config),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(int $configId, Request $request, EntityManagerInterface $entityManager): Response
    {
        $config     = $this->getConfig($configId);
        $definition = (new CookieDefinition())->setConfig($config);
        $this->ensureAllDemoTranslations($definition);

        return $this->handleForm($request, $entityManager, $config, $definition, true);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(int $configId, int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $config     = $this->getConfig($configId);
        $definition = $this->getDefinition($config, $id);
        $this->ensureAllDemoTranslations($definition);

        return $this->handleForm($request, $entityManager, $config, $definition, false);
    }

    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(int $configId, int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $config     = $this->getConfig($configId);
        $definition = $this->getDefinition($config, $id);

        if (!$this->isCsrfTokenValid('delete-cookie-definition-' . $definition->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $entityManager->remove($definition);
        $entityManager->flush();

        $this->addFlash('success', $this->translator->trans('demo.cookies.deleted'));

        return $this->redirectToRoute('demo_cookie_consent_cookies_index', [
            'configId' => $configId,
        ]);
    }

    private function handleForm(
        Request $request,
        EntityManagerInterface $entityManager,
        CookieConsentConfig $config,
        CookieDefinition $definition,
        bool $isNew,
    ): Response {
        $form = $this->createForm(CookieDefinitionType::class, $definition, $this->formOptions());
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
                $this->translator->trans($isNew ? 'demo.cookies.created' : 'demo.cookies.updated'),
            );

            return $this->redirectToRoute('demo_cookie_consent_cookies_index', [
                'configId' => $config->getId(),
            ]);
        }

        return $this->render('demo/cookies/form.html.twig', [
            'config'               => $config,
            'definition'           => $definition,
            'form'                 => $form,
            'isNew'                => $isNew,
            'translationTabLocale' => DemoLocale::isSupported($request->getLocale()) ? $request->getLocale() : DemoLocale::DEFAULT,
        ]);
    }

    /** @return array<string, string> */
    private function formOptions(): array
    {
        return [
            'translation_domain'    => 'messages',
            'label_prefix'          => 'demo.cookies.fields.',
            'category_label_prefix' => 'demo.cookies.category.',
            'type_label_prefix'     => 'demo.cookies.type.',
        ];
    }

    private function ensureAllDemoTranslations(CookieDefinition $definition): void
    {
        foreach (DemoLocale::ALL as $locale) {
            $this->ensureTranslationForLocale($definition, $locale);
        }
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
