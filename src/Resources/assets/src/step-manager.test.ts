import { beforeEach, describe, expect, it } from 'vitest';

import { bindStepNavigation } from './step-manager';

describe('bindStepNavigation', () => {
  beforeEach(() => {
    document.body.innerHTML = `
      <div id="cookieconsent" class="nowo-cookie-consent--two-step" data-nowo-two-step="true" data-nowo-open-preferences="false"
           data-nowo-layout="box" data-nowo-variant="wide" data-nowo-position-y="bottom" data-nowo-position-x="center"
           data-nowo-preferences-layout="bar" data-nowo-preferences-variant="inline" data-nowo-preferences-position-y="middle" data-nowo-preferences-position-x="right">
        <div class="nowo-cookie-consent__step nowo-cookie-consent__step--banner nowo-cookie-consent__step--active" data-nowo-step="banner"></div>
        <div class="nowo-cookie-consent__step nowo-cookie-consent__step--preferences" data-nowo-step="preferences"></div>
        <button type="button" data-nowo-show-preferences>Show</button>
        <button type="button" data-nowo-hide-preferences>Close</button>
      </div>
    `;
  });

  it('switches from banner to preferences when customize is clicked', () => {
    const modal = document.getElementById('cookieconsent')!;
    bindStepNavigation(modal);

    document.querySelector<HTMLButtonElement>('[data-nowo-show-preferences]')?.click();

    const banner = modal.querySelector('[data-nowo-step="banner"]');
    const preferences = modal.querySelector('[data-nowo-step="preferences"]');

    expect(banner?.classList.contains('nowo-cookie-consent__step--active')).toBe(false);
    expect(preferences?.classList.contains('nowo-cookie-consent__step--active')).toBe(true);
    expect(modal.classList.contains('nowo-cookie-consent--preferences-view')).toBe(true);
    expect(modal.classList.contains('nowo-cookie-consent--layout-bar')).toBe(true);
    expect(modal.classList.contains('nowo-cookie-consent--variant-inline')).toBe(true);
    expect(modal.classList.contains('nowo-cookie-consent--pos-y-middle')).toBe(true);
    expect(modal.classList.contains('nowo-cookie-consent--pos-x-right')).toBe(true);
  });

  it('switches back from preferences to banner when close is clicked', () => {
    const modal = document.getElementById('cookieconsent')!;
    bindStepNavigation(modal);

    document.querySelector<HTMLButtonElement>('[data-nowo-show-preferences]')?.click();
    document.querySelector<HTMLButtonElement>('[data-nowo-hide-preferences]')?.click();

    const banner = modal.querySelector('[data-nowo-step="banner"]');
    const preferences = modal.querySelector('[data-nowo-step="preferences"]');

    expect(banner?.classList.contains('nowo-cookie-consent__step--active')).toBe(true);
    expect(preferences?.classList.contains('nowo-cookie-consent__step--active')).toBe(false);
    expect(modal.classList.contains('nowo-cookie-consent--preferences-view')).toBe(false);
  });

  it('binds navigation when step markup exists even if data-nowo-two-step is false', () => {
    const modal = document.getElementById('cookieconsent')!;
    modal.dataset.nowoTwoStep = 'false';
    modal.classList.remove('nowo-cookie-consent--two-step');

    bindStepNavigation(modal);
    document.querySelector<HTMLButtonElement>('[data-nowo-show-preferences]')?.click();

    expect(modal.dataset.nowoTwoStep).toBe('true');
    expect(modal.classList.contains('nowo-cookie-consent--two-step')).toBe(true);
    expect(modal.querySelector('[data-nowo-step="preferences"]')?.classList.contains('nowo-cookie-consent__step--active')).toBe(true);
  });
});
