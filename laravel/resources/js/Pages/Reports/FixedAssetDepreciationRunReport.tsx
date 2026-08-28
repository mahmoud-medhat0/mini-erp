import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Components/AppLayout';
import SearchableSelect from '../../Components/SearchableSelect';
import { Card, PageHeader, StatusBadge, tableClasses } from '../../Components/Primitives';
import type { SharedPageProps } from '../../Types/page';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import { fallbackText, formatMinor, runStatusLabel, statusTone, type Paginated } from './fixedAssetReportUtils';

type FinancialPeriodRef = {
  id: string;
  year?: number | null;
  month?: number | null;
  start_date?: string | null;
  end_date?: string | null;
  status?: string | null;
};

type DepreciationRunRow = {
  id: string;
  number: string;
  run_date: string;
  total_depreciation_minor: number;
  asset_count: number;
  status: string;
  financial_period?: FinancialPeriodRef | null;
  journal_number?: string | null;
};

type ReportProps = SharedPageProps & {
  runs: Paginated<DepreciationRunRow>;
  filters: {
    period_id?: string;
    status?: string;
  };
};

export default function FixedAssetDepreciationRunReport({ locale, runs, filters }: ReportProps) {
  const dict = getDictionary(locale);
  const reportDict = dict.app.pages.reports;
  const can = useCan();
  const canExport = (can('reports.export') || can('fixedAssets.export')) && can('view_financials');
  const canPrint = can('reports.print') && can('view_financials');

  const [status, setStatus] = useState(filters.status || '');

  function applyFilters() {
    router.get('/reports/fixed-asset-depreciation-runs', { status }, { preserveState: true, preserveScroll: true, replace: true });
  }

  function exportHref() {
    const params = new URLSearchParams();
    if (status) params.set('status', status);

    const query = params.toString();
    return `/reports/fixed-asset-depreciation-runs/export${query ? `?${query}` : ''}`;
  }

  const statusOptions = [
    { value: 'posted', label: runStatusLabel('posted', dict) },
    { value: 'reversed', label: runStatusLabel('reversed', dict) },
  ];

  return (
    <AppLayout active="reports.index">
      <Head title={reportDict.fixedAssetDepreciationRunHistoryReport} />

      <PageHeader
        title={reportDict.fixedAssetDepreciationRunHistoryReport}
        description={reportDict.fixedAssetDepreciationRunHistoryDescription}
        actions={
          <>
            {canExport ? (
              <a href={exportHref()} title={dict.app.actions.exportCsv} aria-label={dict.app.actions.exportCsv} className="inline-flex items-center justify-center rounded-md bg-[var(--primary)] px-4 py-2 text-xs font-bold text-white hover:opacity-90">
                {dict.app.actions.exportCsv}
              </a>
            ) : null}
            {canPrint ? (
              <button type="button" onClick={() => window.print()} title={dict.app.actions.printReport} aria-label={dict.app.actions.printReport} className="inline-flex items-center justify-center rounded-md border border-[var(--border)] bg-[var(--surface)] px-4 py-2 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)]">
                {dict.app.actions.printReport}
              </button>
            ) : null}
            <Link href="/reports" title={reportDict.backToReports} aria-label={reportDict.backToReports} className="inline-flex items-center justify-center rounded-md border border-[var(--border)] bg-[var(--surface)] px-4 py-2 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)]">
              {reportDict.backToReports}
            </Link>
          </>
        }
      />

      <div className="space-y-5">
        <Card className="p-4">
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
            <SearchableSelect
              label={reportDict.status}
              options={statusOptions}
              value={status}
              onChange={(value) => setStatus(value || '')}
              placeholder={reportDict.allStatuses}
            />

            <div className="flex items-end">
              <button type="button" onClick={applyFilters} title={reportDict.applyFilters} aria-label={reportDict.applyFilters} className="inline-flex w-full items-center justify-center rounded-md border border-[var(--border)] bg-[var(--surface)] px-4 py-2 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)]">
                {reportDict.applyFilters}
              </button>
            </div>
          </div>
        </Card>

        <div className={tableClasses.wrap}>
          <table className={tableClasses.table}>
            <thead>
              <tr>
                <th className={tableClasses.th}>{reportDict.runNumber}</th>
                <th className={tableClasses.th}>{reportDict.runDate}</th>
                <th className={tableClasses.th}>{reportDict.financialPeriod}</th>
                <th className={tableClasses.th}>{reportDict.assetCount}</th>
                <th className={tableClasses.th}>{reportDict.totalDepreciation}</th>
                <th className={tableClasses.th}>{reportDict.linkedJournal}</th>
                <th className={tableClasses.th}>{reportDict.status}</th>
              </tr>
            </thead>
            <tbody>
              {runs.data.length === 0 ? (
                <tr>
                  <td colSpan={7} className={`${tableClasses.td} text-center text-[var(--text-secondary)]`}>
                    {reportDict.noDepreciationRunRows}
                  </td>
                </tr>
              ) : (
                runs.data.map((run) => (
                  <tr key={run.id}>
                    <td className={`${tableClasses.td} font-mono font-semibold`}>
                      <Link href={`/fixed-assets-depreciation-runs/${run.id}`} className="text-[var(--primary)] hover:underline">
                        {run.number}
                      </Link>
                    </td>
                    <td className={tableClasses.td}>{run.run_date}</td>
                    <td className={tableClasses.td}>
                      {run.financial_period
                        ? `${fallbackText(run.financial_period.year, reportDict.notAvailable)} / ${fallbackText(run.financial_period.month, reportDict.notAvailable)}`
                        : reportDict.notAvailable}
                    </td>
                    <td className={`${tableClasses.td} font-mono`}>{run.asset_count}</td>
                    <td className={`${tableClasses.td} font-mono`}>
                      {formatMinor(run.total_depreciation_minor)}
                    </td>
                    <td className={`${tableClasses.td} font-mono`}>
                      {fallbackText(run.journal_number, reportDict.notAvailable)}
                    </td>
                    <td className={tableClasses.td}>
                      <StatusBadge tone={statusTone(run.status)}>
                        {runStatusLabel(run.status, dict)}
                      </StatusBadge>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>
    </AppLayout>
  );
}
