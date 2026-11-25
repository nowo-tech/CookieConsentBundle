/**
 * Bundle logger for Cookie Consent (same API as Twig Inspector): one "script loaded" line, every other line behind the
 * debug flag (REQ-ASSETS-005 rule 5).
 */

export type BundleLoggerOptions = {
  buildTime?: string;
  alwaysLog?: boolean;
};

export type BundleLogger = {
  scriptLoaded: () => void;
  setDebug: (enabled: boolean) => void;
  debug: (...args: unknown[]) => void;
  info: (...args: unknown[]) => void;
  warn: (...args: unknown[]) => void;
  error: (...args: unknown[]) => void;
};

/** True when the page asked for a silent console (`<html data-nowo-console="quiet">` or `globalThis.NOWO_CONSOLE = 'quiet'`). */
function isQuiet(): boolean {
  try {
    if ((globalThis as { NOWO_CONSOLE?: unknown }).NOWO_CONSOLE === 'quiet') return true;
    return typeof document !== 'undefined' && document.documentElement.dataset.nowoConsole === 'quiet';
  } catch {
    return false;
  }
}

/** Key on `globalThis` of the names whose "script loaded" line this page already printed (REQ-ASSETS-005 once-guard). */
const SCRIPT_LOADED_KEY = '__nowoScriptLoaded';

/**
 * Remembers `name` for this page and tells whether its line was already printed: a script loaded twice (Turbo, a
 * fragment, two copies of the asset) logs one line.
 */
function alreadyPrinted(name: string): boolean {
  const registry = globalThis as { [SCRIPT_LOADED_KEY]?: unknown };
  let printed = registry[SCRIPT_LOADED_KEY];
  if (!(printed instanceof Set)) {
    printed = new Set<string>();
    registry[SCRIPT_LOADED_KEY] = printed;
  }
  const names = printed as Set<string>;
  if (names.has(name)) return true;
  names.add(name);
  return false;
}

/** Forgets the printed "script loaded" names (tests only). */
export function clearScriptLoadedForTest(): void {
  delete (globalThis as { [SCRIPT_LOADED_KEY]?: unknown })[SCRIPT_LOADED_KEY];
}

const STYLES = {
  script: 'color:#0ea5e9;font-weight:bold',
  debug: 'color:#6b7280',
  info: 'color:#2563eb',
  warn: 'color:#d97706',
  error: 'color:#dc2626;font-weight:bold',
} as const;

const EMOJI = {
  script: '📦',
  debug: '🔍',
  info: 'ℹ️',
  warn: '⚠️',
  error: '❌',
} as const;

function formatArgs(args: unknown[]): unknown[] {
  return args.map((a) =>
    typeof a === 'object' && a !== null && !(a instanceof Error) ? JSON.stringify(a) : a,
  );
}

type ConsoleLevel = 'debug' | 'info' | 'warn' | 'error';

function logScriptLoaded(prefix: string, buildTime: string | undefined): void {
  if (buildTime !== undefined && buildTime !== '') {
    console.log(
      `%c${EMOJI.script} ${prefix} script loaded, build: %c${buildTime}`,
      STYLES.script,
      'color:#059669',
    );
    return;
  }
  console.log(`%c${EMOJI.script} ${prefix} script loaded`, STYLES.script);
}

function emitLevelLog(level: ConsoleLevel, prefix: string, args: unknown[]): void {
  const emoji = EMOJI[level];
  const style = STYLES[level];
  const label = `%c${emoji} ${prefix}`;
  const logFn = console[level] as (...fnArgs: unknown[]) => void;
  if (args.length > 0) {
    logFn(label, style, ...formatArgs(args));
    return;
  }
  logFn(label, style);
}

function makeLevelMethod(
  isEnabled: () => boolean,
  prefix: string,
  level: ConsoleLevel,
): (...args: unknown[]) => void {
  return (...args: unknown[]): void => {
    if (!isEnabled() || isQuiet()) {
      return;
    }
    emitLevelLog(level, prefix, args);
  };
}

function noop(): void {}

let instance: BundleLogger | null = null;

export function setBundleLogger(log: BundleLogger): void {
  instance = log;
}

export function clearBundleLoggerForTest(): void {
  instance = null;
}

export function getLogger(): BundleLogger {
  if (instance !== null) {
    return instance;
  }
  return {
    scriptLoaded: noop,
    setDebug: noop,
    debug: noop,
    info: noop,
    warn: noop,
    error: noop,
  };
}

/**
 * Creates the bundle logger. Only {@link BundleLogger.scriptLoaded} prints by default (REQ-ASSETS-005); debug / info /
 * warn / error print only while the debug flag is on (`alwaysLog`, or {@link BundleLogger.setDebug}(true)) and the
 * console is not quiet.
 */
export function createBundleLogger(name: string, options: BundleLoggerOptions = {}): BundleLogger {
  const prefix = `[${name}]`;
  const { buildTime, alwaysLog = false } = options;
  let debugEnabled = alwaysLog === true;
  const isEnabled = (): boolean => debugEnabled;

  return {
    scriptLoaded(): void {
      if (isQuiet() || alreadyPrinted(name)) return;
      logScriptLoaded(prefix, buildTime);
    },

    setDebug(enabled: boolean): void {
      debugEnabled = enabled;
    },

    debug: makeLevelMethod(isEnabled, prefix, 'debug'),
    info: makeLevelMethod(isEnabled, prefix, 'info'),
    warn: makeLevelMethod(isEnabled, prefix, 'warn'),
    error: makeLevelMethod(isEnabled, prefix, 'error'),
  };
}
