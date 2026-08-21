import { Head, router, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import { Card, EmptyState, PageHeader, SearchableSelect, StatusBadge, tableClasses } from '../../Components/Primitives';
import { getAccountNatureLabel, getAccountTypeLabel, getLocalizedName } from '../../lib/accountingHelpers';
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

export default function OpeningBalances({
  locale,
  fiscalYears = [],
  selectedYearId,
  accounts = [],
  existingBalances = {},
}: OpeningBalancesProps) {
  const dict = getDictionary(locale);
  const accDict = (dict.app as any).accounting || {};
  const fieldsDict = dict.app.fields || {};

  const activeYearId = selectedYearId ?? fiscalYears[0]?.id ?? '';

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
    fiscal_year_id: activeYearId,
    balances: [] as { account_id: string; debit_minor: number; credit_minor: number }[],
  });

  const postForm = useForm({
    fiscal_year_id: activeYearId,
  });

  const isAlreadyPosted = Object.values(existingBalances).some((b) => b.status === 'posted');

  const updateBalance = (accountId: string, field: 'debit_minor' | 'credit_minor', val: number) => {
    if (isAlreadyPosted) return;
    setBalancesState((prev) => ({
      ...prev,
      [accountId]: {
        ...prev[accountId],
        [field]: Math.max(0, val),
        [field === 'debit_minor' ? 'credit_minor' : 'debit_minor']:
          val > 0 ? 0 : prev[accountId]?.[field === 'debit_minor' ? 'credit_minor' : 'debit_minor'] ?? 0,
      },
    }));
  };

  const totalDebit = Object.values(balancesState).reduce((sum, b) => sum + (b?.debit_minor || 0), 0);
  const totalCredit = Object.values(balancesState).reduce((sum, b) => sum + (b?.credit_minor || 0), 0);
  const difference = Math.abs(totalDebit - totalCredit);
  const isBalanced = totalDebit === totalCredit && totalDebit > 0;

  function submitDraft(e: FormEvent) {
    e.preventDefault();
    if (isAlreadyPosted) return;

    const payload = Object.entries(balancesState).map(([accountId, val]) => ({
      account_id: accountId,
      debit_minor: val.debit_minor,
      credit_minor: val.credit_minor,
    }));

    saveForm.setData('balances', payload);
    saveForm.post('/accounting/opening-balances');
  }

  function submitPost() {
    if (!isBalanced || isAlreadyPosted) return;
    postForm.post('/accounting/opening-balances/post');
  }

  const fiscalYearOptions = fiscalYears.map((fy) => ({
    value: fy.id,
    label: `${accDict.fiscalYear || (locale === 'ar' ? 'السنة المالية' : 'Fiscal Year')} ${fy.year}`,
  }));

  return (
    <AppLayout active="accounting.opening_balances">
      <Head title={accDict.openingBalances || (locale === 'ar' ? 'الأرصدة الافتتاحية' : 'Opening Balances')} />

      <PageHeader
        title={accDict.openingBalances || (locale === 'ar' ? 'الأرصدة الافتتاحية' : 'Opening Balances')}
        description={
          accDict.openingBalancesDesc ||
          (locale === 'ar'
            ? 'تعيين الأرصدة الافتتاحية على مستوى الحسابات للسنوات المالية الجديدة وترحيل قيد التأسيس الافتتاحي.'
            : 'Set account-level initial balances for new fiscal years and post opening journal entry.')
        }
        actions={
          <button
            type="button"
            onClick={submitPost}
            disabled={!isBalanced || isAlreadyPosted || postForm.processing}
            className="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-xs font-bold text-white shadow-md shadow-emerald-500/20 hover:bg-emerald-700 transition-all cursor-pointer disabled:opacity-40"
          >
            <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
            </svg>
            <span>
              {accDict.postOpeningJournal || (locale === 'ar' ? 'ترحيل الأرصدة الافتتاحية' : 'Post Opening Journal to Ledger')}
            </span>
          </button>
        }
      />

      {fiscalYears.length === 0 && (
        <div className="mb-6 rounded-2xl border border-amber-500/30 bg-amber-500/10 p-4 text-xs font-bold text-amber-700 dark:text-amber-300 flex items-center gap-3">
          <svg className="size-5 shrink-0 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
          <span>
            {accDict.noFiscalYearsWarning || (locale === 'ar' ? 'لا توجد سنوات مالية. يرجى إنشاء سنة مالية أولاً.' : 'No fiscal years found. Please create a fiscal year first.')}
          </span>
        </div>
      )}

      {isAlreadyPosted && (
        <div className="mb-6 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-xs font-bold text-emerald-700 dark:text-emerald-300 flex items-center gap-3">
          <svg className="size-5 shrink-0 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span>
            {accDict.balancesAlreadyPosted || (locale === 'ar' ? 'تم ترحيل الأرصدة الافتتاحية لهذه السنة المالية سابقاً إلى دفتر الاستاد.' : 'Opening balances for this fiscal year have already been posted to the general ledger.')}
          </span>
        </div>
      )}

      <Card className="p-4 mb-6">
        <div className="flex flex-wrap items-center justify-between gap-4">
          <div className="flex items-center gap-3">
            <span className="text-xs font-bold text-[var(--text-secondary)] uppercase">
              {accDict.fiscalYear || (locale === 'ar' ? 'السنة المالية:' : 'Fiscal Year:')}
            </span>
            <div className="w-64 sm:w-80">
              <SearchableSelect
                options={fiscalYearOptions}
                value={activeYearId}
                onChange={(val) => {
                  if (val) {
                    router.get('/accounting/opening-balances', { fiscal_year_id: val }, { preserveState: false });
                  }
                }}
                isClearable={false}
              />
            </div>
          </div>

          <div className="flex flex-wrap items-center gap-4 font-mono text-xs font-bold">
            <div>
              <span className="text-[var(--text-muted)] uppercase me-1.5 font-sans">
                {accDict.totalDebit || (locale === 'ar' ? 'إجمالي المدين:' : 'Total Debits:')}
              </span>
              <span className="text-blue-600 dark:text-blue-400 text-sm font-extrabold">{totalDebit.toLocaleString()}</span>
            </div>
            <div className="h-5 w-px bg-[var(--border)]" />
            <div>
              <span className="text-[var(--text-muted)] uppercase me-1.5 font-sans">
                {accDict.totalCredit || (locale === 'ar' ? 'إجمالي الدائن:' : 'Total Credits:')}
              </span>
              <span className="text-indigo-600 dark:text-indigo-400 text-sm font-extrabold">{totalCredit.toLocaleString()}</span>
            </div>
            {difference > 0 && (
              <>
                <div className="h-5 w-px bg-[var(--border)]" />
                <div>
                  <span className="text-red-500 uppercase me-1.5 font-sans">
                    {accDict.difference || (locale === 'ar' ? 'الفرق:' : 'Difference:')}
                  </span>
                  <span className="text-red-500 text-sm font-extrabold">{difference.toLocaleString()}</span>
                </div>
              </>
            )}
            <StatusBadge tone={isBalanced ? 'ok' : 'danger'}>
              {isBalanced ? (accDict.balanced || (locale === 'ar' ? 'متوازن' : 'BALANCED')) : (accDict.unbalanced || (locale === 'ar' ? 'غير متوازن' : 'UNBALANCED'))}
            </StatusBadge>
          </div>
        </div>
      </Card>

      {accounts.length === 0 ? (
        <EmptyState
          title={accDict.noOpeningBalancesConfigured || (locale === 'ar' ? 'لم يتم إعداد الأرصدة الافتتاحية بعد.' : 'No opening balances have been configured yet.')}
          description={accDict.noOpeningBalancesConfiguredDesc || (locale === 'ar' ? 'يرجى إعداد الحسابات والسنة المالية لتخصيص الأرصدة الافتتاحية.' : 'Configure accounts and fiscal year to set initial opening balances.')}
        />
      ) : (
        <form onSubmit={submitDraft}>
          <div className={tableClasses.wrap + ' mb-6'}>
            <table className={tableClasses.table}>
              <thead>
                <tr>
                  <th className={tableClasses.th}>{accDict.accountCode || (locale === 'ar' ? 'رمز الحساب' : 'Code')}</th>
                  <th className={tableClasses.th}>{accDict.accountName || (locale === 'ar' ? 'اسم الحساب' : 'Account Name')}</th>
                  <th className={tableClasses.th}>{accDict.typeAndNature || (locale === 'ar' ? 'النوع / الطبيعة' : 'Type / Nature')}</th>
                  <th className={`${tableClasses.th} text-right`}>
                    {accDict.openingDebitMinor || (locale === 'ar' ? 'مدين افتتاحي (أصغر)' : 'Opening Debit (Minor)')}
                  </th>
                  <th className={`${tableClasses.th} text-right`}>
                    {accDict.openingCreditMinor || (locale === 'ar' ? 'دائن افتتاحي (أصغر)' : 'Opening Credit (Minor)')}
                  </th>
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
                        <span className="font-bold text-xs text-[var(--text-primary)]">{getLocalizedName(acc.name, locale)}</span>
                      </td>
                      <td className={tableClasses.td}>
                        <span className="text-xs font-bold px-2 py-0.5 rounded-lg bg-[var(--background)] text-[var(--text-secondary)] border border-[var(--border)]">
                          {getAccountTypeLabel(acc.type, locale)} ({getAccountNatureLabel(acc.nature, locale)})
                        </span>
                      </td>
                      <td className={`${tableClasses.td} text-right`}>
                        <input
                          type="number"
                          min="0"
                          disabled={isAlreadyPosted}
                          value={bal.debit_minor}
                          onChange={(e) => updateBalance(acc.id, 'debit_minor', parseInt(e.target.value) || 0)}
                          className="w-36 text-right rounded-lg border border-[var(--border)] bg-[var(--surface)] px-2.5 py-1.5 font-mono text-xs text-[var(--text-primary)] disabled:opacity-50"
                        />
                      </td>
                      <td className={`${tableClasses.td} text-right`}>
                        <input
                          type="number"
                          min="0"
                          disabled={isAlreadyPosted}
                          value={bal.credit_minor}
                          onChange={(e) => updateBalance(acc.id, 'credit_minor', parseInt(e.target.value) || 0)}
                          className="w-36 text-right rounded-lg border border-[var(--border)] bg-[var(--surface)] px-2.5 py-1.5 font-mono text-xs text-[var(--text-primary)] disabled:opacity-50"
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
              disabled={saveForm.processing || isAlreadyPosted}
              className="rounded-xl bg-[var(--primary)] px-6 py-2.5 text-xs font-bold text-white shadow-md shadow-blue-500/20 hover:opacity-90 disabled:opacity-40 transition-colors cursor-pointer"
            >
              {accDict.saveDraft || (locale === 'ar' ? 'حفظ مسودة الأرصدة' : 'Save Draft Balances')}
            </button>
          </div>
        </form>
      )}
    </AppLayout>
  );
}
