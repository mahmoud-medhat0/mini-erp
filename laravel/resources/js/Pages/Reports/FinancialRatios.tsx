import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AppLayout from '../../Components/AppLayout';
import { Card, PageHeader, SearchableSelect } from '../../Components/Primitives';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { SharedPageProps } from '../../Types';

type RatioValue = number | null;

type PeriodRatios = {
  period: { id: string; year: number | null; month: number; start_date: string; end_date: string; label: string };
  liquidity: { current_ratio: RatioValue; quick_ratio: RatioValue; working_capital_minor: number };
  profitability: {
    gross_profit_margin: RatioValue;
    operating_margin: RatioValue;
    net_profit_margin: RatioValue;
    return_on_assets: RatioValue;
    return_on_equity: RatioValue;
  };
  leverage: { debt_to_equity: RatioValue; debt_to_assets: RatioValue; equity_ratio: RatioValue };
  efficiency: {
    inventory_turnover: RatioValue;
    receivables_turnover: RatioValue;
    average_collection_period_days: RatioValue;
    asset_turnover: RatioValue;
  };
};

type FinancialRatiosReportData = { mode: 'single' | 'trend'; periods: PeriodRatios[] } | null;

type PeriodItem = { id: string; year: number | null; month: number; start_date: string; end_date: string; status: string };

type FinancialRatiosProps = SharedPageProps & {
  report: FinancialRatiosReportData;
  periods: PeriodItem[];
  filters: { mode: 'single' | 'trend'; period_id: string | null; period_ids: string[] };
};

type RatioType = 'ratio' | 'percent' | 'days' | 'minor';
type Category = 'liquidity' | 'profitability' | 'leverage' | 'efficiency';

function formatAmount(minor: number): string {
  const digits = String(Math.abs(minor)).padStart(3, '0');
  const major = digits.slice(0, -2) || '0';
  const cents = digits.slice(-2);
  const formatted = `${major.replace(/\B(?=(\d{3})+(?!\d))/g, ',')}.${cents}`;

  return minor < 0 ? `(${formatted})` : formatted;
}

function formatRatioValue(value: RatioValue, type: RatioType, notApplicable: string): string {
  if (value === null) return notApplicable;

  if (type === 'percent') return `${(value * 100).toFixed(2)}%`;
  if (type === 'days') return value.toFixed(1);
  if (type === 'minor') return formatAmount(value);

  return `${value.toFixed(2)}x`;
}

function periodLabel(period: PeriodItem): string {
  return `${period.year ?? '—'}-${String(period.month).padStart(2, '0')} (${period.start_date.split('T')[0]} – ${period.end_date.split('T')[0]})`;
}

