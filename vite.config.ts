import { createHash } from 'node:crypto';
import { copyFileSync, readdirSync, readFileSync, statSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { defineConfig } from 'vite';

const cssSource = resolve(__dirname, 'src/Resources/assets/src/cookie-consent.css');
const cssPublic = resolve(__dirname, 'src/Resources/public/nowo-cookie-consent.css');

/**
 * Deterministic build id: a hash of the TypeScript/CSS sources and pnpm-lock.yaml. It only changes when the code or
 * the dependencies change, so rebuilding unchanged sources (e.g. in `make release-check`) leaves the committed bundle
 * byte-identical (the former wall-clock stamp rewrote it on every build).
 */
function sourceBuildId(dir: string): string {
  const hash = createHash('sha256');
  const files = (readdirSync(dir, { recursive: true }) as string[])
    .filter((file) => !file.includes('.test.') && statSync(join(dir, file)).isFile())
    .sort();
  for (const file of files) {
    hash.update(file).update(readFileSync(join(dir, file)));
  }
  hash.update(readFileSync(join(__dirname, 'pnpm-lock.yaml')));

  return hash.digest('hex').slice(0, 12);
}

export default defineConfig({
  define: {
    __COOKIE_CONSENT_BUILD_TIME__: JSON.stringify(sourceBuildId(resolve(__dirname, 'src/Resources/assets/src'))),
  },
  build: {
    outDir: 'src/Resources/public',
    emptyOutDir: false,
    rollupOptions: {
      input: resolve(__dirname, 'src/Resources/assets/src/cookie-consent.ts'),
      output: {
        format: 'iife',
        entryFileNames: 'nowo-consent-modal.js',
        assetFileNames: 'nowo-consent-modal.[ext]',
      },
    },
    minify: true,
    sourcemap: false,
  },
  plugins: [
    {
      name: 'emit-standalone-cookie-consent-css',
      closeBundle() {
        // Ship a linkable stylesheet for CSP style-src nonces (JS inject is skipped when linked).
        copyFileSync(cssSource, cssPublic);
      },
    },
  ],
});
