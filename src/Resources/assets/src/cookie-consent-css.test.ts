import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import { BUBBLE_ACCENT_PROPERTY } from './preferences-bubble';

const css = readFileSync(resolve(__dirname, 'cookie-consent.css'), 'utf8');

/** Custom properties set at runtime (CSSOM) instead of in the stylesheet. */
const RUNTIME_PROPERTIES = new Set([BUBBLE_ACCENT_PROPERTY]);

function declaredProperties(): Set<string> {
  return new Set([...css.matchAll(/(--nowo-cc-[a-z0-9-]+)\s*:/g)].map((match) => match[1]));
}

function ruleBody(selectorFragment: string): string {
  const start = css.indexOf(selectorFragment);
  expect(start).toBeGreaterThan(-1);
  const open = css.indexOf('{', start);
  return css.slice(open + 1, css.indexOf('}', open));
}

describe('cookie-consent.css custom properties', () => {
  it('declares every --nowo-cc-* custom property it reads', () => {
    const declared = declaredProperties();
    const used = new Set([...css.matchAll(/var\(\s*(--nowo-cc-[a-z0-9-]+)/g)].map((match) => match[1]));
    const undeclared = [...used].filter((name) => !declared.has(name) && !RUNTIME_PROPERTIES.has(name));

    expect(undeclared).toEqual([]);
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
