import { Head, Link, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import { Card, PageHeader, SearchableSelect } from '../../Components/Primitives';
import { formatDate, formatPeriodLabel, getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import type { AccountOption, CurrencyOption, PeriodOption, SharedPageProps } from '../../Types';

type JournalFormProps = SharedPageProps & {
  periods: PeriodOption[];
  accounts: AccountOption[];
  currencies?: CurrencyOption[];
};

export default function JournalForm({ locale, periods = [], accounts = [], currencies = [] }: JournalFormProps) {
  const dict = getDictionary(locale);
  const accDict = (dict.app as any).accounting || {};
  const actionsDict = dict.app.actions || {};
  const fieldsDict = dict.app.fields || {};

  const { data, setData, post, processing, errors } = useForm({
    entry_date: new Date().toISOString().split('T')[0],
    financial_period_id: periods[0]?.id ?? '',
    description: '',
    reference: '',
    currency: currencies[0]?.code ?? 'EGP',
    lines: [
      { account_id: accounts[0]?.id ?? '', debit_minor: 0, credit_minor: 0, memo: '' },
      { account_id: accounts[1]?.id ?? '', debit_minor: 0, credit_minor: 0, memo: '' },
    ],
  });

  const addLine = () => {
    setData('lines', [
      ...data.lines,
      { account_id: accounts[0]?.id ?? '', debit_minor: 0, credit_minor: 0, memo: '' },
    ]);
  };

  const removeLine = (index: number) => {
    if (data.lines.length <= 2) return;
    setData('lines', data.lines.filter((_, i) => i !== index));
  };

  const updateLine = (index: number, field: string, value: any) => {
    const updated = [...data.lines];
    updated[index] = { ...updated[index], [field]: value };
    setData('lines', updated);
  };

  const totalDebit = data.lines.reduce((sum, l) => sum + (Number(l.debit_minor) || 0), 0);
  const totalCredit = data.lines.reduce((sum, l) => sum + (Number(l.credit_minor) || 0), 0);
  const difference = Math.abs(totalDebit - totalCredit);
  const isBalanced = totalDebit === totalCredit && totalDebit > 0;

  function submit(e: FormEvent) {
    e.preventDefault();
    if (!isBalanced) return;
    post('/accounting/journal');
  }

  const periodSelectOptions = periods.map((p) => ({
    value: p.id,
    label: formatPeriodLabel(p, locale),
  }));

  const accountSelectOptions = accounts.map((a) => ({
    value: a.id,
    label: `${a.code} - ${getLocalizedName(a.name, locale)} ${a.is_control ? `(${accDict.isControlAccount || 'CONTROL'})` : ''}`,
  }));

  const currencySelectOptions = currencies.map((c) => ({
    value: c.code,
    label: `${c.code} - ${getLocalizedName(c.name, locale)} (${c.symbol})`,
  }));

  return (
    <AppLayout active="accounting.journal">
      <Head title={accDict.createVoucher || (locale === 'ar' ? 'إنشاء قيد يومية' : 'Create Journal Voucher')} />

      <PageHeader
        title={accDict.createVoucher || (locale === 'ar' ? 'إنشاء قيد يومية' : 'Create Journal Voucher')}
        description={accDict.createVoucherDesc || (locale === 'ar' ? 'إنشاء قيد يومية يدوي مزدوج الإدخال مع التحقق من توازن المدين والدائن.' : 'Draft a double-entry manual journal voucher with line item debit/credit validation.')}
        actions={
          <Link
            href="/accounting/journal"
            className="inline-flex items-center gap-2 rounded-xl border border-[var(--border)] bg-[var(--surface)] px-4 py-2.5 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] transition-all no-underline shadow-xs cursor-pointer"
          >
            <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            <span>{accDict.journal || (locale === 'ar' ? 'قيود اليومية' : 'General Journal')}</span>
          </Link>
        }
      />

      {periods.length === 0 && (
        <div className="mb-6 rounded-2xl border border-amber-500/30 bg-amber-500/10 p-4 text-xs font-bold text-amber-700 dark:text-amber-300 flex items-center gap-3">
          <svg className="size-5 shrink-0 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
          <span>{accDict.noOpenPeriodsWarning || (locale === 'ar' ? 'لا توجد فترة مالية مفتوحة. يرجى فتح فترة مالية أولاً.' : 'No open financial period found. Please open a fiscal period first.')}</span>
        </div>
      )}

      {/* Live Balance Summary Bar */}
      <Card className="p-4 mb-6 border border-[var(--border)] bg-gradient-to-r from-[var(--surface)] to-[var(--background)]">
        <div className="flex flex-wrap items-center justify-between gap-4">
          <div className="flex flex-wrap items-center gap-6 font-mono text-xs">
            <div>
              <span className="text-[var(--text-muted)] uppercase me-2 font-sans font-bold">{accDict.totalDebit || (locale === 'ar' ? 'إجمالي المدين' : 'Total Debit')}:</span>
              <span className="font-bold text-lg text-blue-600 dark:text-blue-400">{totalDebit.toLocaleString()}</span>
            </div>
            <div className="h-6 w-px bg-[var(--border)]" />
            <div>
              <span className="text-[var(--text-muted)] uppercase me-2 font-sans font-bold">{accDict.totalCredit || (locale === 'ar' ? 'إجمالي الدائن' : 'Total Credit')}:</span>
              <span className="font-bold text-lg text-indigo-600 dark:text-indigo-400">{totalCredit.toLocaleString()}</span>
            </div>
            {difference > 0 ? (
              <>
                <div className="h-6 w-px bg-[var(--border)]" />
                <div>
                  <span className="text-red-500 uppercase me-2 font-sans font-bold">{accDict.difference || (locale === 'ar' ? 'الفرق' : 'Difference')}:</span>
                  <span className="font-bold text-lg text-red-500">{difference.toLocaleString()}</span>
                </div>
              </>
            ) : null}
          </div>

          <div className="flex items-center gap-3">
            <span className={`font-bold px-3 py-1.5 rounded-xl text-xs flex items-center gap-2 ${
              isBalanced
                ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30'
                : 'bg-red-500/15 text-red-600 dark:text-red-400 border border-red-500/30 animate-pulse'
            }`}>
              <div className={`size-2 rounded-full ${isBalanced ? 'bg-emerald-500' : 'bg-red-500'}`} />
              <span>{isBalanced ? (accDict.balanced || (locale === 'ar' ? 'متوازن' : 'BALANCED')) : (accDict.unbalanced || (locale === 'ar' ? 'غير متوازن' : 'UNBALANCED'))}</span>
            </span>
          </div>
        </div>
      </Card>

      <form onSubmit={submit}>
        <Card className="p-6 mb-6">
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {accDict.entryDate || (locale === 'ar' ? 'تاريخ القيد' : 'Entry Date')}
              </label>
              <input
                type="date"
                value={data.entry_date}
                onChange={(e) => setData('entry_date', e.target.value)}
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2.5 text-xs text-[var(--text-primary)] font-mono"
                required
              />
              {errors.entry_date && <p className="mt-1 text-xs text-red-500">{errors.entry_date}</p>}
            </div>

            <div className="sm:col-span-1 lg:col-span-2">
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {accDict.financialPeriod || (locale === 'ar' ? 'الفترة المالية' : 'Financial Period')}
              </label>
              <SearchableSelect
                options={periodSelectOptions}
                value={data.financial_period_id}
                onChange={(val) => setData('financial_period_id', val || '')}
                isClearable={false}
              />
              {errors.financial_period_id && <p className="mt-1 text-xs text-red-500">{errors.financial_period_id}</p>}
            </div>

            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {accDict.reference || (locale === 'ar' ? 'المرجع' : 'Reference')}
              </label>
              <input
                type="text"
                value={data.reference}
                onChange={(e) => setData('reference', e.target.value)}
                placeholder="e.g. REF-1001"
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2.5 text-xs text-[var(--text-primary)] font-mono"
              />
              {errors.reference && <p className="mt-1 text-xs text-red-500">{errors.reference}</p>}
            </div>

            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {accDict.currency || (locale === 'ar' ? 'العملة' : 'Currency')}
              </label>
              <SearchableSelect
                options={currencySelectOptions}
                value={data.currency}
                onChange={(val) => setData('currency', val || currencies[0]?.code || 'EGP')}
                isClearable={false}
              />
              {errors.currency && <p className="mt-1 text-xs text-red-500">{errors.currency}</p>}
            </div>

            <div className="sm:col-span-2 lg:col-span-4">
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {accDict.descriptionMemo || (locale === 'ar' ? 'الوصف / البيان' : 'Description / Memo')}
              </label>
              <input
                type="text"
                value={data.description}
                onChange={(e) => setData('description', e.target.value)}
                placeholder={locale === 'ar' ? 'ملخص مختصر لغرض المعاملة...' : 'Brief summary of transaction purpose...'}
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2.5 text-xs text-[var(--text-primary)]"
              />
              {errors.description && <p className="mt-1 text-xs text-red-500">{errors.description}</p>}
            </div>
          </div>

          <div className="flex items-center justify-between border-b border-[var(--border)] pb-3 mb-4">
            <div className="flex items-center gap-2">
              <h3 className="m-0 text-sm font-bold text-[var(--text-primary)]">
                {accDict.journalLines || (locale === 'ar' ? 'بنود القيد' : 'Journal Lines')}
              </h3>
              <span className="font-mono text-xs text-[var(--text-muted)]">({data.lines.length})</span>
            </div>
            <button
              type="button"
              onClick={addLine}
              className="inline-flex items-center gap-1.5 rounded-xl border border-[var(--border)] bg-[var(--surface)] px-3.5 py-2 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] active:scale-95 transition-all cursor-pointer"
            >
              <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
              </svg>
              <span>{accDict.addLine || (locale === 'ar' ? 'إضافة بند' : 'Add Line')}</span>
            </button>
          </div>

          {errors.lines && <p className="mb-4 text-xs text-red-500 font-bold">{errors.lines}</p>}

          <div className="space-y-3">
            {data.lines.map((line, idx) => (
              <div key={idx} className="grid gap-3 sm:grid-cols-12 items-center bg-[var(--background)]/50 p-3.5 rounded-2xl border border-[var(--border)] hover:border-blue-500/30 transition-all">
                <div className="sm:col-span-1 text-center font-mono text-xs font-bold text-[var(--text-muted)]">
                  #{idx + 1}
                </div>
                <div className="sm:col-span-4">
                  <label className="block text-[10px] font-bold text-[var(--text-muted)] uppercase mb-1 sm:hidden">
                    {accDict.account || (locale === 'ar' ? 'الحساب' : 'Account')}
                  </label>
                  <SearchableSelect
                    options={accountSelectOptions}
                    value={line.account_id}
                    onChange={(val) => updateLine(idx, 'account_id', val || '')}
                    isClearable={false}
                  />
                </div>
                <div className="sm:col-span-2">
                  <label className="block text-[10px] font-bold text-[var(--text-muted)] uppercase mb-1 sm:hidden">
                    {accDict.debitLabel || (locale === 'ar' ? 'مدين' : 'Debit')}
                  </label>
                  <input
                    type="number"
                    min="0"
                    value={line.debit_minor}
                    onChange={(e) => updateLine(idx, 'debit_minor', Number(e.target.value))}
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--surface)] px-3.5 py-2 text-xs text-[var(--text-primary)] font-mono text-end"
                  />
                </div>
                <div className="sm:col-span-2">
                  <label className="block text-[10px] font-bold text-[var(--text-muted)] uppercase mb-1 sm:hidden">
                    {accDict.creditLabel || (locale === 'ar' ? 'دائن' : 'Credit')}
                  </label>
                  <input
                    type="number"
                    min="0"
                    value={line.credit_minor}
                    onChange={(e) => updateLine(idx, 'credit_minor', Number(e.target.value))}
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--surface)] px-3.5 py-2 text-xs text-[var(--text-primary)] font-mono text-end"
                  />
                </div>
                <div className="sm:col-span-2">
                  <label className="block text-[10px] font-bold text-[var(--text-muted)] uppercase mb-1 sm:hidden">
                    {accDict.lineMemo || (locale === 'ar' ? 'ملاحظات البند' : 'Line Memo')}
                  </label>
                  <input
                    type="text"
                    value={line.memo || ''}
                    onChange={(e) => updateLine(idx, 'memo', e.target.value)}
                    placeholder={locale === 'ar' ? 'ملاحظات...' : 'Memo...'}
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--surface)] px-3.5 py-2 text-xs text-[var(--text-primary)]"
                  />
                </div>
                <div className="sm:col-span-1 flex justify-end">
                  <button
                    type="button"
                    onClick={() => removeLine(idx)}
                    disabled={data.lines.length <= 2}
                    className="p-2 rounded-lg text-[var(--text-muted)] hover:bg-red-500/10 hover:text-red-500 disabled:opacity-30 transition-all cursor-pointer"
                  >
                    <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                      <path strokeLinecap="round" strokeLinejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                </div>
              </div>
            ))}
          </div>

          <div className="mt-6 flex flex-wrap items-center justify-end border-t border-[var(--border)] pt-4 gap-4">
            <Link
              href="/accounting/journal"
              className="rounded-xl border border-[var(--border)] bg-[var(--surface)] px-5 py-2.5 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] transition-colors no-underline"
            >
              {actionsDict.cancel || (locale === 'ar' ? 'إلغاء' : 'Cancel')}
            </Link>
            <button
              type="submit"
              disabled={processing || !isBalanced || periods.length === 0}
              className="rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-7 py-3 text-xs font-bold text-white shadow-lg shadow-blue-500/25 disabled:opacity-40 active:scale-95 transition-all cursor-pointer"
            >
              {accDict.saveDraftJournal || accDict.saveDraft || (locale === 'ar' ? 'حفظ مسودة القيد' : 'Save Draft Journal')}
            </button>
          </div>
        </Card>
      </form>
    </AppLayout>
  );
}
