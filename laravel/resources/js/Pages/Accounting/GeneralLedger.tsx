import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState, type ReactElement } from 'react';
import AppLayout from '../../Components/AppLayout';
import ServerDataTable, { type DataTableSlots } from '../../Components/ServerDataTable';
import { AccountingAmount, Button, Card, MetricCard, PageHeader, SearchableSelect } from '../../Components/Primitives';
import { formatAccountingAmount, formatDate, formatPeriodLabel, getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { LedgerRow, SharedPageProps } from '../../Types';

type GeneralLedgerProps = SharedPageProps & {
  totals: {
    debit: number;
    credit: number;
    net: number;
  };
  accounts: { id: string; code: string; name: Record<string, string> | string }[];
  branches: { id: string; code: string; name: Record<string, string> | string; is_active: boolean }[];
  periods: { id: string; month: number; fiscal_year?: { year: number } | null }[];
  filters: { account_id?: string; period_id?: string; branch_id?: string; start_date?: string; end_date?: string };
  displayCurrency: string;
};

export default function GeneralLedger({ locale, totals, accounts = [], branches = [], periods = [], filters, displayCurrency }: GeneralLedgerProps) {
  const dict = getDictionary(locale);
  const accDict = dict.app.accounting;
  const branchReportDict = dict.app.pages.branchOperationsReport;
  const can = useCan();
  const canPrint = can('reports.print') && can('view_financials');

  const [accountId, setAccountId] = useState(filters.account_id ?? '');
  const [periodId, setPeriodId] = useState(filters.period_id ?? '');
  const [branchId, setBranchId] = useState(filters.branch_id ?? '');
  const hasActiveFilter = Boolean(accountId || periodId || branchId);

  const accountSelectOptions = [
    { value: '', label: accDict.allAccounts },
    ...accounts.map((a) => ({
      value: a.id,
      label: `${a.code} - ${getLocalizedName(a.name, locale)}`,
    })),
  ];

  const periodSelectOptions = [
    { value: '', label: accDict.allPeriods },
    ...periods.map((p) => ({
      value: p.id,
      label: formatPeriodLabel(p, locale),
    })),
  ];

  const branchSelectOptions = [
    { value: '', label: branchReportDict.allBranches },
    ...branches.map((branch) => ({
      value: branch.id,
      label: `${branch.code} - ${getLocalizedName(branch.name, locale)}`,
      sublabel: branch.is_active ? branchReportDict.active : branchReportDict.inactive,
    })),
  ];

  function applyFilter() {
    router.get('/accounting/ledger', {
      account_id: accountId || undefined,
      period_id: periodId || undefined,
      branch_id: branchId || undefined,
    }, { preserveScroll: true });
  }

  function resetFilters() {
    setAccountId('');
    setPeriodId('');
    setBranchId('');
    router.get('/accounting/ledger', {}, { preserveScroll: true });
  }

  const dtColumns = useMemo(() => [
    { data: 'entry_date', name: 'entry_date', title: accDict.postingDate, className: 'font-mono text-xs', width: '120px' },
    { data: 'account_code', name: 'account_code', title: accDict.accountCode, width: '120px' },
    { data: 'account_name', name: 'account_name', title: accDict.accountName },
    { data: 'branch_name', name: 'branch_name', title: branchReportDict.branch },
    { data: 'voucher_number', name: 'voucher_number', title: accDict.voucherNumber, width: '150px' },
    { data: 'debit_minor', name: 'debit_minor', title: accDict.debitMinor, className: 'text-end', width: '140px' },
    { data: 'credit_minor', name: 'credit_minor', title: accDict.creditMinor, className: 'text-end', width: '140px' },
  ], [accDict, branchReportDict]);

  const dtSlots = useMemo<DataTableSlots>(() => ({
    entry_date: (data: string): ReactElement => (
      <span className="accounting-date font-mono text-xs text-[var(--text-primary)]">{formatDate(data)}</span>
    ),
    account_code: (data: string, _t: unknown, row: LedgerRow): ReactElement => (
      <span className="accounting-code font-mono font-bold text-xs text-blue-600 dark:text-blue-400 bg-blue-500/10 border border-blue-500/20 px-2 py-0.5 rounded-md inline-block">
        {row.account?.code || data || '—'}
      </span>
    ),
    account_name: (_d: unknown, _t: unknown, row: LedgerRow): ReactElement => (
      <span className="font-bold text-xs text-[var(--text-primary)]">
        {getLocalizedName(row.account?.name, locale)}
      </span>
    ),
    branch_name: (_d: unknown, _t: unknown, row: LedgerRow): ReactElement => (
      <span className="text-xs text-[var(--text-secondary)]">
        {row.branch ? (
          <span className="inline-flex items-center gap-1 rounded bg-[var(--background)] px-2 py-0.5 border border-[var(--border)]">
            {row.branch.code} - {getLocalizedName(row.branch.name, locale)}
          </span>
        ) : (
          branchReportDict.notAssigned
        )}
      </span>
    ),
    voucher_number: (_d: unknown, _t: unknown, row: LedgerRow): ReactElement => (
      row.journalEntry ? (
        <Link
          href={`/accounting/journal/${row.journalEntry.id}`}
          className="inline-flex items-center gap-1 font-mono font-bold text-xs text-blue-600 dark:text-blue-400 bg-blue-500/10 border border-blue-500/20 px-2.5 py-1 rounded-lg hover:bg-blue-500/20 transition-colors"
          title={dict.app.actions.numberDetails}
        >
          <svg className="size-3 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            <path strokeLinecap="round" strokeLinejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
          </svg>
          <span>{row.journalEntry.number || accDict.unnumberedVoucher}</span>
        </Link>
      ) : (
        <span className="text-xs text-[var(--text-muted)]">{accDict.notAvailable}</span>
      )
    ),
    debit_minor: (data: number, _t: unknown, row: LedgerRow): ReactElement => (
      <AccountingAmount amountMinor={data} currency={row.currency || row.currency_code || displayCurrency} tone="debit" />
    ),
    credit_minor: (data: number, _t: unknown, row: LedgerRow): ReactElement => (
      <AccountingAmount amountMinor={data} currency={row.currency || row.currency_code || displayCurrency} tone="credit" />
    ),
  } as unknown as DataTableSlots), [accDict, branchReportDict, dict, displayCurrency, locale]);

  const dtFilters = useMemo(() => ({
    account_id: accountId,
    period_id: periodId,
    branch_id: branchId,
  }), [accountId, periodId, branchId]);

  return (
    <AppLayout active="accounting.ledger">
      <Head title={accDict.ledger} />

      <PageHeader
        title={accDict.ledger}
        description={accDict.ledgerDesc}
        actions={
          canPrint ? (
            <Button variant="secondary" onClick={() => window.print()}>
              {dict.app.actions.printReport}
            </Button>
          ) : undefined
        }
      />

      <Card className="p-4 mb-6">
        <div className="grid gap-4 sm:grid-cols-1 md:grid-cols-2 lg:grid-cols-8 items-end">
          <div className="lg:col-span-3">
            <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
              {accDict.filterAccount}
            </label>
            <SearchableSelect
              options={accountSelectOptions}
              value={accountId}
              onChange={(val) => setAccountId(val || '')}
            />
          </div>

          <div className="lg:col-span-2">
            <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
              {accDict.financialPeriod}
            </label>
            <SearchableSelect
              options={periodSelectOptions}
              value={periodId}
              onChange={(val) => setPeriodId(val || '')}
            />
          </div>

          <div className="lg:col-span-2">
            <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
              {branchReportDict.branch}
            </label>
            <SearchableSelect
              options={branchSelectOptions}
              value={branchId}
              onChange={(val) => setBranchId(val || '')}
            />
          </div>

          <div className="lg:col-span-1 grid grid-cols-1 gap-2">
            <Button onClick={applyFilter} className="w-full px-3">
              {accDict.applyFilter}
            </Button>
            <Button variant="secondary" onClick={resetFilters} disabled={!hasActiveFilter} className="w-full px-3">
              {dict.app.actions.reset}
            </Button>
          </div>
        </div>
      </Card>

      <div className="grid gap-4 sm:grid-cols-3 mb-6">
        <MetricCard label={accDict.totalDebits} value={formatAccountingAmount(totals.debit, displayCurrency)} tone="blue" />
        <MetricCard label={accDict.totalCredits} value={formatAccountingAmount(totals.credit, displayCurrency)} tone="emerald" />
        <MetricCard label={accDict.netMovement} value={formatAccountingAmount(totals.net, displayCurrency, { zeroAsDash: false })} tone="purple" />
      </div>

      <Card className="overflow-hidden p-0 shadow-sm">
        <ServerDataTable
          ajaxUrl="/accounting/ledger/data"
          columns={dtColumns}
          filters={dtFilters}
          locale={locale}
          order={[[0, 'asc']]}
          pageLength={25}
          slots={dtSlots}
          tableId="general-ledger-table"
        />
      </Card>
    </AppLayout>
  );
}
