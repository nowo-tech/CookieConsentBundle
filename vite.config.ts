import { defineConfig } from 'vite';

export default defineConfig({
  define: {
    __COOKIE_CONSENT_BUILD_TIME__: JSON.stringify(new Date().toISOString()),
  },
  build: {
    outDir: 'src/Resources/public',
    emptyOutDir: false,
    rollupOptions: {
      input: 'src/Resources/assets/src/cookie-consent.ts',
      output: {
        format: 'iife',
        entryFileNames: 'nowo-consent-modal.js',
        assetFileNames: 'nowo-consent-modal.[ext]',
      },
    },
    minify: true,
    sourcemap: false,
  },
});
