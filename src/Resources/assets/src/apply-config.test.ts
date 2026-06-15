import { describe, expect, it } from 'vitest';

import { applyRemoteConfig } from './apply-config';

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
    expect(modal.querySelector('.nowo-cookie-consent__title')?.textContent).toBe('API title');
    expect(modal.querySelector('.nowo-cookie-consent__intro')?.textContent).toBe('API intro');
    expect(modal.querySelector('.nowo-cookie-consent__read-more')?.textContent).toBe('API read more');
    expect(modal.querySelector('[name="use_all_cookies"]')?.textContent).toBe('API all');
    expect(modal.querySelector('[name="save"]')?.textContent).toBe('API save');
    expect(modal.querySelector('[data-nowo-category="analytics"] .nowo-cookie-consent__category-title')?.textContent).toBe('API analytics');
  });
});
