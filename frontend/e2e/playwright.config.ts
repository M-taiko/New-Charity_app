import { defineConfig } from '@playwright/test';

// E2E smoke flows (plan 24.1) against the live PHP server + packed SPA.
export default defineConfig({
  testDir: '.',
  timeout: 60_000,
  fullyParallel: false,
  workers: 1,
  retries: 0,
  use: {
    baseURL: 'http://127.0.0.1:8000',
    headless: true,
    viewport: { width: 1366, height: 768 },
    locale: 'ar-EG',
  },
});
