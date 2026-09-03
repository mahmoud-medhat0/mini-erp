import { expect, test } from '@playwright/test';

import { collectPageErrors, signIn } from './support/session';

/**
 * ServerDataTable must keep header and body columns aligned.
 *
 * DataTables' `scrollX` clones the header into a second table and pins it to a
 * pixel width measured at draw time. Slot cells are painted by React into their
 * own roots *after* that measurement, so the body outgrows the frozen header
 * and every column drifts — by up to 271px on the widest grid. The fix is a
 * single table inside a horizontally scrollable wrapper: one colgroup, so the
 * two halves cannot disagree. This asserts that invariant directly.
 */
const grids = [
  { path: '/accounting/statement-mappings', id: 'statement-mappings-table' },
  { path: '/customer-opening-balances', id: 'customer-opening-balances-table' },
  { path: '/customers', id: 'customers-data-table' },
  { path: '/accounting/account-mappings', id: 'account-mappings-table' },
];

const setLocale = (page: import('@playwright/test').Page, locale: string) =>
  page.evaluate(async (loc) => {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    await fetch('/locale', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, Accept: 'application/json' },
      body: JSON.stringify({ locale: loc }),
    });
  }, locale);

async function measure(page: import('@playwright/test').Page, tableId: string) {
  return page.evaluate((id) => {
    const table = document.getElementById(id) as HTMLTableElement | null;
    if (!table) {
      return null;
    }

    const box = (sel: string) =>
      Array.from(table.querySelectorAll(sel)).map((cell) => {
        const rect = (cell as HTMLElement).getBoundingClientRect();
        return { width: Math.round(rect.width), left: Math.round(rect.left) };
      });

    const scroller = table.closest('.sdt-scroll') as HTMLElement | null;

    return {
      // A header clone means scrollX is back and the drift can return.
      hasHeaderClone: !!table.closest('.dt-container')?.querySelector('.dt-scroll-headInner'),
      head: box('thead th'),
      body: box('tbody tr:first-child td'),
      rows: table.querySelectorAll('tbody tr').length,
      scrolls: scroller ? scroller.scrollWidth > scroller.clientWidth : false,
    };
  }, tableId);
}

for (const { path, id } of grids) {
  test(`${path} keeps header and body columns aligned`, async ({ page }) => {
    // These grids are heavy; two locales means two full renders.
    test.setTimeout(180_000);

    const errors = collectPageErrors(page);
    await signIn(page);
    await page.goto(path, { waitUntil: 'networkidle' });

    for (const locale of ['en', 'ar'] as const) {
      if (locale !== 'en') {
        await setLocale(page, locale);
        await page.goto(path, { waitUntil: 'networkidle' });
      }

      const table = page.locator(`#${id}`);
      await expect(table).toBeVisible();
      await expect(table.locator('tbody tr').first().locator('td').first())
        .not.toHaveText(/Loading|جارٍ/i, { timeout: 20_000 });

      const m = await measure(page, id);
      expect(m, `${id} should exist`).not.toBeNull();
      test.skip(!m!.rows, 'Grid is empty in this environment.');

      expect(m!.hasHeaderClone, `${path} [${locale}] must not use a cloned scroll header`).toBe(false);
      expect(m!.head.length).toBe(m!.body.length);

      m!.head.forEach((cell, i) => {
        expect(cell.width, `${path} [${locale}] column ${i} width must match its header`)
          .toBe(m!.body[i].width);
        expect(cell.left, `${path} [${locale}] column ${i} position must match its header`)
          .toBe(m!.body[i].left);
      });
    }

    await setLocale(page, 'en');
    errors.assertClean();
  });
}
