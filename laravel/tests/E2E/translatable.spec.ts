import { expect, test } from '@playwright/test';

import { collectPageErrors, signIn } from './support/session';

/**
 * Pages that display Spatie Translatable fields.
 *
 * Rendering a `{en, ar}` object as a JSX child throws React error #31, which
 * unmounts the tree and leaves a blank page — while the route still returns 200.
 * Server-side tests cannot see that; only a browser can.
 *
 * These pages must hold data for the check to mean anything: an empty table
 * never renders the offending value. Row counts are asserted before the page is
 * judged clean.
 */

const pages: Array<{ path: string; name: string; entity: string }> = [
  { path: '/catalog/uoms', name: 'Units of measure', entity: 'unit_of_measure' },
  { path: '/cash-accounts', name: 'Cash accounts', entity: 'cash_account' },
  { path: '/bank-accounts', name: 'Bank accounts', entity: 'bank_account' },
  { path: '/customer-receipts', name: 'Customer receipts', entity: 'customer_receipt' },
  { path: '/supplier-payments', name: 'Supplier payments', entity: 'supplier_payment' },
  { path: '/incoming-cheques', name: 'Incoming cheques', entity: 'incoming_cheque' },
  { path: '/outgoing-cheques', name: 'Outgoing cheques', entity: 'outgoing_cheque' },
  { path: '/customer-opening-balances', name: 'Customer opening balances', entity: 'customer_opening_balance' },
  { path: '/supplier-opening-balances', name: 'Supplier opening balances', entity: 'supplier_opening_balance' },
  { path: '/receivable-allocations', name: 'Receivable allocations', entity: 'receivable_allocation' },
  { path: '/payable-allocations', name: 'Payable allocations', entity: 'payable_allocation' },
  { path: '/sales/receivable-settlements', name: 'Receivable settlements', entity: 'receivable_settlement' },
  { path: '/purchasing/payable-settlements', name: 'Payable settlements', entity: 'payable_settlement' },
  { path: '/treasury-transfers', name: 'Treasury transfers', entity: 'treasury_transfer' },
];

test.describe('Translatable field rendering', () => {
  test.beforeEach(async ({ page }) => {
    await signIn(page);
  });

  for (const { path, name } of pages) {
    test(`${name} (${path}) renders translated values without crashing`, async ({ page }) => {
      const errors = collectPageErrors(page);

      const response = await page.goto(path, { waitUntil: 'networkidle' });
      expect(response?.status(), `${path} should not error`).toBeLessThan(400);

      // React error #31 unmounts everything, so the root goes empty. Wait for the
      // app shell to mount first, otherwise this races the initial render.
      await expect(page.locator('#app > *').first()).toBeAttached({ timeout: 20_000 });
      await expect(page.locator('#app')).not.toBeEmpty();

      // The raw object would surface as "[object Object]" wherever a translated
      // value was interpolated into a string rather than rendered as a child.
      await expect(page.locator('body')).not.toContainText('[object Object]');

      errors.assertClean();
    });
  }
});

test.describe('Arabic locale', () => {
  test('translated master data switches language with the locale', async ({ page }) => {
    await signIn(page);

    await page.goto('/catalog/uoms', { waitUntil: 'networkidle' });
    await expect(page.locator('#app > *').first()).toBeAttached({ timeout: 20_000 });

    const englishBody = await page.locator('table').first().innerText();

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

    await page.goto('/catalog/uoms', { waitUntil: 'networkidle' });
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(page.locator('#app > *').first()).toBeAttached({ timeout: 20_000 });
    await expect(page.locator('body')).not.toContainText('[object Object]');

    const arabicBody = await page.locator('table').first().innerText();

    // The point of the helper: the same records read differently per locale.
    // If the table were rendering raw objects or ignoring locale, these match.
    expect(arabicBody, 'Arabic table should not be identical to English').not.toBe(englishBody);

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
