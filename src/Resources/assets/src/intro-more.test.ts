import { describe, expect, it } from 'vitest';
import { bindIntroToggle, CLAMP_CLASS, EXPANDED_CLASS, refreshIntroToggle } from './intro-more';
import { bindStepNavigation, openBannerStep, openPreferencesStep } from './step-manager';

function setup(scrollHeight: number, clientHeight: number): { modal: HTMLElement; intro: HTMLElement; toggle: HTMLButtonElement } {
  document.body.innerHTML = `
    <div id="cookieconsent">
      <p id="nowoCookieConsentIntro" class="nowo-cookie-consent__intro" data-nowo-intro>Long text</p>
      <button type="button" data-nowo-intro-more data-nowo-label-more="Show more" data-nowo-label-less="Show less"
              aria-controls="nowoCookieConsentIntro" aria-expanded="false" hidden>Show more</button>
      <button type="submit" name="use_all_cookies" class="nowo-cookie-consent__btn">Accept</button>
      <button type="submit" name="use_only_functional_cookies" class="nowo-cookie-consent__btn nowo-cookie-consent__btn--secondary">Reject</button>
    </div>
  `;
  const modal = document.getElementById('cookieconsent')!;
  const intro = modal.querySelector<HTMLElement>('[data-nowo-intro]')!;
  Object.defineProperty(intro, 'scrollHeight', { configurable: true, get: () => scrollHeight });
  Object.defineProperty(intro, 'clientHeight', { configurable: true, get: () => clientHeight });

  return { modal, intro, toggle: modal.querySelector<HTMLButtonElement>('[data-nowo-intro-more]')! };
}

describe('intro "Show more" toggle (MUX-151)', () => {
  it('marks the modal for the clamp and shows the toggle only when the intro is cut', () => {
    const { modal, toggle } = setup(120, 60);
    bindIntroToggle(modal);
    expect(modal.classList.contains(CLAMP_CLASS)).toBe(true);
    expect(toggle.hidden).toBe(true);

    refreshIntroToggle(modal);
    expect(toggle.hidden).toBe(false);
  });

  it('keeps the toggle hidden for a short intro (or a wide screen without clamp)', () => {
    const { modal, toggle } = setup(40, 40);
    bindIntroToggle(modal);
    refreshIntroToggle(modal);
    expect(toggle.hidden).toBe(true);
  });

  it('expands and collapses with aria-expanded and the labels', () => {
    const { modal, intro, toggle } = setup(120, 60);
    bindIntroToggle(modal);
    refreshIntroToggle(modal);

    toggle.click();
    expect(intro.classList.contains(EXPANDED_CLASS)).toBe(true);
    expect(toggle.getAttribute('aria-expanded')).toBe('true');
    expect(toggle.textContent).toBe('Show less');
    expect(toggle.hidden).toBe(false);

    toggle.click();
    expect(intro.classList.contains(EXPANDED_CLASS)).toBe(false);
    expect(toggle.getAttribute('aria-expanded')).toBe('false');
    expect(toggle.textContent).toBe('Show more');
  });

  it('keeps the current label when the template has none', () => {
    const { modal, toggle } = setup(120, 60);
    delete toggle.dataset.nowoLabelLess;
    bindIntroToggle(modal);
    toggle.click();
    expect(toggle.textContent).toBe('Show more');
  });

  it('re-measures on resize', () => {
    const { modal, intro, toggle } = setup(40, 40);
    bindIntroToggle(modal, window);
    Object.defineProperty(intro, 'scrollHeight', { configurable: true, get: () => 200 });
    window.dispatchEvent(new Event('resize'));
    expect(toggle.hidden).toBe(false);
  });

  it('never touches the consent buttons', () => {
    const { modal, toggle } = setup(120, 60);
    const before = [...modal.querySelectorAll('.nowo-cookie-consent__btn')].map((button) => button.outerHTML);
    bindIntroToggle(modal);
    toggle.click();
    expect([...modal.querySelectorAll('.nowo-cookie-consent__btn')].map((button) => button.outerHTML)).toEqual(before);
  });

  it('is a no-op without the intro or the toggle (host template)', () => {
    document.body.innerHTML = '<div id="cookieconsent"><p class="nowo-cookie-consent__intro">Text</p></div>';
    const modal = document.getElementById('cookieconsent')!;
    bindIntroToggle(modal);
    refreshIntroToggle(modal);
    expect(modal.classList.contains(CLAMP_CLASS)).toBe(false);
  });

  it('shows the toggle after the modal opened on the preferences step and the user goes back (JD-L1)', () => {
    document.body.innerHTML = `
      <div id="cookieconsent" class="nowo-cookie-consent--two-step" data-nowo-two-step="true">
        <div data-nowo-step="banner" class="nowo-cookie-consent__step nowo-cookie-consent__step--active">
          <p id="nowoCookieConsentIntro" data-nowo-intro>Long text</p>
          <button type="button" data-nowo-intro-more data-nowo-label-more="Show more" aria-expanded="false" hidden>Show more</button>
        </div>
        <div data-nowo-step="preferences" class="nowo-cookie-consent__step">
          <button type="button" data-nowo-hide-preferences>x</button>
        </div>
      </div>
    `;
    const modal = document.getElementById('cookieconsent')!;
    const intro = modal.querySelector<HTMLElement>('[data-nowo-intro]')!;
    const toggle = modal.querySelector<HTMLButtonElement>('[data-nowo-intro-more]')!;
    // Hidden banner step: nothing to measure.
    let heights = { scroll: 0, client: 0 };
    Object.defineProperty(intro, 'scrollHeight', { configurable: true, get: () => heights.scroll });
    Object.defineProperty(intro, 'clientHeight', { configurable: true, get: () => heights.client });

    bindStepNavigation(modal);
    bindIntroToggle(modal);
    openPreferencesStep(modal);
    refreshIntroToggle(modal);
    expect(toggle.hidden).toBe(true);

    // Back to the banner (×): the intro is laid out and cut.
    heights = { scroll: 130, client: 65 };
    openBannerStep(modal);
    expect(toggle.hidden).toBe(false);
  });

  it('re-measures when the intro box changes size and when the web fonts are ready (JD-L1)', async () => {
    const { modal, intro, toggle } = setup(40, 40);
    let observed: Element | null = null;
    let notify: () => void = () => undefined;
    let fontsReady: () => void = () => undefined;
    const win = {
      addEventListener: () => undefined,
      ResizeObserver: class {
        constructor(callback: () => void) {
          notify = callback;
        }
        observe(target: Element): void {
          observed = target;
        }
      },
      document: { fonts: { ready: new Promise<void>((resolve) => { fontsReady = resolve; }) } },
    } as unknown as Window & typeof globalThis;

    bindIntroToggle(modal, win);
    expect(observed).toBe(intro);

    Object.defineProperty(intro, 'scrollHeight', { configurable: true, get: () => 200 });
    notify();
    expect(toggle.hidden).toBe(false);

    Object.defineProperty(intro, 'scrollHeight', { configurable: true, get: () => 40 });
    expect(toggle.hidden).toBe(false);
    fontsReady();
    await Promise.resolve();
    await Promise.resolve();
    expect(toggle.hidden).toBe(true);
  });

  it('works without ResizeObserver or document.fonts', () => {
    const { modal, toggle } = setup(120, 60);
    const win = { addEventListener: () => undefined, document: {} } as unknown as Window & typeof globalThis;
    bindIntroToggle(modal, win);
    refreshIntroToggle(modal);
    expect(toggle.hidden).toBe(false);
  });
});
