import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './tests/e2e',
  timeout: 30_000,
  expect: { timeout: 10_000 },
  use: {
    baseURL: process.env.E2E_BASE_URL ?? 'http://127.0.0.1:3000',
    trace: 'on-first-retry',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
  webServer: process.env.E2E_BASE_URL
    ? undefined
    : {
        command: 'npm run dev -- --hostname 127.0.0.1 --port 3000',
        env: { ...process.env, AUTH_SECRET: process.env.AUTH_SECRET ?? 'playwright-local-secret' },
        url: 'http://127.0.0.1:3000/en',
        reuseExistingServer: !process.env.CI,
        timeout: 120_000,
      },
});
