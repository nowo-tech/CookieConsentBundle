<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\CookieConsentBundle\Admin\CookieConsentConfigSettingsSection;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Form\CookieConsentConfigSettingsType;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(
    '/cookie-consent-config/{configId}/settings',
    name: 'nowo_cookie_consent_config_settings_',
    requirements: ['configId' => '\d+'],
)]
/**
 * Admin controller for editing CookieConsentConfig profile settings (including page overlay).
 */
final class CookieConsentConfigSettingsAdminController extends AbstractController
{
    /**
     * Creates a new config settings admin controller.
     *
     * @param CookieConsentConfigRepository $configRepository Repository for consent profiles
     * @param TranslatorInterface $translator Symfony translator for flash messages
     */
    public function __construct(
        private readonly CookieConsentConfigRepository $configRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * BC entry: /settings redirects to the Profile section.
     *
     * @param int $configId The consent profile identifier
     */
    #[Route('', name: 'edit', methods: ['GET'])]
    public function edit(int $configId): RedirectResponse
    {
        $this->getConfig($configId);

        return $this->redirectToRoute('nowo_cookie_consent_config_settings_section', [
            'configId' => $configId,
            'section'  => CookieConsentConfigSettingsSection::Profile->value,
        ]);
    }

    /**
     * Edits one settings section for a consent profile.
     *
     * @param int $configId The consent profile identifier
     * @param string $section Route slug (see CookieConsentConfigSettingsSection)
     * @param Request $request The current HTTP request
     * @param EntityManagerInterface $entityManager Doctrine entity manager
     *
     * @return Response The rendered form or redirect after success
     */
    #[Route(
        '/{section}',
        name: 'section',
        requirements: ['section' => 'profile|behavior|appearance|consent-modal|preferences-modal|route-targeting'],
        methods: ['GET', 'POST'],
    )]
    public function section(
        int $configId,
        string $section,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $sectionEnum = CookieConsentConfigSettingsSection::tryFrom($section);
        if ($sectionEnum === null) {
            throw $this->createNotFoundException();
        }

        $config = $this->getConfig($configId);
        $form   = $this->createForm(CookieConsentConfigSettingsType::class, $config, [
            'section' => $sectionEnum,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($config->isDefault()) {
                $this->clearOtherDefaultFlags($config);
            }

            $entityManager->flush();

            $this->addFlash(
                'success',
                $this->translator->trans('nowo_cookie_consent.admin.config.settings.updated', [], 'NowoCookieConsentBundle'),
            );

            return $this->redirectToRoute('nowo_cookie_consent_config_settings_section', [
                'configId' => $configId,
                'section'  => $sectionEnum->value,
            ]);
        }

        return $this->render('@NowoCookieConsentBundle/admin/config/settings.html.twig', [
            'config'   => $config,
            'form'     => $form,
            'section'  => $sectionEnum,
            'sections' => CookieConsentConfigSettingsSection::cases(),
        ]);
    }

    private function getConfig(int $configId): CookieConsentConfig
    {
        $config = $this->configRepository->find($configId);

        if (!$config instanceof CookieConsentConfig || !$config->isEnabled()) {
            throw $this->createNotFoundException();
        }

        return $config;
    }

    private function clearOtherDefaultFlags(CookieConsentConfig $current): void
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
}
