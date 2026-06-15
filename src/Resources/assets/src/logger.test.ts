import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
  clearBundleLoggerForTest,
  createBundleLogger,
  getLogger,
  setBundleLogger,
} from './logger';

describe('logger', () => {
  beforeEach(() => {
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
});
