export interface ThemeOptions {
  colorTheme?: string;
  darkModeEnabled?: boolean;
  disableTransitions?: boolean;
}

const COLOR_THEMES = ['light', 'dark', 'dark-turquoise', 'light-funky', 'elegant-black'] as const;
const MODIFIER_PREFIX = 'nowo-cookie-consent--';

function removeThemeModifiers(modalElement: HTMLElement): void {
  modalElement.classList.forEach((className) => {
    if (className.startsWith(`${MODIFIER_PREFIX}theme-`)) {
      modalElement.classList.remove(className);
    }
  });

  modalElement.classList.remove(`${MODIFIER_PREFIX}dark-mode`, `${MODIFIER_PREFIX}no-transitions`);
}

export function applyThemeOptions(modalElement: HTMLElement, options: ThemeOptions): void {
  removeThemeModifiers(modalElement);

  if (options.colorTheme && COLOR_THEMES.includes(options.colorTheme as (typeof COLOR_THEMES)[number])) {
    modalElement.classList.add(`${MODIFIER_PREFIX}theme-${options.colorTheme}`);
    modalElement.dataset.nowoColorTheme = options.colorTheme;
  }

  if (options.darkModeEnabled) {
    modalElement.classList.add(`${MODIFIER_PREFIX}dark-mode`);
  }

  if (options.disableTransitions) {
    modalElement.classList.add(`${MODIFIER_PREFIX}no-transitions`);
  }

  if (typeof options.darkModeEnabled === 'boolean') {
    modalElement.dataset.nowoDarkMode = options.darkModeEnabled ? 'true' : 'false';
  }

  if (typeof options.disableTransitions === 'boolean') {
    modalElement.dataset.nowoDisableTransitions = options.disableTransitions ? 'true' : 'false';
  }
}

export function applyThemeOptionsFromElement(modalElement: HTMLElement): void {
  applyThemeOptions(modalElement, {
    colorTheme: modalElement.dataset.nowoColorTheme,
    darkModeEnabled: modalElement.dataset.nowoDarkMode === 'true',
    disableTransitions: modalElement.dataset.nowoDisableTransitions === 'true',
  });
}
