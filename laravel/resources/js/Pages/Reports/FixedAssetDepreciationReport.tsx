import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AppLayout from '../../Components/AppLayout';
import SearchableSelect from '../../Components/SearchableSelect';
import ServerDataTable, { type DataTableSlots } from '../../Components/ServerDataTable';
import { Card, PageHeader, StatusBadge } from '../../Components/Primitives';
import type { SharedPageProps } from '../../Types/page';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import {
  depreciationStatusLabel,
  fallbackText,
  formatMinor,
  localizedName,
  statusTone,
  type LocalizedName,
} from './fixedAssetReportUtils';

type DepreciationScheduleRow = {
  id: string;
  period_number: number;
  period_start_date: string;
  period_end_date: string;
  depreciation_minor: number;
  accumulated_depreciation_minor: number;
  net_book_value_minor: number;
  status: string;
  depreciation_run_number?: string | null;
  journal_number?: string | null;
  asset?: {
    id: string;
    asset_number: string;
    name: LocalizedName;
    currency: string;
  } | null;
};

type ReportProps = SharedPageProps & {
  schedules: DepreciationScheduleRow[];
  filters: {
    search?: string;
    status?: string;
  };
};

export default function FixedAssetDepreciationReport({ locale, filters }: ReportProps) {
  const dict = getDictionary(locale);
  const reportDict = dict.app.pages.reports;
  const can = useCan();
  const canExport = (can('reports.export') || can('fixedAssets.export')) && can('view_financials');
  const canPrint = can('reports.print') && can('view_financials');

  const [search, setSearch] = useState(filters.search || '');
  const [status, setStatus] = useState(filters.status || '');

  function applyFilters() {
    router.get('/reports/fixed-asset-depreciation', { search, status }, { preserveState: true, preserveScroll: true, replace: true });
  }

  function exportHref() {
    const params = new URLSearchParams();
    if (search) params.set('search', search);
    if (status) params.set('status', status);

    const query = params.toString();
    return `/reports/fixed-asset-depreciation/export${query ? `?${query}` : ''}`;
  }

  const statusOptions = [
    { value: 'planned', label: depreciationStatusLabel('planned', dict) },
    { value: 'posted', label: depreciationStatusLabel('posted', dict) },
    { value: 'reversed', label: depreciationStatusLabel('reversed', dict) },
    { value: 'skipped', label: depreciationStatusLabel('skipped', dict) },
  ];

  const columns = useMemo(() => [
    { data: 'asset', name: 'asset', title: reportDict.fixedAsset, orderable: false },
    { data: 'period_number', name: 'period_number', title: reportDict.periodNumber, searchable: false },
    { data: 'period_start_date', name: 'period_start_date', title: reportDict.startDate },
    { data: 'period_end_date', name: 'period_end_date', title: reportDict.endDate },
    { data: 'depreciation_minor', name: 'depreciation_minor', title: reportDict.depreciation, searchable: false },
    { data: 'accumulated_depreciation_minor', name: 'accumulated_depreciation_minor', title: reportDict.accumulatedDepreciation, searchable: false },
    { data: 'net_book_value_minor', name: 'net_book_value_minor', title: reportDict.netBookValue, searchable: false },
    { data: 'status', name: 'status', title: reportDict.status },
  ], [reportDict]);

  const slots = useMemo<DataTableSlots>(() => ({
    asset: (data: DepreciationScheduleRow['asset']) => data ? (
      <Link href={`/fixed-assets/${data.id}`} className="text-[var(--primary)] hover:underline">
        {localizedName(data.name, locale)} {fallbackText(data.asset_number, reportDict.notAvailable)}
      </Link>
    ) : reportDict.notAvailable,
    period_number: (data: number) => <span className="font-mono">{Number(data)}</span>,
    depreciation_minor: (data: number, _type: unknown, row: DepreciationScheduleRow) => (
      <span className="font-mono">{formatMinor(Number(data), row.asset?.currency)}</span>
    ),
    accumulated_depreciation_minor: (data: number, _type: unknown, row: DepreciationScheduleRow) => (
      <span className="font-mono">{formatMinor(Number(data), row.asset?.currency)}</span>
    ),
    net_book_value_minor: (data: number, _type: unknown, row: DepreciationScheduleRow) => (
      <span className="font-mono font-semibold text-emerald-600 dark:text-emerald-400">
        {formatMinor(Number(data), row.asset?.currency)}
      </span>
    ),
    status: (data: string) => (
      <StatusBadge tone={statusTone(data)}>{depreciationStatusLabel(data, dict)}</StatusBadge>
    ),
  }), [dict, locale, reportDict]);

  return (
    <AppLayout active="reports.index">
      <Head title={reportDict.fixedAssetDepreciationScheduleReport} />

      <PageHeader
        title={reportDict.fixedAssetDepreciationScheduleReport}
        description={reportDict.fixedAssetDepreciationScheduleDescription}
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
          <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
            <label className="space-y-1 text-sm font-medium text-[var(--text-secondary)]">
              <span>{reportDict.search}</span>
              <input
                type="text"
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                onKeyDown={(event) => event.key === 'Enter' && applyFilters()}
                placeholder={reportDict.searchFixedAssetPlaceholder}
                className="w-full rounded-md border border-[var(--border)] bg-[var(--surface)] px-3 py-2 text-sm text-[var(--text-primary)]"
              />
            </label>

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
            key={`fixed-asset-depreciation-${JSON.stringify(filters)}`}
            ajaxUrl="/reports/fixed-asset-depreciation/data"
            columns={columns}
            filters={{ status: filters.status || '' }}
            initialSearch={filters.search || ''}
            locale={locale}
            order={[[2, 'asc'], [1, 'asc']]}
            slots={slots}
            tableId="fixed-asset-depreciation-data-table"
          />
        </Card>
      </div>
    </AppLayout>
  );
}
