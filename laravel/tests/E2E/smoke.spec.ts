import { expect, test } from '@playwright/test';

import { collectPageErrors, signIn } from './support/session';

/**
 * Browser smoke coverage.
 *
 * These assert what server-side tests structurally cannot: that pages actually
 * render in a real browser, that the JS bundle loads, and that navigation works.
 * Business rules, authorization logic, and financial arithmetic stay covered by
 * the PHPUnit suite — they are not duplicated here.
 */

test.describe('Authentication', () => {
  test('unauthenticated visitors are redirected to the sign-in page', async ({ page }) => {
    await page.goto('/dashboard');

    await expect(page).toHaveURL(/\/login/);
    await expect(page.locator('input[name="email"]')).toBeVisible();
    await expect(page.locator('input[name="password"]')).toBeVisible();
  });

  test('invalid credentials are rejected and do not create a session', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'definitely-not-a-user@mini-erp.test');
    await page.fill('input[name="password"]', 'WrongPassword-000000');
    await page.click('button[type="submit"]');

    await expect(page).toHaveURL(/\/login/);

    // The session must not exist: a direct dashboard hit still bounces to login.
    await page.goto('/dashboard');
    await expect(page).toHaveURL(/\/login/);
  });

  test('valid credentials sign in and land on the dashboard', async ({ page }) => {
    const errors = collectPageErrors(page);

    await signIn(page);

    await expect(page).toHaveURL(/\/dashboard/);

    // The Inertia root must actually mount; an empty #app means the bundle failed.
    await expect(page.locator('#app')).not.toBeEmpty();

    // The session is real: a protected page no longer bounces to /login.
    await page.goto('/accounting');
    await expect(page).toHaveURL(/\/accounting/);

    errors.assertClean();
  });
});

test.describe('Core pages render for an authorized user', () => {
  test.beforeEach(async ({ page }) => {
    await signIn(page);
  });

  // One entry per major module. If a page throws in React or ships a broken
  // bundle, it fails here even though the server returned 200.
  const pages: Array<{ path: string; name: string }> = [
    { path: '/dashboard', name: 'Dashboard' },
    { path: '/accounting', name: 'Accounting hub' },
    { path: '/accounting/coa', name: 'Chart of accounts' },
    { path: '/accounting/journal', name: 'Journals' },
    { path: '/accounting/periods', name: 'Fiscal periods' },
    { path: '/reports', name: 'Reports hub' },
    { path: '/accounting/trial-balance', name: 'Trial balance' },
    { path: '/reports/balance-sheet', name: 'Balance sheet' },
    { path: '/reports/income-statement', name: 'Income statement' },
    { path: '/customers', name: 'Customers' },
    { path: '/suppliers', name: 'Suppliers' },
    { path: '/catalog/products', name: 'Products' },
    { path: '/settings', name: 'Settings hub' },
  ];

  for (const { path, name } of pages) {
    test(`${name} (${path}) renders without browser errors`, async ({ page }) => {
      const errors = collectPageErrors(page);

      const response = await page.goto(path);

      expect(response?.status(), `${path} should not return an error status`).toBeLessThan(400);
      await expect(page).toHaveURL(new RegExp(path.replace(/\//g, '\\/')));

      // Inertia mounts into #app; an empty root means the bundle failed.
      await expect(page.locator('#app')).not.toBeEmpty();

      errors.assertClean();
    });
  }
});

test.describe('Server-side report pagination', () => {
  test.beforeEach(async ({ page }) => {
    await signIn(page);
  });

  // Phase 21 moved these reports onto Yajra DataTables. A broken endpoint or a
  // mis-wired column definition shows up as an empty or erroring table here.
  const dataTableReports: Array<{ path: string; tableId: string; name: string }> = [
    { path: '/reports/ar-aging', tableId: 'ar-aging-data-table', name: 'AR aging' },
    { path: '/reports/ap-aging', tableId: 'ap-aging-data-table', name: 'AP aging' },
    { path: '/reports/cheque-register', tableId: 'cheque-register-data-table', name: 'Cheque register' },
    { path: '/reports/vat-register', tableId: 'vat-register-data-table', name: 'VAT register' },
    { path: '/reports/rentals', tableId: 'rental-operations-data-table', name: 'Rental operations' },
  ];

  for (const { path, tableId, name } of dataTableReports) {
    test(`${name} loads its DataTable and reports a row count`, async ({ page }) => {
      const errors = collectPageErrors(page);

      await page.goto(path);

      const table = page.locator(`#${tableId}`);
      await expect(table, `${name} table should be present`).toBeVisible({ timeout: 20_000 });

      // Wait for the AJAX round-trip to settle. DataTables removes the
      // "processing" indicator once the endpoint has answered.
      await expect(page.locator(`#${tableId}_processing`)).toBeHidden({ timeout: 20_000 });

      // The table must be initialised with the expected column set. This proves
      // the endpoint answered and the column definitions matched, and it holds
      // whether or not the environment happens to contain rows.
      const headerCount = await page.locator(`#${tableId} thead th`).count();
      expect(headerCount, `${name} should render its columns`).toBeGreaterThan(0);

      // Either real rows, or DataTables' own empty-state row. A silent failure
      // renders neither.
      const bodyRows = await page.locator(`#${tableId} tbody tr`).count();
      expect(bodyRows, `${name} should render rows or an empty state`).toBeGreaterThan(0);

      errors.assertClean();
    });
  }
});

test.describe('Localization', () => {
  test('switching to Arabic flips the document direction to RTL', async ({ page }) => {
    await signIn(page);
    await page.goto('/dashboard');

    await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');

    // The locale switch posts to /locale; drive it the way the app does.
    await page.evaluate(async () => {
      const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
      await fetch('/locale', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': token,
          Accept: 'application/json',
        },
        body: JSON.stringify({ locale: 'ar' }),
      });
    });

    await page.goto('/dashboard');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(page.locator('html')).toHaveAttribute('lang', 'ar');

    // Restore English so test order stays irrelevant.
    await page.evaluate(async () => {
      const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
      await fetch('/locale', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': token,
          Accept: 'application/json',
        },
        body: JSON.stringify({ locale: 'en' }),
      });
    });
  });
});
