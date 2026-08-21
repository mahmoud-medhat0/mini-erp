import { Head, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import { Card, PageHeader, SearchableSelect, tableClasses } from '../../Components/Primitives';
import { getDictionary } from '../../lib/i18n';
import type { SharedPageProps } from '../../Types/page';

type AccountRow = {
  id: string;
  code: string;
  name: Record<string, string> | string;
  type: string;
  nature: string;
};

type OpeningBalanceRow = {
  debit_minor: number;
  credit_minor: number;
  status: string;
};

type OpeningBalancesProps = SharedPageProps & {
  fiscalYears: { id: string; year: number }[];
  selectedYearId?: string;
  accounts: AccountRow[];
  existingBalances: Record<string, OpeningBalanceRow>;
};

export default function OpeningBalances({ locale, fiscalYears = [], selectedYearId, accounts = [], existingBalances = {} }: OpeningBalancesProps) {
  const dict = getDictionary(locale);
  const accDict = (dict.app as any).accounting || {};

  const [balancesState, setBalancesState] = useState<Record<string, { debit_minor: number; credit_minor: number }>>(() => {
    const initial: Record<string, { debit_minor: number; credit_minor: number }> = {};
    accounts.forEach((acc) => {
      const existing = existingBalances[acc.id];
      initial[acc.id] = {
        debit_minor: existing ? existing.debit_minor : 0,
        credit_minor: existing ? existing.credit_minor : 0,
      };
    });
    return initial;
  });

  const saveForm = useForm({
    fiscal_year_id: selectedYearId ?? fiscalYears[0]?.id ?? '',
    balances: [] as { account_id: string; debit_minor: number; credit_minor: number }[],
  });

  const postForm = useForm({
    fiscal_year_id: selectedYearId ?? fiscalYears[0]?.id ?? '',
  });

  const updateBalance = (accountId: string, field: 'debit_minor' | 'credit_minor', val: number) => {
    setBalancesState((prev) => ({
      ...prev,
      [accountId]: {
        ...prev[accountId],
        [field]: Math.max(0, val),
        [field === 'debit_minor' ? 'credit_minor' : 'debit_minor']: val > 0 ? 0 : (prev[accountId]?.[field === 'debit_minor' ? 'credit_minor' : 'debit_minor'] ?? 0),
      },
    }));
  };

  const totalDebit = Object.values(balancesState).reduce((sum, b) => sum + (b?.debit_minor || 0), 0);
  const totalCredit = Object.values(balancesState).reduce((sum, b) => sum + (b?.credit_minor || 0), 0);
  const isBalanced = totalDebit === totalCredit && totalDebit > 0;

  function submitDraft(e: FormEvent) {
    e.preventDefault();
    const payload = Object.entries(balancesState).map(([accountId, val]) => ({
      account_id: accountId,
      debit_minor: val.debit_minor,
      credit_minor: val.credit_minor,
    }));

    saveForm.setData('balances', payload);
    saveForm.post('/accounting/opening-balances');
  }

  function submitPost() {
    if (!isBalanced) return;
    postForm.post('/accounting/opening-balances/post');
  }

  const getName = (nameObj?: Record<string, string> | string | null) => {
    if (!nameObj) return '';
    if (typeof nameObj === 'string') return nameObj;
    return locale === 'ar' ? nameObj.ar || nameObj.en : nameObj.en || nameObj.ar;
  };

  const fiscalYearOptions = fiscalYears.map((fy) => ({
    value: fy.id,
    label: `Fiscal Year ${fy.year}`,
  }));

  return (
    <AppLayout active="accounting.opening_balances">
      <Head title={accDict.openingBalances || 'Opening Balances'} />

      <PageHeader
        title={accDict.openingBalances || 'Opening Balances'}
        description={accDict.openingBalancesDesc || 'Set account-level initial balances for new fiscal years and post opening journal entry.'}
        actions={
          <button
            type="button"
            onClick={submitPost}
            disabled={!isBalanced || postForm.processing}
            className="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-xs font-bold text-white shadow-md shadow-emerald-500/20 hover:bg-emerald-700 transition-colors disabled:opacity-50"
          >
            <span>Post Opening Journal to Ledger</span>
          </button>
        }
      />

      <Card className="p-4 mb-6">
        <div className="flex flex-wrap items-center justify-between gap-4">
          <div className="flex items-center gap-3">
            <span className="text-xs font-bold text-[var(--text-secondary)] uppercase">Fiscal Year:</span>
            <div className="w-56">
              <SearchableSelect
                options={fiscalYearOptions}
                value={saveForm.data.fiscal_year_id}
                onChange={(val) => {
                  saveForm.setData('fiscal_year_id', val || '');
                  postForm.setData('fiscal_year_id', val || '');
                  window.location.href = `/accounting/opening-balances?fiscal_year_id=${val}`;
                }}
                isClearable={false}
              />
            </div>
          </div>

          <div className="flex items-center gap-3 font-mono text-xs font-bold">
            <span>Total Debits: <span className="text-blue-500">{totalDebit}</span></span>
            <span>Total Credits: <span className="text-emerald-500">{totalCredit}</span></span>
            <span className={`px-2 py-0.5 rounded text-[10px] ${isBalanced ? 'bg-emerald-500/20 text-emerald-600' : 'bg-red-500/20 text-red-600'}`}>
              {isBalanced ? (accDict.balanced || 'BALANCED') : (accDict.unbalanced || 'UNBALANCED')}
            </span>
          </div>
        </div>
      </Card>

      <form onSubmit={submitDraft}>
        <div className={tableClasses.wrap + ' mb-6'}>
          <table className={tableClasses.table}>
            <thead>
              <tr>
                <th className={tableClasses.th}>Code</th>
                <th className={tableClasses.th}>Account Name</th>
                <th className={tableClasses.th}>Type / Nature</th>
                <th className={`${tableClasses.th} text-right`}>Opening Debit (Minor)</th>
                <th className={`${tableClasses.th} text-right`}>Opening Credit (Minor)</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-[var(--border)]">
              {accounts.map((acc) => {
                const bal = balancesState[acc.id] || { debit_minor: 0, credit_minor: 0 };
                return (
                  <tr key={acc.id} className="hover:bg-[var(--background)]/50 transition-colors">
                    <td className={tableClasses.td}>
                      <span className="font-mono font-bold text-xs text-blue-600 dark:text-blue-400">{acc.code}</span>
                    </td>
                    <td className={tableClasses.td}>
                      <span className="font-bold text-xs text-[var(--text-primary)]">{getName(acc.name)}</span>
                    </td>
                    <td className={tableClasses.td}>
                      <span className="text-xs font-mono uppercase text-[var(--text-muted)]">
                        {acc.type} ({acc.nature})
                      </span>
                    </td>
                    <td className={`${tableClasses.td} text-right`}>
                      <input
                        type="number"
                        min="0"
                        value={bal.debit_minor}
                        onChange={(e) => updateBalance(acc.id, 'debit_minor', parseInt(e.target.value) || 0)}
                        className="w-36 text-right rounded-lg border border-[var(--border)] bg-[var(--surface)] px-2.5 py-1.5 font-mono text-xs text-[var(--text-primary)]"
                      />
                    </td>
                    <td className={`${tableClasses.td} text-right`}>
                      <input
                        type="number"
                        min="0"
                        value={bal.credit_minor}
                        onChange={(e) => updateBalance(acc.id, 'credit_minor', parseInt(e.target.value) || 0)}
                        className="w-36 text-right rounded-lg border border-[var(--border)] bg-[var(--surface)] px-2.5 py-1.5 font-mono text-xs text-[var(--text-primary)]"
                      />
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>

        <div className="flex justify-end">
          <button
            type="submit"
            disabled={saveForm.processing}
            className="rounded-xl bg-[var(--primary)] px-6 py-2.5 text-xs font-bold text-white shadow-md shadow-blue-500/20 hover:bg-[var(--primary-hover)] transition-colors"
          >
            {accDict.saveDraft || 'Save Draft Balances'}
          </button>
        </div>
      </form>
    </AppLayout>
  );
}
