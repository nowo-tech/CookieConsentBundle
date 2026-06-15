import { describe, expect, it, vi } from 'vitest';

import { applyRemoteConfig, fetchRemoteConfig } from './apply-config';

describe('applyRemoteConfig', () => {
  it('applies modal copy, gui options, and category labels from API data', () => {
    document.body.innerHTML = `
      <div id="cookieconsent" data-nowo-open="true">
        <h5 class="nowo-cookie-consent__title">Old title</h5>
        <p class="nowo-cookie-consent__intro">Old intro</p>
        <a class="nowo-cookie-consent__read-more">Old read more</a>
        <button class="nowo-cookie-consent__btn" name="use_all_cookies">Old all</button>
        <button class="nowo-cookie-consent__btn" name="use_only_functional_cookies">Old necessary</button>
        <button class="nowo-cookie-consent__btn" name="save">Old save</button>
        <div class="nowo-cookie-consent__category" data-nowo-category="analytics">
          <h4 class="nowo-cookie-consent__category-title">Old analytics</h4>
          <p class="nowo-cookie-consent__category-description">Old analytics description</p>
        </div>
      </div>
    `;

    const modal = document.getElementById('cookieconsent')!;

    applyRemoteConfig(modal, {
      autoShow: false,
      revision: 3,
      disablePageInteraction: true,
      guiOptions: {
        consentModal: {
          layout: 'cloud',
          variant: 'inline',
          position: 'top right',
        },
        preferencesModal: {
          layout: 'bar',
          variant: 'wide',
          position: 'middle left',
          equalWeightButtons: true,
          flipButtons: true,
        },
      },
      language: {
        default: 'en',
        translations: {
          en: {
            consentModal: {
              title: 'API title',
              description: 'API intro',
              footer: 'API read more',
              acceptAllBtn: 'API all',
              acceptNecessaryBtn: 'API necessary',
            },
            preferencesModal: {
              savePreferencesBtn: 'API save',
            },
            categories: {
              analytics: {
                title: 'API analytics',
                description: 'API analytics description',
              },
            },
          },
        },
      },
    }, 'en');

    expect(modal.dataset.nowoOpen).toBe('false');
    expect(modal.dataset.nowoLayout).toBe('cloud');
    expect(modal.dataset.nowoVariant).toBe('inline');
    expect(modal.dataset.nowoPositionY).toBe('top');
    expect(modal.dataset.nowoPositionX).toBe('right');
    expect(modal.dataset.nowoRevision).toBe('3');
    expect(modal.classList.contains('nowo-cookie-consent--layout-cloud')).toBe(true);
    expect(modal.classList.contains('nowo-cookie-consent--variant-inline')).toBe(true);
    expect(modal.classList.contains('nowo-cookie-consent--pos-y-top')).toBe(true);
    expect(modal.classList.contains('nowo-cookie-consent--pos-x-right')).toBe(true);
    expect(modal.classList.contains('nowo-cookie-consent--disable-page-interaction')).toBe(true);
    expect(modal.dataset.nowoPreferencesLayout).toBe('bar');
    expect(modal.dataset.nowoPreferencesVariant).toBe('wide');
    expect(modal.dataset.nowoPreferencesPositionY).toBe('middle');
    expect(modal.dataset.nowoPreferencesPositionX).toBe('left');
    expect(modal.dataset.nowoPreferencesEqualWeightButtons).toBe('true');
    expect(modal.dataset.nowoPreferencesFlipButtons).toBe('true');
    expect(modal.querySelector('.nowo-cookie-consent__title')?.textContent).toBe('API title');
    expect(modal.querySelector('.nowo-cookie-consent__intro')?.textContent).toBe('API intro');
    expect(modal.querySelector('.nowo-cookie-consent__read-more')?.textContent).toBe('API read more');
    expect(modal.querySelector('[name="use_all_cookies"]')?.textContent).toBe('API all');
    expect(modal.querySelector('[name="save"]')?.textContent).toBe('API save');
    expect(modal.querySelector('[data-nowo-category="analytics"] .nowo-cookie-consent__category-title')?.textContent).toBe('API analytics');
  });

  it('opens the preferences step and applies optional flags from API data', () => {
    document.body.innerHTML = `
      <div id="cookieconsent" data-nowo-open="true">
        <div data-nowo-step="banner" class="nowo-cookie-consent__step nowo-cookie-consent__step--active"></div>
        <div data-nowo-step="preferences" class="nowo-cookie-consent__step"></div>
        <h5 class="nowo-cookie-consent__preferences-intro-title">Old usage title</h5>
        <p class="nowo-cookie-consent__preferences-intro-description">Old usage description</p>
        <div data-nowo-step="preferences" class="nowo-cookie-consent__step">
          <h5 class="nowo-cookie-consent__title">Old preferences title</h5>
        </div>
      </div>
    `;

    const modal = document.getElementById('cookieconsent')!;

    applyRemoteConfig(modal, {
      twoStepModal: true,
      openPreferencesModal: true,
      manageIframePlaceholders: true,
      colorTheme: 'dark',
      darkModeEnabled: false,
      disableTransitions: false,
      language: {
        default: 'es',
        translations: {
          en: {
            preferencesModal: {
              title: 'English preferences',
              usageTitle: 'English usage',
              usageDescription: 'English usage description',
            },
          },
        },
      },
    }, 'en');

    expect(modal.dataset.nowoTwoStep).toBe('true');
    expect(modal.dataset.nowoOpenPreferences).toBe('true');
    expect(modal.dataset.nowoManageIframePlaceholders).toBe('true');
    expect(modal.classList.contains('nowo-cookie-consent--theme-dark')).toBe(true);
    expect(modal.querySelector('.nowo-cookie-consent__preferences-intro-title')?.textContent).toBe('English usage');
    expect(modal.querySelector('.nowo-cookie-consent__preferences-intro-description')?.textContent).toBe('English usage description');
    expect(modal.querySelector('[data-nowo-step="preferences"] .nowo-cookie-consent__title')?.textContent).toBe('English preferences');
    expect(modal.querySelector('[data-nowo-step="preferences"]')?.classList.contains('nowo-cookie-consent__step--active')).toBe(true);
  });
});

describe('fetchRemoteConfig', () => {
  it('returns config data from a successful JSON response', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ code: 200, data: { autoShow: false, revision: 2 } }),
    });
    vi.stubGlobal('fetch', fetchMock);

    const data = await fetchRemoteConfig('/cookie-consent/config?locale=en');

    expect(fetchMock).toHaveBeenCalledWith('/cookie-consent/config?locale=en', {
      method: 'GET',
      headers: { Accept: 'application/json' },
    });
    expect(data).toEqual({ autoShow: false, revision: 2 });

    vi.unstubAllGlobals();
  });

  it('throws when the config request is not successful', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false, status: 503 }));

    await expect(fetchRemoteConfig('/cookie-consent/config')).rejects.toThrow(
      'Cookie consent config request failed with status 503',
    );

    vi.unstubAllGlobals();
  });

  it('returns null when the API response has no data payload', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ code: 200 }),
    }));

    await expect(fetchRemoteConfig('/cookie-consent/config')).resolves.toBeNull();

    vi.unstubAllGlobals();
  });
});
