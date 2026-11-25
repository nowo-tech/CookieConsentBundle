import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
  clearBundleLoggerForTest,
  clearScriptLoadedForTest,
  createBundleLogger,
  getLogger,
  setBundleLogger,
} from './logger';

describe('logger', () => {
  beforeEach(() => {
    clearScriptLoadedForTest();
    vi.spyOn(console, 'log').mockImplementation(() => {});
    vi.spyOn(console, 'debug').mockImplementation(() => {});
    vi.spyOn(console, 'info').mockImplementation(() => {});
    vi.spyOn(console, 'warn').mockImplementation(() => {});
    vi.spyOn(console, 'error').mockImplementation(() => {});
  });

  afterEach(() => {
    vi.restoreAllMocks();
    clearBundleLoggerForTest();
  });

  it('scriptLoaded logs build time when provided', () => {
    const log = createBundleLogger('cookie-consent', {
      buildTime: '2026-04-14T12:42:34.906Z',
    });
    log.scriptLoaded();
    expect(console.log).toHaveBeenCalledWith(
      expect.stringContaining('[cookie-consent] script loaded'),
      expect.any(String),
      'color:#059669',
    );
  });

  it('alwaysLog enables info output', () => {
    const log = createBundleLogger('cookie-consent', { alwaysLog: true });
    log.info('Cookie Consent ready');
    expect(console.info).toHaveBeenCalledWith(
      expect.stringContaining('[cookie-consent]'),
      expect.any(String),
      'Cookie Consent ready',
    );
  });

  it('getLogger returns no-op logger when unset', () => {
    clearBundleLoggerForTest();
    getLogger().info('silent');
    expect(console.info).not.toHaveBeenCalled();
  });

  it('setBundleLogger registers the shared logger', () => {
    const log = createBundleLogger('cookie-consent', { alwaysLog: true });
    setBundleLogger(log);
    getLogger().info('shared');
    expect(console.info).toHaveBeenCalled();
  });

  it('prints nothing but the script-loaded line by default', () => {
    const log = createBundleLogger('cookie-consent');
    log.debug('d');
    log.info('Cookie Consent init');
    log.warn('w');
    log.error('e');
    expect(console.debug).not.toHaveBeenCalled();
    expect(console.info).not.toHaveBeenCalled();
    expect(console.warn).not.toHaveBeenCalled();
    expect(console.error).not.toHaveBeenCalled();
  });

  it('setDebug turns the level output on and off', () => {
    const log = createBundleLogger('cookie-consent');
    log.setDebug(true);
    log.info('on');
    log.setDebug(false);
    log.info('off');
    expect(console.info).toHaveBeenCalledTimes(1);
  });

  it('the debug flag never prints in quiet mode', () => {
    document.documentElement.dataset.nowoConsole = 'quiet';
    const log = createBundleLogger('cookie-consent', { alwaysLog: true });
    log.info('silent');
    log.warn('silent');
    delete document.documentElement.dataset.nowoConsole;
    expect(console.info).not.toHaveBeenCalled();
    expect(console.warn).not.toHaveBeenCalled();
  });
});
