import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import { BUBBLE_ACCENT_PROPERTY } from './preferences-bubble';

const css = readFileSync(resolve(__dirname, 'cookie-consent.css'), 'utf8');

/** Custom properties set at runtime (CSSOM) instead of in the stylesheet. */
const RUNTIME_PROPERTIES = new Set([BUBBLE_ACCENT_PROPERTY]);

/**
 * 1.22 host tokens: never declared by the kit (a declaration on the element would shadow a host value on :root, and the
 * bubble sits outside the modal), always read with today's value as the fallback.
 */
const HOST_PROPERTIES = new Set([
  '--nowo-cc-z-index',
  '--nowo-cc-viewport-padding-top',
  '--nowo-cc-viewport-padding-bottom',
  '--nowo-cc-panel-max-height',
  '--nowo-cc-box-max-width',
  '--nowo-cc-bubble-shadow',
  '--nowo-cc-bubble-z-index',
  '--nowo-cc-bubble-icon-filter',
  '--nowo-cc-bubble-offset-top',
  '--nowo-cc-bubble-offset-bottom',
  '--nowo-cc-bubble-offset-inline',
  '--nowo-cc-bubble-display-while-open',
  // 1.23 (MUX-151)
  '--nowo-cc-font-family',
  '--nowo-cc-intro-lines-phone',
]);

function declaredProperties(): Set<string> {
  return new Set([...css.matchAll(/(--nowo-cc-[a-z0-9-]+)\s*:/g)].map((match) => match[1]));
}

function ruleBody(selectorFragment: string): string {
  const start = css.indexOf(selectorFragment);
  expect(start).toBeGreaterThan(-1);
  const open = css.indexOf('{', start);
  return css.slice(open + 1, css.indexOf('}', open));
}

describe('cookie-consent.css without host utilities', () => {
  it('hides the closed modal and places the Tailwind overlay itself', () => {
    expect(ruleBody('.nowo-cookie-consent:not(.show) {')).toContain('display: none !important;');
    const overlay = ruleBody(".nowo-cookie-consent[data-nowo-ui-theme='tailwind'] {");
    expect(overlay).toContain('position: fixed;');
    expect(overlay).toContain('inset: 0;');
    expect(ruleBody(".nowo-cookie-consent[data-nowo-ui-theme='tailwind'] .nowo-cookie-consent__dialog {")).toContain('max-width: 56rem;');
    expect(ruleBody(".nowo-cookie-consent[data-nowo-ui-theme='tailwind'].show {")).toContain('padding-left: 1rem;');
  });
});

