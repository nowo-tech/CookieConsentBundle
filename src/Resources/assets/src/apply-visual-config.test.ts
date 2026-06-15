import { describe, expect, it } from 'vitest';

import {
  applyVisualConfig,
  applyVisualConfigFromElement,
  setPageInteractionBlocked,
} from './apply-visual-config';

describe('applyVisualConfig', () => {
  it('applies layout, position, and button modifier classes to the modal', () => {
    document.body.innerHTML = `
      <div id="cookieconsent" class="modal nowo-cookie-consent">
        <div class="modal-dialog modal-xl"></div>
      </div>
    `;

    const modal = document.getElementById('cookieconsent')!;
    const dialog = modal.querySelector('.modal-dialog')!;

    applyVisualConfig(modal, {
      layout: 'bar',
      variant: 'wide',
      positionY: 'bottom',
      positionX: 'center',
      equalWeightButtons: true,
      flipButtons: true,
      disablePageInteraction: true,
    });

    expect(modal.classList.contains('nowo-cookie-consent--layout-bar')).toBe(true);
    expect(modal.classList.contains('nowo-cookie-consent--variant-wide')).toBe(true);
    expect(modal.classList.contains('nowo-cookie-consent--pos-y-bottom')).toBe(true);
    expect(modal.classList.contains('nowo-cookie-consent--pos-x-center')).toBe(true);
    expect(modal.classList.contains('nowo-cookie-consent--equal-weight-buttons')).toBe(true);
    expect(modal.classList.contains('nowo-cookie-consent--flip-buttons')).toBe(true);
    expect(modal.classList.contains('nowo-cookie-consent--disable-page-interaction')).toBe(true);
    expect(dialog.classList.contains('nowo-cookie-consent--dialog-bar')).toBe(true);
    expect(modal.dataset.nowoLayout).toBe('bar');
    expect(modal.dataset.nowoEqualWeightButtons).toBe('true');
    expect(modal.dataset.nowoFlipButtons).toBe('true');
    expect(modal.dataset.nowoDisablePageInteraction).toBe('true');
  });

  it('replaces previous visual modifiers when config changes', () => {
    document.body.innerHTML = `
      <div id="cookieconsent" class="modal nowo-cookie-consent nowo-cookie-consent--layout-box nowo-cookie-consent--pos-y-middle">
        <div class="modal-dialog modal-xl nowo-cookie-consent--dialog-box"></div>
      </div>
    `;

    const modal = document.getElementById('cookieconsent')!;

    applyVisualConfig(modal, {
      layout: 'cloud',
      positionY: 'top',
      positionX: 'right',
    });

    expect(modal.classList.contains('nowo-cookie-consent--layout-box')).toBe(false);
    expect(modal.classList.contains('nowo-cookie-consent--layout-cloud')).toBe(true);
    expect(modal.classList.contains('nowo-cookie-consent--pos-y-middle')).toBe(false);
    expect(modal.classList.contains('nowo-cookie-consent--pos-y-top')).toBe(true);
    expect(modal.classList.contains('nowo-cookie-consent--pos-x-right')).toBe(true);
  });

  it('reads visual options from data attributes', () => {
    document.body.innerHTML = `
      <div id="cookieconsent"
           class="modal nowo-cookie-consent"
           data-nowo-layout="cloud"
           data-nowo-variant="inline"
           data-nowo-position-y="top"
           data-nowo-position-x="left"
           data-nowo-equal-weight-buttons="true"
           data-nowo-flip-buttons="false"
           data-nowo-disable-page-interaction="true">
        <div class="modal-dialog modal-xl"></div>
      </div>
    `;

    const modal = document.getElementById('cookieconsent')!;

    applyVisualConfigFromElement(modal);

    expect(modal.classList.contains('nowo-cookie-consent--layout-cloud')).toBe(true);
    expect(modal.classList.contains('nowo-cookie-consent--variant-inline')).toBe(true);
    expect(modal.classList.contains('nowo-cookie-consent--pos-y-top')).toBe(true);
    expect(modal.classList.contains('nowo-cookie-consent--pos-x-left')).toBe(true);
    expect(modal.classList.contains('nowo-cookie-consent--equal-weight-buttons')).toBe(true);
    expect(modal.classList.contains('nowo-cookie-consent--flip-buttons')).toBe(false);
    expect(modal.classList.contains('nowo-cookie-consent--disable-page-interaction')).toBe(true);
  });
});

describe('setPageInteractionBlocked', () => {
  it('blocks page interaction only when the option is enabled', () => {
    document.body.innerHTML = `
      <div id="cookieconsent" data-nowo-disable-page-interaction="true"></div>
    `;

    const modal = document.getElementById('cookieconsent')!;

    setPageInteractionBlocked(modal, true);
    expect(document.body.classList.contains('nowo-cookie-consent-page-blocked')).toBe(true);

    setPageInteractionBlocked(modal, false);
    expect(document.body.classList.contains('nowo-cookie-consent-page-blocked')).toBe(false);
  });
});
