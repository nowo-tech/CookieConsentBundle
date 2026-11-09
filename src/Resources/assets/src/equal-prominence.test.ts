import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { afterEach, describe, expect, it } from 'vitest';

/**
 * Owner decision JD-L3 (1.23, "Both filled the same"): with equal-weight buttons, reject and accept are drawn
 * identically. Computed styles of both buttons, in the markup of the Bootstrap and the Tailwind template, for every
 * colour theme, with and without the UiKit / brand primary.
 */
const css = readFileSync(resolve(__dirname, 'cookie-consent.css'), 'utf8');
const views = resolve(__dirname, '../../views');

const PROPERTIES = [
  'background-color',
  'color',
  'border-top-color',
  'border-top-width',
  'border-top-style',
  'border-radius',
  'font-weight',
  'font-size',
  'min-height',
  'padding-top',
  'padding-left',
  'box-shadow',
  'width',
] as const;

/** The footer markup of each template (classes as the form type and form themes print them). */
const TEMPLATES: Record<string, string> = {
  bootstrap: `
    <div class="modal-footer nowo-cookie-consent__btn-group">
      <div><button type="submit" name="save" class="btn nowo-cookie-consent__btn nowo-cookie-consent__btn--secondary">Save</button></div>
      <div><button type="submit" name="use_only_functional_cookies" class="btn nowo-cookie-consent__btn nowo-cookie-consent__btn--secondary">Reject</button></div>
      <div><button type="submit" name="use_all_cookies" class="btn nowo-cookie-consent__btn">Accept</button></div>
    </div>`,
  tailwind: `
    <div class="nowo-cookie-consent__btn-group nowo-cookie-consent__section-footer flex flex-wrap justify-end gap-2 border-t px-6 py-4">
      <button type="submit" name="save" class="btn nowo-cookie-consent__btn nowo-cookie-consent__btn--secondary">Save</button>
      <button type="submit" name="use_only_functional_cookies" class="btn nowo-cookie-consent__btn nowo-cookie-consent__btn--secondary">Reject</button>
      <button type="submit" name="use_all_cookies" class="btn nowo-cookie-consent__btn">Accept</button>
    </div>`,
};

const THEMES = ['', 'nowo-cookie-consent--theme-light', 'nowo-cookie-consent--theme-light nowo-cookie-consent--dark-mode',
  'nowo-cookie-consent--theme-dark-turquoise', 'nowo-cookie-consent--theme-light-funky', 'nowo-cookie-consent--theme-elegant-black'];

function render(template: string, theme: string, equalWeight: boolean, brand: boolean): { reject: HTMLElement; accept: HTMLElement } {
  document.head.innerHTML = `<style>${brand ? ':root { --nowo-ui-primary: #1f6f54; --nowo-ui-primary-contrast: #ffffff; }' : ''}${css}</style>`;
  document.body.innerHTML = `
    <div id="cookieconsent" class="modal nowo-cookie-consent show ${theme} ${equalWeight ? 'nowo-cookie-consent--equal-weight-buttons' : ''}"
         ${template === 'tailwind' ? 'data-nowo-ui-theme="tailwind"' : ''}>
      <div class="modal-dialog"><div class="modal-content"><form class="nowo-cookie-consent__form">${TEMPLATES[template]}</form></div></div>
    </div>`;

  return {
    reject: document.querySelector<HTMLElement>('[name="use_only_functional_cookies"]')!,
    accept: document.querySelector<HTMLElement>('[name="use_all_cookies"]')!,
  };
}

function styles(element: HTMLElement): Record<string, string> {
  const computed = window.getComputedStyle(element);
  return Object.fromEntries(PROPERTIES.map((property) => [property, computed.getPropertyValue(property)]));
}

afterEach(() => {
  document.head.innerHTML = '';
  document.body.innerHTML = '';
});

describe.each(Object.keys(TEMPLATES))('equal-weight reject and accept (%s template)', (template) => {
  it.each(THEMES.flatMap((theme) => [[theme, false], [theme, true]] as const))(
    'theme "%s" (brand primary: %s): both buttons are filled the same',
    (theme, brand) => {
      const { reject, accept } = render(template, theme, true, brand);
      const rejectStyles = styles(reject);
      expect(rejectStyles['background-color']).not.toBe('');
      expect(rejectStyles.color).not.toBe('');
      expect(rejectStyles['font-weight']).toBe('600');
      expect(rejectStyles).toEqual(styles(accept));
    },
  );

  it('uses the brand primary for both in the default theme', () => {
    const { reject, accept } = render(template, 'nowo-cookie-consent--theme-light', true, true);
    expect(window.getComputedStyle(accept).getPropertyValue('background-color')).toMatch(/31,\s*111,\s*84|#1f6f54/i);
    expect(window.getComputedStyle(reject).getPropertyValue('background-color')).toMatch(/31,\s*111,\s*84|#1f6f54/i);
  });

  it('keeps the 1.22 secondary reject without equal-weight buttons', () => {
    const { reject, accept } = render(template, 'nowo-cookie-consent--theme-light', false, true);
    const rejectStyles = styles(reject);
    const acceptStyles = styles(accept);
    expect(rejectStyles).not.toEqual(acceptStyles);
    expect(rejectStyles.color).not.toBe(acceptStyles.color);
    expect(rejectStyles['font-weight']).toBe('500');
    expect(acceptStyles['font-weight']).toBe('600');
  });
});

describe('templates print the reject and accept buttons by name', () => {
  it('both modal templates place use_only_functional_cookies and use_all_cookies through the form', () => {
    for (const template of ['cookie_consent.html.twig', 'cookie_consent.tailwind.html.twig']) {
      const twig = readFileSync(resolve(views, template), 'utf8');
      expect(twig).toContain("child.vars.name == 'use_only_functional_cookies'");
      expect(twig).toContain("child.vars.name == 'use_all_cookies'");
    }
  });
});
