import { beforeEach, describe, expect, it, vi } from 'vitest';

function setReadyState(value: DocumentReadyState): void {
  Object.defineProperty(document, 'readyState', {
    value,
    configurable: true,
  });
}

describe('cookie-consent entrypoint', () => {
  beforeEach(() => {
    vi.resetModules();
    vi.restoreAllMocks();
    document.body.innerHTML = '';
    setReadyState('complete');
    delete window.bootstrap;
  });

  it('applies visual config classes from data attributes on init', async () => {
    document.body.innerHTML = `
      <div id="cookieconsent"
           class="modal nowo-cookie-consent"
           data-nowo-open="false"
           data-nowo-layout="bar"
           data-nowo-position-y="bottom"
           data-nowo-position-x="center"
           aria-hidden="true">
        <div class="modal-dialog modal-xl"></div>
      </div>
    `;

    await import('./cookie-consent');

    const modal = document.getElementById('cookieconsent');
    expect(modal?.classList.contains('nowo-cookie-consent--layout-bar')).toBe(true);
    expect(modal?.classList.contains('nowo-cookie-consent--pos-y-bottom')).toBe(true);
    expect(modal?.classList.contains('nowo-cookie-consent--pos-x-center')).toBe(true);
  });

  it('opens the modal with the CSS fallback when Bootstrap is unavailable', async () => {
    document.body.innerHTML = `
      <div id="cookieconsent" class="modal nowo-cookie-consent" data-nowo-open="true" aria-hidden="true"></div>
    `;

    await import('./cookie-consent');

    const modal = document.getElementById('cookieconsent');

    expect(modal?.classList.contains('show')).toBe(true);
    expect(modal?.style.display).toBe('block');
    expect(modal?.getAttribute('aria-hidden')).toBeNull();
  });

  it('uses Bootstrap Modal when available', async () => {
    const show = vi.fn();
    const hide = vi.fn();

    window.bootstrap = {
      Modal: vi.fn().mockImplementation(() => ({ show, hide })),
    };

    document.body.innerHTML = `
      <div id="cookieconsent" class="modal nowo-cookie-consent" data-nowo-open="true" aria-hidden="true"></div>
    `;

    await import('./cookie-consent');

    expect(window.bootstrap.Modal).toHaveBeenCalledWith(
      document.getElementById('cookieconsent'),
      expect.objectContaining({ backdrop: false, keyboard: false, focus: true }),
    );
    expect(show).toHaveBeenCalled();
    expect(hide).not.toHaveBeenCalled();
  });

  it('does not open the modal when data-nowo-open is false', async () => {
    document.body.innerHTML = `
      <div id="cookieconsent" class="modal nowo-cookie-consent" data-nowo-open="false" aria-hidden="true"></div>
    `;

    await import('./cookie-consent');

    const modal = document.getElementById('cookieconsent');
    expect(modal?.classList.contains('show')).toBe(false);
    expect(modal?.style.display).toBe('');
  });

  it('initializes on DOMContentLoaded when the document is still loading', async () => {
    setReadyState('loading');

    document.body.innerHTML = `
      <div id="cookieconsent" class="modal nowo-cookie-consent" data-nowo-open="true" aria-hidden="true"></div>
    `;

    await import('./cookie-consent');

    const modal = document.getElementById('cookieconsent');
    expect(modal?.classList.contains('show')).toBe(false);

    document.dispatchEvent(new Event('DOMContentLoaded'));

    expect(modal?.classList.contains('show')).toBe(true);
  });

  it('submits the form via XHR, dispatches success event, and hides the modal', async () => {
    document.body.innerHTML = `
      <div id="cookieconsent" class="modal nowo-cookie-consent" data-nowo-open="false" aria-hidden="true"></div>
      <form class="nowo-cookie-consent__form" action="/consent">
        <input type="checkbox" name="analytics" value="1" checked />
        <button type="submit" class="nowo-cookie-consent__btn" name="save">Save</button>
      </form>
    `;

    const xhrOpen = vi.spyOn(XMLHttpRequest.prototype, 'open').mockImplementation(() => undefined);
    const xhrSend = vi.spyOn(XMLHttpRequest.prototype, 'send').mockImplementation(function send(this: XMLHttpRequest) {
      Object.defineProperty(this, 'status', { value: 204, configurable: true });
      this.onload?.(new ProgressEvent('load'));
    });
    const xhrSetHeader = vi.spyOn(XMLHttpRequest.prototype, 'setRequestHeader').mockImplementation(() => undefined);
    const successListener = vi.fn();
    document.addEventListener('nowo-cookie-consent-form-submit-successful', successListener);

    await import('./cookie-consent');

    const button = document.querySelector<HTMLButtonElement>('.nowo-cookie-consent__btn');
    button?.click();

    expect(xhrOpen).toHaveBeenCalledWith('POST', expect.stringContaining('/consent'));
    expect(xhrSetHeader).toHaveBeenCalledWith('Content-Type', 'application/x-www-form-urlencoded');
    expect(xhrSend).toHaveBeenCalled();
    expect(successListener).toHaveBeenCalledTimes(1);

    const modal = document.getElementById('cookieconsent');
    expect(modal?.classList.contains('show')).toBe(false);
    expect(modal?.style.display).toBe('none');
  });

  it('keeps the modal visible when the XHR response is not successful', async () => {
    document.body.innerHTML = `
      <div id="cookieconsent" class="modal nowo-cookie-consent show" data-nowo-open="false" aria-hidden="true" style="display:block"></div>
      <form class="nowo-cookie-consent__form" action="/consent">
        <button type="submit" class="nowo-cookie-consent__btn" name="save">Save</button>
      </form>
    `;

    vi.spyOn(XMLHttpRequest.prototype, 'open').mockImplementation(() => undefined);
    vi.spyOn(XMLHttpRequest.prototype, 'send').mockImplementation(function send(this: XMLHttpRequest) {
      Object.defineProperty(this, 'status', { value: 500, configurable: true });
      this.onload?.(new ProgressEvent('load'));
    });
    vi.spyOn(XMLHttpRequest.prototype, 'setRequestHeader').mockImplementation(() => undefined);

    await import('./cookie-consent');

    document.querySelector<HTMLButtonElement>('.nowo-cookie-consent__btn')?.click();

    const modal = document.getElementById('cookieconsent');
    expect(modal?.classList.contains('show')).toBe(true);
    expect(modal?.style.display).toBe('block');
  });

  it('does nothing when the modal element is missing', async () => {
    document.body.innerHTML = `
      <form class="nowo-cookie-consent__form" action="/consent">
        <button type="submit" class="nowo-cookie-consent__btn" name="save">Save</button>
      </form>
    `;

    const xhrOpen = vi.spyOn(XMLHttpRequest.prototype, 'open');

    await import('./cookie-consent');

    document.querySelector<HTMLButtonElement>('.nowo-cookie-consent__btn')?.click();

    expect(xhrOpen).not.toHaveBeenCalled();
  });
});
