function getCategoryToggleInput(categoryBlock: HTMLElement): HTMLInputElement | null {
  return categoryBlock.querySelector<HTMLInputElement>(
    '[data-nowo-toggle] .nowo-cookie-consent__toggle-input',
  );
}

function syncCategoryToggleFromCookies(categoryBlock: HTMLElement): void {
  const categoryInput = getCategoryToggleInput(categoryBlock);
  const cookieInputs = categoryBlock.querySelectorAll<HTMLInputElement>('.nowo-cookie-consent__cookie-toggle-input');

  if (!categoryInput || cookieInputs.length === 0) {
    return;
  }

  categoryInput.checked = Array.from(cookieInputs).some((input) => input.checked);
  categoryInput.indeterminate =
    categoryInput.checked && Array.from(cookieInputs).some((input) => !input.checked);
}

function syncCookiesFromCategoryToggle(categoryBlock: HTMLElement): void {
  const categoryInput = getCategoryToggleInput(categoryBlock);
  const cookieInputs = categoryBlock.querySelectorAll<HTMLInputElement>('.nowo-cookie-consent__cookie-toggle-input');

  if (!categoryInput) {
    return;
  }

  categoryInput.indeterminate = false;

  cookieInputs.forEach((input) => {
    input.checked = categoryInput.checked;
  });
}

/**
 * Keeps category master toggles and per-cookie toggles in sync inside each block.
 */
export function bindGranularCookieToggles(modalElement: HTMLElement): void {
  modalElement.querySelectorAll<HTMLElement>('.nowo-cookie-consent__category--granular').forEach((categoryBlock) => {
    const categoryInput = getCategoryToggleInput(categoryBlock);

    categoryInput?.addEventListener('change', () => {
      syncCookiesFromCategoryToggle(categoryBlock);
    });

    categoryBlock.querySelectorAll<HTMLInputElement>('.nowo-cookie-consent__cookie-toggle-input').forEach((cookieInput) => {
      cookieInput.addEventListener('change', () => {
        syncCategoryToggleFromCookies(categoryBlock);
      });
    });

    categoryBlock.querySelectorAll<HTMLElement>('[data-nowo-cookie-toggle]').forEach((wrapper) => {
      wrapper.addEventListener('click', (event) => {
        event.stopPropagation();
      });
    });

    syncCategoryToggleFromCookies(categoryBlock);
  });
}

export { getCategoryToggleInput, syncCategoryToggleFromCookies, syncCookiesFromCategoryToggle };
