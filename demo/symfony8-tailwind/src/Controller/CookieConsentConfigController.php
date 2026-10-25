<?php

declare(strict_types=1);

namespace App\Controller;

use App\Demo\DemoLocale;
use App\Form\CookieConsentConfigTranslationType;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfigTranslation;
use Nowo\CookieConsentBundle\Form\Settings\CookieConsentConfigFullSettingsType;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigTranslationRepository;
use Nowo\FormKitBundle\Form\CsrfOnlyFormFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(
    '/{_locale}/demo/admin/cookie-consent-config',
    name: 'demo_cookie_consent_config_',
    requirements: ['_locale' => DemoLocale::REQUIREMENT],
    defaults: ['_locale' => DemoLocale::DEFAULT],
)]
class CookieConsentConfigController extends AbstractController
{
    public function __construct(
        private readonly CookieConsentConfigRepository $configRepository,
        private readonly CookieConsentConfigTranslationRepository $translationRepository,
        private readonly TranslatorInterface $translator,
        private readonly CsrfOnlyFormFactory $csrfOnlyFormFactory,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('demo/config/index.html.twig', [
            'configs' => $this->configRepository->findAllEnabled(),
        ]);
    }

    #[Route('/new-profile', name: 'new_profile', methods: ['GET', 'POST'])]
    public function newProfile(Request $request, EntityManagerInterface $entityManager): Response
    {
        $config = (new CookieConsentConfig())
            ->setEnabled(true)
            ->setName('New profile');

        return $this->handleSettingsForm($request, $entityManager, $config, true);
    }

    #[Route('/settings', name: 'settings', methods: ['GET'])]
    public function legacySettings(): Response
    {
        $config = $this->configRepository->findDefaultEnabled();

        if ($config === null) {
            return $this->redirectToRoute('demo_cookie_consent_config_new_profile');
        }

        return $this->redirectToRoute('demo_cookie_consent_config_profile_settings', ['configId' => $config->getId()]);
    }

    #[Route('/{configId}/settings', name: 'profile_settings', requirements: ['configId' => '\d+'], methods: ['GET', 'POST'])]
    public function profileSettings(
        int $configId,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        return $this->handleSettingsForm($request, $entityManager, $this->getConfig($configId), false);
    }

    #[Route('/{configId}', name: 'show', requirements: ['configId' => '\d+'], methods: ['GET'])]
    public function show(int $configId): Response
    {
        $config = $this->getConfig($configId);

        $deleteForms = [];
        foreach ($config->getTranslations() as $translation) {
            $deleteForms[$translation->getId()] = $this->csrfForm(
                'demo_cookie_consent_config_delete',
                ['id' => $translation->getId()],
                'delete-cookie-consent-config-' . $translation->getId(),
            )->createView();
        }

        return $this->render('demo/config/show.html.twig', [
            'config' => $config,
            'translations' => $config->getTranslations(),
            'delete_forms' => $deleteForms,
            'delete_profile_form' => $this->csrfForm(
                'demo_cookie_consent_config_delete_profile',
                ['configId' => $config->getId()],
                'delete-cookie-consent-profile-' . $config->getId(),
            )->createView(),
        ]);
    }

