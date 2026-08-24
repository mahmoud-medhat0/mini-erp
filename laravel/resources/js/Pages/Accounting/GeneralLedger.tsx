import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Components/AppLayout';
import { AccountingAmount, Card, EmptyState, MetricCard, PageHeader, SearchableSelect, tableClasses } from '../../Components/Primitives';
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
  periods: { id: string; month: number; fiscal_year?: { year: number } | null }[];
  filters: { account_id?: string; period_id?: string; start_date?: string; end_date?: string };
};

export default function GeneralLedger({ locale, ledger, totals, accounts = [], periods = [], filters }: GeneralLedgerProps) {
  const dict = getDictionary(locale);
  const accDict = (dict.app as any).accounting || {};

  const [accountId, setAccountId] = useState(filters.account_id ?? '');
  const [periodId, setPeriodId] = useState(filters.period_id ?? '');
  const displayCurrency = ledger.data[0]?.currency || ledger.data[0]?.currency_code || 'EGP';

  const getName = (nameObj?: Record<string, string> | string | null) => {
    if (!nameObj) return '';
    if (typeof nameObj === 'string') return nameObj;
    return locale === 'ar' ? nameObj.ar || nameObj.en : nameObj.en || nameObj.ar;
  };

  const accountSelectOptions = [
    { value: '', label: accDict.allAccounts || 'All Accounts' },
    ...accounts.map((a) => ({
      value: a.id,
      label: `${a.code} - ${getName(a.name)}`,
    })),
  ];

  const periodSelectOptions = [
    { value: '', label: accDict.allPeriods || dict.app.pages.accountingGeneralLedger.allPeriods },
    ...periods.map((p) => ({
      value: p.id,
      label: formatPeriodLabel(p, locale),
    })),
  ];

  function applyFilter() {
    router.get('/accounting/ledger', {
      account_id: accountId || undefined,
      period_id: periodId || undefined,
    });
  }

  return (
    <AppLayout active="accounting.ledger">
      <Head title={accDict.ledger || 'General Ledger'} />

      <PageHeader
        title={accDict.ledger || 'General Ledger'}
        description={accDict.ledgerDesc || 'Immutable posted ledger entries stream derived directly from approved and posted journal vouchers.'}
      />

      <Card className="p-4 mb-6">
        <div className="grid gap-4 sm:grid-cols-1 md:grid-cols-2 lg:grid-cols-7 items-end">
          <div className="lg:col-span-3">
            <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
              {accDict.filterAccount || 'Filter Account'}
            </label>
            <SearchableSelect
              options={accountSelectOptions}
              value={accountId}
              onChange={(val) => setAccountId(val || '')}
            />
          </div>

          <div className="lg:col-span-3">
            <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
              {accDict.financialPeriod || 'Financial Period'}
            </label>
            <SearchableSelect
              options={periodSelectOptions}
              value={periodId}
              onChange={(val) => setPeriodId(val || '')}
            />
          </div>

          <div className="lg:col-span-1 flex items-end">
            <button
              type="button"
              onClick={applyFilter}
              className="w-full sm:w-auto rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-5 py-2.5 text-xs font-bold text-white shadow-md shadow-blue-500/20 hover:opacity-95 active:scale-95 transition-all cursor-pointer"
            >
              {accDict.applyFilter || 'Apply Filter'}
            </button>
          </div>
        </div>
      </Card>

      {/* Totals Summary */}
      <div className="grid gap-4 sm:grid-cols-3 mb-6">
        <MetricCard label={accDict.totalDebits || 'Total Debits'} value={formatAccountingAmount(totals.debit, displayCurrency)} tone="blue" />
        <MetricCard label={accDict.totalCredits || 'Total Credits'} value={formatAccountingAmount(totals.credit, displayCurrency)} tone="emerald" />
        <MetricCard label={accDict.netMovement || 'Net Movement'} value={formatAccountingAmount(totals.net, displayCurrency, { zeroAsDash: false })} tone="purple" />
      </div>

      {ledger.data.length === 0 ? (
        <EmptyState
          title={accDict.noLedgerEntries || dict.app.pages.accountingGeneralLedger.noPostedLedgerEntriesYet}
          description={accDict.noLedgerEntriesDesc || dict.app.pages.accountingGeneralLedger.generalLedgerEntriesStreamAutomaticallyWhen}
        />
      ) : (
        <div className={tableClasses.wrap}>
          <table className={tableClasses.table}>
            <thead>
              <tr>
                <th className={tableClasses.th}>{accDict.postingDate || 'Posting Date'}</th>
                <th className={tableClasses.th}>{accDict.accountCode || 'Account Code'}</th>
                <th className={tableClasses.th}>{accDict.accountName || 'Account Name'}</th>
                <th className={tableClasses.th}>{accDict.voucherNumber || 'Voucher #'}</th>
                <th className={`${tableClasses.th} text-end`}>{accDict.debitMinor || 'Debit (Minor)'}</th>
                <th className={`${tableClasses.th} text-end`}>{accDict.creditMinor || 'Credit (Minor)'}</th>
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
                        <span>{l.journalEntry.number || 'JV'}</span>
                      </Link>
                    ) : (
                      '-'
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
