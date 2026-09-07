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
  fixedAssetStatusLabel,
  formatMinor,
  localizedName,
  statusTone,
  type FixedAssetReportAsset,
} from './fixedAssetReportUtils';

type ReportProps = SharedPageProps & {
  assets: FixedAssetReportAsset[];
  filters: {
    search?: string;
    category_id?: string;
    status?: string;
  };
};

export default function FixedAssetNetBookValueReport({ locale, filters }: ReportProps) {
  const dict = getDictionary(locale);
  const reportDict = dict.app.pages.reports;
  const can = useCan();
  const canExport = (can('reports.export') || can('fixedAssets.export')) && can('view_financials');
  const canPrint = can('reports.print') && can('view_financials');

  const [search, setSearch] = useState(filters.search || '');
  const [status, setStatus] = useState(filters.status || '');

  function applyFilters() {
    router.get('/reports/fixed-asset-net-book-values', { search, status }, { preserveState: true, preserveScroll: true, replace: true });
  }

  function exportHref() {
    const params = new URLSearchParams();
    if (search) params.set('search', search);
    if (status) params.set('status', status);

    const query = params.toString();
    return `/reports/fixed-asset-net-book-values/export${query ? `?${query}` : ''}`;
  }

  const statusOptions = [
    { value: 'draft', label: fixedAssetStatusLabel('draft', dict) },
    { value: 'active', label: fixedAssetStatusLabel('active', dict) },
    { value: 'fully_depreciated', label: fixedAssetStatusLabel('fully_depreciated', dict) },
    { value: 'disposed', label: fixedAssetStatusLabel('disposed', dict) },
  ];

  const columns = useMemo(() => [
    { data: 'asset_number', name: 'asset_number', title: reportDict.assetNumber },
    { data: 'name', name: 'name', title: reportDict.assetName, orderable: false },
    { data: 'cost_minor', name: 'cost_minor', title: reportDict.cost, searchable: false },
    { data: 'opening_accumulated_depreciation_minor', name: 'opening_accumulated_depreciation_minor', title: reportDict.openingAccumulatedDepreciation, searchable: false },
    { data: 'posted_accumulated_depreciation_minor', name: 'posted_accumulated_depreciation_minor', title: reportDict.postedAccumulatedDepreciation, searchable: false },
    { data: 'total_accumulated_depreciation_minor', name: 'total_accumulated_depreciation_minor', title: reportDict.totalAccumulatedDepreciation, searchable: false },
    { data: 'net_book_value_minor', name: 'net_book_value_minor', title: reportDict.netBookValue, searchable: false },
    { data: 'status', name: 'status', title: reportDict.status },
  ], [reportDict]);

  const slots = useMemo<DataTableSlots>(() => ({
    asset_number: (data: string, _type: unknown, asset: FixedAssetReportAsset) => (
      <Link href={`/fixed-assets/${asset.id}`} className="font-mono font-semibold text-[var(--primary)] hover:underline">
        {data}
      </Link>
    ),
    name: (data: FixedAssetReportAsset['name']) => localizedName(data, locale),
    cost_minor: (data: number, _type: unknown, asset: FixedAssetReportAsset) => (
      <span className="font-mono">{formatMinor(Number(data), asset.currency)}</span>
    ),
    opening_accumulated_depreciation_minor: (data: number, _type: unknown, asset: FixedAssetReportAsset) => (
      <span className="font-mono">{formatMinor(Number(data), asset.currency)}</span>
    ),
    posted_accumulated_depreciation_minor: (data: number, _type: unknown, asset: FixedAssetReportAsset) => (
      <span className="font-mono">{formatMinor(Number(data), asset.currency)}</span>
    ),
    total_accumulated_depreciation_minor: (data: number, _type: unknown, asset: FixedAssetReportAsset) => (
      <span className="font-mono">{formatMinor(Number(data), asset.currency)}</span>
    ),
    net_book_value_minor: (data: number, _type: unknown, asset: FixedAssetReportAsset) => (
      <span className="font-mono font-semibold text-emerald-600 dark:text-emerald-400">
        {formatMinor(Number(data), asset.currency)}
      </span>
    ),
    status: (data: string) => (
      <StatusBadge tone={statusTone(data)}>{fixedAssetStatusLabel(data, dict)}</StatusBadge>
    ),
  }), [dict, locale]);

  return (
    <AppLayout active="reports.index">
      <Head title={reportDict.fixedAssetNetBookValueReport} />

      <PageHeader
        title={reportDict.fixedAssetNetBookValueReport}
        description={reportDict.fixedAssetNetBookValueReportDescription}
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
            key={`fixed-asset-net-book-values-${JSON.stringify(filters)}`}
            ajaxUrl="/reports/fixed-asset-net-book-values/data"
            columns={columns}
            filters={{ category_id: filters.category_id || '', status: filters.status || '' }}
            initialSearch={filters.search || ''}
            locale={locale}
            order={[[0, 'asc']]}
            slots={slots}
            tableId="fixed-asset-net-book-values-data-table"
          />
        </Card>
      </div>
    </AppLayout>
  );
}
