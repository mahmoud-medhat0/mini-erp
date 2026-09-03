import { defineConfig, devices } from '@playwright/test';

/**
 * Browser smoke coverage for the Laravel + Inertia ERP.
 *
 * Scope is deliberately narrow: sign-in, navigation, permission boundaries, and
 * that key accountant pages actually render in a real browser. The PHPUnit suite
 * already asserts business rules, authorization, and financial arithmetic — these
 * tests exist to catch what server-side tests structurally cannot, such as a page
 * that 200s but throws in React, or a broken asset bundle.
 *
 * The app server is expected to be running already (webServer below starts one
 * when PLAYWRIGHT_SKIP_WEBSERVER is unset).
 */

const baseURL = process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8000';

export default defineConfig({
  testDir: './tests/E2E',
  outputDir: './storage/e2e/test-results',

  // A failing smoke test should be loud, not flaky-retried into a green build.
  retries: process.env.CI ? 1 : 0,
  workers: 1,
  fullyParallel: false,

  // The dev server is a single-process `artisan serve`; heavier pages under a
  // full-suite run legitimately take longer than a solo load.
  timeout: 90_000,
  expect: { timeout: 20_000 },

  reporter: process.env.CI
    ? [['list'], ['html', { outputFolder: './storage/e2e/report', open: 'never' }]]
    : [['list']],

  use: {
    baseURL,
    locale: 'en-US',
    timezoneId: 'Africa/Cairo',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'off',
  },

  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],

  webServer: process.env.PLAYWRIGHT_SKIP_WEBSERVER
    ? undefined
    : {
        command: 'php artisan serve --host=127.0.0.1 --port=8000',
        url: baseURL,
        reuseExistingServer: !process.env.CI,
        timeout: 120_000,
      },
});