describe('cookie-consent.css custom properties', () => {
  it('declares every --nowo-cc-* custom property it reads', () => {
    const declared = declaredProperties();
    const used = new Set([...css.matchAll(/var\(\s*(--nowo-cc-[a-z0-9-]+)/g)].map((match) => match[1]));
    const undeclared = [...used].filter(
      (name) => !declared.has(name) && !RUNTIME_PROPERTIES.has(name) && !HOST_PROPERTIES.has(name),
    );

    expect(undeclared).toEqual([]);
  });

  it('reads every host token with a fallback and never declares it', () => {
    const declared = declaredProperties();
    for (const name of HOST_PROPERTIES) {
      expect(declared.has(name)).toBe(false);
      const reads = [...css.matchAll(new RegExp(`var\\(\\s*${name}\\s*([,)])`, 'g'))];
      expect(reads.length).toBeGreaterThan(0);
      for (const read of reads) {
        expect(read[1]).toBe(',');
      }
    }
  });

  it('keeps the 1.21 geometry as the token fallbacks', () => {
    expect(ruleBody('.nowo-cookie-consent {')).toContain('z-index: var(--nowo-cc-z-index, 60);');
    expect(ruleBody('.nowo-cookie-consent.show {')).toContain(
      'padding: var(--nowo-cc-viewport-padding-top, 1rem) 0 var(--nowo-cc-viewport-padding-bottom, 1rem);',
    );
    expect(ruleBody('.nowo-cookie-consent .nowo-cookie-consent__panel {')).toContain(
      'max-height: var(--nowo-cc-panel-max-height, calc(100dvh - var(--nowo-cc-viewport-padding-top, 1rem) - var(--nowo-cc-viewport-padding-bottom, 1rem)));',
    );
    expect(css).not.toContain('calc(100dvh - 2rem)');
    expect(ruleBody('.nowo-cookie-consent__preferences-bubble--bottom-right {')).toContain(
      'bottom: var(--nowo-cc-bubble-offset-bottom, 1.25rem);',
    );
    expect(ruleBody('.nowo-cookie-consent__preferences-bubble--top-left {')).toContain(
      'left: var(--nowo-cc-bubble-offset-inline, 1.25rem);',
    );
  });

  it('lets the host hide the bubble while the modal is open, visible by default', () => {
    expect(
      ruleBody('.nowo-cookie-consent-open .nowo-cookie-consent__preferences-bubble.nowo-cookie-consent__preferences-bubble {'),
    ).toContain('display: var(--nowo-cc-bubble-display-while-open, inline-flex);');
  });

  it('draws the switched-on toggle knob in a colour that differs from the track', () => {
    const base = ruleBody('.nowo-cookie-consent {');
    expect(base).toContain('--nowo-cc-toggle-on-knob-bg: #ffffff;');
    expect(base).toContain('--nowo-cc-toggle-on-bg: #30363c;');

    for (const darkTheme of [
      '.nowo-cookie-consent--dark-mode.nowo-cookie-consent--theme-light {',
      ".nowo-cookie-consent.nowo-cookie-consent--dark-mode {",
      '.nowo-cookie-consent--theme-dark-turquoise {',
      '.nowo-cookie-consent--theme-elegant-black {',
    ]) {
      expect(ruleBody(darkTheme)).toContain('--nowo-cc-toggle-on-knob-bg: var(--nowo-cc-bg);');
    }

    expect(css).not.toContain('var(--nowo-cc-toggle-on-knob-bg, #30363a)');
  });
});

describe('cookie-consent.css compact first layer (1.23, MUX-151)', () => {
  const phoneStart = css.indexOf('@media (max-width: 639.98px) {');
  const phone = css.slice(phoneStart, css.indexOf(' * Equal prominence (1.23', phoneStart));

  it('inherits the host font (token, then UiKit, then inherit)', () => {
    expect(ruleBody('.nowo-cookie-consent {')).toContain(
      'font-family: var(--nowo-cc-font-family, var(--nowo-ui-font-family, inherit));',
    );
    expect(css).not.toContain("BlinkMacSystemFont");
  });

  it('clamps the intro on phones only when the toggle is wired, and never on wider screens', () => {
    expect(phone).toContain(
      '.nowo-cookie-consent.nowo-cookie-consent--intro-clamp .nowo-cookie-consent__intro[data-nowo-intro]:not(.nowo-cookie-consent__intro--expanded) {',
    );
    expect(phone).toContain('-webkit-line-clamp: var(--nowo-cc-intro-lines-phone, 3);');
    const clampAt = css.indexOf('-webkit-line-clamp');
    expect(css.lastIndexOf('@media (max-width: 639.98px) {', clampAt)).toBeGreaterThan(-1);
    expect(ruleBody('.nowo-cookie-consent .nowo-cookie-consent__intro-more[hidden] {')).toContain('display: none;');
  });

  it('keeps reject and accept the same size on one row on phones (equal weight)', () => {
    expect(phone).toContain('grid-template-columns: repeat(2, minmax(0, 1fr));');
    expect(phone).toContain('.nowo-cookie-consent__btn-group > :first-child:nth-last-child(3) {');
    expect(phone).toContain('.nowo-cookie-consent__btn-group > :only-child {');
    // The button colours are not changed by the phone layout.
    expect(phone).not.toContain('--nowo-cc-btn-');
  });

  it('gives the more toggle a 44px tap target', () => {
    expect(ruleBody('.nowo-cookie-consent .nowo-cookie-consent__intro-more {')).toContain('min-height: 2.75rem;');
  });

  it('draws the more toggle in the modal text colour, never the brand primary (JD-I12)', () => {
    const body = ruleBody('.nowo-cookie-consent .nowo-cookie-consent__intro-more {');
    expect(body).toContain('color: var(--nowo-cc-primary);');
    expect(body).not.toContain('btn-primary');
    expect(body).not.toContain('--nowo-ui-');
  });
});

describe('cookie-consent.css equal prominence (1.23, JD-L3)', () => {
  it('fills reject with the primary tokens only under equal-weight buttons, outside the phone block', () => {
    const rule = ruleBody(
      ".nowo-cookie-consent.nowo-cookie-consent--equal-weight-buttons .nowo-cookie-consent__btn--secondary[name='use_only_functional_cookies'] {",
    );
    expect(rule).toContain('background: var(--nowo-cc-btn-primary-bg);');
    expect(rule).toContain('color: var(--nowo-cc-btn-primary-color);');
    expect(rule).toContain('font-weight: 600;');
    expect(css.indexOf('Equal prominence (1.23')).toBeGreaterThan(css.indexOf('@media (max-width: 639.98px) {'));
  });
});
