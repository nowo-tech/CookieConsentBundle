<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Form\CookieConsentConfigSettingsType;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
class CookieConsentConfigSettingsAdminController extends AbstractController
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
     * Edits display and behavior settings for a consent profile.
     *
     * @param int $configId The consent profile identifier
     * @param Request $request The current HTTP request
     * @param EntityManagerInterface $entityManager Doctrine entity manager
     *
     * @return Response The rendered form or redirect after success
     */
    #[Route('', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(int $configId, Request $request, EntityManagerInterface $entityManager): Response
    {
        $config = $this->getConfig($configId);
        $form   = $this->createForm(CookieConsentConfigSettingsType::class, $config);
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

            return $this->redirectToRoute('nowo_cookie_consent_config_settings_edit', [
                'configId' => $configId,
            ]);
        }

        return $this->render('@NowoCookieConsentBundle/admin/config/settings.html.twig', [
            'config' => $config,
            'form'   => $form,
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
