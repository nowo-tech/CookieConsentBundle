import { describe, expect, it } from 'vitest';

import { applyThemeOptions, applyThemeOptionsFromElement } from './apply-theme';

describe('applyThemeOptions', () => {
  it('applies known color themes and modifier classes', () => {
    const modal = document.createElement('div');
    modal.classList.add('nowo-cookie-consent--theme-light');

    applyThemeOptions(modal, {
      colorTheme: 'dark-turquoise',
      darkModeEnabled: true,
      disableTransitions: true,
    });

    expect(modal.classList.contains('nowo-cookie-consent--theme-light')).toBe(false);
    expect(modal.classList.contains('nowo-cookie-consent--theme-dark-turquoise')).toBe(true);
    expect(modal.classList.contains('nowo-cookie-consent--dark-mode')).toBe(true);
    expect(modal.classList.contains('nowo-cookie-consent--no-transitions')).toBe(true);
    expect(modal.dataset.nowoColorTheme).toBe('dark-turquoise');
    expect(modal.dataset.nowoDarkMode).toBe('true');
    expect(modal.dataset.nowoDisableTransitions).toBe('true');
  });

  it('ignores unknown color themes', () => {
    const modal = document.createElement('div');

    applyThemeOptions(modal, { colorTheme: 'unknown-theme' });

    expect(modal.classList.contains('nowo-cookie-consent--theme-unknown-theme')).toBe(false);
    expect(modal.dataset.nowoColorTheme).toBeUndefined();
  });

  it('records explicit false flags for dark mode and transitions', () => {
    const modal = document.createElement('div');

    applyThemeOptions(modal, {
      colorTheme: 'light',
      darkModeEnabled: false,
      disableTransitions: false,
    });

    expect(modal.classList.contains('nowo-cookie-consent--dark-mode')).toBe(false);
    expect(modal.classList.contains('nowo-cookie-consent--no-transitions')).toBe(false);
    expect(modal.dataset.nowoDarkMode).toBe('false');
    expect(modal.dataset.nowoDisableTransitions).toBe('false');
  });
});

describe('applyThemeOptionsFromElement', () => {
  it('reads theme options from modal data attributes', () => {
    const modal = document.createElement('div');
    modal.dataset.nowoColorTheme = 'elegant-black';
    modal.dataset.nowoDarkMode = 'true';
    modal.dataset.nowoDisableTransitions = 'false';

    applyThemeOptionsFromElement(modal);

    expect(modal.classList.contains('nowo-cookie-consent--theme-elegant-black')).toBe(true);
    expect(modal.classList.contains('nowo-cookie-consent--dark-mode')).toBe(true);
    expect(modal.classList.contains('nowo-cookie-consent--no-transitions')).toBe(false);
  });
});
