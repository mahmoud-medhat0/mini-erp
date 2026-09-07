import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AppLayout from '../../Components/AppLayout';
import { AccountingAmount, Button, Card, PageHeader, SearchableSelect, StatusBadge } from '../../Components/Primitives';
import ServerDataTable, { type DataTableSlots } from '../../Components/ServerDataTable';
import { formatAccountingAmount, getAccountTypeLabel, getLocalizedName, formatPeriodLabel } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { SharedPageProps, TbRow } from '../../Types';

type TrialBalanceProps = SharedPageProps & {
  totals: {
    debit: number;
    credit: number;
    is_balanced: boolean;
  };
  periods: { id: string; month: number; fiscal_year?: { year: number } | null }[];
  filters: { period_id?: string; start_date?: string; end_date?: string; include_zero?: boolean };
  displayCurrency: string;
};

export default function TrialBalance({ locale, totals, periods = [], filters, displayCurrency }: TrialBalanceProps) {
  const dict = getDictionary(locale);
  const accDict = dict.app.accounting;
  const actionsDict = dict.app.actions;
  const can = useCan();
  const canPrint = can('reports.print') && can('view_financials');

  const [periodId, setPeriodId] = useState(filters.period_id ?? '');
  const hasActiveFilter = Boolean(periodId);

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

  function resetFilters() {
    setPeriodId('');
    router.get('/accounting/trial-balance', {}, { preserveScroll: true });
  }

  function handlePrint() {
    window.print();
  }

  const columns = useMemo(() => [
    { data: 'account_code', name: 'account_code', title: accDict.accountCode },
    { data: 'account_name', name: 'account_name', title: accDict.accountName, orderable: false },
    { data: 'type', name: 'type', title: accDict.accountType },
    { data: 'debit_balance', name: 'debit_balance', title: accDict.endingDebit, searchable: false, className: 'text-end' },
    { data: 'credit_balance', name: 'credit_balance', title: accDict.endingCredit, searchable: false, className: 'text-end' },
  ], [accDict]);

  const slots = useMemo<DataTableSlots>(() => ({
    account_code: (data: unknown) => (
      <span className="accounting-code font-mono text-xs font-bold text-blue-600 dark:text-blue-400">{String(data || '')}</span>
    ),
    account_name: (data: unknown) => (
      <span className="text-xs font-bold text-[var(--text-primary)]">{getLocalizedName(data as TbRow['account_name'], locale)}</span>
    ),
    type: (data: unknown) => (
      <span className="rounded-lg border border-blue-500/20 bg-blue-500/10 px-2 py-0.5 text-xs font-bold text-blue-600 dark:text-blue-400">
        {getAccountTypeLabel(String(data || ''), locale)}
      </span>
    ),
    debit_balance: (data: unknown, _type: unknown, row: TbRow) => (
      <AccountingAmount amountMinor={Number(data || 0)} currency={row.currency_code || displayCurrency} tone="debit" />
    ),
    credit_balance: (data: unknown, _type: unknown, row: TbRow) => (
      <AccountingAmount amountMinor={Number(data || 0)} currency={row.currency_code || displayCurrency} tone="credit" />
    ),
  }), [displayCurrency, locale]);

  const tableFilters = useMemo(() => ({
    period_id: filters.period_id || '',
    start_date: filters.start_date || '',
    end_date: filters.end_date || '',
    include_zero: filters.include_zero ? '1' : '',
  }), [filters.end_date, filters.include_zero, filters.period_id, filters.start_date]);

  return (
    <AppLayout active="accounting.trial_balance">
      <Head title={accDict.trialBalance} />

      <PageHeader
        title={accDict.trialBalance}
        description={accDict.trialBalanceDesc}
        actions={
          canPrint ? (
            <button
              type="button"
              onClick={handlePrint}
              title={actionsDict.printReport}
              aria-label={actionsDict.printReport}
              className="inline-flex items-center gap-2 rounded-xl bg-[var(--surface-subtle)] border border-[var(--border)] px-4 py-2 text-xs font-semibold text-[var(--text-primary)] hover:bg-[var(--background)] transition-all cursor-pointer"
            >
              <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
              </svg>
              <span>{actionsDict.printReport}</span>
            </button>
          ) : undefined
        }
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

          <div className="flex items-center gap-2">
            <button
              type="button"
              onClick={applyFilter}
              title={accDict.generateTrialBalance}
              aria-label={accDict.generateTrialBalance}
              className="rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-5 py-2.5 text-xs font-bold text-white shadow-md shadow-blue-500/20 hover:opacity-95 active:scale-95 transition-all cursor-pointer"
            >
              {accDict.generateTrialBalance}
            </button>
            <Button
              variant="secondary"
              onClick={resetFilters}
              disabled={!hasActiveFilter}
              title={actionsDict.reset}
              aria-label={actionsDict.reset}
              className="px-4 py-2.5"
            >
              {actionsDict.reset}
            </Button>
          </div>
        </div>
      </Card>

      {/* Balance Assertion Banner */}
      <Card className="p-5 mb-6 flex items-center justify-between border-l-4 border-l-emerald-500">
        <div className="flex items-center gap-3">
          <div className={`flex size-10 items-center justify-center rounded-xl text-white ${totals.is_balanced ? 'bg-emerald-500' : 'bg-red-500'}`}>
            {totals.is_balanced ? (
              <svg className="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" aria-hidden="true"><path strokeLinecap="round" strokeLinejoin="round" d="m5 12 4 4L19 6" /></svg>
            ) : (
              <svg className="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" aria-hidden="true"><path strokeLinecap="round" d="M6 6l12 12M18 6 6 18" /></svg>
            )}
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

      <Card className="overflow-hidden p-0">
        <ServerDataTable
          ajaxUrl="/accounting/trial-balance/data"
          columns={columns}
          filters={tableFilters}
          locale={locale}
          order={[[0, 'asc']]}
          pageLength={25}
          slots={slots}
          tableId="accounting-trial-balance-data-table"
        />
      </Card>
    </AppLayout>
  );
}
