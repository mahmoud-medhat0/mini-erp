import { Head, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import AppLayout from '../../Components/AppLayout';
import { Card, PageHeader } from '../../Components/Primitives';
import { formatMoney } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import type { SharedPageProps } from '../../Types';

type MonthlyHistory = {
  month: string;
  sales_minor: number;
  expense_minor: number;
  profit_minor: number;
};

type MonthlyProjection = MonthlyHistory & {
  cash_flow_minor: number;
};

type ForecastData = {
  currency: string;
  lookback_months: number;
  months_ahead: number;
  assumptions: { sales_growth_pct: number; expense_growth_pct: number };
  history: MonthlyHistory[];
  baseline: { average_sales_minor: number; average_expense_minor: number };
  projection: MonthlyProjection[];
};

type Props = SharedPageProps & {
  forecast: ForecastData;
  filters: { lookback_months: number; months_ahead: number; sales_growth_pct: number; expense_growth_pct: number };
};

export default function Forecast({ locale, forecast, filters }: Props) {
  const dict = getDictionary(locale);
  const pageDict = dict.app.pages.forecast;

  const [lookbackMonths, setLookbackMonths] = useState(String(filters.lookback_months));
  const [monthsAhead, setMonthsAhead] = useState(String(filters.months_ahead));
  const [salesGrowthPct, setSalesGrowthPct] = useState(String(filters.sales_growth_pct));
  const [expenseGrowthPct, setExpenseGrowthPct] = useState(String(filters.expense_growth_pct));

  function submitFilters(event: FormEvent) {
    event.preventDefault();
    router.get('/reports/forecast', {
      lookback_months: lookbackMonths,
      months_ahead: monthsAhead,
      sales_growth_pct: salesGrowthPct,
      expense_growth_pct: expenseGrowthPct,
    }, { preserveState: true, preserveScroll: true });
  }

  return (
    <AppLayout active="reports.forecast">
      <Head title={pageDict.headTitle} />

      <div className="space-y-6 p-6">
        <PageHeader title={pageDict.title} description={pageDict.description} />

        <Card className="p-4">
          <p className="m-0 text-xs text-[var(--text-secondary)]">{pageDict.methodologyNote}</p>
        </Card>

        <Card className="p-4">
          <form onSubmit={submitFilters} className="flex flex-wrap items-end gap-4">
            <label className="block text-xs font-bold uppercase text-[var(--text-secondary)]">
              {pageDict.lookbackMonths}
              <input className="input mt-1 w-28" type="number" min="1" max="24" value={lookbackMonths} onChange={(event) => setLookbackMonths(event.target.value)} />
            </label>
            <label className="block text-xs font-bold uppercase text-[var(--text-secondary)]">
              {pageDict.monthsAhead}
              <input className="input mt-1 w-28" type="number" min="1" max="12" value={monthsAhead} onChange={(event) => setMonthsAhead(event.target.value)} />
            </label>
            <label className="block text-xs font-bold uppercase text-[var(--text-secondary)]">
              {pageDict.salesGrowthPct}
              <input className="input mt-1 w-28" type="number" step="0.1" value={salesGrowthPct} onChange={(event) => setSalesGrowthPct(event.target.value)} />
            </label>
            <label className="block text-xs font-bold uppercase text-[var(--text-secondary)]">
              {pageDict.expenseGrowthPct}
              <input className="input mt-1 w-28" type="number" step="0.1" value={expenseGrowthPct} onChange={(event) => setExpenseGrowthPct(event.target.value)} />
            </label>
            <button type="submit" title={pageDict.apply} aria-label={pageDict.apply} className="rounded-xl bg-[var(--primary)] px-4 py-2 text-xs font-bold text-white">{pageDict.apply}</button>
          </form>
        </Card>

        <Card className="overflow-hidden p-0">
          <div className="border-b border-[var(--border)] p-4">
            <h3 className="m-0 text-sm font-bold text-[var(--text-primary)]">{pageDict.historyTitle}</h3>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-[var(--border)] text-start text-xs font-bold uppercase text-[var(--text-secondary)]">
                  <th className="px-4 py-2 text-start">{pageDict.month}</th>
                  <th className="px-4 py-2 text-end">{pageDict.sales}</th>
                  <th className="px-4 py-2 text-end">{pageDict.expense}</th>
                  <th className="px-4 py-2 text-end">{pageDict.profit}</th>
                </tr>
              </thead>
              <tbody>
                {forecast.history.map((row) => (
                  <tr key={row.month} className="border-b border-[var(--border)]">
                    <td className="px-4 py-2">{row.month}</td>
                    <td className="px-4 py-2 text-end">{formatMoney(row.sales_minor, forecast.currency)}</td>
                    <td className="px-4 py-2 text-end">{formatMoney(row.expense_minor, forecast.currency)}</td>
                    <td className="px-4 py-2 text-end">{formatMoney(row.profit_minor, forecast.currency)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </Card>

        <Card className="overflow-hidden p-0">
          <div className="border-b border-[var(--border)] p-4">
            <h3 className="m-0 text-sm font-bold text-[var(--text-primary)]">{pageDict.projectionTitle}</h3>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-[var(--border)] text-start text-xs font-bold uppercase text-[var(--text-secondary)]">
                  <th className="px-4 py-2 text-start">{pageDict.month}</th>
                  <th className="px-4 py-2 text-end">{pageDict.sales}</th>
                  <th className="px-4 py-2 text-end">{pageDict.expense}</th>
                  <th className="px-4 py-2 text-end">{pageDict.profit}</th>
                  <th className="px-4 py-2 text-end">{pageDict.cashFlow}</th>
                </tr>
              </thead>
              <tbody>
                {forecast.projection.map((row) => (
                  <tr key={row.month} className="border-b border-[var(--border)]">
                    <td className="px-4 py-2 font-semibold">{row.month}</td>
                    <td className="px-4 py-2 text-end">{formatMoney(row.sales_minor, forecast.currency)}</td>
                    <td className="px-4 py-2 text-end">{formatMoney(row.expense_minor, forecast.currency)}</td>
                    <td className="px-4 py-2 text-end font-semibold">{formatMoney(row.profit_minor, forecast.currency)}</td>
                    <td className="px-4 py-2 text-end">{formatMoney(row.cash_flow_minor, forecast.currency)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </Card>
      </div>
    </AppLayout>
  );
}
