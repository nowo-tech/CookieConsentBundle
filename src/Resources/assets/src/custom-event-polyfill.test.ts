import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import { installCustomEventPolyfill } from './custom-event-polyfill';

describe('installCustomEventPolyfill', () => {
  const originalCustomEvent = window.CustomEvent;

  beforeEach(() => {
    window.CustomEvent = originalCustomEvent;
  });

  afterEach(() => {
    window.CustomEvent = originalCustomEvent;
  });

  it('does nothing when CustomEvent is already available', () => {
    const before = window.CustomEvent;

    installCustomEventPolyfill();

    expect(window.CustomEvent).toBe(before);
  });

  it('installs a working CustomEvent constructor when missing', () => {
    Object.defineProperty(window, 'CustomEvent', {
      configurable: true,
      writable: true,
      value: undefined,
    });

    installCustomEventPolyfill();

    const event = new window.CustomEvent('nowo-cookie-consent-form-submit-successful', {
      detail: { saved: true },
    });

    expect(event.type).toBe('nowo-cookie-consent-form-submit-successful');
    expect(event.detail).toEqual({ saved: true });
  });
});
