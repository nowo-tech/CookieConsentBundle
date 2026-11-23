/**
 * Collects and publishes lightweight client diagnostics for the consent modal.
 */
declare global {
  interface Window {
    __NOWO_COOKIE_CONSENT_DIAGNOSTIC__?: Record<string, unknown>;
  }
}

export function collectClientDiagnostics(modalElement: HTMLElement | null): Record<string, unknown> {
  return {
    modal_in_dom: modalElement !== null,
    data_nowo_open: modalElement?.dataset.nowoOpen ?? null,
    modal_has_show_class: modalElement?.classList.contains('show') ?? false,
    modal_display: modalElement ? window.getComputedStyle(modalElement).display : null,
    config_api_url: modalElement?.dataset.nowoConfigUrl ?? null,
    bootstrap_available:
      typeof window.bootstrap !== 'undefined' && typeof window.bootstrap.Modal !== 'undefined',
    manage_link_count: document.querySelectorAll('[data-nowo-open-consent]').length,
    form_in_dom: document.querySelector('.nowo-cookie-consent__form') !== null,
  };
}

/**
 * True when the console diagnostics are wanted: `data-nowo-debug="true"` on the modal element, or
 * `<html data-nowo-console="debug">` (the page-wide switch of the "script loaded" console line).
 */
export function isDiagnosticsDebug(modalElement: HTMLElement | null): boolean {
  return modalElement?.dataset.nowoDebug === 'true' || document.documentElement.dataset.nowoConsole === 'debug';
}

/**
 * Stores the diagnostic report on `window.__NOWO_COOKIE_CONSENT_DIAGNOSTIC__` and prints it to the console only when
 * the debug flag is on (see {@link isDiagnosticsDebug}); production pages stay quiet by default.
 */
export function publishClientDiagnostics(
  modalElement: HTMLElement | null,
  debug: boolean = isDiagnosticsDebug(modalElement),
): void {
  const existing = window.__NOWO_COOKIE_CONSENT_DIAGNOSTIC__ ?? {};
  const report = {
    ...existing,
    client_after_init: collectClientDiagnostics(modalElement),
  };

  window.__NOWO_COOKIE_CONSENT_DIAGNOSTIC__ = report;

  if (!debug) {
    return;
  }

  console.group('[nowo-cookie-consent] Diagnostic report (after init)');
  console.log(report);
  console.log('Copy JSON:\n' + JSON.stringify(report, null, 2));
  console.groupEnd();
}
