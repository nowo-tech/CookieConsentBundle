import { describe, expect, it } from 'vitest';

import { activateIframesForConsent, readAllowedCategoriesFromModal } from './iframe-manager';

describe('readAllowedCategoriesFromModal', () => {
  it('returns an empty array when the consent form is missing', () => {
    const modal = document.createElement('div');

    expect(readAllowedCategoriesFromModal(modal)).toEqual([]);
  });

  it('returns required plus checked consent categories', () => {
    document.body.innerHTML = `
      <div id="modal">
        <form class="nowo-cookie-consent__form">
          <input type="checkbox" name="cookie_consent[analytics]" checked />
          <input type="checkbox" name="cookie_consent[marketing]" />
        </form>
      </div>
    `;

    const allowed = readAllowedCategoriesFromModal(document.getElementById('modal')!);

    expect(allowed).toContain('required');
    expect(allowed).toContain('analytics');
    expect(allowed).not.toContain('marketing');
  });

  it('removes unchecked categories from the allowed set', () => {
    document.body.innerHTML = `
      <div id="modal">
        <form class="nowo-cookie-consent__form">
          <input type="checkbox" name="cookie_consent[analytics]" />
        </form>
      </div>
    `;

    const allowed = readAllowedCategoriesFromModal(document.getElementById('modal')!);

    expect(allowed).toEqual(['required']);
  });

  it('ignores checkbox inputs without a parseable category name', () => {
    document.body.innerHTML = `
      <div id="modal">
        <form class="nowo-cookie-consent__form">
          <input type="checkbox" name="invalid" checked />
        </form>
      </div>
    `;

    expect(readAllowedCategoriesFromModal(document.getElementById('modal')!)).toEqual(['required']);
  });
});

describe('activateIframesForConsent', () => {
  it('activates blocked scripts for allowed categories', () => {
    document.body.innerHTML = `
      <script type="text/plain" data-cookie-category="analytics" data-testid="blocked">
        window.__analyticsActivated = true;
      </script>
      <script type="text/plain" data-cookie-category="marketing" data-testid="blocked-marketing">
        window.__marketingActivated = true;
      </script>
    `;

    activateIframesForConsent(['required', 'analytics']);

    const activatedScripts = [...document.querySelectorAll('script')].filter(
      (script) => script.type !== 'text/plain',
    );
    expect(activatedScripts).toHaveLength(1);
    expect(activatedScripts[0]?.text).toContain('__analyticsActivated');
    expect(document.querySelector('script[data-testid="blocked-marketing"]')).not.toBeNull();
  });

  it('does not re-activate scripts that were already activated', () => {
    document.body.innerHTML = `
      <script type="text/plain" data-cookie-category="analytics" data-nowo-activated="true">
        window.__alreadyActivated = true;
      </script>
    `;

    activateIframesForConsent(['analytics']);

    expect(document.querySelectorAll('script').length).toBe(1);
  });

  it('replaces iframe placeholders with iframe elements', () => {
    document.body.innerHTML = `
      <div
        data-nowo-iframe-category="analytics"
        data-nowo-iframe-src="https://example.com/embed"
        data-nowo-iframe-title="Analytics embed"
        data-nowo-iframe-loading="eager"
        data-nowo-iframe-allow="fullscreen"
      ></div>
    `;

    activateIframesForConsent(['analytics']);

    const iframe = document.querySelector('iframe');
    expect(iframe).not.toBeNull();
    expect(iframe?.src).toBe('https://example.com/embed');
    expect(iframe?.title).toBe('Analytics embed');
    expect(iframe?.getAttribute('loading')).toBe('eager');
    expect(iframe?.getAttribute('allow')).toBe('fullscreen');
  });

  it('supports legacy data-src and data-title attributes on placeholders', () => {
    document.body.innerHTML = `
      <div data-nowo-iframe-category="marketing" data-src="https://legacy.example" data-title="Legacy"></div>
    `;

    activateIframesForConsent(['marketing']);

    const iframe = document.querySelector('iframe');
    expect(iframe?.src).toBe('https://legacy.example/');
    expect(iframe?.title).toBe('Legacy');
  });

  it('skips iframe placeholders without a source URL', () => {
    document.body.innerHTML = `
      <div data-nowo-iframe-category="analytics" data-nowo-activated="true"></div>
    `;

    activateIframesForConsent(['analytics']);

    expect(document.querySelector('iframe')).toBeNull();
  });

  it('does not activate placeholders for disallowed categories', () => {
    document.body.innerHTML = `
      <div data-nowo-iframe-category="marketing" data-nowo-iframe-src="https://example.com"></div>
    `;

    activateIframesForConsent(['analytics']);

    expect(document.querySelector('iframe')).toBeNull();
  });

  it('skips iframe placeholders that were already activated', () => {
    document.body.innerHTML = `
      <div
        data-nowo-iframe-category="analytics"
        data-nowo-iframe-src="https://example.com"
        data-nowo-activated="true"
      ></div>
    `;

    activateIframesForConsent(['analytics']);

    expect(document.querySelector('iframe')).toBeNull();
  });
});
