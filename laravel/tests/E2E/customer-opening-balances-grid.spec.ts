import { expect, test } from '@playwright/test';

import { collectPageErrors, signIn } from './support/session';

/**
 * The grid is server-side: every cell is painted by a datatables.net-react
 * "slot". Slots are targeted by the column's `name`, not its `data`, so a
 * mismatch silently falls back to the raw feed value — money as minor units,
 * enums as raw strings, and the action column as a bare UUID. Server-side tests
 * cannot see that, because the JSON they assert on is correct either way.
 */
test.describe('Customer opening balances grid', () => {
  test('paints every column through its slot', async ({ page }) => {
    const errors = collectPageErrors(page);

    await signIn(page);
    await page.goto('/customer-opening-balances', { waitUntil: 'networkidle' });

    const table = page.locator('#customer-opening-balances-table');
    await expect(table).toBeVisible();

    // DataTable chrome replaced the old hand-rolled table.
    await expect(page.locator('input[type="search"]')).toBeVisible();

    const firstRow = table.locator('tbody tr').first();
    await expect(firstRow.locator('td').first()).not.toHaveText(/Loading|جارٍ/i, { timeout: 20_000 });

    const texts = await firstRow.locator('td').allInnerTexts();
    test.skip(texts.length < 7, 'No opening balances seeded in this environment.');

    const [customer, , reference, , amount, status, actions] = texts;

    expect(customer, 'customer name must render as text, not a translations object').not.toContain('[object Object]');
    expect(amount, 'amount must be formatted money, not raw minor units').toMatch(/[.,]\d{2}\b/);
    expect(reference.trim(), 'missing reference must use the canonical label').not.toBe('');
    expect(actions, 'action column must not leak the row id').not.toMatch(/[0-9a-f]{8}-[0-9a-f]{4}-/i);
    expect(['posted', 'draft'], 'status must render as a badge, not the raw enum').not.toContain(status.trim());

    errors.assertClean();
  });
});
