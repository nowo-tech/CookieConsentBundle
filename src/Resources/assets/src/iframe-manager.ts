const ACTIVATED_ATTRIBUTE = 'data-nowo-activated';

function parseCategoryFromInputName(name: string): string | null {
  const match = name.match(/\[([^\]]+)\]$/);

  return match?.[1] ?? null;
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