    #[Route('/{configId}/new', name: 'new', requirements: ['configId' => '\d+'], methods: ['GET', 'POST'])]
    public function new(int $configId, Request $request, EntityManagerInterface $entityManager): Response
    {
        $config = $this->getConfig($configId);
        $translation = new CookieConsentConfigTranslation();
        $translation->setConfig($config);

        $form = $this->createForm(CookieConsentConfigTranslationType::class, $translation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $config->addTranslation($translation);
            $entityManager->persist($translation);
            $entityManager->flush();

            $this->addFlash('success', $this->translator->trans('demo.flash.config_created', ['%locale%' => $translation->getLocale()]));

            return $this->redirectToRoute('demo_cookie_consent_config_show', ['configId' => $config->getId()]);
        }

        return $this->render('demo/config/new.html.twig', [
            'config' => $config,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $translation = $this->getTranslation($id);
        $form = $this->createForm(CookieConsentConfigTranslationType::class, $translation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', $this->translator->trans('demo.flash.config_updated', ['%locale%' => $translation->getLocale()]));

            return $this->redirectToRoute('demo_cookie_consent_config_show', ['configId' => $translation->getConfig()?->getId()]);
        }

        return $this->render('demo/config/edit.html.twig', [
            'translation' => $translation,
            'config' => $translation->getConfig(),
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $translation = $this->getTranslation($id);
        $tokenId = 'delete-cookie-consent-config-' . $translation->getId();

        if (!$this->isCsrfFormValid('demo_cookie_consent_config_delete', ['id' => $translation->getId()], $tokenId, $request)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $configId = $translation->getConfig()?->getId();
        $locale = $translation->getLocale();
        $entityManager->remove($translation);
        $entityManager->flush();

        $this->addFlash('success', $this->translator->trans('demo.flash.config_deleted', ['%locale%' => $locale]));

        return $this->redirectToRoute('demo_cookie_consent_config_show', ['configId' => $configId]);
    }

    #[Route('/{configId}/delete-profile', name: 'delete_profile', requirements: ['configId' => '\d+'], methods: ['POST'])]
    public function deleteProfile(
        int $configId,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $config = $this->getConfig($configId);

        if ($config->isDefault()) {
            throw $this->createAccessDeniedException('The default profile cannot be deleted.');
        }

        $tokenId = 'delete-cookie-consent-profile-' . $config->getId();

        if (!$this->isCsrfFormValid('demo_cookie_consent_config_delete_profile', ['configId' => $config->getId()], $tokenId, $request)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $entityManager->remove($config);
        $entityManager->flush();

        $this->addFlash('success', $this->translator->trans('demo.flash.profile_deleted', ['%name%' => $config->getDisplayName()]));

        return $this->redirectToRoute('demo_cookie_consent_config_index');
    }

    private function getConfig(int $configId): CookieConsentConfig
    {
        $config = $this->configRepository->find($configId);

        if ($config === null) {
            throw $this->createNotFoundException(sprintf('Cookie consent config "%d" not found.', $configId));
        }

        return $config;
    }

    private function getTranslation(int $id): CookieConsentConfigTranslation
    {
        $translation = $this->translationRepository->find($id);

        if ($translation === null) {
            throw $this->createNotFoundException(sprintf('Cookie consent translation "%d" not found.', $id));
        }

        return $translation;
    }

    private function handleSettingsForm(
        Request $request,
        EntityManagerInterface $entityManager,
        CookieConsentConfig $config,
        bool $isNew,
    ): Response {
        if ($isNew) {
            $entityManager->persist($config);
        }

        $form = $this->createForm(CookieConsentConfigFullSettingsType::class, $config, [
            'translation_domain'           => 'messages',
            'label_prefix'                 => 'demo.config.settings.fields.',
            'choice_label_prefix'          => 'demo.config.settings.',
            'route_patterns_placeholder'   => "demo_admin_*\ndemo_cookie_consent_config_*",
            'auto_show_routes_placeholder' => "demo_home\ndemo_admin_*",
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($config->isDefault()) {
                $this->clearOtherDefaultFlags($entityManager, $config);
            }

            $entityManager->flush();

            $this->addFlash('success', $this->translator->trans('demo.flash.settings_updated'));

            return $this->redirectToRoute('demo_cookie_consent_config_show', ['configId' => $config->getId()]);
        }

        return $this->render('demo/config/settings.html.twig', [
            'config' => $config,
            'form' => $form,
            'is_new_profile' => $isNew,
        ]);
    }

    private function clearOtherDefaultFlags(EntityManagerInterface $entityManager, CookieConsentConfig $current): void
    {
        foreach ($this->configRepository->findAllEnabled() as $config) {
            if ($config->getId() === $current->getId()) {
                continue;
            }

            if ($config->isDefault()) {
                $config->setDefault(false);
            }
        }
    }

    /**
     * REQ-TWIG-005: CSRF-only Form (FormKit CsrfOnlyType, flat _token, same token id as before).
     *
     * @param array<string, mixed> $params
     *
     * @return FormInterface<mixed>
     */
    private function csrfForm(string $route, array $params, string $tokenId): FormInterface
    {
        return $this->csrfOnlyFormFactory->create($this->generateUrl($route, $params), $tokenId);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function isCsrfFormValid(string $route, array $params, string $tokenId, Request $request): bool
    {
        $form = $this->csrfForm($route, $params, $tokenId);
        $form->submit($request->request->all());

        return $form->isValid();
    }
}
