import {
  applyVisualConfigForStep,
  parseModalPosition,
  storeVisualConfigOnElement,
} from './apply-visual-config';
import { openPreferencesStep, syncTwoStepModalState } from './step-manager';
import { applyThemeOptions } from './apply-theme';

export interface CookieConsentModalTranslation {
  label?: string | null;
  title?: string;
  description?: string;
  acceptAllBtn?: string;
  acceptNecessaryBtn?: string;
  showPreferencesBtn?: string | null;
  footer?: string | null;
  privacyRoute?: string | null;
}

export interface CookieConsentCategoryTranslation {
  title?: string;
  description?: string;
}

export interface CookieConsentLocaleTranslation {
  consentModal?: CookieConsentModalTranslation;
  preferencesModal?: {
    title?: string | null;
    savePreferencesBtn?: string | null;
    usageTitle?: string | null;
    usageDescription?: string | null;
  };
  categories?: Record<string, CookieConsentCategoryTranslation>;
}

export interface CookieConsentGuiModalOptions {
  layout?: string;
  variant?: string;
  position?: string;
  equalWeightButtons?: boolean;
  flipButtons?: boolean;
}

export interface CookieConsentConfigData {
  autoShow?: boolean;
  revision?: number;
  disablePageInteraction?: boolean;
  colorTheme?: string;
  darkModeEnabled?: boolean;
  disableTransitions?: boolean;
  twoStepModal?: boolean;
  openPreferencesModal?: boolean;
  manageIframePlaceholders?: boolean;
  preferenceSections?: Array<{ title: string; description: string; categories: string[] }>;
  guiOptions?: {
    consentModal?: CookieConsentGuiModalOptions;
    preferencesModal?: CookieConsentGuiModalOptions;
  };
  language?: {
    default?: string;
    translations?: Record<string, CookieConsentLocaleTranslation>;
  };
}

export interface CookieConsentApiResponse {
  code: number;
  data: CookieConsentConfigData;
}

