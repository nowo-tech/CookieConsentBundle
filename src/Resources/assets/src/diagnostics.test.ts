import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { collectClientDiagnostics, isDiagnosticsDebug, publishClientDiagnostics } from './diagnostics';

describe('client diagnostics', () => {
  let group: ReturnType<typeof vi.spyOn>;
  let log: ReturnType<typeof vi.spyOn>;
  let groupEnd: ReturnType<typeof vi.spyOn>;

  beforeEach(() => {
    group = vi.spyOn(console, 'group').mockImplementation(() => undefined);
    log = vi.spyOn(console, 'log').mockImplementation(() => undefined);
    groupEnd = vi.spyOn(console, 'groupEnd').mockImplementation(() => undefined);
    delete window.__NOWO_COOKIE_CONSENT_DIAGNOSTIC__;
  });

  afterEach(() => {
    vi.restoreAllMocks();
    delete document.documentElement.dataset.nowoConsole;
    document.body.innerHTML = '';
  });

  it('collects the modal state', () => {
    document.body.innerHTML = '<div id="m" class="show" data-nowo-open="true" data-nowo-config-url="/c"></div>';
    const report = collectClientDiagnostics(document.getElementById('m'));

    expect(report).toMatchObject({ modal_in_dom: true, data_nowo_open: 'true', modal_has_show_class: true, config_api_url: '/c' });
    expect(collectClientDiagnostics(null)).toMatchObject({ modal_in_dom: false, modal_display: null });
  });

  it('prints nothing to the console by default, but still stores the report', () => {
    publishClientDiagnostics(null);

    expect(group).not.toHaveBeenCalled();
    expect(log).not.toHaveBeenCalled();
    expect(groupEnd).not.toHaveBeenCalled();
    expect(window.__NOWO_COOKIE_CONSENT_DIAGNOSTIC__).toHaveProperty('client_after_init');
  });

  it('prints the report group when the modal has data-nowo-debug="true"', () => {
    document.body.innerHTML = '<div id="m" data-nowo-debug="true"></div>';
    const modal = document.getElementById('m');

    expect(isDiagnosticsDebug(modal)).toBe(true);
    publishClientDiagnostics(modal);

    expect(group).toHaveBeenCalledWith('[nowo-cookie-consent] Diagnostic report (after init)');
    expect(log).toHaveBeenCalledTimes(2);
    expect(groupEnd).toHaveBeenCalledTimes(1);
  });

  it('prints the report group with <html data-nowo-console="debug">', () => {
    document.documentElement.dataset.nowoConsole = 'debug';

    expect(isDiagnosticsDebug(null)).toBe(true);
    publishClientDiagnostics(null);

    expect(group).toHaveBeenCalledTimes(1);
  });

  it('stays quiet when debug is not "true" / "debug"', () => {
    document.body.innerHTML = '<div id="m" data-nowo-debug="false"></div>';
    document.documentElement.dataset.nowoConsole = 'quiet';

    expect(isDiagnosticsDebug(document.getElementById('m'))).toBe(false);
  });

  it('an explicit debug argument wins', () => {
    publishClientDiagnostics(null, true);
    expect(group).toHaveBeenCalledTimes(1);
  });
});
