import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

/**
 * Resolves the --nowo-cc-* tokens of every colour theme the way the browser does (base block + theme block on the
 * same element) and checks WCAG contrast of the pairs the preferences modal actually draws.
 */
const css = readFileSync(resolve(__dirname, 'cookie-consent.css'), 'utf8');

type Rgba = [number, number, number, number];
type Tokens = Map<string, string>;

function ruleBody(selectorStart: string): string {
  const start = css.indexOf(selectorStart);
  expect(start, selectorStart).toBeGreaterThan(-1);
  const open = css.indexOf('{', start);
  return css.slice(open + 1, css.indexOf('}', open));
}

function declarations(body: string): Tokens {
  const tokens: Tokens = new Map();
  for (const match of body.matchAll(/(--nowo-cc-[a-z0-9-]+)\s*:\s*([^;]+);/g)) {
    tokens.set(match[1], match[2].trim());
  }
  return tokens;
}

const BASE = '.nowo-cookie-consent {';
const LIGHT = '.nowo-cookie-consent--theme-light {';

/** Theme name → rule blocks applied in cascade order (later wins). */
const THEMES: Record<string, string[]> = {
  light: [BASE, LIGHT],
  dark: [BASE, '.nowo-cookie-consent--theme-dark,'],
  'light + dark mode': [BASE, LIGHT, '.nowo-cookie-consent--theme-dark,'],
  'dark-turquoise': [BASE, '.nowo-cookie-consent--theme-dark-turquoise {'],
  'light-funky': [BASE, '.nowo-cookie-consent--theme-light-funky {'],
  'elegant-black': [BASE, '.nowo-cookie-consent--theme-elegant-black {'],
};

const DARK_THEMES = ['dark', 'light + dark mode', 'dark-turquoise', 'elegant-black'];
const CATEGORIES = ['required', 'analytics', 'preferences', 'marketing'];

function themeTokens(theme: string): Tokens {
  const tokens: Tokens = new Map();
  for (const block of THEMES[theme]) {
    for (const [name, value] of declarations(ruleBody(block))) {
      tokens.set(name, value);
    }
  }
  return tokens;
}

function splitArgs(inner: string): string[] {
  const parts: string[] = [];
  let depth = 0;
  let current = '';
  for (const char of inner) {
    if (char === '(') depth++;
    if (char === ')') depth--;
    if (char === ',' && depth === 0) {
      parts.push(current.trim());
      current = '';
      continue;
    }
    current += char;
  }
  parts.push(current.trim());
  return parts;
}

function parseColor(raw: string, tokens: Tokens, depth = 0): Rgba {
  expect(depth, `token cycle resolving ${raw}`).toBeLessThan(20);
  const value = raw.trim();
  const varMatch = value.match(/^var\(\s*(--nowo-cc-[a-z0-9-]+)\s*(?:,\s*(.+))?\)$/);
  if (varMatch) {
    const resolved = tokens.get(varMatch[1]) ?? varMatch[2];
    expect(resolved, `undefined token ${varMatch[1]}`).toBeDefined();
    return parseColor(resolved as string, tokens, depth + 1);
  }
  if (value === 'transparent') return [0, 0, 0, 0];
  const hex = value.match(/^#([0-9a-f]{3}|[0-9a-f]{6})$/i);
  if (hex) {
    const digits = hex[1].length === 3 ? [...hex[1]].map((d) => d + d).join('') : hex[1];
    return [0, 2, 4].map((i) => parseInt(digits.slice(i, i + 2), 16)).concat(1) as Rgba;
  }
  const rgb = value.match(/^rgba?\(([^)]+)\)$/);
  if (rgb) {
    const [r, g, b, a = '1'] = rgb[1].split(',').map((p) => p.trim());
    return [Number(r), Number(g), Number(b), Number(a)];
  }
  const mix = value.match(/^color-mix\(in srgb,\s*(.+)\)$/);
  if (mix) {
    const [first, second] = splitArgs(mix[1]);
    const pct = first.match(/\s(\d+(?:\.\d+)?)%$/);
    const weight = pct ? Number(pct[1]) / 100 : 0.5;
    const a = parseColor(pct ? first.slice(0, pct.index) : first, tokens, depth + 1);
    const b = parseColor(second.replace(/\s\d+(?:\.\d+)?%$/, ''), tokens, depth + 1);
    // CSS Color 4: premultiplied interpolation.
    const alpha = a[3] * weight + b[3] * (1 - weight);
    if (alpha === 0) return [0, 0, 0, 0];
    const channel = (i: number) => (a[i] * a[3] * weight + b[i] * b[3] * (1 - weight)) / alpha;
    return [channel(0), channel(1), channel(2), alpha];
  }
  throw new Error(`Unsupported colour value: ${value}`);
}