export function applyRemoteConfig(
  modalElement: HTMLElement,
  data: CookieConsentConfigData,
  locale: string,
): void {
  const localeData = data.language?.translations?.[locale] ?? data.language?.translations?.[data.language?.default ?? ''];
  const consentModal = localeData?.consentModal;
  const consentGui = data.guiOptions?.consentModal;
  const preferencesGui = data.guiOptions?.preferencesModal;

  const titleElement = modalElement.querySelector<HTMLElement>('.nowo-cookie-consent__title');
  if (titleElement && consentModal?.title) {
    titleElement.textContent = consentModal.title;
  }

  const introElement = modalElement.querySelector<HTMLElement>('.nowo-cookie-consent__intro');
  if (introElement && consentModal?.description) {
    introElement.textContent = consentModal.description;
  }

  const readMore = modalElement.querySelector<HTMLElement>('.nowo-cookie-consent__read-more');
  if (readMore && consentModal?.footer) {
    readMore.textContent = consentModal.footer;
  }

  if (consentModal?.acceptAllBtn) {
    modalElement
      .querySelector<HTMLButtonElement>('.nowo-cookie-consent__btn[name="use_all_cookies"]')
      ?.replaceChildren(document.createTextNode(consentModal.acceptAllBtn));
  }

  if (consentModal?.acceptNecessaryBtn) {
    modalElement
      .querySelector<HTMLButtonElement>('.nowo-cookie-consent__btn[name="use_only_functional_cookies"]')
      ?.replaceChildren(document.createTextNode(consentModal.acceptNecessaryBtn));
  }

  const saveButton = modalElement.querySelector<HTMLButtonElement>('.nowo-cookie-consent__btn[name="save"]');
  const saveLabel = localeData?.preferencesModal?.savePreferencesBtn;

  if (saveButton && saveLabel) {
    saveButton.replaceChildren(document.createTextNode(saveLabel));
  }

  const preferencesUsageTitle = modalElement.querySelector<HTMLElement>('.nowo-cookie-consent__preferences-intro-title');
  if (preferencesUsageTitle && localeData?.preferencesModal?.usageTitle) {
    preferencesUsageTitle.textContent = localeData.preferencesModal.usageTitle;
  }

  const preferencesUsageDescription = modalElement.querySelector<HTMLElement>(
    '.nowo-cookie-consent__preferences-intro-description',
  );
  if (preferencesUsageDescription && localeData?.preferencesModal?.usageDescription) {
    preferencesUsageDescription.textContent = localeData.preferencesModal.usageDescription;
  }

  if (typeof data.twoStepModal === 'boolean') {
    modalElement.dataset.nowoTwoStep = data.twoStepModal ? 'true' : 'false';
  }

  if (typeof data.openPreferencesModal === 'boolean') {
    modalElement.dataset.nowoOpenPreferences = data.openPreferencesModal ? 'true' : 'false';
  }

  syncTwoStepModalState(modalElement);

  const consentPosition = parseModalPosition(consentGui?.position);
  const preferencesPosition = parseModalPosition(preferencesGui?.position);

  storeVisualConfigOnElement(modalElement, 'banner', {
    layout: consentGui?.layout,
    variant: consentGui?.variant,
    positionY: consentPosition.positionY,
    positionX: consentPosition.positionX,
    equalWeightButtons: consentGui?.equalWeightButtons,
    flipButtons: consentGui?.flipButtons,
    disablePageInteraction: data.disablePageInteraction,
  });

  storeVisualConfigOnElement(modalElement, 'preferences', {
    layout: preferencesGui?.layout,
    variant: preferencesGui?.variant,
    positionY: preferencesPosition.positionY,
    positionX: preferencesPosition.positionX,
    equalWeightButtons: preferencesGui?.equalWeightButtons,
    flipButtons: preferencesGui?.flipButtons,
  });

  const preferencesTitleElement = modalElement.querySelector<HTMLElement>(
    '[data-nowo-step="preferences"] .nowo-cookie-consent__title',
  );
  if (preferencesTitleElement && localeData?.preferencesModal?.title) {
    preferencesTitleElement.textContent = localeData.preferencesModal.title;
  }

  applyVisualConfigForStep(
    modalElement,
    modalElement.dataset.nowoOpenPreferences === 'true' ? 'preferences' : 'banner',
  );

  applyThemeOptions(modalElement, {
    colorTheme: data.colorTheme,
    darkModeEnabled: data.darkModeEnabled,
    disableTransitions: data.disableTransitions,
  });

  if (modalElement.dataset.nowoOpenPreferences === 'true') {
    openPreferencesStep(modalElement);
  }

  if (typeof data.manageIframePlaceholders === 'boolean') {
    modalElement.dataset.nowoManageIframePlaceholders = data.manageIframePlaceholders ? 'true' : 'false';
  }

  if (typeof data.revision === 'number') {
    modalElement.dataset.nowoRevision = String(data.revision);
  }

  if (data.autoShow === false) {
    modalElement.dataset.nowoOpen = 'false';
  }

  const categories = localeData?.categories;
  if (!categories) {
    return;
  }

  Object.entries(categories).forEach(([category, copy]) => {
    const row = modalElement.querySelector<HTMLElement>(`[data-nowo-category="${category}"]`);
    if (!row || !copy) {
      return;
    }

    const title = row.querySelector<HTMLElement>('.nowo-cookie-consent__category-title');
    const description = row.querySelector<HTMLElement>('.nowo-cookie-consent__category-description');

    if (title && copy.title) {
      title.textContent = copy.title;
    }

    if (description && copy.description) {
      description.textContent = copy.description;
    }
  });
}

export async function fetchRemoteConfig(configUrl: string): Promise<CookieConsentConfigData | null> {
  const response = await fetch(configUrl, {
    method: 'GET',
    headers: {
      Accept: 'application/json',
    },
  });

  if (!response.ok) {
    throw new Error(`Cookie consent config request failed with status ${response.status}`);
  }

  const json = (await response.json()) as CookieConsentApiResponse;

  return json.data ?? null;
}
