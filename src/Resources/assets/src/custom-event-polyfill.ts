/**
 * Installs a CustomEvent polyfill when the runtime does not expose a proper constructor.
 */
export function installCustomEventPolyfill(): void {
  if (typeof window.CustomEvent === 'function') {
    return;
  }

  function CustomEventPolyfill(
    event: string,
    params?: CustomEventInit,
  ): CustomEvent {
    const eventParams = params ?? { bubbles: false, cancelable: false, detail: undefined };
    const customEvent = document.createEvent('CustomEvent');

    customEvent.initCustomEvent(
      event,
      eventParams.bubbles ?? false,
      eventParams.cancelable ?? false,
      eventParams.detail,
    );

    return customEvent;
  }

  CustomEventPolyfill.prototype = window.Event.prototype;
  window.CustomEvent = CustomEventPolyfill as typeof CustomEvent;
}
