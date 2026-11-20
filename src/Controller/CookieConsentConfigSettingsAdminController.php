<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\CookieConsentBundle\Admin\AdminPageFactory;
use Nowo\CookieConsentBundle\Admin\CookieConsentConfigSettingsSection;
use Nowo\CookieConsentBundle\Concurrency\RowVersionGuard;
use Nowo\CookieConsentBundle\Concurrency\StaleVersionException;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Form\RowVersionField;
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
     * @param AdminPageFactory $pages UiKit view models and templates of the admin pages (REQ-UI-003)
     */
    public function __construct(
        private readonly CookieConsentConfigRepository $configRepository,
        private readonly TranslatorInterface $translator,
        private readonly AdminPageFactory $pages,
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
        $form   = $this->createForm($sectionEnum->formType(), $config);
        $form->handleRequest($request);

        $stale = false;
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // 1.31 (REQ-RUNTIME-003 rules 1, 4): one transaction for the save and the "only one default" rule (ordered
                // row locks, then an atomic UPDATE of the other profiles). A lock conflict answers 409 + CONFLICT_001
                // (CookieConsentConflictListener).
                // 1.33 (rule 4): the page's row version is checked and advanced in the same transaction; a profile
                // saved by someone else since the page was opened is not overwritten.
                $this->configRepository->saveSettings($config, $entityManager, RowVersionField::read($form));
            } catch (StaleVersionException) {
                $stale = true;
                // Drop the form's unsaved changes from the managed entity (a refresh of this entity only: the manager is
                // never cleared or reset), so no later flush of the manager can write them over the other admin's save.
                RowVersionGuard::discard($entityManager, $config);
                // The UiKit layout prints the flashes of every type above the page.
                $this->addFlash('error', $this->translator->trans(StaleVersionException::MESSAGE_KEY, [], 'NowoCookieConsentBundle'));
            }

            if (!$stale) {
                $this->addFlash(
                    'success',
                    $this->translator->trans('nowo_cookie_consent.admin.config.settings.updated', [], 'NowoCookieConsentBundle'),
                );

                return $this->redirectToRoute('nowo_cookie_consent_config_settings_section', [
                    'configId' => $configId,
                    'section'  => $sectionEnum->value,
                ]);
            }
        }

        $view = $form->createView();

        return $this->render($this->pages->template('settings'), [
            'page' => $this->pages->settings($config, $sectionEnum, $view),
            // 1.x variables, for host templates (web_ui.templates.settings)
            'config'   => $config,
            'form'     => $view,
            'section'  => $sectionEnum,
            'sections' => CookieConsentConfigSettingsSection::cases(),
        ], $this->answer($form->isSubmitted(), $stale));
    }

    /**
     * The status of the re-rendered page: 409 + `X-Error-Code: COOKIE_CONSENT_STALE_VERSION` for a stale edit (a
     * domain answer, never CONFLICT_001 / CONFLICT_002: retrying the same page cannot succeed), 422 for a form error.
     */
    private function answer(bool $submitted, bool $stale): Response
    {
        if ($stale) {
            return new Response(null, Response::HTTP_CONFLICT, ['X-Error-Code' => StaleVersionException::ERROR_CODE]);
        }

        return new Response(null, $submitted ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK);
    }

    private function getConfig(int $configId): CookieConsentConfig
    {
        $config = $this->configRepository->find($configId);

        if (!$config instanceof CookieConsentConfig || !$config->isEnabled()) {
            throw $this->createNotFoundException();
        }

        return $config;
    }
}
