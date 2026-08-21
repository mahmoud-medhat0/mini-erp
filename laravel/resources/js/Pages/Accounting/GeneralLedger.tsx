import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Components/AppLayout';
import { Card, EmptyState, PageHeader, SearchableSelect, tableClasses } from '../../Components/Primitives';
import { formatDate, formatPeriodLabel } from '../../lib/accountingHelpers';
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
    { value: '', label: accDict.allPeriods || (locale === 'ar' ? 'جميع الفترات' : 'All Periods') },
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
        <Card className="p-4 border-l-4 border-l-blue-500">
          <span className="text-xs font-bold text-[var(--text-secondary)] uppercase block">{accDict.totalDebits || 'Total Debits'}</span>
          <span className="text-xl font-mono font-extrabold text-blue-600 dark:text-blue-400">{totals.debit}</span>
        </Card>
        <Card className="p-4 border-l-4 border-l-emerald-500">
          <span className="text-xs font-bold text-[var(--text-secondary)] uppercase block">{accDict.totalCredits || 'Total Credits'}</span>
          <span className="text-xl font-mono font-extrabold text-emerald-600 dark:text-emerald-400">{totals.credit}</span>
        </Card>
        <Card className="p-4 border-l-4 border-l-purple-500">
          <span className="text-xs font-bold text-[var(--text-secondary)] uppercase block">{accDict.netMovement || 'Net Movement'}</span>
          <span className="text-xl font-mono font-extrabold text-[var(--text-primary)]">{totals.net}</span>
        </Card>
      </div>

      {ledger.data.length === 0 ? (
        <EmptyState
          title={accDict.noLedgerEntries || (locale === 'ar' ? 'لا توجد قيود مرحلة في دفتر الاستاد حالياً.' : 'No posted ledger entries yet.')}
          description={accDict.noLedgerEntriesDesc || (locale === 'ar' ? 'تظهر قيود دفتر الاستاد تلقائياً بمجرد ترحيل قيود اليومية المعتمدة.' : 'General Ledger entries stream automatically when journal vouchers are approved and posted.')}
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
                <th className={`${tableClasses.th} text-right`}>{accDict.debitMinor || 'Debit (Minor)'}</th>
                <th className={`${tableClasses.th} text-right`}>{accDict.creditMinor || 'Credit (Minor)'}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-[var(--border)]">
              {ledger.data.map((l) => (
                <tr key={l.id} className="hover:bg-[var(--background)]/50 transition-colors">
                  <td className={tableClasses.td}>
                    <span className="font-mono text-xs text-[var(--text-primary)]">{formatDate(l.entry_date)}</span>
                  </td>
                  <td className={tableClasses.td}>
                    <span className="font-mono font-bold text-xs text-blue-600 dark:text-blue-400">
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
                        className="font-mono font-bold text-xs text-blue-600 dark:text-blue-400 hover:underline"
                      >
                        {l.journalEntry.number || 'JV'}
                      </Link>
                    ) : (
                      '-'
                    )}
                  </td>
                  <td className={`${tableClasses.td} text-right font-mono text-xs font-bold text-blue-600 dark:text-blue-400`}>
                    {l.debit_minor > 0 ? l.debit_minor : '-'}
                  </td>
                  <td className={`${tableClasses.td} text-right font-mono text-xs font-bold text-purple-600 dark:text-purple-400`}>
                    {l.credit_minor > 0 ? l.credit_minor : '-'}
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
