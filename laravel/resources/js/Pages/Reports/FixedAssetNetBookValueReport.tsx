import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Components/AppLayout';
import SearchableSelect from '../../Components/SearchableSelect';
import { Card, PageHeader, StatusBadge, tableClasses } from '../../Components/Primitives';
import type { SharedPageProps } from '../../Types/page';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import {
  fixedAssetStatusLabel,
  formatMinor,
  localizedName,
  statusTone,
  type FixedAssetReportAsset,
  type Paginated,
} from './fixedAssetReportUtils';

type ReportProps = SharedPageProps & {
  assets: Paginated<FixedAssetReportAsset>;
  filters: {
    search?: string;
    category_id?: string;
    status?: string;
  };
};

export default function FixedAssetNetBookValueReport({ locale, assets, filters }: ReportProps) {
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

        <div className={tableClasses.wrap}>
          <table className={tableClasses.table}>
            <thead>
              <tr>
                <th className={tableClasses.th}>{reportDict.assetNumber}</th>
                <th className={tableClasses.th}>{reportDict.assetName}</th>
                <th className={tableClasses.th}>{reportDict.cost}</th>
                <th className={tableClasses.th}>{reportDict.openingAccumulatedDepreciation}</th>
                <th className={tableClasses.th}>{reportDict.postedAccumulatedDepreciation}</th>
                <th className={tableClasses.th}>{reportDict.totalAccumulatedDepreciation}</th>
                <th className={tableClasses.th}>{reportDict.netBookValue}</th>
                <th className={tableClasses.th}>{reportDict.status}</th>
              </tr>
            </thead>
            <tbody>
              {assets.data.length === 0 ? (
                <tr>
                  <td colSpan={8} className={`${tableClasses.td} text-center text-[var(--text-secondary)]`}>
                    {reportDict.noFixedAssetReportRows}
                  </td>
                </tr>
              ) : (
                assets.data.map((asset) => (
                  <tr key={asset.id}>
                    <td className={`${tableClasses.td} font-mono font-semibold`}>
                      <Link href={`/fixed-assets/${asset.id}`} className="text-[var(--primary)] hover:underline">
                        {asset.asset_number}
                      </Link>
                    </td>
                    <td className={tableClasses.td}>{localizedName(asset.name, locale)}</td>
                    <td className={`${tableClasses.td} font-mono`}>{formatMinor(asset.cost_minor, asset.currency)}</td>
                    <td className={`${tableClasses.td} font-mono`}>
                      {formatMinor(asset.opening_accumulated_depreciation_minor, asset.currency)}
                    </td>
                    <td className={`${tableClasses.td} font-mono`}>
                      {formatMinor(asset.posted_accumulated_depreciation_minor, asset.currency)}
                    </td>
                    <td className={`${tableClasses.td} font-mono`}>
                      {formatMinor(asset.total_accumulated_depreciation_minor, asset.currency)}
                    </td>
                    <td className={`${tableClasses.td} font-mono font-semibold text-emerald-600 dark:text-emerald-400`}>
                      {formatMinor(asset.net_book_value_minor, asset.currency)}
                    </td>
                    <td className={tableClasses.td}>
                      <StatusBadge tone={statusTone(asset.status)}>
                        {fixedAssetStatusLabel(asset.status, dict)}
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
