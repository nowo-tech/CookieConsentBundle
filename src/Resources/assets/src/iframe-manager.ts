/**
 * Activates iframe placeholders after the user allows matching cookie categories.
 */
const ACTIVATED_ATTRIBUTE = 'data-nowo-activated';

function parseCategoryFromInputName(name: string): string | null {
  const match = name.match(/\[([^\]]+)\]$/);

  return match?.[1] ?? null;
}

/**
 * Categories the visitor's answer allows (1.25, audit JR-M1): "accept all" allows every category of the form,
 * "reject" (`use_only_functional_cookies`) only `required`, and Save what is ticked. The server applies the same rule
 * (CookieConsentType PRE_SUBMIT); pre-ticked boxes must never count for a reject.
 */
export function readAnsweredCategoriesFromModal(modalElement: HTMLElement, buttonName: string): string[] {
  if (/(^|\[)use_only_functional_cookies\]?$/.test(buttonName)) {
    return ['required'];
  }

  if (/(^|\[)use_all_cookies\]?$/.test(buttonName)) {
    const all = new Set<string>(['required']);
    modalElement
      .querySelectorAll<HTMLInputElement>('.nowo-cookie-consent__form input[type="checkbox"]')
      .forEach((input) => {
        const category = parseCategoryFromInputName(input.name);
        if (category) {
          all.add(category);
        }
      });

    return [...all];
  }

  return readAllowedCategoriesFromModal(modalElement);
}

export function readAllowedCategoriesFromModal(modalElement: HTMLElement): string[] {
  const form = modalElement.querySelector<HTMLFormElement>('.nowo-cookie-consent__form');

  if (!form) {
    return [];
  }

  const allowed = new Set<string>(['required']);

  form.querySelectorAll<HTMLInputElement>('input[type="checkbox"]').forEach((input) => {
    const category = parseCategoryFromInputName(input.name);

    if (!category) {
      return;
    }

    if (input.checked) {
      allowed.add(category);
    } else {
      allowed.delete(category);
    }
  });

  return [...allowed];
}

function activateBlockedScript(script: HTMLScriptElement): void {
  if (script.getAttribute(ACTIVATED_ATTRIBUTE) === 'true') {
    return;
  }

  const executable = document.createElement('script');

  Array.from(script.attributes).forEach((attribute) => {
    if (attribute.name === 'type' || attribute.name === ACTIVATED_ATTRIBUTE) {
      return;
    }

    executable.setAttribute(attribute.name, attribute.value);
  });

  executable.text = script.text;
  script.setAttribute(ACTIVATED_ATTRIBUTE, 'true');
  script.parentNode?.replaceChild(executable, script);
}

function activateIframePlaceholder(placeholder: HTMLElement): void {
  if (placeholder.getAttribute(ACTIVATED_ATTRIBUTE) === 'true') {
    return;
  }

  const src = placeholder.dataset.nowoIframeSrc ?? placeholder.dataset.src;

  if (!src) {
    return;
  }

  const iframe = document.createElement('iframe');
  iframe.src = src;
  iframe.title = placeholder.dataset.nowoIframeTitle ?? placeholder.dataset.title ?? '';
  iframe.setAttribute('loading', placeholder.dataset.nowoIframeLoading ?? 'lazy');

  if (placeholder.dataset.nowoIframeAllow) {
    iframe.setAttribute('allow', placeholder.dataset.nowoIframeAllow);
  }

  placeholder.replaceWith(iframe);
}

export function activateIframesForConsent(allowedCategories: string[]): void {
  const allowed = new Set(allowedCategories);

  document.querySelectorAll<HTMLScriptElement>('script[type="text/plain"][data-cookie-category]').forEach((script) => {
    const category = script.dataset.cookieCategory;

    if (category && allowed.has(category)) {
      activateBlockedScript(script);
    }
  });

  document.querySelectorAll<HTMLElement>('[data-nowo-iframe-category]').forEach((placeholder) => {
    const category = placeholder.dataset.nowoIframeCategory;

    if (category && allowed.has(category)) {
      activateIframePlaceholder(placeholder);
    }
  });
}
