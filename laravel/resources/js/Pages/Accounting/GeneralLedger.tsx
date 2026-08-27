import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Components/AppLayout';
import { AccountingAmount, Button, Card, EmptyState, MetricCard, PageHeader, SearchableSelect, tableClasses } from '../../Components/Primitives';
import { formatAccountingAmount, formatDate, formatPeriodLabel } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import type { LedgerRow, SharedPageProps } from '../../Types';

type GeneralLedgerProps = SharedPageProps & {
  ledger: {
    data: LedgerRow[];
  };
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

export default function GeneralLedger({ locale, ledger, totals, accounts = [], branches = [], periods = [], filters, displayCurrency }: GeneralLedgerProps) {
  const dict = getDictionary(locale);
  const accDict = dict.app.accounting;
  const branchReportDict = dict.app.pages.branchOperationsReport;

  const [accountId, setAccountId] = useState(filters.account_id ?? '');
  const [periodId, setPeriodId] = useState(filters.period_id ?? '');
  const [branchId, setBranchId] = useState(filters.branch_id ?? '');
  const hasActiveFilter = Boolean(accountId || periodId || branchId);

  const getName = (nameObj?: Record<string, string> | string | null) => {
    if (!nameObj) return '';
    if (typeof nameObj === 'string') return nameObj;
    return locale === 'ar' ? nameObj.ar || nameObj.en : nameObj.en || nameObj.ar;
  };

  const accountSelectOptions = [
    { value: '', label: accDict.allAccounts },
    ...accounts.map((a) => ({
      value: a.id,
      label: `${a.code} - ${getName(a.name)}`,
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
      label: `${branch.code} - ${getName(branch.name)}`,
      sublabel: branch.is_active ? branchReportDict.active : branchReportDict.inactive,
    })),
  ];

  function applyFilter() {
    router.get('/accounting/ledger', {
      account_id: accountId || undefined,
      period_id: periodId || undefined,
      branch_id: branchId || undefined,
    });
  }

  function resetFilters() {
    setAccountId('');
    setPeriodId('');
    setBranchId('');
    router.get('/accounting/ledger');
  }

  return (
    <AppLayout active="accounting.ledger">
      <Head title={accDict.ledger} />

      <PageHeader
        title={accDict.ledger}
        description={accDict.ledgerDesc}
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

      {ledger.data.length === 0 ? (
        <EmptyState
          title={accDict.noLedgerEntries}
          description={hasActiveFilter ? accDict.noLedgerEntriesFilteredDesc : accDict.noLedgerEntriesDesc}
        />
      ) : (
        <div className={tableClasses.wrap}>
          <table className={tableClasses.table}>
            <thead>
              <tr>
                <th className={tableClasses.th}>{accDict.postingDate}</th>
                <th className={tableClasses.th}>{accDict.accountCode}</th>
                <th className={tableClasses.th}>{accDict.accountName}</th>
                <th className={tableClasses.th}>{branchReportDict.branch}</th>
                <th className={tableClasses.th}>{accDict.voucherNumber}</th>
                <th className={`${tableClasses.th} text-end`}>{accDict.debitMinor}</th>
                <th className={`${tableClasses.th} text-end`}>{accDict.creditMinor}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-[var(--border)]">
              {ledger.data.map((l) => (
                <tr key={l.id} className="hover:bg-[var(--background)]/50 transition-colors">
                  <td className={tableClasses.td}>
                    <span className="accounting-date font-mono text-xs text-[var(--text-primary)]">{formatDate(l.entry_date)}</span>
                  </td>
                  <td className={tableClasses.td}>
                    <span className="accounting-code font-mono font-bold text-xs text-blue-600 dark:text-blue-400">
                      {l.account?.code}
                    </span>
                  </td>
                  <td className={tableClasses.td}>
                    <span className="font-bold text-xs text-[var(--text-primary)]">{getName(l.account?.name)}</span>
                  </td>
                  <td className={tableClasses.td}>
                    <span className="text-xs text-[var(--text-secondary)]">
                      {l.branch ? `${l.branch.code} - ${getName(l.branch.name)}` : branchReportDict.notAssigned}
                    </span>
                  </td>
                  <td className={tableClasses.td}>
                    {l.journalEntry ? (
                      <Link
                        href={`/accounting/journal/${l.journalEntry.id}`}
                        className="inline-flex items-center gap-1 font-mono font-bold text-xs text-blue-600 dark:text-blue-400 bg-blue-500/10 border border-blue-500/20 px-2.5 py-1 rounded-lg hover:bg-blue-500/20 transition-colors"
                        title={dict.app.actions.numberDetails}
                      >
                        <svg className="size-3 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                          <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                          <path strokeLinecap="round" strokeLinejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <span>{l.journalEntry.number || accDict.unnumberedVoucher}</span>
                      </Link>
                    ) : (
                      accDict.notAvailable
                    )}
                  </td>
                  <td className={`${tableClasses.td} text-end text-xs`}>
                    <AccountingAmount amountMinor={l.debit_minor} currency={l.currency || l.currency_code || displayCurrency} tone="debit" />
                  </td>
                  <td className={`${tableClasses.td} text-end text-xs`}>
                    <AccountingAmount amountMinor={l.credit_minor} currency={l.currency || l.currency_code || displayCurrency} tone="credit" />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </AppLayout>
  );
}
