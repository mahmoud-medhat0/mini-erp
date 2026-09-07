import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AppLayout from '../../Components/AppLayout';
import SearchableSelect from '../../Components/SearchableSelect';
import ServerDataTable, { type DataTableSlots } from '../../Components/ServerDataTable';
import { Card, PageHeader, StatusBadge } from '../../Components/Primitives';
import type { SharedPageProps } from '../../Types/page';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import { fallbackText, formatMinor, runStatusLabel, statusTone } from './fixedAssetReportUtils';

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
  runs: DepreciationRunRow[];
  filters: {
    period_id?: string;
    status?: string;
  };
};

export default function FixedAssetDepreciationRunReport({ locale, filters }: ReportProps) {
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

  const columns = useMemo(() => [
    { data: 'number', name: 'number', title: reportDict.runNumber },
    { data: 'run_date', name: 'run_date', title: reportDict.runDate },
    { data: 'financial_period', name: 'financial_period', title: reportDict.financialPeriod, orderable: false },
    { data: 'asset_count', name: 'asset_count', title: reportDict.assetCount, searchable: false },
    { data: 'total_depreciation_minor', name: 'total_depreciation_minor', title: reportDict.totalDepreciation, searchable: false },
    { data: 'journal_number', name: 'journal_number', title: reportDict.linkedJournal, orderable: false },
    { data: 'status', name: 'status', title: reportDict.status },
  ], [reportDict]);

  const slots = useMemo<DataTableSlots>(() => ({
    number: (data: string, _type: unknown, run: DepreciationRunRow) => (
      <Link href={`/fixed-assets-depreciation-runs/${run.id}`} className="font-mono font-semibold text-[var(--primary)] hover:underline">
        {data}
      </Link>
    ),
    financial_period: (data: FinancialPeriodRef | null) => data
      ? `${fallbackText(data.year, reportDict.notAvailable)} / ${fallbackText(data.month, reportDict.notAvailable)}`
      : reportDict.notAvailable,
    asset_count: (data: number) => <span className="font-mono">{Number(data)}</span>,
    total_depreciation_minor: (data: number) => <span className="font-mono">{formatMinor(Number(data))}</span>,
    journal_number: (data: string | null) => <span className="font-mono">{fallbackText(data, reportDict.notAvailable)}</span>,
    status: (data: string) => <StatusBadge tone={statusTone(data)}>{runStatusLabel(data, dict)}</StatusBadge>,
  }), [dict, reportDict]);

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

        <Card className="overflow-hidden p-0">
          <ServerDataTable
            key={`fixed-asset-depreciation-runs-${JSON.stringify(filters)}`}
            ajaxUrl="/reports/fixed-asset-depreciation-runs/data"
            columns={columns}
            filters={{ period_id: filters.period_id || '', status: filters.status || '' }}
            locale={locale}
            order={[[1, 'desc'], [0, 'desc']]}
            slots={slots}
            tableId="fixed-asset-depreciation-runs-data-table"
          />
        </Card>
      </div>
    </AppLayout>
  );
}
