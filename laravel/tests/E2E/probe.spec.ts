import { test } from '@playwright/test';
import { signIn } from './support/session';

test('measure chart of accounts load', async ({ page }) => {
  test.setTimeout(240_000);

  await signIn(page);
  await page.goto('/dashboard', { waitUntil: 'domcontentloaded' }).catch(() => {});

  // Instrument only the navigation we care about.
  const rows: Array<{ url: string; ms: number; kb: number; type: string }> = [];
  page.on('response', (r) => {
    const u = new URL(r.url()).pathname;
    if (/health|debugbar|favicon/.test(u)) return;
    const len = Number(r.headers()['content-length'] ?? 0);
    rows.push({ url: u.slice(-56), ms: 0, kb: Math.round(len / 102.4) / 10, type: r.request().resourceType() });
  });

  const started = Date.now();
  await page.goto('/accounting/coa', { waitUntil: 'domcontentloaded' });

  const table = page.locator('#chart-of-accounts-table');
  await table.waitFor({ state: 'visible', timeout: 200_000 });
  const visibleAt = Date.now() - started;

  await page.locator('#chart-of-accounts-table tbody tr').first().waitFor({ timeout: 60_000 });
  const firstRowAt = Date.now() - started;

  // Per-resource timing from the browser itself.
  const perf = await page.evaluate(() =>
    performance.getEntriesByType('resource')
      .filter((e) => !/health|debugbar|favicon/.test(e.name))
      .map((e) => ({
        url: new URL(e.name).pathname.slice(-56),
        ms: Math.round(e.duration),
        kb: Math.round(((e as PerformanceResourceTiming).transferSize ?? 0) / 102.4) / 10,
      }))
      .sort((a, b) => b.ms - a.ms)
      .slice(0, 8));

  console.log('=== slowest resources (browser timing) ===');
  perf.forEach((e) => console.log(`  ${String(e.ms).padStart(7)}ms ${String(e.kb).padStart(8)}kB  ${e.url}`));

  console.log('=== milestones ===');
  console.log('  grid visible at :', visibleAt, 'ms');
  console.log('  first row at    :', firstRowAt, 'ms');
  console.log('  rows rendered   :', await page.locator('#chart-of-accounts-table tbody tr').count());
  console.log('  inertia payload :', await page.evaluate(() => Math.round((document.getElementById('app')?.dataset.page?.length ?? 0) / 102.4) / 10), 'kB');
});