export default function FinancialRatios({ locale, report, periods, filters }: FinancialRatiosProps) {
  const dict = getDictionary(locale);
  const pageDict = dict.app.pages.reportsFinancialRatios;
  const accDict = dict.app.accounting;
  const actionsDict = dict.app.actions;
  const can = useCan();
  const canExport = can('reports.export') && can('view_financials');
  const canPrint = can('reports.print') && can('view_financials');

  const [mode, setMode] = useState<'single' | 'trend'>(filters.mode || 'single');
  const [periodId, setPeriodId] = useState(filters.period_id || '');
  const [periodIds, setPeriodIds] = useState<string[]>(filters.period_ids || []);

  const ratioDefs = useMemo(() => ({
    liquidity: [
      { key: 'current_ratio', label: pageDict.currentRatio, formula: pageDict.currentRatioFormula, type: 'ratio' as RatioType },
      { key: 'quick_ratio', label: pageDict.quickRatio, formula: pageDict.quickRatioFormula, type: 'ratio' as RatioType },
      { key: 'working_capital_minor', label: pageDict.workingCapital, formula: pageDict.workingCapitalFormula, type: 'minor' as RatioType },
    ],
    profitability: [
      { key: 'gross_profit_margin', label: pageDict.grossProfitMargin, formula: pageDict.grossProfitMarginFormula, type: 'percent' as RatioType },
      { key: 'operating_margin', label: pageDict.operatingMargin, formula: pageDict.operatingMarginFormula, type: 'percent' as RatioType },
      { key: 'net_profit_margin', label: pageDict.netProfitMargin, formula: pageDict.netProfitMarginFormula, type: 'percent' as RatioType },
      { key: 'return_on_assets', label: pageDict.returnOnAssets, formula: pageDict.returnOnAssetsFormula, type: 'percent' as RatioType },
      { key: 'return_on_equity', label: pageDict.returnOnEquity, formula: pageDict.returnOnEquityFormula, type: 'percent' as RatioType },
    ],
    leverage: [
      { key: 'debt_to_equity', label: pageDict.debtToEquity, formula: pageDict.debtToEquityFormula, type: 'ratio' as RatioType },
      { key: 'debt_to_assets', label: pageDict.debtToAssets, formula: pageDict.debtToAssetsFormula, type: 'percent' as RatioType },
      { key: 'equity_ratio', label: pageDict.equityRatio, formula: pageDict.equityRatioFormula, type: 'percent' as RatioType },
    ],
    efficiency: [
      { key: 'inventory_turnover', label: pageDict.inventoryTurnover, formula: pageDict.inventoryTurnoverFormula, type: 'ratio' as RatioType },
      { key: 'receivables_turnover', label: pageDict.receivablesTurnover, formula: pageDict.receivablesTurnoverFormula, type: 'ratio' as RatioType },
      { key: 'average_collection_period_days', label: pageDict.averageCollectionPeriod, formula: pageDict.averageCollectionPeriodFormula, type: 'days' as RatioType },
      { key: 'asset_turnover', label: pageDict.assetTurnover, formula: pageDict.assetTurnoverFormula, type: 'ratio' as RatioType },
    ],
  }), [pageDict]);

  const categories: Array<{ key: Category; title: string; tone: string }> = [
    { key: 'liquidity', title: pageDict.categoryLiquidity, tone: 'border-blue-500/20 bg-blue-500/5' },
    { key: 'profitability', title: pageDict.categoryProfitability, tone: 'border-emerald-500/20 bg-emerald-500/5' },
    { key: 'leverage', title: pageDict.categoryLeverage, tone: 'border-amber-500/20 bg-amber-500/5' },
    { key: 'efficiency', title: pageDict.categoryEfficiency, tone: 'border-purple-500/20 bg-purple-500/5' },
  ];

  const periodOptions = periods.map((p) => ({ value: p.id, label: periodLabel(p) }));

  function handleModeChange(next: 'single' | 'trend') {
    setMode(next);
  }

  function handleApplySingle(nextPeriodId: string) {
    setPeriodId(nextPeriodId);
    if (!nextPeriodId) return;
    router.get('/reports/financial-ratios', { mode: 'single', period_id: nextPeriodId }, { preserveScroll: true });
  }

  function togglePeriodId(id: string) {
    setPeriodIds((prev) => (prev.includes(id) ? prev.filter((p) => p !== id) : [...prev, id]));
  }

  function handleApplyTrend() {
    router.get('/reports/financial-ratios', { mode: 'trend', 'period_ids[]': periodIds }, { preserveScroll: true });
  }

  const exportUrl = (() => {
    const params = new URLSearchParams();
    params.append('mode', mode);
    if (mode === 'single' && periodId) {
      params.append('period_id', periodId);
    } else if (mode === 'trend') {
      periodIds.forEach((id) => params.append('period_ids[]', id));
    }

    return `/reports/financial-ratios/export?${params.toString()}`;
  })();

  const hasReport = Boolean(report && report.periods.length > 0);

  return (
    <AppLayout active="reports.financial-ratios">
      <Head title={accDict.financialRatiosMiniErp} />

      <div className="space-y-6 p-6">
        <PageHeader
          title={accDict.financialRatios}
          description={accDict.financialRatiosDesc}
          actions={
            hasReport && (canPrint || canExport) ? (
              <div className="flex items-center gap-2">
                {canPrint ? (
                  <button
                    type="button"
                    onClick={() => window.print()}
                    title={actionsDict.printReport}
                    aria-label={actionsDict.printReport}
                    className="inline-flex items-center gap-2 rounded-xl bg-[var(--surface-subtle)] border border-[var(--border)] px-4 py-2 text-xs font-semibold text-[var(--text-primary)] hover:bg-[var(--background)] transition-all"
                  >
                    {actionsDict.printReport}
                  </button>
                ) : null}
                {canExport ? (
                  <a
                    href={exportUrl}
                    title={pageDict.exportCsv}
                    aria-label={pageDict.exportCsv}
                    className="inline-flex items-center gap-2 rounded-xl bg-[var(--surface-subtle)] border border-[var(--border)] px-4 py-2 text-xs font-semibold text-[var(--text-primary)] hover:bg-[var(--background)] transition-all"
                  >
                    {pageDict.exportCsv}
                  </a>
                ) : null}
              </div>
            ) : null
          }
        />

        <Card className="p-4 bg-[var(--surface)] border border-[var(--border)] space-y-4">
          <div className="inline-flex rounded-xl border border-[var(--border)] bg-[var(--background)] p-1">
            <button
              type="button"
              onClick={() => handleModeChange('single')}
              title={pageDict.modeSingle}
              aria-label={pageDict.modeSingle}
              className={`rounded-lg px-4 py-1.5 text-xs font-bold transition-colors ${mode === 'single' ? 'bg-[var(--primary)] text-white shadow-sm' : 'text-[var(--text-secondary)]'}`}
            >
              {pageDict.modeSingle}
            </button>
            <button
              type="button"
              onClick={() => handleModeChange('trend')}
              title={pageDict.modeTrend}
              aria-label={pageDict.modeTrend}
              className={`rounded-lg px-4 py-1.5 text-xs font-bold transition-colors ${mode === 'trend' ? 'bg-[var(--primary)] text-white shadow-sm' : 'text-[var(--text-secondary)]'}`}
            >
              {pageDict.modeTrend}
            </button>
          </div>

          {mode === 'single' ? (
            <div className="max-w-sm">
              <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">{pageDict.period}</label>
              <SearchableSelect
                options={periodOptions}
                value={periodId}
                onChange={(val) => handleApplySingle(val || '')}
                placeholder={pageDict.selectPeriod}
              />
            </div>
          ) : (
            <div className="space-y-3">
              <label className="block text-xs font-semibold text-[var(--text-secondary)]">{pageDict.selectPeriods}</label>
              <div className="flex flex-wrap gap-2 max-h-40 overflow-y-auto rounded-xl border border-[var(--border)] p-3 bg-[var(--background)]">
                {periods.map((p) => (
                  <label
                    key={p.id}
                    className={`flex items-center gap-2 rounded-lg border px-3 py-1.5 text-xs font-medium cursor-pointer transition-colors ${
                      periodIds.includes(p.id)
                        ? 'border-[var(--primary)] bg-[var(--primary)]/10 text-[var(--primary)]'
                        : 'border-[var(--border)] text-[var(--text-secondary)] hover:bg-[var(--surface)]'
                    }`}
                  >
                    <input
                      type="checkbox"
                      checked={periodIds.includes(p.id)}
                      onChange={() => togglePeriodId(p.id)}
                      className="size-3.5"
                    />
                    <span>{periodLabel(p)}</span>
                  </label>
                ))}
              </div>
              <button
                type="button"
                onClick={handleApplyTrend}
                disabled={periodIds.length === 0}
                title={pageDict.applyFilter}
                aria-label={pageDict.applyFilter}
                className="rounded-xl bg-[var(--primary)] px-4 py-2 text-xs font-semibold text-white shadow-md shadow-blue-500/20 hover:bg-blue-600 disabled:cursor-not-allowed disabled:opacity-50"
              >
                {pageDict.applyFilter}
              </button>
            </div>
          )}
        </Card>

        {!hasReport ? (
          <Card className="p-12 text-center text-[var(--text-muted)]">
            {mode === 'single' ? pageDict.pleaseSelectAPeriod : pageDict.noPeriodsSelected}
          </Card>
        ) : mode === 'single' ? (
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {categories.map((category) => (
              <Card key={category.key} className={`p-5 border ${category.tone}`}>
                <h3 className="text-xs font-extrabold uppercase tracking-wider text-[var(--text-primary)] mb-4">
                  {category.title}
                </h3>
                <div className="space-y-3">
                  {ratioDefs[category.key].map((def) => {
                    const value = (report!.periods[0][category.key] as Record<string, RatioValue | number>)[def.key] as RatioValue;
                    return (
                      <div key={def.key} className="flex items-center justify-between gap-3">
                        <div className="min-w-0">
                          <div className="text-xs font-bold text-[var(--text-primary)]">{def.label}</div>
                          <div className="text-[10px] text-[var(--text-muted)] font-mono truncate">{def.formula}</div>
                        </div>
                        <div className="font-mono font-extrabold text-sm text-[var(--text-primary)] shrink-0">
                          {formatRatioValue(value, def.type, pageDict.notApplicable)}
                        </div>
                      </div>
                    );
                  })}
                </div>
              </Card>
            ))}
          </div>
        ) : (
          <div className="space-y-6">
            {categories.map((category) => (
              <Card key={category.key} className="overflow-hidden p-0">
                <div className={`border-b border-[var(--border-color)] px-4 py-3 text-xs font-extrabold uppercase tracking-wider ${category.tone}`}>
                  {category.title}
                </div>
                <div className="overflow-x-auto">
                  <table className="w-full text-xs">
                    <thead>
                      <tr className="border-b border-[var(--border)] bg-[var(--background)]/50">
                        <th className="text-start px-4 py-2 font-bold text-[var(--text-secondary)]">{pageDict.ratioColumn}</th>
                        {report!.periods.map((p) => (
                          <th key={p.period.id} className="text-end px-4 py-2 font-bold text-[var(--text-secondary)] font-mono">
                            {p.period.label}
                          </th>
                        ))}
                      </tr>
                    </thead>
                    <tbody>
                      {ratioDefs[category.key].map((def) => (
                        <tr key={def.key} className="border-b border-[var(--border-color)] last:border-0">
                          <td className="px-4 py-2 font-semibold text-[var(--text-primary)]">
                            {def.label}
                            <div className="text-[10px] text-[var(--text-muted)] font-mono font-normal">{def.formula}</div>
                          </td>
                          {report!.periods.map((p) => {
                            const value = (p[category.key] as Record<string, RatioValue | number>)[def.key] as RatioValue;
                            return (
                              <td key={p.period.id} className="px-4 py-2 text-end font-mono font-bold text-[var(--text-primary)]">
                                {formatRatioValue(value, def.type, pageDict.notApplicable)}
                              </td>
                            );
                          })}
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </Card>
            ))}
          </div>
        )}
      </div>
    </AppLayout>
  );
}
