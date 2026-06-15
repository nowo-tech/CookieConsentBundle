import { applyVisualConfig } from './apply-visual-config';

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
    savePreferencesBtn?: string | null;
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

  let positionY: string | undefined;
  let positionX: string | undefined;

  if (consentGui?.position) {
    [positionY, positionX] = consentGui.position.split(/\s+/, 2);
  }

  applyVisualConfig(modalElement, {
    layout: consentGui?.layout,
    variant: consentGui?.variant,
    positionY,
    positionX,
    equalWeightButtons: consentGui?.equalWeightButtons,
    flipButtons: consentGui?.flipButtons,
    disablePageInteraction: data.disablePageInteraction,
  });

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
