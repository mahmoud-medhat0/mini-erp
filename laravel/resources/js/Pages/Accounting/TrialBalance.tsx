import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Components/AppLayout';
import { AccountingAmount, Card, EmptyState, PageHeader, SearchableSelect, StatusBadge, tableClasses } from '../../Components/Primitives';
import { formatAccountingAmount, getAccountTypeLabel, getLocalizedName, formatPeriodLabel } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import type { SharedPageProps, TbRow } from '../../Types';

type TrialBalanceProps = SharedPageProps & {
  rows: TbRow[];
  totals: {
    debit: number;
    credit: number;
    is_balanced: boolean;
  };
  periods: { id: string; month: number; fiscal_year?: { year: number } | null }[];
  filters: { period_id?: string; start_date?: string; end_date?: string; include_zero?: boolean };
  displayCurrency: string;
};

export default function TrialBalance({ locale, rows = [], totals, periods = [], filters, displayCurrency }: TrialBalanceProps) {
  const dict = getDictionary(locale);
  const accDict = dict.app.accounting;
  const [periodId, setPeriodId] = useState(filters.period_id ?? '');

  const periodSelectOptions = [
    { value: '', label: accDict.allPeriodsCumulative },
    ...periods.map((p) => ({
      value: p.id,
      label: formatPeriodLabel(p, locale),
    })),
  ];

  function applyFilter() {
    router.get('/accounting/trial-balance', {
      period_id: periodId || undefined,
    }, { preserveScroll: true });
  }

  return (
    <AppLayout active="accounting.trial_balance">
      <Head title={accDict.trialBalance} />

      <PageHeader
        title={accDict.trialBalance}
        description={accDict.trialBalanceDesc}
      />

      <Card className="p-4 mb-6">
        <div className="flex flex-wrap items-end gap-3">
          <div className="w-full sm:w-80 lg:w-[420px]">
            <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
              {accDict.financialPeriod}
            </label>
            <SearchableSelect
              options={periodSelectOptions}
              value={periodId}
              onChange={(val) => setPeriodId(val || '')}
            />
          </div>

          <button
            type="button"
            onClick={applyFilter}
            title={accDict.generateTrialBalance}
            aria-label={accDict.generateTrialBalance}
            className="rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-5 py-2.5 text-xs font-bold text-white shadow-md shadow-blue-500/20 hover:opacity-95 active:scale-95 transition-all cursor-pointer"
          >
            {accDict.generateTrialBalance}
          </button>
        </div>
      </Card>

      {/* Balance Assertion Banner */}
      <Card className="p-5 mb-6 flex items-center justify-between border-l-4 border-l-emerald-500">
        <div className="flex items-center gap-3">
          <div className={`flex size-10 items-center justify-center rounded-xl font-bold text-white ${totals.is_balanced ? 'bg-emerald-500' : 'bg-red-500'}`}>
            {totals.is_balanced ? '✓' : '✗'}
          </div>
          <div>
            <h4 className="m-0 text-sm font-bold text-[var(--text-primary)]">
              {totals.is_balanced ? accDict.tbBalancedTitle : accDict.tbUnbalancedTitle}
            </h4>
            <p className="m-0 text-xs text-[var(--text-muted)]">
              {accDict.tbBalancedDesc}
            </p>
          </div>
        </div>

        <StatusBadge tone={totals.is_balanced ? 'ok' : 'danger'}>
          {totals.is_balanced ? accDict.matched : accDict.unbalanced}
        </StatusBadge>
      </Card>

      <div className="grid gap-4 sm:grid-cols-3 mb-6">
        <Card className="border-s-4 border-s-blue-500 p-4">
          <span className="block text-xs font-bold uppercase text-[var(--text-secondary)]">{accDict.totalDebits}</span>
          <span className="accounting-amount mt-2 block text-xl font-extrabold text-blue-600 dark:text-blue-400">
            {formatAccountingAmount(totals.debit, displayCurrency)}
          </span>
        </Card>
        <Card className="border-s-4 border-s-purple-500 p-4">
          <span className="block text-xs font-bold uppercase text-[var(--text-secondary)]">{accDict.totalCredits}</span>
          <span className="accounting-amount mt-2 block text-xl font-extrabold text-purple-600 dark:text-purple-400">
            {formatAccountingAmount(totals.credit, displayCurrency)}
          </span>
        </Card>
        <Card className={`border-s-4 p-4 ${totals.is_balanced ? 'border-s-emerald-500' : 'border-s-red-500'}`}>
          <span className="block text-xs font-bold uppercase text-[var(--text-secondary)]">{accDict.netMovement}</span>
          <span className={`accounting-amount mt-2 block text-xl font-extrabold ${totals.is_balanced ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'}`}>
            {formatAccountingAmount(totals.debit - totals.credit, displayCurrency, { zeroAsDash: false })}
          </span>
        </Card>
      </div>

      {rows.length === 0 ? (
        <EmptyState
          title={accDict.noTrialBalanceRows}
          description={accDict.noTrialBalanceRowsDesc}
        />
      ) : (
        <div className={tableClasses.wrap}>
          <table className={tableClasses.table}>
            <thead>
              <tr>
                <th className={tableClasses.th}>{accDict.accountCode}</th>
                <th className={tableClasses.th}>{accDict.accountName}</th>
                <th className={tableClasses.th}>{accDict.accountType}</th>
                <th className={`${tableClasses.th} text-end`}>{accDict.endingDebit}</th>
                <th className={`${tableClasses.th} text-end`}>{accDict.endingCredit}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-[var(--border)]">
              {rows.map((r) => (
                <tr key={r.account_id} className="hover:bg-[var(--background)]/50 transition-colors">
                  <td className={tableClasses.td}>
                    <span className="accounting-code font-mono font-bold text-xs text-blue-600 dark:text-blue-400">{r.account_code}</span>
                  </td>
                  <td className={tableClasses.td}>
                    <span className="font-bold text-xs text-[var(--text-primary)]">{getLocalizedName(r.account_name, locale)}</span>
                  </td>
                  <td className={tableClasses.td}>
                    <span className="text-xs font-bold px-2 py-0.5 rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20">
                      {getAccountTypeLabel(r.type, locale)}
                    </span>
                  </td>
                  <td className={`${tableClasses.td} text-end text-xs`}>
                    <AccountingAmount amountMinor={r.debit_balance} currency={r.currency_code || displayCurrency} tone="debit" />
                  </td>
                  <td className={`${tableClasses.td} text-end text-xs`}>
                    <AccountingAmount amountMinor={r.credit_balance} currency={r.currency_code || displayCurrency} tone="credit" />
                  </td>
                </tr>
              ))}
            </tbody>
            <tfoot className="bg-[var(--background)] border-t border-[var(--border)] font-bold text-xs">
              <tr>
                <td colSpan={3} className="p-3.5 text-end">{accDict.totalTrialBalance}</td>
                <td className="p-3.5 text-end text-blue-600 dark:text-blue-400">
                  <AccountingAmount amountMinor={totals.debit} currency={displayCurrency} tone="debit" />
                </td>
                <td className="p-3.5 text-end text-purple-600 dark:text-purple-400">
                  <AccountingAmount amountMinor={totals.credit} currency={displayCurrency} tone="credit" />
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      )}
    </AppLayout>
  );
}
