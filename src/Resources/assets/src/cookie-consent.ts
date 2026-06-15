/**
 * Cookie consent modal entrypoint.
 * Opens the Bootstrap modal (or a CSS fallback), submits consent via AJAX, and hides on success.
 */

import './cookie-consent.css';
import { applyRemoteConfig, fetchRemoteConfig } from './apply-config';
import { applyThemeOptionsFromElement } from './apply-theme';
import { applyVisualConfigFromElement, setPageInteractionBlocked } from './apply-visual-config';
import { collectClientDiagnostics, publishClientDiagnostics } from './diagnostics';
import { installCustomEventPolyfill } from './custom-event-polyfill';
import { serializeForm } from './form-serializer';
import { activateIframesForConsent, readAllowedCategoriesFromModal } from './iframe-manager';
import { createBundleLogger, setBundleLogger } from './logger';
import { bindCategoryToggles } from './category-toggles';
import { bindGranularCookieToggles } from './granular-cookie-toggles';
import { bindStepNavigation, openPreferencesStep, shouldBlockPageInteraction } from './step-manager';

declare const __COOKIE_CONSENT_BUILD_TIME__: string;

const log = createBundleLogger('cookie-consent', {
  buildTime:
    typeof __COOKIE_CONSENT_BUILD_TIME__ !== 'undefined' ? __COOKIE_CONSENT_BUILD_TIME__ : undefined,
  alwaysLog: true,
});
log.scriptLoaded();
setBundleLogger(log);

interface BootstrapModal {
  show(): void;
  hide(): void;
}

interface BootstrapNamespace {
  Modal: new (
    element: HTMLElement,
    options: {
      backdrop: boolean;
      keyboard: boolean;
      focus: boolean;
    },
  ) => BootstrapModal;
}

declare global {
  interface Window {
    bootstrap?: BootstrapNamespace;
  }
}

const SUCCESS_EVENT = 'nowo-cookie-consent-form-submit-successful';

/**
 * Initializes the cookie consent modal and AJAX form handlers.
 */
export function initCookieConsent(): void {
  installCustomEventPolyfill();

  const modalElement = document.getElementById('cookieconsent');

  if (!modalElement) {
    log.debug('Modal element not found, skipping Cookie Consent init', collectClientDiagnostics(null));
    publishClientDiagnostics(null);
    return;
  }

  const cookieConsentForm = modalElement.querySelector<HTMLFormElement>('.nowo-cookie-consent__form');
  const cookieConsentButtons = modalElement.querySelectorAll<HTMLButtonElement>(
    '.nowo-cookie-consent__btn[name]',
  );
  let bootstrapModal: BootstrapModal | null = null;

  applyVisualConfigFromElement(modalElement);
  applyThemeOptionsFromElement(modalElement);
  bindStepNavigation(modalElement);
  bindCategoryToggles(modalElement);
  bindGranularCookieToggles(modalElement);

  if (modalElement.dataset.nowoManageIframePlaceholders === 'true') {
    activateIframesForConsent(readAllowedCategoriesFromModal(modalElement));
  }

  const hasBootstrap = typeof window.bootstrap !== 'undefined' && window.bootstrap.Modal !== undefined;

  log.info('Cookie Consent init', {
    open_by_default: modalElement.dataset.nowoOpen === 'true',
    bootstrap: hasBootstrap,
    button_count: cookieConsentButtons.length,
    config_api: Boolean(modalElement.dataset.nowoConfigUrl),
  });

  if (hasBootstrap) {
    bootstrapModal = new window.bootstrap!.Modal(modalElement, {
      backdrop: false,
      keyboard: false,
      focus: true,
    });
  } else {
    log.debug('Bootstrap Modal unavailable, using CSS fallback');
  }

  const showModal = (): void => {
    const blockInteraction = shouldBlockPageInteraction(modalElement);

    if (bootstrapModal) {
      bootstrapModal.show();
      setPageInteractionBlocked(modalElement, blockInteraction);
      return;
    }

    modalElement.classList.add('show');
    modalElement.classList.remove('hidden');
    modalElement.style.display = 'block';
    modalElement.removeAttribute('aria-hidden');
    setPageInteractionBlocked(modalElement, blockInteraction);
  };

  const hideModal = (): void => {
    if (bootstrapModal) {
      bootstrapModal.hide();
      setPageInteractionBlocked(modalElement, false);
      return;
    }

    modalElement.classList.remove('show');
    modalElement.classList.add('hidden');
    modalElement.style.display = 'none';
    modalElement.setAttribute('aria-hidden', 'true');
    setPageInteractionBlocked(modalElement, false);
  };

  modalElement.addEventListener('hidden.bs.modal', () => {
    setPageInteractionBlocked(modalElement, false);
  });

  if (modalElement.dataset.nowoOpen === 'true') {
    showModal();
  }

  document.addEventListener('click', (event) => {
    const target = (event.target as Element | null)?.closest('[data-nowo-open-consent]');

    if (!target) {
      return;
    }

    event.preventDefault();
    openPreferencesStep(modalElement);
    showModal();
  });

  if (!cookieConsentForm) {
    log.warn('Cookie consent form not found');
    return;
  }

  cookieConsentButtons.forEach((button) => {
    button.addEventListener('click', (event) => {
      event.preventDefault();

      const formAction = cookieConsentForm.action || location.href;
      const xhr = new XMLHttpRequest();

      xhr.onload = () => {
        if (xhr.status >= 200 && xhr.status < 300) {
          log.debug('Consent saved successfully');
          if (modalElement.dataset.nowoManageIframePlaceholders === 'true') {
            activateIframesForConsent(readAllowedCategoriesFromModal(modalElement));
          }
          document.dispatchEvent(
            new CustomEvent(SUCCESS_EVENT, {
              detail: event.target,
            }),
          );
          hideModal();
        }
      };

      xhr.open('POST', formAction);
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
      xhr.send(serializeForm(cookieConsentForm, button));

      document.body.style.marginBottom = '';
      document.body.style.marginTop = '';
    });
  });

  log.info('Cookie Consent ready');
  publishClientDiagnostics(modalElement);
}

async function loadRemoteConfig(modalElement: HTMLElement): Promise<void> {
  const configUrl = modalElement.dataset.nowoConfigUrl;

  if (!configUrl) {
    return;
  }

  const locale = document.documentElement.lang || 'en';

  try {
    log.info('Fetching cookie consent config', { url: configUrl, locale });
    const data = await fetchRemoteConfig(configUrl);

    if (data) {
      applyRemoteConfig(modalElement, data, locale);
      log.debug('Cookie consent config applied');
    }
  } catch (error) {
    log.warn('Failed to fetch cookie consent config', error);
  }
}

function bootstrapCookieConsent(): void {
  const modalElement = document.getElementById('cookieconsent');

  if (modalElement?.dataset.nowoConfigUrl) {
    void loadRemoteConfig(modalElement).finally(() => initCookieConsent());
    return;
  }

  initCookieConsent();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', bootstrapCookieConsent);
} else {
  bootstrapCookieConsent();
}
