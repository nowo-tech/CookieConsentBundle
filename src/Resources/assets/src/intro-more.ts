/**
 * MUX-151 (1.23): compact first layer on phones. While the modal carries `nowo-cookie-consent--intro-clamp` (added
 * here, so a host template without the toggle is never clamped), the CSS cuts the intro to
 * `--nowo-cc-intro-lines-phone` lines below 640px. The "Show more" button is shown only when the text is really cut,
 * and toggles `nowo-cookie-consent__intro--expanded` plus `aria-expanded`. The consent buttons are never touched.
 *
 * Measured again when the modal opens, when the banner step becomes active (step-manager), when the remote config
 * changes the text, on resize, whenever the intro box changes size (ResizeObserver: a hidden intro shown again, a
 * container change) and once the web fonts are ready (the modal inherits the host font, which may swap in later).
 */
export const INTRO_SELECTOR = '[data-nowo-intro]';
export const TOGGLE_SELECTOR = '[data-nowo-intro-more]';
export const CLAMP_CLASS = 'nowo-cookie-consent--intro-clamp';
export const EXPANDED_CLASS = 'nowo-cookie-consent__intro--expanded';

function parts(modal: HTMLElement): { intro: HTMLElement; toggle: HTMLButtonElement } | null {
  const intro = modal.querySelector<HTMLElement>(INTRO_SELECTOR);
  const toggle = modal.querySelector<HTMLButtonElement>(TOGGLE_SELECTOR);

  return intro && toggle ? { intro, toggle } : null;
}

/**
 * Shows the toggle when the intro is expanded or overflows its clamp; hides it otherwise (short text, wide screen).
 * Call it after the modal is visible and after the intro text changes (remote config).
 */
export function refreshIntroToggle(modal: HTMLElement): void {
  const found = parts(modal);
  if (!found) {
    return;
  }

  const { intro, toggle } = found;
  toggle.hidden = !intro.classList.contains(EXPANDED_CLASS) && intro.scrollHeight <= intro.clientHeight + 1;
}

/**
 * Wires the toggle once and re-measures on resize. No-op without the intro or the toggle.
 */
export function bindIntroToggle(modal: HTMLElement, win: Window & typeof globalThis = window): void {
  const found = parts(modal);
  if (!found) {
    return;
  }

  const { intro, toggle } = found;
  modal.classList.add(CLAMP_CLASS);

  toggle.addEventListener('click', () => {
    const expanded = !intro.classList.contains(EXPANDED_CLASS);
    intro.classList.toggle(EXPANDED_CLASS, expanded);
    toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    const label = expanded ? toggle.dataset.nowoLabelLess : toggle.dataset.nowoLabelMore;
    if (label) {
      toggle.textContent = label;
    }
    refreshIntroToggle(modal);
  });

  const refresh = (): void => refreshIntroToggle(modal);
  win.addEventListener('resize', refresh);

  if (typeof win.ResizeObserver === 'function') {
    new win.ResizeObserver(refresh).observe(intro);
  }

  const fonts = (win.document as Document & { fonts?: { ready?: Promise<unknown> } }).fonts;
  fonts?.ready?.then(refresh).catch(() => undefined);
}