function over(top: Rgba, bottom: Rgba): Rgba {
  const a = top[3];
  return [0, 1, 2].map((i) => top[i] * a + bottom[i] * (1 - a)).concat(1) as Rgba;
}

function luminance([r, g, b]: Rgba): number {
  const lin = (c: number) => {
    const s = c / 255;
    return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
  };
  return 0.2126 * lin(r) + 0.7152 * lin(g) + 0.0722 * lin(b);
}

function contrast(fg: Rgba, bg: Rgba): number {
  const [hi, lo] = [luminance(fg), luminance(bg)].sort((x, y) => y - x);
  return (hi + 0.05) / (lo + 0.05);
}

function resolveOn(tokens: Tokens, token: string, surface: Rgba): Rgba {
  return over(parseColor(`var(${token})`, tokens), surface);
}

describe.each(Object.keys(THEMES))('theme %s', (theme) => {
  const tokens = themeTokens(theme);
  const surface = over(parseColor('var(--nowo-cc-bg)', tokens), [255, 255, 255, 1]);
  const text = resolveOn(tokens, '--nowo-cc-primary', surface);

  it('keeps body and secondary text readable on the modal surface', () => {
    expect(contrast(text, surface)).toBeGreaterThanOrEqual(4.5);
    expect(contrast(resolveOn(tokens, '--nowo-cc-secondary', surface), surface)).toBeGreaterThanOrEqual(4.5);
  });

  it.each(CATEGORIES)('keeps the %s category header title readable', (category) => {
    const header = resolveOn(tokens, `--nowo-cc-card-${category}-bg`, surface);
    expect(contrast(text, header)).toBeGreaterThanOrEqual(4.5);
  });

  it('keeps the footer readable and both button variants legible', () => {
    const footer = resolveOn(tokens, '--nowo-cc-footer-bg', surface);
    expect(contrast(text, footer)).toBeGreaterThanOrEqual(4.5);

    const primaryBg = resolveOn(tokens, '--nowo-cc-btn-primary-bg', footer);
    expect(contrast(resolveOn(tokens, '--nowo-cc-btn-primary-color', primaryBg), primaryBg)).toBeGreaterThanOrEqual(4.5);
    const hoverBg = resolveOn(tokens, '--nowo-cc-btn-primary-hover-bg', footer);
    expect(contrast(resolveOn(tokens, '--nowo-cc-btn-primary-hover-color', hoverBg), hoverBg)).toBeGreaterThanOrEqual(4.5);

    const secondaryBg = resolveOn(tokens, '--nowo-cc-btn-secondary-bg', footer);
    expect(contrast(resolveOn(tokens, '--nowo-cc-btn-secondary-color', secondaryBg), secondaryBg)).toBeGreaterThanOrEqual(4.5);
  });
});

describe.each(DARK_THEMES)('dark theme %s', (theme) => {
  const tokens = themeTokens(theme);
  const surface = over(parseColor('var(--nowo-cc-bg)', tokens), [255, 255, 255, 1]);

  it('draws the footer on the modal surface instead of a light strip', () => {
    expect(resolveOn(tokens, '--nowo-cc-footer-bg', surface)).toEqual(surface);
  });

  it.each(CATEGORIES)('uses a dark tint (no light pastel) for the %s category header', (category) => {
    expect(luminance(resolveOn(tokens, `--nowo-cc-card-${category}-bg`, surface))).toBeLessThan(0.1);
  });
});

describe('preferences step markup', () => {
  it('renders the save button as the primary (accent) action in both modal templates', () => {
    for (const template of ['cookie_consent.html.twig', 'cookie_consent.tailwind.html.twig']) {
      const twig = readFileSync(resolve(__dirname, '../../views', template), 'utf8');
      expect(twig).toContain("class: 'btn nowo-cookie-consent__btn nowo-cookie-consent__btn--save'");
    }
  });
});
