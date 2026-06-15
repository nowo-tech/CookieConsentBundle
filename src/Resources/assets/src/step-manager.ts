import { applyVisualConfigForStep, type ModalVisualStep } from './apply-visual-config';

function getStepElements(modalElement: HTMLElement): {
  bannerStep: HTMLElement | null;
  preferencesStep: HTMLElement | null;
} {
  return {
    bannerStep: modalElement.querySelector<HTMLElement>('[data-nowo-step="banner"]'),
    preferencesStep: modalElement.querySelector<HTMLElement>('[data-nowo-step="preferences"]'),
  };
}

export function isTwoStepModalElement(modalElement: HTMLElement): boolean {
  const { bannerStep, preferencesStep } = getStepElements(modalElement);

  if (bannerStep !== null && preferencesStep !== null) {
    return true;
  }

  return modalElement.dataset.nowoTwoStep === 'true';
}

export function syncTwoStepModalState(modalElement: HTMLElement): void {
  const isTwoStep = isTwoStepModalElement(modalElement);

  modalElement.dataset.nowoTwoStep = isTwoStep ? 'true' : 'false';
  modalElement.classList.toggle('nowo-cookie-consent--two-step', isTwoStep);
}

export function activateCookieConsentStep(modalElement: HTMLElement, step: ModalVisualStep): boolean {
  const { bannerStep, preferencesStep } = getStepElements(modalElement);

  if (!bannerStep || !preferencesStep) {
    return false;
  }

  const isBanner = step === 'banner';

  bannerStep.classList.toggle('nowo-cookie-consent__step--active', isBanner);
  preferencesStep.classList.toggle('nowo-cookie-consent__step--active', !isBanner);
  bannerStep.removeAttribute('hidden');
  preferencesStep.removeAttribute('hidden');

  modalElement.classList.toggle('nowo-cookie-consent--preferences-view', !isBanner);
  applyVisualConfigForStep(modalElement, step);

  return true;
}

export function openPreferencesStep(modalElement: HTMLElement): boolean {
  if (!isTwoStepModalElement(modalElement)) {
    return false;
  }

  syncTwoStepModalState(modalElement);

  return activateCookieConsentStep(modalElement, 'preferences');
}

export function openBannerStep(modalElement: HTMLElement): boolean {
  if (!isTwoStepModalElement(modalElement)) {
    return false;
  }

  syncTwoStepModalState(modalElement);

  return activateCookieConsentStep(modalElement, 'banner');
}

/**
 * Two-step modal navigation: compact banner → full preferences.
 */
export function bindStepNavigation(modalElement: HTMLElement): void {
  syncTwoStepModalState(modalElement);

  if (!isTwoStepModalElement(modalElement)) {
    return;
  }

  const showPreferencesButtons = modalElement.querySelectorAll<HTMLElement>('[data-nowo-show-preferences]');

  showPreferencesButtons.forEach((button) => {
    button.addEventListener('click', (event) => {
      event.preventDefault();
      openPreferencesStep(modalElement);
    });
  });

  modalElement.querySelectorAll<HTMLElement>('[data-nowo-hide-preferences]').forEach((button) => {
    button.addEventListener('click', (event) => {
      event.preventDefault();
      openBannerStep(modalElement);
    });
  });

  if (modalElement.dataset.nowoOpenPreferences === 'true') {
    openPreferencesStep(modalElement);
  }
}

export function shouldBlockPageInteraction(modalElement: HTMLElement): boolean {
  return modalElement.dataset.nowoDisablePageInteraction === 'true';
}
