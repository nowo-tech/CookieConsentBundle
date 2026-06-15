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
    modal_display: modalElement?.style.display ?? null,
    config_api_url: modalElement?.dataset.nowoConfigUrl ?? null,
    bootstrap_available:
      typeof window.bootstrap !== 'undefined' && typeof window.bootstrap.Modal !== 'undefined',
    manage_link_count: document.querySelectorAll('[data-nowo-open-consent]').length,
    form_in_dom: document.querySelector('.nowo-cookie-consent__form') !== null,
  };
}

export function publishClientDiagnostics(modalElement: HTMLElement | null): void {
  const existing = window.__NOWO_COOKIE_CONSENT_DIAGNOSTIC__ ?? {};
  const report = {
    ...existing,
    client_after_init: collectClientDiagnostics(modalElement),
  };

  window.__NOWO_COOKIE_CONSENT_DIAGNOSTIC__ = report;

  console.group('[nowo-cookie-consent] Diagnostic report (after init)');
  console.log(report);
  console.log('Copy JSON:\n' + JSON.stringify(report, null, 2));
  console.groupEnd();
}
