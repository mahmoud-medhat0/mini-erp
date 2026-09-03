import { test, expect } from '@playwright/test';
import { signIn } from './support/session';

test('full page load timing after fixes', async ({ page }) => {
  test.setTimeout(120_000);
  const started = Date.now();
  await signIn(page);
  console.log('login flow took:', Date.now() - started, 'ms');

  const t2 = Date.now();
  await page.goto('/accounting/account-categories', { waitUntil: 'domcontentloaded' });
  await expect(page.locator('#app')).not.toBeEmpty({ timeout: 20000 });
  console.log('account-categories page ready:', Date.now() - t2, 'ms');

  const t3 = Date.now();
  await page.goto('/accounting/coa', { waitUntil: 'domcontentloaded' });
  const table = page.locator('#chart-of-accounts-table');
  await table.waitFor({ state: 'visible', timeout: 20000 });
  await page.locator('#chart-of-accounts-table tbody tr').first().waitFor({ timeout: 20000 });
  console.log('chart-of-accounts ready:', Date.now() - t3, 'ms');
  console.log('rows:', await table.locator('tbody tr').count());
});
