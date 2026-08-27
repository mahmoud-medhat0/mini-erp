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
  baseCurrency?: string | null;
  baseCurrencyRef?: CurrencyRow | null;
};

export default function ExchangeRates({ locale, rates, currencies = [], baseCurrency = null, baseCurrencyRef = null }: ExchangeRatesProps) {
  const dict = getDictionary(locale);
  const accDict = dict.app.accounting;
  const actionsDict = dict.app.actions;

  const [search, setSearch] = useState('');
  const baseCurrencyCode = baseCurrency ?? '';
  const foreignCurrencies = currencies.filter((c) => c.code !== baseCurrencyCode);
  const defaultCurrency = foreignCurrencies[0]?.code ?? '';
  const baseCurrencyLabel = baseCurrencyRef ? `${baseCurrencyRef.code} (${baseCurrencyRef.symbol})` : (baseCurrencyCode || accDict.noBaseCurrency);
  const baseCurrencyDisplay = baseCurrencyCode || accDict.noBaseCurrency;

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

  const currencyOptions = foreignCurrencies.map((c) => ({
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
      <Head title={accDict.fxRates} />

      <PageHeader
        title={accDict.fxRates}
        description={accDict.fxRatesDesc}
        actions={
          <button
            type="button"
            onClick={() => setShowModal(true)}
            className="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-4 py-2.5 text-xs font-bold text-white shadow-md shadow-blue-500/20 hover:opacity-95 active:scale-95 transition-all"
          >
            <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            <span>{accDict.setFxRate}</span>
          </button>
        }
      />

      {/* Summary Cards */}
      <div className="grid gap-4 sm:grid-cols-3 mb-6">
        <Card className="p-5 border border-[var(--border)] hover:border-blue-500/30 transition-all">
          <div className="flex items-center justify-between">
            <div>
              <span className="text-[10px] font-bold uppercase tracking-wider text-[var(--text-muted)]">
                {accDict.baseCurrency}
              </span>
              <p className="mt-1 text-2xl font-black font-mono text-[var(--primary)]">{baseCurrencyLabel}</p>
            </div>
            <div className="p-3 rounded-2xl bg-blue-500/10 text-blue-600 dark:text-blue-400 font-mono font-bold text-xs">
              {accDict.baseTag}
            </div>
          </div>
        </Card>

        <Card className="p-5 border border-[var(--border)] hover:border-indigo-500/30 transition-all">
          <div className="flex items-center justify-between">
            <div>
              <span className="text-[10px] font-bold uppercase tracking-wider text-[var(--text-muted)]">
                {accDict.totalRateEntries}
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
                {accDict.activeFxCurrencies}
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
              {accDict.addFxRate}
            </h3>
            <button
              type="button"
              onClick={() => setShowModal(false)}
              className="inline-flex items-center gap-1.5 rounded-xl border border-[var(--border)] bg-[var(--surface)] px-3 py-1.5 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] transition-all cursor-pointer shadow-sm"
            >
              <svg className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
              <span>{actionsDict.close}</span>
            </button>
          </div>

          <form onSubmit={submit} className="grid gap-4 sm:grid-cols-3 items-end">
            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {accDict.targetCurrency}
              </label>
              {currencyOptions.length === 0 ? (
                <p className="mb-2 rounded-lg border border-amber-500/20 bg-amber-500/10 px-3 py-2 text-xs font-bold text-amber-700 dark:text-amber-300">
                  {accDict.noForeignCurrencyOptions}
                </p>
              ) : null}
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
                label={accDict.effectiveDate}
                value={form.data.date}
                onChange={(val) => form.setData('date', val || '')}
                error={form.errors.date}
                required
              />
            </div>

            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {accDict.rateAgainstBaseWithCurrency.replace('{currency}', baseCurrencyDisplay)}
              </label>
              <input
                type="number"
                step="0.000001"
                min="0.000001"
                placeholder={accDict.fxRatePlaceholder}
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
                {actionsDict.cancel}
              </button>
              <button
                type="submit"
                disabled={form.processing || currencyOptions.length === 0}
                className="rounded-xl bg-[var(--primary)] px-6 py-2.5 text-xs font-bold text-white shadow-md shadow-blue-500/20 hover:opacity-90 disabled:opacity-50 transition-all cursor-pointer"
              >
                {accDict.saveFxRate}
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
              placeholder={accDict.searchFxRatesPlaceholder}
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
              <th className={tableClasses.th}>{accDict.currency}</th>
              <th className={tableClasses.th}>{accDict.effectiveDate}</th>
              <th className={tableClasses.th}>{accDict.rateDecimal}</th>
              <th className={tableClasses.th}>{accDict.rateE6}</th>
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
                        {accDict.fxConversionLine
                          .replace('{currency}', r.currency)
                          .replace('{rate}', decimalValue)
                          .replace('{baseCurrency}', baseCurrencyDisplay)}
                      </span>
                    </div>
                  </td>
                  <td className={tableClasses.td}>
                    <span className="font-mono text-xs text-[var(--text-muted)]">{r.rate_e6}</span>
                  </td>
                </tr>
              );
            })}
            {filteredRates.length === 0 ? (
              <tr>
                <td colSpan={4} className="p-6 text-center text-xs font-bold text-[var(--text-muted)]">
                  {accDict.noFxRates}
                </td>
              </tr>
            ) : null}
          </tbody>
        </table>
      </div>
    </AppLayout>
  );
}
