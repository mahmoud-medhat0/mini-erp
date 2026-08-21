import { Head, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import { Card, PageHeader, SearchableSelect } from '../../Components/Primitives';
import { getDictionary } from '../../lib/i18n';
import type { SharedPageProps } from '../../Types/page';

type AccountOption = {
  id: string;
  code: string;
  name: Record<string, string> | string;
  nature: string;
  is_control: boolean;
};

type PeriodOption = {
  id: string;
  month: number;
  start_date: string;
  end_date: string;
  fiscal_year?: {
    year: number;
  } | null;
};

type CurrencyOption = {
  code: string;
  name: Record<string, string> | string;
  symbol: string;
};

type JournalFormProps = SharedPageProps & {
  periods: PeriodOption[];
  accounts: AccountOption[];
  currencies?: CurrencyOption[];
};

export default function JournalForm({ locale, periods = [], accounts = [], currencies = [] }: JournalFormProps) {
  const dict = getDictionary(locale);
  const accDict = (dict.app as any).accounting || {};
  const actionsDict = dict.app.actions || {};

  const { data, setData, post, processing } = useForm({
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

  const getName = (nameObj: Record<string, string> | string) => {
    if (!nameObj) return '';
    if (typeof nameObj === 'string') return nameObj;
    return locale === 'ar' ? nameObj.ar || nameObj.en : nameObj.en || nameObj.ar;
  };

  const periodSelectOptions = periods.map((p) => ({
    value: p.id,
    label: p.fiscal_year ? `${p.fiscal_year.year} - M${p.month} (${p.start_date} to ${p.end_date})` : `M${p.month} (${p.start_date} to ${p.end_date})`,
  }));

  const accountSelectOptions = accounts.map((a) => ({
    value: a.id,
    label: `${a.code} - ${getName(a.name)} ${a.is_control ? '(CONTROL)' : ''}`,
  }));

  const currencySelectOptions = currencies.map((c) => ({
    value: c.code,
    label: `${c.code} - ${getName(c.name)} (${c.symbol})`,
  }));

  return (
    <AppLayout active="accounting.journal">
      <Head title={accDict.createVoucher || 'Create Journal Voucher'} />

      <PageHeader
        title={accDict.createVoucher || 'Create Journal Voucher'}
        description="Draft a double-entry manual journal voucher with line item debit/credit validation."
      />

      {/* Live Balance Summary Bar */}
      <Card className="p-4 mb-6 border border-[var(--border)] bg-gradient-to-r from-[var(--surface)] to-[var(--background)]">
        <div className="flex flex-wrap items-center justify-between gap-4">
          <div className="flex flex-wrap items-center gap-6 font-mono text-xs">
            <div>
              <span className="text-[var(--text-muted)] uppercase me-2 font-sans font-bold">{accDict.totalDebit || 'Total Debit'}:</span>
              <span className="font-bold text-lg text-blue-600 dark:text-blue-400">{totalDebit}</span>
            </div>
            <div className="h-6 w-px bg-[var(--border)]" />
            <div>
              <span className="text-[var(--text-muted)] uppercase me-2 font-sans font-bold">{accDict.totalCredit || 'Total Credit'}:</span>
              <span className="font-bold text-lg text-indigo-600 dark:text-indigo-400">{totalCredit}</span>
            </div>
            {difference > 0 ? (
              <>
                <div className="h-6 w-px bg-[var(--border)]" />
                <div>
                  <span className="text-red-500 uppercase me-2 font-sans font-bold">Difference:</span>
                  <span className="font-bold text-lg text-red-500">{difference}</span>
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
              <span>{isBalanced ? (accDict.balanced || 'BALANCED') : (accDict.unbalanced || 'UNBALANCED')}</span>
            </span>
          </div>
        </div>
      </Card>

      <form onSubmit={submit}>
        <Card className="p-6 mb-6">
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {accDict.entryDate || 'Entry Date'}
              </label>
              <input
                type="date"
                value={data.entry_date}
                onChange={(e) => setData('entry_date', e.target.value)}
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2.5 text-xs text-[var(--text-primary)] font-mono"
                required
              />
            </div>
            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {accDict.financialPeriod || 'Financial Period'}
              </label>
              <SearchableSelect
                options={periodSelectOptions}
                value={data.financial_period_id}
                onChange={(val) => setData('financial_period_id', val || '')}
                isClearable={false}
              />
            </div>
            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {accDict.reference || 'Reference'}
              </label>
              <input
                type="text"
                value={data.reference}
                onChange={(e) => setData('reference', e.target.value)}
                placeholder="e.g. REF-1001"
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2.5 text-xs text-[var(--text-primary)] font-mono"
              />
            </div>
            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {accDict.currency || 'Currency'}
              </label>
              <SearchableSelect
                options={currencySelectOptions}
                value={data.currency}
                onChange={(val) => setData('currency', val || currencies[0]?.code || 'EGP')}
                isClearable={false}
              />
            </div>
            <div className="sm:col-span-2 lg:col-span-4">
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {accDict.descriptionMemo || 'Description / Memo'}
              </label>
              <input
                type="text"
                value={data.description}
                onChange={(e) => setData('description', e.target.value)}
                placeholder="Brief summary of transaction purpose..."
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2.5 text-xs text-[var(--text-primary)]"
              />
            </div>
          </div>

          <div className="flex items-center justify-between border-b border-[var(--border)] pb-3 mb-4">
            <div className="flex items-center gap-2">
              <h3 className="m-0 text-sm font-bold text-[var(--text-primary)]">{accDict.journalLines || 'Journal Lines'}</h3>
              <span className="font-mono text-xs text-[var(--text-muted)]">({data.lines.length} lines)</span>
            </div>
            <button
              type="button"
              onClick={addLine}
              className="inline-flex items-center gap-1.5 rounded-xl border border-[var(--border)] bg-[var(--surface)] px-3.5 py-2 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] active:scale-95 transition-all"
            >
              <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
              </svg>
              <span>{accDict.addLine || 'Add Line'}</span>
            </button>
          </div>

          <div className="space-y-3">
            {data.lines.map((line, idx) => (
              <div key={idx} className="grid gap-3 sm:grid-cols-12 items-center bg-[var(--background)]/50 p-3.5 rounded-2xl border border-[var(--border)] hover:border-blue-500/30 transition-all">
                <div className="sm:col-span-1 text-center font-mono text-xs font-bold text-[var(--text-muted)]">
                  #{idx + 1}
                </div>
                <div className="sm:col-span-4">
                  <label className="block text-[10px] font-bold text-[var(--text-muted)] uppercase mb-1 sm:hidden">
                    {accDict.account || 'Account'}
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
                    {accDict.debitMinor || 'Debit (Minor)'}
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
                    {accDict.creditMinor || 'Credit (Minor)'}
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
                    {accDict.lineMemo || 'Line Memo'}
                  </label>
                  <input
                    type="text"
                    value={line.memo || ''}
                    onChange={(e) => updateLine(idx, 'memo', e.target.value)}
                    placeholder="Memo..."
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--surface)] px-3.5 py-2 text-xs text-[var(--text-primary)]"
                  />
                </div>
                <div className="sm:col-span-1 flex justify-end">
                  <button
                    type="button"
                    onClick={() => removeLine(idx)}
                    disabled={data.lines.length <= 2}
                    className="p-2 rounded-lg text-[var(--text-muted)] hover:bg-red-500/10 hover:text-red-500 disabled:opacity-30 transition-all"
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
            <button
              type="submit"
              disabled={processing || !isBalanced}
              className="rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-7 py-3 text-xs font-bold text-white shadow-lg shadow-blue-500/25 disabled:opacity-40 active:scale-95 transition-all"
            >
              {accDict.saveDraft || 'Save Draft Journal'}
            </button>
          </div>
        </Card>
      </form>
    </AppLayout>
  );
}
