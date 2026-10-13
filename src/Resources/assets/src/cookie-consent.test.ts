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
      Modal: vi.fn().mockImplementation(function () {
        return { show, hide };
      }),
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
      <div id="cookieconsent" class="modal nowo-cookie-consent" data-nowo-open="false" aria-hidden="true">
        <form class="nowo-cookie-consent__form" action="/consent">
          <input type="hidden" name="_token" value="csrf-token" data-controller="csrf-protection" />
          <input type="checkbox" name="analytics" value="1" checked />
          <button type="submit" class="nowo-cookie-consent__btn" name="save">Save</button>
        </form>
      </div>
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
    expect(xhrSetHeader).toHaveBeenCalledWith('csrf-token', expect.stringMatching(/^[-_/+a-zA-Z0-9]{24,}$/));
    expect(xhrSend).toHaveBeenCalled();
    const payload = String(xhrSend.mock.calls[0]?.[0] ?? '');
    expect(payload).toMatch(/_token=/);
    expect(payload).not.toContain('_token=csrf-token');
    expect(successListener).toHaveBeenCalledTimes(1);

    const modal = document.getElementById('cookieconsent');
    expect(modal?.classList.contains('show')).toBe(false);
    expect(modal?.style.display).toBe('none');
  });

  it('keeps the modal visible when the XHR response is not successful', async () => {
    document.body.innerHTML = `
      <div id="cookieconsent" class="modal nowo-cookie-consent show" data-nowo-open="false" aria-hidden="true" style="display:block">
        <form class="nowo-cookie-consent__form" action="/consent">
          <button type="submit" class="nowo-cookie-consent__btn" name="save">Save</button>
        </form>
      </div>
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

  it('does not submit the form when the show-preferences button is clicked', async () => {
    document.body.innerHTML = `
      <div id="cookieconsent" class="modal nowo-cookie-consent nowo-cookie-consent--two-step" data-nowo-open="false" data-nowo-two-step="true" aria-hidden="true">
        <div class="nowo-cookie-consent__step nowo-cookie-consent__step--banner nowo-cookie-consent__step--active" data-nowo-step="banner">
          <button type="button" data-nowo-show-preferences>Show preferences</button>
        </div>
        <div class="nowo-cookie-consent__step nowo-cookie-consent__step--preferences" data-nowo-step="preferences"></div>
        <form class="nowo-cookie-consent__form" action="/consent">
          <button type="submit" class="nowo-cookie-consent__btn" name="save">Save</button>
        </form>
      </div>
    `;

    const xhrOpen = vi.spyOn(XMLHttpRequest.prototype, 'open');

    await import('./cookie-consent');

    document.querySelector<HTMLButtonElement>('[data-nowo-show-preferences]')?.click();

    expect(xhrOpen).not.toHaveBeenCalled();
    expect(document.querySelector('[data-nowo-step="preferences"]')?.classList.contains('nowo-cookie-consent__step--active')).toBe(true);
  });

  it('hides through Bootstrap after a successful save and activates consented scripts', async () => {
    const show = vi.fn();
    const hide = vi.fn();
    window.bootstrap = {
      Modal: vi.fn().mockImplementation(function () {
        return { show, hide };
      }),
    };

    document.body.innerHTML = `
      <script type="text/plain" data-cookie-category="required">window.__nowoRequiredRan = true;</script>
      <div id="cookieconsent" class="modal nowo-cookie-consent" data-nowo-open="true" data-nowo-manage-iframe-placeholders="true" aria-hidden="true">
        <form class="nowo-cookie-consent__form">
          <button type="submit" class="nowo-cookie-consent__btn" name="save">Save</button>
        </form>
      </div>
    `;

    const xhrOpen = vi.spyOn(XMLHttpRequest.prototype, 'open').mockImplementation(() => undefined);
    vi.spyOn(XMLHttpRequest.prototype, 'send').mockImplementation(function send(this: XMLHttpRequest) {
      Object.defineProperty(this, 'status', { value: 200, configurable: true });
      this.onload?.(new ProgressEvent('load'));
    });
    vi.spyOn(XMLHttpRequest.prototype, 'setRequestHeader').mockImplementation(() => undefined);

    await import('./cookie-consent');

    document.querySelector<HTMLButtonElement>('.nowo-cookie-consent__btn')?.click();

    expect(show).toHaveBeenCalledTimes(1);
    expect(hide).toHaveBeenCalledTimes(1);
    expect(xhrOpen).toHaveBeenCalledWith('POST', location.href);
    expect(document.querySelector('script[type="text/plain"][data-cookie-category="required"]')).toBeNull();
  });

  it('opens the preferences step from a [data-nowo-open-consent] trigger and ignores other clicks', async () => {
    document.body.innerHTML = `
      <a href="#" id="other">Other</a>
      <button type="button" data-nowo-open-consent><span id="inner">Cookie settings</span></button>
      <div id="cookieconsent" class="modal nowo-cookie-consent" data-nowo-open="false" aria-hidden="true">
        <div class="nowo-cookie-consent__step nowo-cookie-consent__step--banner nowo-cookie-consent__step--active" data-nowo-step="banner"></div>
        <div class="nowo-cookie-consent__step nowo-cookie-consent__step--preferences" data-nowo-step="preferences"></div>
      </div>
    `;

    await import('./cookie-consent');

    const modal = document.getElementById('cookieconsent');
    document.getElementById('other')?.click();
    expect(modal?.classList.contains('show')).toBe(false);

    document.getElementById('inner')?.click();
    expect(modal?.classList.contains('show')).toBe(true);
    expect(document.querySelector('[data-nowo-step="preferences"]')?.classList.contains('nowo-cookie-consent__step--active')).toBe(true);
  });

  it('releases the page interaction lock when Bootstrap reports the modal hidden', async () => {
    document.body.innerHTML = `
      <div id="cookieconsent" class="modal nowo-cookie-consent" data-nowo-open="true" data-nowo-disable-page-interaction="true" aria-hidden="true"></div>
    `;

    await import('./cookie-consent');

    expect(document.body.classList.contains('nowo-cookie-consent-page-blocked')).toBe(true);

    document.getElementById('cookieconsent')?.dispatchEvent(new Event('hidden.bs.modal'));

    expect(document.body.classList.contains('nowo-cookie-consent-page-blocked')).toBe(false);
  });

  it('applies the remote config before initialising when data-nowo-config-url is set', async () => {
    document.documentElement.lang = 'es';
    document.body.innerHTML = `
      <div id="cookieconsent" class="modal nowo-cookie-consent" data-nowo-open="true" data-nowo-config-url="/cookie-consent/config" aria-hidden="true">
        <h2 class="nowo-cookie-consent__title">Cookie settings</h2>
      </div>
    `;

    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => ({
        data: { language: { default: 'en', translations: { es: { consentModal: { title: 'Configuración' } } } } },
      }),
    });
    vi.stubGlobal('fetch', fetchMock);

    await import('./cookie-consent');
    await vi.waitFor(() => {
      expect(document.getElementById('cookieconsent')?.classList.contains('show')).toBe(true);
    });

    expect(fetchMock).toHaveBeenCalledWith('/cookie-consent/config', expect.objectContaining({ method: 'GET' }));
    expect(document.querySelector('.nowo-cookie-consent__title')?.textContent).toBe('Configuración');

    vi.unstubAllGlobals();
    document.documentElement.lang = '';
  });

  it('still initialises when the remote config request fails', async () => {
    document.body.innerHTML = `
      <div id="cookieconsent" class="modal nowo-cookie-consent" data-nowo-open="true" data-nowo-config-url="/cookie-consent/config" aria-hidden="true">
        <h2 class="nowo-cookie-consent__title">Cookie settings</h2>
      </div>
    `;

    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false, status: 503, json: async () => ({}) }));

    await import('./cookie-consent');
    await vi.waitFor(() => {
      expect(document.getElementById('cookieconsent')?.classList.contains('show')).toBe(true);
    });

    expect(document.querySelector('.nowo-cookie-consent__title')?.textContent).toBe('Cookie settings');

    vi.unstubAllGlobals();
  });

  it('keeps the original copy when the remote config has no data', async () => {
    document.body.innerHTML = `
      <div id="cookieconsent" class="modal nowo-cookie-consent" data-nowo-open="true" data-nowo-config-url="/cookie-consent/config" aria-hidden="true">
        <h2 class="nowo-cookie-consent__title">Cookie settings</h2>
      </div>
    `;

    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, status: 200, json: async () => ({}) }));

    await import('./cookie-consent');
    await vi.waitFor(() => {
      expect(document.getElementById('cookieconsent')?.classList.contains('show')).toBe(true);
    });

    expect(document.querySelector('.nowo-cookie-consent__title')?.textContent).toBe('Cookie settings');

    vi.unstubAllGlobals();
  });
});
