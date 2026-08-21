import { Head, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { Card, PageHeader, SearchableSelect, tableClasses } from '../../Components/Primitives';
import { getDictionary } from '../../lib/i18n';
import type { CurrencyRow, FxRateRow, SharedPageProps } from '../../Types';

type ExchangeRatesProps = SharedPageProps & {
  rates: {
    data: FxRateRow[];
    links: any[];
  };
  currencies?: CurrencyRow[];
};

export default function ExchangeRates({ locale, rates, currencies = [] }: ExchangeRatesProps) {
  const dict = getDictionary(locale);
  const accDict = (dict.app as any).accounting || {};
  const actionsDict = dict.app.actions || {};

  const [search, setSearch] = useState('');
  const defaultCurrency = currencies.find((c) => c.code !== 'EGP')?.code ?? currencies[0]?.code ?? 'USD';

  const [showModal, setShowModal] = useState(false);
  const form = useForm({
    currency: defaultCurrency,
    date: new Date().toISOString().split('T')[0],
    rate: '',
  });

  function submit(e: FormEvent) {
    e.preventDefault();
    form.post('/accounting/fx-rates', {
      onSuccess: () => {
        setShowModal(false);
        form.reset();
      },
    });
  }

  const getName = (nameObj?: Record<string, string> | string | null) => {
    if (!nameObj) return '';
    if (typeof nameObj === 'string') return nameObj;
    return locale === 'ar' ? nameObj.ar || nameObj.en : nameObj.en || nameObj.ar;
  };

  const currencyOptions = currencies.map((c) => ({
    value: c.code,
    label: `${c.code} - ${getName(c.name)} (${c.symbol})`,
  }));

  const filteredRates = rates.data.filter((r) => {
    const q = search.toLowerCase();
    return (
      r.currency.toLowerCase().includes(q) ||
      r.date.includes(q) ||
      (r.currency_ref && getName(r.currency_ref.name).toLowerCase().includes(q))
    );
  });

  return (
    <AppLayout active="accounting.fx_rates">
      <Head title={accDict.fxRates || 'Exchange Rates'} />

      <PageHeader
        title={accDict.fxRates || 'Exchange Rates'}
        description={accDict.fxRatesDesc || 'Exact exchange rate records stored using rate_e6 scaled 6-decimal integers.'}
        actions={
          <button
            type="button"
            onClick={() => setShowModal(true)}
            className="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-4 py-2.5 text-xs font-bold text-white shadow-md shadow-blue-500/20 hover:opacity-95 active:scale-95 transition-all"
          >
            <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            <span>+ {accDict.setFxRate || 'Set Exchange Rate'}</span>
          </button>
        }
      />

      {/* Summary Cards */}
      <div className="grid gap-4 sm:grid-cols-3 mb-6">
        <Card className="p-5 border border-[var(--border)] hover:border-blue-500/30 transition-all">
          <div className="flex items-center justify-between">
            <div>
              <span className="text-[10px] font-bold uppercase tracking-wider text-[var(--text-muted)]">
                {accDict.baseCurrency || 'Base Currency'}
              </span>
              <p className="mt-1 text-2xl font-black font-mono text-[var(--primary)]">EGP ({accDict.currencyEgpLabel || 'EGP'})</p>
            </div>
            <div className="p-3 rounded-2xl bg-blue-500/10 text-blue-600 dark:text-blue-400 font-mono font-bold text-xs">
              {accDict.baseTag || 'BASE'}
            </div>
          </div>
        </Card>

        <Card className="p-5 border border-[var(--border)] hover:border-indigo-500/30 transition-all">
          <div className="flex items-center justify-between">
            <div>
              <span className="text-[10px] font-bold uppercase tracking-wider text-[var(--text-muted)]">
                {accDict.totalRateEntries || 'Total Rate Entries'}
              </span>
              <p className="mt-1 text-2xl font-black font-mono text-[var(--text-primary)]">{rates.data.length}</p>
            </div>
            <div className="p-3 rounded-2xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400">
              <svg className="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
              </svg>
            </div>
          </div>
        </Card>

        <Card className="p-5 border border-[var(--border)] hover:border-emerald-500/30 transition-all">
          <div className="flex items-center justify-between">
            <div>
              <span className="text-[10px] font-bold uppercase tracking-wider text-[var(--text-muted)]">
                {accDict.activeFxCurrencies || 'Active FX Currencies'}
              </span>
              <p className="mt-1 text-2xl font-black font-mono text-emerald-600 dark:text-emerald-400">
                {new Set(rates.data.map((r) => r.currency)).size}
              </p>
            </div>
            <div className="p-3 rounded-2xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
              <svg className="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
              </svg>
            </div>
          </div>
        </Card>
      </div>

      {/* Add FX Rate Form Modal */}
      {showModal ? (
        <Card className="p-6 mb-6 border-2 border-[var(--primary)]/40 shadow-2xl bg-[var(--surface)]">
          <div className="flex items-center justify-between border-b border-[var(--border)] pb-3 mb-4">
            <h3 className="m-0 text-sm font-bold text-[var(--text-primary)]">
              {accDict.addFxRate || 'Add Exchange Rate Record'}
            </h3>
            <button
              type="button"
              onClick={() => setShowModal(false)}
              className="inline-flex items-center gap-1.5 rounded-xl border border-[var(--border)] bg-[var(--surface)] px-3 py-1.5 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] transition-all cursor-pointer shadow-sm"
            >
              <svg className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
              <span>{actionsDict.cancel || 'Close'}</span>
            </button>
          </div>

          <form onSubmit={submit} className="grid gap-4 sm:grid-cols-3 items-end">
            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {accDict.currency || 'Target Currency'}
              </label>
              <SearchableSelect
                options={currencyOptions}
                value={form.data.currency}
                onChange={(val) => form.setData('currency', val || defaultCurrency)}
                isClearable={false}
              />
              {form.errors.currency ? <p className="text-xs text-red-500 mt-1">{form.errors.currency}</p> : null}
            </div>

            <div>
              <DatePicker
                label={accDict.effectiveDate || 'Effective Date'}
                value={form.data.date}
                onChange={(val) => form.setData('date', val || '')}
                error={form.errors.date}
                required
              />
            </div>

            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {accDict.rateAgainstBase || 'Rate against Base (e.g. 50.25)'}
              </label>
              <input
                type="number"
                step="0.000001"
                min="0.000001"
                placeholder="50.2500"
                value={form.data.rate}
                onChange={(e) => form.setData('rate', e.target.value)}
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2.5 text-xs font-mono"
                required
              />
              {form.errors.rate ? <p className="text-xs text-red-500 mt-1">{form.errors.rate}</p> : null}
            </div>

            <div className="sm:col-span-3 flex justify-end gap-3 mt-2">
              <button
                type="button"
                onClick={() => setShowModal(false)}
                className="rounded-xl border border-[var(--border)] bg-[var(--surface)] px-4.5 py-2.5 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] transition-colors cursor-pointer"
              >
                {actionsDict.cancel || 'Cancel'}
              </button>
              <button
                type="submit"
                disabled={form.processing}
                className="rounded-xl bg-[var(--primary)] px-6 py-2.5 text-xs font-bold text-white shadow-md shadow-blue-500/20 hover:opacity-90 disabled:opacity-50 transition-all cursor-pointer"
              >
                {accDict.saveFxRate || 'Save FX Rate'}
              </button>
            </div>
          </form>
        </Card>
      ) : null}

      {/* Search Bar */}
      <Card className="p-4 mb-6">
        <div className="flex items-center gap-3">
          <div className="relative flex-1">
            <div className="absolute inset-y-0 start-0 flex items-center ps-3.5 pointer-events-none text-[var(--text-muted)]">
              <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </div>
            <input
              type="text"
              placeholder={accDict.searchFxRatesPlaceholder || 'Search FX rates by currency code, name, or date...'}
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] ps-10 pe-3.5 py-2.5 text-xs text-[var(--text-primary)] focus:ring-2 focus:ring-blue-500/20"
            />
          </div>
        </div>
      </Card>

      {/* Table */}
      <div className={tableClasses.wrap}>
        <table className={tableClasses.table}>
          <thead>
            <tr>
              <th className={tableClasses.th}>{accDict.currency || 'Currency'}</th>
              <th className={tableClasses.th}>{accDict.effectiveDate || 'Effective Date'}</th>
              <th className={tableClasses.th}>{accDict.rateDecimal || 'Rate (Decimal)'}</th>
              <th className={tableClasses.th}>{accDict.rateE6 || 'Rate E6 (Scaled Integer)'}</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-[var(--border)]">
            {filteredRates.map((r, idx) => {
              const decimalValue = (r.rate_e6 / 1000000).toFixed(4);
              return (
                <tr key={idx} className="hover:bg-[var(--background)]/50 transition-colors">
                  <td className={tableClasses.td}>
                    <div className="flex items-center gap-2.5">
                      <span className="font-mono font-bold text-xs text-blue-600 dark:text-blue-400 bg-blue-500/10 px-2 py-0.5 rounded-lg border border-blue-500/20">
                        {r.currency}
                      </span>
                      {r.currency_ref ? (
                        <span className="text-xs font-medium text-[var(--text-primary)]">
                          {getName(r.currency_ref.name)} ({r.currency_ref.symbol})
                        </span>
                      ) : null}
                    </div>
                  </td>
                  <td className={tableClasses.td}>
                    <span className="font-mono text-xs text-[var(--text-primary)]">{r.date.split('T')[0]}</span>
                  </td>
                  <td className={tableClasses.td}>
                    <div className="flex items-center gap-2">
                      <span className="font-mono font-bold text-xs text-[var(--primary)]">{decimalValue}</span>
                      <span className="text-[10px] text-[var(--text-muted)] font-mono">
                        (1 {r.currency} = {decimalValue} EGP)
                      </span>
                    </div>
                  </td>
                  <td className={tableClasses.td}>
                    <span className="font-mono text-xs text-[var(--text-muted)]">{r.rate_e6}</span>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
    </AppLayout>
  );
}
