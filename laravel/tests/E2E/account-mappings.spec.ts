import { expect, test } from '@playwright/test';

import { collectPageErrors, signIn } from './support/session';

/**
 * The mappings grid is server-side. Two things can only be seen in a browser:
 *
 * 1. Every mapping key must resolve to a label. An unlabelled key falls back to
 *    the raw slug, which then renders twice in the cell (label + sublabel) —
 *    ten of the thirty-four keys used to do exactly that.
 * 2. The grid fetches its own rows, so it does not observe the Inertia redirect
 *    after a mutation. Without an explicit reload signal it shows stale rows.
 */
test.describe('Account mappings grid', () => {
  test('serves rows from the feed and resolves every key label', async ({ page }) => {
    test.setTimeout(180_000);
    const errors = collectPageErrors(page);

    await signIn(page);
    await page.goto('/accounting/account-mappings', { waitUntil: 'domcontentloaded' });

    const table = page.locator('#account-mappings-table');
    await expect(table).toBeVisible({ timeout: 30_000 });

    // Server-side chrome: quick search plus paged info.
    await expect(page.locator('input[type="search"]')).toBeVisible();
    const info = page.locator('.dt-info');
    await expect(info).toContainText(/\d+/, { timeout: 20_000 });

    const rows = await table.locator('tbody tr').count();
    test.skip(!rows, 'No account mappings seeded in this environment.');

    // A page of rows, not the whole table at once.
    const infoText = await info.innerText();
    const total = Number(infoText.replace(/,/g, '').match(/of\s+(\d+)/i)?.[1] ?? 0);
    if (total > 25) {
      expect(rows, 'grid must paginate rather than render every mapping').toBeLessThanOrEqual(25);
    }

    // Each key cell shows a human label above the raw slug; an unlabelled key
    // would repeat the same slug twice.
    for (const cell of await table.locator('tbody tr td:first-child').allInnerTexts()) {
      const [label, slug] = cell.split('\n').map((part) => part.trim()).filter(Boolean);
      expect(slug, `key cell should carry a slug: ${JSON.stringify(cell)}`).toBeTruthy();
      expect(label, `mapping key [${slug}] has no label`).not.toBe(slug);
    }

    await expect(table).not.toContainText('[object Object]');
    errors.assertClean();
  });

  test('filters by scope on the server', async ({ page }) => {
    test.setTimeout(180_000);

    await signIn(page);
    await page.goto('/accounting/account-mappings', { waitUntil: 'domcontentloaded' });

    const table = page.locator('#account-mappings-table');
    const info = page.locator('.dt-info');
    await expect(table).toBeVisible({ timeout: 30_000 });
    await expect(info).toContainText(/\d+/, { timeout: 20_000 });

    const totalOf = async () =>
      Number((await info.innerText()).replace(/,/g, '').match(/of\s+(\d+)/i)?.[1] ?? 0);

    const unfiltered = await totalOf();
    test.skip(!unfiltered, 'No account mappings seeded in this environment.');

    // Global scope excludes branch overrides, so it can never exceed the total.
    await page.locator('.sdt-top-bar button[type="button"]').nth(0).click();
    await page.locator('button[type="button"]:visible').filter({ hasText: /^Global$/ }).first().click();
    await expect(info).not.toHaveText(await info.innerText(), { timeout: 20_000 }).catch(() => {});

    const globalOnly = await totalOf();
    expect(globalOnly).toBeLessThanOrEqual(unfiltered);
    expect(globalOnly).toBeGreaterThan(0);

    // Every visible row in this slice is global.
    const scopeCells = await table.locator('tbody tr td:nth-child(2)').allInnerTexts();
    scopeCells.forEach((cell) => {
      expect(cell, 'global slice must not contain branch overrides').not.toContain('Branch Override');
    });
  });
});
