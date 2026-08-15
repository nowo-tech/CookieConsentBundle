import { copyFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { defineConfig } from 'vite';

const cssSource = resolve(__dirname, 'src/Resources/assets/src/cookie-consent.css');
const cssPublic = resolve(__dirname, 'src/Resources/public/nowo-cookie-consent.css');

export default defineConfig({
  define: {
    __COOKIE_CONSENT_BUILD_TIME__: JSON.stringify(new Date().toISOString()),
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
