import { describe, expect, it } from 'vitest';
import { bindIntroToggle, CLAMP_CLASS, EXPANDED_CLASS, refreshIntroToggle } from './intro-more';

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
});
