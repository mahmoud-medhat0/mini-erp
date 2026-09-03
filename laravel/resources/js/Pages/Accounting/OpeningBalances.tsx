import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import { Card, EmptyState, PageHeader, SearchableSelect, SensitiveActionModal, StatusBadge, tableClasses } from '../../Components/Primitives';
import { getAccountNatureLabel, getAccountTypeLabel, getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import type { AccountRow, OpeningBalanceRow, SharedPageProps } from '../../Types';

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
  const accDict = dict.app.accounting;

  const activeYearId = selectedYearId ?? fiscalYears[0]?.id ?? '';
  const [searchQuery, setSearchQuery] = useState('');

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
  const [showPostConfirmation, setShowPostConfirmation] = useState(false);

  const saveForm = useForm({
    fiscal_year_id: activeYearId,
    balances: [] as { account_id: string; debit_minor: number; credit_minor: number }[],
  });

  const postForm = useForm({
    fiscal_year_id: activeYearId,
    confirm_action: 'POST_OPENING_BALANCES',
  });

  const isAlreadyPosted = Object.values(existingBalances).some((b) => b.status === 'posted');

  const filteredAccounts = useMemo(() => {
    if (!searchQuery.trim()) return accounts;
    const q = searchQuery.toLowerCase().trim();
    return accounts.filter((acc) => {
      const codeMatch = acc.code.toLowerCase().includes(q);
      const nameMatch = getLocalizedName(acc.name, locale).toLowerCase().includes(q);
      const typeMatch = getAccountTypeLabel(acc.type, locale).toLowerCase().includes(q);
      const natureMatch = getAccountNatureLabel(acc.nature, locale).toLowerCase().includes(q);
      return codeMatch || nameMatch || typeMatch || natureMatch;
    });
  }, [accounts, searchQuery, locale]);

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
  const postingReadinessMessage = isAlreadyPosted
    ? accDict.openingPostBlockedPosted
    : isBalanced
    ? accDict.openingPostReady
    : accDict.openingPostBlockedUnbalanced;

  function submitDraft(e: FormEvent) {
    e.preventDefault();
    if (isAlreadyPosted) return;

    const payload = Object.entries(balancesState).map(([accountId, val]) => ({
      account_id: accountId,
      debit_minor: val.debit_minor,
      credit_minor: val.credit_minor,
    }));

    saveForm.setData('balances', payload);
    saveForm.post('/accounting/opening-balances', { preserveScroll: true });
  }

  function submitPost() {
    if (!isBalanced || isAlreadyPosted) return;
    setShowPostConfirmation(true);
  }

  const fiscalYearOptions = fiscalYears.map((fy) => ({
    value: fy.id,
    label: `${accDict.fiscalYear} ${fy.year}`,
  }));

  return (
    <AppLayout active="accounting.opening_balances">
      <Head title={accDict.openingBalances} />

      <PageHeader
        title={accDict.openingBalances}
        description={accDict.openingBalancesDesc}
        actions={
          <button
            type="button"
            onClick={submitPost}
            disabled={!isBalanced || isAlreadyPosted || postForm.processing}
            title={postingReadinessMessage}
            aria-label={postingReadinessMessage}
            className="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-xs font-bold text-white shadow-md shadow-emerald-500/20 hover:bg-emerald-700 transition-all cursor-pointer disabled:opacity-40"
          >
            <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
            </svg>
            <span>{accDict.postOpeningJournal}</span>
          </button>
        }
      />

      {fiscalYears.length === 0 && (
        <div className="mb-6 rounded-2xl border border-amber-500/30 bg-amber-500/10 p-4 text-xs font-bold text-amber-700 dark:text-amber-300 flex items-center gap-3">
          <svg className="size-5 shrink-0 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
          <span>{accDict.noFiscalYearsWarning}</span>
        </div>
      )}

      {isAlreadyPosted && (
        <div className="mb-6 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-xs font-bold text-emerald-700 dark:text-emerald-300 flex items-center gap-3">
          <svg className="size-5 shrink-0 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span>{accDict.balancesAlreadyPosted}</span>
        </div>
      )}

      <Card className="p-4 mb-6">
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div className="flex flex-wrap items-center gap-3 flex-1">
            <div className="flex items-center gap-2">
              <span className="text-xs font-bold text-[var(--text-secondary)] uppercase whitespace-nowrap">
                {accDict.fiscalYear}
              </span>
              <div className="w-48 sm:w-60">
                <SearchableSelect
                  options={fiscalYearOptions}
                  value={activeYearId}
                  onChange={(val) => {
                    if (val) {
                      router.get('/accounting/opening-balances', { fiscal_year_id: val }, { preserveState: false, preserveScroll: true });
                    }
                  }}
                  isClearable={false}
                />
              </div>
            </div>

            <div className="relative flex-1 min-w-[200px] max-w-sm">
              <div className="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-[var(--text-muted)]">
                <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
              </div>
              <input
                type="text"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder={locale === 'ar' ? 'البحث بكود أو اسم الحساب...' : 'Search by account code or name...'}
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] ps-9 pe-9 py-2 text-xs font-semibold text-[var(--text-primary)] placeholder-[var(--text-muted)] focus:border-[var(--primary)] focus:outline-none focus:ring-1 focus:ring-[var(--primary)] transition-all"
              />
              {searchQuery && (
                <button
                  type="button"
                  onClick={() => setSearchQuery('')}
                  className="absolute inset-y-0 end-0 flex items-center pe-3 text-[var(--text-muted)] hover:text-[var(--text-primary)] cursor-pointer"
                  title={locale === 'ar' ? 'مسح البحث' : 'Clear search'}
                >
                  <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              )}
            </div>
          </div>

          <div className="flex flex-wrap items-center gap-4 font-mono text-xs font-bold shrink-0">
            <div>
              <span className="text-[var(--text-muted)] uppercase me-1.5 font-sans">
                {accDict.totalDebit}
              </span>
              <span className="text-blue-600 dark:text-blue-400 text-sm font-extrabold">{totalDebit.toLocaleString()}</span>
            </div>
            <div className="h-5 w-px bg-[var(--border)]" />
            <div>
              <span className="text-[var(--text-muted)] uppercase me-1.5 font-sans">
                {accDict.totalCredit}
              </span>
              <span className="text-indigo-600 dark:text-indigo-400 text-sm font-extrabold">{totalCredit.toLocaleString()}</span>
            </div>
            {difference > 0 && (
              <>
                <div className="h-5 w-px bg-[var(--border)]" />
                <div>
                  <span className="text-red-500 uppercase me-1.5 font-sans">
                    {accDict.difference}
                  </span>
                  <span className="text-red-500 text-sm font-extrabold">{difference.toLocaleString()}</span>
                </div>
              </>
            )}
            <StatusBadge tone={isBalanced ? 'ok' : 'danger'}>
              {isBalanced ? accDict.balanced : accDict.unbalanced}
            </StatusBadge>
          </div>
        </div>
      </Card>

      {accounts.length === 0 ? (
        <EmptyState
          title={accDict.noOpeningBalancesConfigured}
          description={accDict.noOpeningBalancesConfiguredDesc}
        />
      ) : filteredAccounts.length === 0 ? (
        <Card className="p-8 text-center mb-6">
          <svg className="size-8 mx-auto text-[var(--text-muted)] mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
          <p className="text-xs font-bold text-[var(--text-secondary)] mb-1">
            {locale === 'ar' ? 'لا توجد نتائج تطابق البحث' : 'No accounts found matching your search'}
          </p>
          <button
            type="button"
            onClick={() => setSearchQuery('')}
            className="text-xs font-bold text-blue-600 dark:text-blue-400 hover:underline cursor-pointer"
          >
            {locale === 'ar' ? 'إعادة ضبط البحث' : 'Reset search'}
          </button>
        </Card>
      ) : (
        <form onSubmit={submitDraft}>
          <div className={tableClasses.wrap + ' mb-6'}>
            <table className={tableClasses.table}>
              <thead>
                <tr>
                  <th className={tableClasses.th}>{accDict.accountCode}</th>
                  <th className={tableClasses.th}>{accDict.accountName}</th>
                  <th className={tableClasses.th}>{accDict.typeAndNature}</th>
                  <th className={`${tableClasses.th} text-end`}>
                    {accDict.openingDebitMinor}
                  </th>
                  <th className={`${tableClasses.th} text-end`}>
                    {accDict.openingCreditMinor}
                  </th>
                </tr>
              </thead>
              <tbody className="divide-y divide-[var(--border)]">
                {filteredAccounts.map((acc) => {
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
                      <td className={`${tableClasses.td} text-end`}>
                        <input
                          type="number"
                          min="0"
                          disabled={isAlreadyPosted}
                          value={bal.debit_minor}
                          onChange={(e) => updateBalance(acc.id, 'debit_minor', parseInt(e.target.value) || 0)}
                          className="w-36 text-end rounded-lg border border-[var(--border)] bg-[var(--surface)] px-2.5 py-1.5 font-mono text-xs text-[var(--text-primary)] disabled:opacity-50"
                        />
                      </td>
                      <td className={`${tableClasses.td} text-end`}>
                        <input
                          type="number"
                          min="0"
                          disabled={isAlreadyPosted}
                          value={bal.credit_minor}
                          onChange={(e) => updateBalance(acc.id, 'credit_minor', parseInt(e.target.value) || 0)}
                          className="w-36 text-end rounded-lg border border-[var(--border)] bg-[var(--surface)] px-2.5 py-1.5 font-mono text-xs text-[var(--text-primary)] disabled:opacity-50"
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
              title={accDict.saveDraft}
              aria-label={accDict.saveDraft}
              className="rounded-xl bg-[var(--primary)] px-6 py-2.5 text-xs font-bold text-white shadow-md shadow-blue-500/20 hover:opacity-90 disabled:opacity-40 transition-colors cursor-pointer"
            >
              {accDict.saveDraft}
            </button>
          </div>
        </form>
      )}

      <SensitiveActionModal
        isOpen={showPostConfirmation}
        onClose={() => setShowPostConfirmation(false)}
        onConfirm={(payload) => {
          postForm.setData('confirm_action', payload.confirm_action);
          postForm.post('/accounting/opening-balances/post', {
            preserveScroll: true,
            onSuccess: () => setShowPostConfirmation(false),
          });
        }}
        confirmCode="POST_OPENING_BALANCES"
        message={accDict.confirmPostOpeningJournal}
        isProcessing={postForm.processing}
        locale={locale}
      />
    </AppLayout>
  );
}
