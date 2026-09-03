import { expect, test } from '@playwright/test';

import { collectPageErrors, signIn } from './support/session';

/**
 * datatables.net-react mounts every cell slot in its own detached React root,
 * outside the Inertia provider. Any component that calls `usePage()` there
 * throws, React unmounts that root, and the cell renders blank — while the
 * route still returns 200 and the JSON feed stays correct. Only a browser sees
 * it, so the interactive columns are asserted here.
 */
const setLocale = (page: import('@playwright/test').Page, locale: string) =>
  page.evaluate(async (loc) => {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    await fetch('/locale', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, Accept: 'application/json' },
      body: JSON.stringify({ locale: loc }),
    });
  }, locale);

test.describe('Statement mappings grid', () => {
  test('renders interactive cells without leaving the Inertia context', async ({ page }) => {
    const errors = collectPageErrors(page);

    await signIn(page);
    await page.goto('/accounting/statement-mappings', { waitUntil: 'networkidle' });

    const table = page.locator('#statement-mappings-table');
    await expect(table).toBeVisible();

    const row = table.locator('tbody tr').first();
    await expect(row.locator('td').first()).not.toHaveText(/Loading|جارٍ/i, { timeout: 20_000 });

    const cells = await row.locator('td').allInnerTexts();
    test.skip(cells.length < 8, 'Chart of accounts not seeded in this environment.');

    // The two right-hand columns hold selects; a broken slot leaves them blank.
    expect(cells[6].trim(), 'cash flow activity cell must render its control').not.toBe('');
    expect(cells[7].trim(), 'assign cell must render its control').not.toBe('');

    // The assign select must open and be populated from the page's closure.
    await row.locator('td').last().locator('button[type="button"]').first().click();
    const options = page.locator('button[type="button"]:visible').filter({ hasText: /\((BS|IS)\)/ });
    await expect(options.first()).toBeVisible({ timeout: 5_000 });
    expect(await options.count()).toBeGreaterThan(0);
    await page.keyboard.press('Escape');

    errors.assertClean();
  });

  test('translates the grid chrome in Arabic', async ({ page }) => {
    await signIn(page);
    await page.goto('/accounting/statement-mappings', { waitUntil: 'networkidle' });
    await setLocale(page, 'ar');

    try {
      await page.goto('/accounting/statement-mappings', { waitUntil: 'networkidle' });
      await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');

      // Filter labels come from the dictionary, so none may leak English.
      const toolbar = page.locator('.sdt-top-bar');
      await expect(toolbar).toBeVisible();
      const text = await toolbar.innerText();
      expect(text).not.toContain('Statement Type');
      expect(text).not.toContain('Mapping Status');
      await expect(page.locator('body')).not.toContainText('[object Object]');
    } finally {
      await setLocale(page, 'en');
    }
  });
});
