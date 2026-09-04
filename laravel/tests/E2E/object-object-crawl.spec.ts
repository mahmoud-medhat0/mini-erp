import { expect, test } from '@playwright/test';

import { signIn } from './support/session';

/**
 * A full-app crawl for the "[object Object]" symptom.
 *
 * The static guard (TranslatableDisplayGuardTest) catches `{entity.name}` reads
 * against a known list of Spatie Translatable entities and field names. That
 * heuristic can miss a case: a variable name it doesn't recognise, a value
 * routed through an intermediate variable, or a field the list hasn't been
 * taught about yet. This test instead visits every reachable page and reads
 * the actual rendered text — it does not care *why* an object leaked into the
 * DOM, only whether one did.
 *
 * The route list below was generated from `php artisan route:list --json`,
 * filtered to GET routes with no required path parameter (so every entry is
 * an index/create page reachable with no id) and with auth/export/data-feed
 * routes excluded. It is a snapshot: a new top-level page added later needs
 * a manual entry here to be covered by this specific test — the static guard
 * still covers it either way.
 */
const PAGES: string[] = [
  '/',
  '/accounting',
  '/accounting/account-categories',
  '/accounting/account-mappings',
  '/accounting/account-types',
  '/accounting/coa',
  '/accounting/currencies',
  '/accounting/fx-rates',
  '/accounting/journal',
  '/accounting/journal/create',
  '/accounting/ledger',
  '/accounting/opening-balances',
  '/accounting/periods',
  '/accounting/statement-mappings',
  '/accounting/trial-balance',
  '/attachments',
  '/audit-log',
  '/bank-accounts',
  '/bank-reconciliations',
  '/budgeting/budgets',
  '/budgeting/variance',
  '/cash-accounts',
  '/catalog/categories',
  '/catalog/products',
  '/catalog/uoms',
  '/cost-centers',
  '/customer-opening-balances',
  '/customer-receipts',
  '/customers',
  '/dashboard',
  '/expenses',
  '/expenses/accruals',
  '/expenses/categories',
  '/expenses/prepaids',
  '/fixed-asset-categories',
  '/fixed-asset-locations',
  '/fixed-assets',
  '/fixed-assets-depreciation-runs',
  '/fixed-assets-disposals',
  '/fixed-assets/create',
  '/foundation',
  '/incoming-cheques',
  '/inventory/adjustments',
  '/inventory/stock-balances',
  '/inventory/stock-counts',
  '/inventory/transfers',
  '/inventory/warehouses',
  '/notifications',
  '/outgoing-cheques',
  '/payable-allocations',
  '/payroll/components',
  '/payroll/employees',
  '/payroll/runs',
  '/projects',
  '/purchasing/adjustment-notes',
  '/purchasing/bills',
  '/purchasing/goods-receipts',
  '/purchasing/landed-costs',
  '/purchasing/orders',
  '/purchasing/payable-settlements',
  '/purchasing/returns',
  '/receivable-allocations',
  '/rentals/contracts',
  '/rentals/handovers',
  '/rentals/invoices',
  '/rentals/items',
  '/rentals/returns',
  '/reports',
  '/reports/ap-aging',
  '/reports/ap-gl-reconciliation',
  '/reports/ar-aging',
  '/reports/ar-gl-reconciliation',
  '/reports/balance-sheet',
  '/reports/bank-book',
  '/reports/bank-reconciliations',
  '/reports/branch-operations',
  '/reports/branch-profitability',
  '/reports/cash-book',
  '/reports/cash-flow',
  '/reports/cheque-register',
  '/reports/cost-center-actuals',
  '/reports/customer-invoices',
  '/reports/customer-statement',
  '/reports/delivery-notes',
  '/reports/fixed-asset-depreciation',
  '/reports/fixed-asset-depreciation-runs',
  '/reports/fixed-asset-disposals',
  '/reports/fixed-asset-net-book-values',
  '/reports/fixed-asset-register',
  '/reports/goods-receipts',
  '/reports/income-statement',
  '/reports/project-profitability',
  '/reports/purchase-orders',
  '/reports/rentals',
  '/reports/sales-orders',
  '/reports/stock-movements',
  '/reports/supplier-bills',
  '/reports/supplier-statement',
  '/reports/vat-gl-reconciliation',
  '/reports/vat-register',
  '/reports/vat-summary',
  '/sales/credit-notes',
  '/sales/delivery-notes',
  '/sales/invoice-revisions',
  '/sales/invoices',
  '/sales/orders',
  '/sales/receivable-settlements',
  '/sales/returns',
  '/settings',
  '/settings/branch-approval-rules',
  '/settings/branches',
  '/settings/company',
  '/settings/numbering',
  '/settings/users',
  '/supplier-opening-balances',
  '/supplier-payments',
  '/suppliers',
  '/taxes/codes',
  '/taxes/codes/create',
  '/taxes/periods',
  '/taxes/rates',
  '/treasury-transfers',
];

test.describe('Whole-app [object Object] crawl', () => {
  test('no reachable page renders a raw translatable object', async ({ page }) => {
    test.setTimeout(15 * 60 * 1000);

    await signIn(page);

    const failures: string[] = [];
    const serverErrors: string[] = [];
    const jsErrors: Array<{ path: string; error: string }> = [];

    for (const path of PAGES) {
      let pageErrored = '';
      const onPageError = (e: Error) => { pageErrored = e.message; };
      page.on('pageerror', onPageError);

      let httpStatus = 0;
      const onResponse = (r: import('@playwright/test').Response) => {
        if (r.url().endsWith(path) || new URL(r.url()).pathname === path) {
          httpStatus = r.status();
        }
      };
      page.on('response', onResponse);

      try {
        await page.goto(path, { waitUntil: 'domcontentloaded', timeout: 20_000 });
        await page.waitForTimeout(600);

        const bodyText = await page.locator('body').innerText().catch(() => '');
        if (bodyText.includes('[object Object]')) {
          failures.push(`${path} -> body contains "[object Object]"`);
        }

        if (httpStatus >= 500) {
          serverErrors.push(`${path} -> HTTP ${httpStatus}`);
        }

        if (pageErrored) {
          jsErrors.push({ path, error: pageErrored });
        }
      } catch (e) {
        serverErrors.push(`${path} -> navigation failed: ${(e as Error).message.slice(0, 120)}`);
      } finally {
        page.off('pageerror', onPageError);
        page.off('response', onResponse);
      }
    }

    console.log(`Crawled ${PAGES.length} pages.`);
    if (jsErrors.length) {
      console.log('=== Uncaught JS errors (informational; not all are [object Object]) ===');
      for (const { path, error } of jsErrors) console.log(`  ${path}: ${error.slice(0, 200)}`);
    }
    if (serverErrors.length) {
      console.log('=== Server / navigation errors ===');
      for (const line of serverErrors) console.log(`  ${line}`);
    }
    if (failures.length) {
      console.log('=== [object Object] FOUND ===');
      for (const line of failures) console.log(`  ${line}`);
    } else {
      console.log('No page rendered a raw translatable object.');
    }

    expect(failures, `Pages rendering a raw object:\n${failures.join('\n')}`).toEqual([]);
  });
});
