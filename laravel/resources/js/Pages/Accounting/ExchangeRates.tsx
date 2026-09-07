import { Head, useForm } from '@inertiajs/react';
import { useMemo, useState, type FormEvent, type ReactElement } from 'react';
import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import ServerDataTable, { type DataTableSlots } from '../../Components/ServerDataTable';
import { Card, PageHeader, SearchableSelect } from '../../Components/Primitives';
import { getDictionary } from '../../lib/i18n';
import type { CurrencyRow, SharedPageProps } from '../../Types';

type ExchangeRateTableRow = {
  id: string;
  currency: string;
  currency_name: Record<string, string> | string;
  currency_symbol?: string | null;
  date: string;
  rate_decimal: number;
  rate_e6: number;
};

type ExchangeRatesProps = SharedPageProps & {
  rateEntryCount?: number;
  currencies?: CurrencyRow[];
  baseCurrency?: string | null;
  baseCurrencyRef?: CurrencyRow | null;
  filters?: { search?: string | null };
  activeCurrencyCount?: number;
};

export default function ExchangeRates({ locale, rateEntryCount = 0, currencies = [], baseCurrency = null, baseCurrencyRef = null, filters = {}, activeCurrencyCount = 0 }: ExchangeRatesProps) {
  const dict = getDictionary(locale);
  const accDict = dict.app.accounting;
  const actionsDict = dict.app.actions;

  const baseCurrencyCode = baseCurrency ?? '';
  const foreignCurrencies = currencies.filter((c) => c.code !== baseCurrencyCode);
  const defaultCurrency = foreignCurrencies[0]?.code ?? '';
  const baseCurrencyLabel = baseCurrencyRef ? `${baseCurrencyRef.code} (${baseCurrencyRef.symbol})` : (baseCurrencyCode || accDict.noBaseCurrency);
  const baseCurrencyDisplay = baseCurrencyCode || accDict.noBaseCurrency;

  const [showModal, setShowModal] = useState(false);
  const [reloadToken, setReloadToken] = useState(0);
  const form = useForm({
    currency: defaultCurrency,
    date: new Date().toISOString().split('T')[0],
    rate: '',
  });

  function submit(e: FormEvent) {
    e.preventDefault();
    form.post('/accounting/fx-rates', {
      preserveScroll: true,
      onSuccess: () => {
        setShowModal(false);
        form.reset();
        setReloadToken((token) => token + 1);
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

  const columns = useMemo(() => [
    { data: 'currency_name', name: 'currency_name', title: accDict.currency },
    { data: 'date', name: 'date', title: accDict.effectiveDate, width: '120px' },
    { data: 'rate_decimal', name: 'rate_decimal', title: accDict.rateDecimal, searchable: false },
    { data: 'rate_e6', name: 'rate_e6', title: accDict.rateE6, searchable: false },
  ], [accDict]);

  const slots = useMemo<DataTableSlots>(() => ({
    currency_name: (data: ExchangeRateTableRow['currency_name'], _type: unknown, row: ExchangeRateTableRow): ReactElement => (
      <div className="flex items-center gap-2.5">
        <span className="rounded-lg border border-blue-500/20 bg-blue-500/10 px-2 py-0.5 font-mono text-xs font-bold text-blue-600 dark:text-blue-400">
          {row.currency}
        </span>
        <span className="text-xs font-medium text-[var(--text-primary)]">
          {getName(data)}{row.currency_symbol ? ` (${row.currency_symbol})` : ''}
        </span>
      </div>
    ),
    date: (data: string): ReactElement => (
      <span className="font-mono text-xs text-[var(--text-primary)]">{data?.split('T')[0]}</span>
    ),
    rate_decimal: (data: number, _type: unknown, row: ExchangeRateTableRow): ReactElement => {
      const decimalValue = Number(data).toFixed(4);

      return (
        <div className="flex items-center gap-2">
          <span className="font-mono text-xs font-bold text-[var(--primary)]">{decimalValue}</span>
          <span className="font-mono text-[10px] text-[var(--text-muted)]">
            {accDict.fxConversionLine
              .replace('{currency}', row.currency)
              .replace('{rate}', decimalValue)
              .replace('{baseCurrency}', baseCurrencyDisplay)}
          </span>
        </div>
      );
    },
    rate_e6: (data: number): ReactElement => (
      <span className="font-mono text-xs text-[var(--text-muted)]">{data}</span>
    ),
  } as unknown as DataTableSlots), [accDict, baseCurrencyDisplay, locale]);

  return (
    <AppLayout active="accounting.fx_rates">
      <Head title={accDict.fxRates} />

      <PageHeader
        title={accDict.fxRates}
        description={accDict.fxRatesDesc}
        actions={
          <button
            type="button"
            title={accDict.setFxRate}
            aria-label={accDict.setFxRate}
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
              <p className="mt-1 text-2xl font-black font-mono text-[var(--text-primary)]">{rateEntryCount}</p>
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
                {activeCurrencyCount}
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
              title={actionsDict.close}
              aria-label={actionsDict.close}
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
                title={actionsDict.cancel}
                aria-label={actionsDict.cancel}
                onClick={() => setShowModal(false)}
                className="rounded-xl border border-[var(--border)] bg-[var(--surface)] px-4.5 py-2.5 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] transition-colors cursor-pointer"
              >
                {actionsDict.cancel}
              </button>
              <button
                type="submit"
                title={accDict.saveFxRate}
                aria-label={accDict.saveFxRate}
                disabled={form.processing || currencyOptions.length === 0}
                className="rounded-xl bg-[var(--primary)] px-6 py-2.5 text-xs font-bold text-white shadow-md shadow-blue-500/20 hover:opacity-90 disabled:opacity-50 transition-all cursor-pointer"
              >
                {accDict.saveFxRate}
              </button>
            </div>
          </form>
        </Card>
      ) : null}

      <Card className="overflow-hidden p-0">
        <ServerDataTable
          ajaxUrl="/accounting/fx-rates/data"
          columns={columns}
          initialSearch={filters.search || ''}
          locale={locale}
          order={[[1, 'desc']]}
          pageLength={25}
          reloadToken={reloadToken}
          slots={slots}
          tableId="exchange-rates-table"
        />
      </Card>
    </AppLayout>
  );
}
