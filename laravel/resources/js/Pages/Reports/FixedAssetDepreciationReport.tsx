import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Components/AppLayout';
import { Card, PageHeader, StatusBadge, tableClasses } from '../../Components/Primitives';
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
  type Paginated,
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
  schedules: Paginated<DepreciationScheduleRow>;
  filters: {
    search?: string;
    status?: string;
  };
};

export default function FixedAssetDepreciationReport({ locale, schedules, filters }: ReportProps) {
  const dict = getDictionary(locale);
  const reportDict = dict.app.pages.reports;
  const can = useCan();
  const canExport = (can('reports.export') || can('fixedAssets.export')) && can('view_financials');
  const canPrint = can('reports.print') && can('view_financials');

  const [search, setSearch] = useState(filters.search || '');
  const [status, setStatus] = useState(filters.status || '');

  function applyFilters() {
    router.get('/reports/fixed-asset-depreciation', { search, status }, { preserveState: true, replace: true });
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

  return (
    <AppLayout active="reports.index">
      <Head title={reportDict.fixedAssetDepreciationScheduleReport} />

      <PageHeader
        title={reportDict.fixedAssetDepreciationScheduleReport}
        description={reportDict.fixedAssetDepreciationScheduleDescription}
        actions={
          <>
            {canExport ? (
              <a href={exportHref()} className="inline-flex items-center justify-center rounded-md bg-[var(--primary)] px-4 py-2 text-xs font-bold text-white hover:opacity-90">
                {dict.app.actions.exportCsv}
              </a>
            ) : null}
            {canPrint ? (
              <button type="button" onClick={() => window.print()} className="inline-flex items-center justify-center rounded-md border border-[var(--border)] bg-[var(--surface)] px-4 py-2 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)]">
                {dict.app.actions.printReport}
              </button>
            ) : null}
            <Link href="/reports" className="inline-flex items-center justify-center rounded-md border border-[var(--border)] bg-[var(--surface)] px-4 py-2 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)]">
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

            <label className="space-y-1 text-sm font-medium text-[var(--text-secondary)]">
              <span>{reportDict.status}</span>
              <select
                value={status}
                onChange={(event) => setStatus(event.target.value)}
                className="w-full rounded-md border border-[var(--border)] bg-[var(--surface)] px-3 py-2 text-sm text-[var(--text-primary)]"
              >
                <option value="">{reportDict.allStatuses}</option>
                {statusOptions.map((option) => (
                  <option key={option.value} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </select>
            </label>

            <div className="flex items-end">
              <button type="button" onClick={applyFilters} className="inline-flex w-full items-center justify-center rounded-md border border-[var(--border)] bg-[var(--surface)] px-4 py-2 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)]">
                {reportDict.applyFilters}
              </button>
            </div>
          </div>
        </Card>

        <div className={tableClasses.wrap}>
          <table className={tableClasses.table}>
            <thead>
              <tr>
                <th className={tableClasses.th}>{reportDict.fixedAsset}</th>
                <th className={tableClasses.th}>{reportDict.periodNumber}</th>
                <th className={tableClasses.th}>{reportDict.startDate}</th>
                <th className={tableClasses.th}>{reportDict.endDate}</th>
                <th className={tableClasses.th}>{reportDict.depreciation}</th>
                <th className={tableClasses.th}>{reportDict.accumulatedDepreciation}</th>
                <th className={tableClasses.th}>{reportDict.netBookValue}</th>
                <th className={tableClasses.th}>{reportDict.status}</th>
              </tr>
            </thead>
            <tbody>
              {schedules.data.length === 0 ? (
                <tr>
                  <td colSpan={8} className={`${tableClasses.td} text-center text-[var(--text-secondary)]`}>
                    {reportDict.noDepreciationScheduleRows}
                  </td>
                </tr>
              ) : (
                schedules.data.map((row) => (
                  <tr key={row.id}>
                    <td className={tableClasses.td}>
                      {row.asset ? (
                        <Link href={`/fixed-assets/${row.asset.id}`} className="text-[var(--primary)] hover:underline">
                          {localizedName(row.asset.name, locale)} {fallbackText(row.asset.asset_number, reportDict.notAvailable)}
                        </Link>
                      ) : (
                        reportDict.notAvailable
                      )}
                    </td>
                    <td className={`${tableClasses.td} font-mono`}>{row.period_number}</td>
                    <td className={tableClasses.td}>{row.period_start_date}</td>
                    <td className={tableClasses.td}>{row.period_end_date}</td>
                    <td className={`${tableClasses.td} font-mono`}>
                      {formatMinor(row.depreciation_minor, row.asset?.currency)}
                    </td>
                    <td className={`${tableClasses.td} font-mono`}>
                      {formatMinor(row.accumulated_depreciation_minor, row.asset?.currency)}
                    </td>
                    <td className={`${tableClasses.td} font-mono font-semibold text-emerald-600 dark:text-emerald-400`}>
                      {formatMinor(row.net_book_value_minor, row.asset?.currency)}
                    </td>
                    <td className={tableClasses.td}>
                      <StatusBadge tone={statusTone(row.status)}>
                        {depreciationStatusLabel(row.status, dict)}
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
