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
  disposalTypeLabel,
  fallbackText,
  formatMinor,
  localizedName,
  runStatusLabel,
  statusTone,
  type LocalizedName,
} from './fixedAssetReportUtils';

type DisposalRow = {
  id: string;
  number: string;
  disposal_date: string;
  disposal_type: string;
  proceeds_minor: number;
  net_book_value_minor: number;
  gain_minor: number;
  loss_minor: number;
  gain_loss_minor: number;
  status: string;
  journal_number?: string | null;
  asset?: {
    id: string;
    asset_number: string;
    name: LocalizedName;
    currency: string;
  } | null;
};

type ReportProps = SharedPageProps & {
  disposals: DisposalRow[];
  filters: {
    search?: string;
    disposal_type?: string;
    status?: string;
  };
};

export default function FixedAssetDisposalReport({ locale, filters }: ReportProps) {
  const dict = getDictionary(locale);
  const reportDict = dict.app.pages.reports;
  const can = useCan();
  const canExport = (can('reports.export') || can('fixedAssets.export')) && can('view_financials');
  const canPrint = can('reports.print') && can('view_financials');

  const [search, setSearch] = useState(filters.search || '');
  const [type, setType] = useState(filters.disposal_type || '');
  const [status, setStatus] = useState(filters.status || '');

  function applyFilters() {
    router.get('/reports/fixed-asset-disposals', { search, disposal_type: type, status }, { preserveState: true, preserveScroll: true, replace: true });
  }

  function exportHref() {
    const params = new URLSearchParams();
    if (search) params.set('search', search);
    if (type) params.set('disposal_type', type);
    if (status) params.set('status', status);

    const query = params.toString();
    return `/reports/fixed-asset-disposals/export${query ? `?${query}` : ''}`;
  }

  const typeOptions = [
    { value: 'sale', label: disposalTypeLabel('sale', dict) },
    { value: 'scrap', label: disposalTypeLabel('scrap', dict) },
    { value: 'retirement', label: disposalTypeLabel('retirement', dict) },
  ];

  const statusOptions = [
    { value: 'posted', label: runStatusLabel('posted', dict) },
    { value: 'reversed', label: runStatusLabel('reversed', dict) },
  ];

  const columns = useMemo(() => [
    { data: 'number', name: 'number', title: reportDict.disposalNumber },
    { data: 'asset', name: 'asset', title: reportDict.fixedAsset, orderable: false },
    { data: 'disposal_date', name: 'disposal_date', title: reportDict.disposalDate },
    { data: 'disposal_type', name: 'disposal_type', title: reportDict.disposalType },
    { data: 'proceeds_minor', name: 'proceeds_minor', title: reportDict.proceeds, searchable: false },
    { data: 'net_book_value_minor', name: 'net_book_value_minor', title: reportDict.netBookValue, searchable: false },
    { data: 'gain_loss_minor', name: 'gain_loss_minor', title: reportDict.gainLoss, searchable: false },
    { data: 'status', name: 'status', title: reportDict.status },
  ], [reportDict]);

  const slots = useMemo<DataTableSlots>(() => ({
    number: (data: string, _type: unknown, item: DisposalRow) => (
      <Link href={`/fixed-assets-disposals/${item.id}`} className="font-mono font-semibold text-[var(--primary)] hover:underline">
        {data}
      </Link>
    ),
    asset: (data: DisposalRow['asset']) => data ? (
      <Link href={`/fixed-assets/${data.id}`} className="text-[var(--primary)] hover:underline">
        {localizedName(data.name, locale)} {fallbackText(data.asset_number, reportDict.notAvailable)}
      </Link>
    ) : reportDict.notAvailable,
    disposal_type: (data: string) => disposalTypeLabel(data, dict),
    proceeds_minor: (data: number, _type: unknown, item: DisposalRow) => (
      <span className="font-mono">{formatMinor(Number(data), item.asset?.currency)}</span>
    ),
    net_book_value_minor: (data: number, _type: unknown, item: DisposalRow) => (
      <span className="font-mono">{formatMinor(Number(data), item.asset?.currency)}</span>
    ),
    gain_loss_minor: (data: number, _type: unknown, item: DisposalRow) => (
      <span className="font-mono font-semibold">{formatMinor(Number(data), item.asset?.currency)}</span>
    ),
    status: (data: string) => <StatusBadge tone={statusTone(data)}>{runStatusLabel(data, dict)}</StatusBadge>,
  }), [dict, locale, reportDict]);

  return (
    <AppLayout active="reports.index">
      <Head title={reportDict.fixedAssetDisposalHistoryReport} />

      <PageHeader
        title={reportDict.fixedAssetDisposalHistoryReport}
        description={reportDict.fixedAssetDisposalHistoryDescription}
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
          <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
            <label className="space-y-1 text-sm font-medium text-[var(--text-secondary)]">
              <span>{reportDict.search}</span>
              <input
                type="text"
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                onKeyDown={(event) => event.key === 'Enter' && applyFilters()}
                placeholder={reportDict.searchDisposalPlaceholder}
                className="w-full rounded-md border border-[var(--border)] bg-[var(--surface)] px-3 py-2 text-sm text-[var(--text-primary)]"
              />
            </label>

            <SearchableSelect
              label={reportDict.disposalType}
              options={typeOptions}
              value={type}
              onChange={(value) => setType(value || '')}
              placeholder={reportDict.allTypes}
            />

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
            key={`fixed-asset-disposals-${JSON.stringify(filters)}`}
            ajaxUrl="/reports/fixed-asset-disposals/data"
            columns={columns}
            filters={{ disposal_type: filters.disposal_type || '', status: filters.status || '' }}
            initialSearch={filters.search || ''}
            locale={locale}
            order={[[2, 'desc'], [0, 'desc']]}
            slots={slots}
            tableId="fixed-asset-disposals-data-table"
          />
        </Card>
      </div>
    </AppLayout>
  );
}
