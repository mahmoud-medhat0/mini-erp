import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Components/AppLayout';
import { Card, EmptyState, PageHeader, SearchableSelect, StatusBadge, tableClasses } from '../../Components/Primitives';
import { getAccountTypeLabel, getLocalizedName, formatPeriodLabel } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import type { SharedPageProps } from '../../Types/page';

type TbRow = {
  account_id: string;
  account_code: string;
  account_name: Record<string, string> | string;
  type: string;
  nature: string;
  total_debit: number;
  total_credit: number;
  debit_balance: number;
  credit_balance: number;
};

type TrialBalanceProps = SharedPageProps & {
  rows: TbRow[];
  totals: {
    debit: number;
    credit: number;
    is_balanced: boolean;
  };
  periods: { id: string; month: number; fiscal_year?: { year: number } | null }[];
  filters: { period_id?: string; start_date?: string; end_date?: string; include_zero?: boolean };
};

export default function TrialBalance({ locale, rows = [], totals, periods = [], filters }: TrialBalanceProps) {
  const dict = getDictionary(locale);
  const accDict = (dict.app as any).accounting || {};
  const [periodId, setPeriodId] = useState(filters.period_id ?? '');

  const periodSelectOptions = [
    { value: '', label: accDict.allPeriodsCumulative || 'All Periods (Cumulative)' },
    ...periods.map((p) => ({
      value: p.id,
      label: formatPeriodLabel(p, locale),
    })),
  ];

  function applyFilter() {
    router.get('/accounting/trial-balance', {
      period_id: periodId || undefined,
    });
  }

  return (
    <AppLayout active="accounting.trial_balance">
      <Head title={accDict.trialBalance || 'Trial Balance'} />

      <PageHeader
        title={accDict.trialBalance || 'Trial Balance'}
        description={accDict.trialBalanceDesc || 'Verification of ending debit and credit balances derived strictly from posted ledger entries.'}
      />

      <Card className="p-4 mb-6">
        <div className="flex flex-wrap items-end gap-3">
          <div className="w-full sm:w-80 lg:w-[420px]">
            <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
              {accDict.financialPeriod || 'Financial Period'}
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
            className="rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-5 py-2.5 text-xs font-bold text-white shadow-md shadow-blue-500/20 hover:opacity-95 active:scale-95 transition-all cursor-pointer"
          >
            {accDict.generateTrialBalance || 'Generate Trial Balance'}
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
              {totals.is_balanced ? (accDict.tbBalancedTitle || 'Trial Balance Verified & In Balance') : (accDict.tbUnbalancedTitle || 'Trial Balance Out of Balance Warning')}
            </h4>
            <p className="m-0 text-xs text-[var(--text-muted)]">
              {accDict.tbBalancedDesc || 'Total debits equal total credits accounting equation verification.'}
            </p>
          </div>
        </div>

        <StatusBadge tone={totals.is_balanced ? 'ok' : 'danger'}>
          {totals.is_balanced ? (accDict.matched || 'MATCHED') : (accDict.unbalanced || 'UNBALANCED')}
        </StatusBadge>
      </Card>

      {rows.length === 0 ? (
        <EmptyState
          title={accDict.noTrialBalanceRows || (locale === 'ar' ? 'لا توجد حركة حركات تطابق الفلاتر المحددة.' : 'No posted movements match the selected filters.')}
          description={accDict.noTrialBalanceRowsDesc || (locale === 'ar' ? 'يتكون ميزان المراجعة من الأرصدة التراكمية الناتجة عن قيود دفتر الاستاد المرحّلة.' : 'The trial balance calculates cumulative debit and credit totals directly from posted ledger entries.')}
        />
      ) : (
        <div className={tableClasses.wrap}>
          <table className={tableClasses.table}>
            <thead>
              <tr>
                <th className={tableClasses.th}>{accDict.accountCode || 'Code'}</th>
                <th className={tableClasses.th}>{accDict.accountName || 'Account Name'}</th>
                <th className={tableClasses.th}>{accDict.accountType || 'Type'}</th>
                <th className={`${tableClasses.th} text-right`}>{accDict.endingDebit || 'Ending Debit (Minor)'}</th>
                <th className={`${tableClasses.th} text-right`}>{accDict.endingCredit || 'Ending Credit (Minor)'}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-[var(--border)]">
              {rows.map((r) => (
                <tr key={r.account_id} className="hover:bg-[var(--background)]/50 transition-colors">
                  <td className={tableClasses.td}>
                    <span className="font-mono font-bold text-xs text-blue-600 dark:text-blue-400">{r.account_code}</span>
                  </td>
                  <td className={tableClasses.td}>
                    <span className="font-bold text-xs text-[var(--text-primary)]">{getLocalizedName(r.account_name, locale)}</span>
                  </td>
                  <td className={tableClasses.td}>
                    <span className="text-xs font-bold px-2 py-0.5 rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20">
                      {getAccountTypeLabel(r.type, locale)}
                    </span>
                  </td>
                  <td className={`${tableClasses.td} text-right font-mono text-xs font-bold text-blue-600 dark:text-blue-400`}>
                    {r.debit_balance > 0 ? r.debit_balance : '-'}
                  </td>
                  <td className={`${tableClasses.td} text-right font-mono text-xs font-bold text-purple-600 dark:text-purple-400`}>
                    {r.credit_balance > 0 ? r.credit_balance : '-'}
                  </td>
                </tr>
              ))}
            </tbody>
            <tfoot className="bg-[var(--background)] border-t border-[var(--border)] font-bold text-xs">
              <tr>
                <td colSpan={3} className="p-3.5 text-right">{accDict.totalTrialBalance || 'TOTAL TRIAL BALANCE:'}</td>
                <td className="p-3.5 text-right font-mono text-blue-600 dark:text-blue-400">{totals.debit}</td>
                <td className="p-3.5 text-right font-mono text-purple-600 dark:text-purple-400">{totals.credit}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      )}
    </AppLayout>
  );
}
