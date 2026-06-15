/**
 * Prevents category toggle clicks from collapsing the preference details panel.
 */
export function bindCategoryToggles(modalElement: HTMLElement): void {
  modalElement.querySelectorAll<HTMLElement>('[data-nowo-toggle]').forEach((wrapper) => {
    wrapper.addEventListener('click', (event) => {
      event.stopPropagation();
    });
  });
}
