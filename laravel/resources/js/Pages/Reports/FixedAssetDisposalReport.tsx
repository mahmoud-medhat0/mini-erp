import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Components/AppLayout';
import SearchableSelect from '../../Components/SearchableSelect';
import { Card, PageHeader, StatusBadge, tableClasses } from '../../Components/Primitives';
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
  type Paginated,
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
  disposals: Paginated<DisposalRow>;
  filters: {
    search?: string;
    disposal_type?: string;
    status?: string;
  };
};

export default function FixedAssetDisposalReport({ locale, disposals, filters }: ReportProps) {
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

        <div className={tableClasses.wrap}>
          <table className={tableClasses.table}>
            <thead>
              <tr>
                <th className={tableClasses.th}>{reportDict.disposalNumber}</th>
                <th className={tableClasses.th}>{reportDict.fixedAsset}</th>
                <th className={tableClasses.th}>{reportDict.disposalDate}</th>
                <th className={tableClasses.th}>{reportDict.disposalType}</th>
                <th className={tableClasses.th}>{reportDict.proceeds}</th>
                <th className={tableClasses.th}>{reportDict.netBookValue}</th>
                <th className={tableClasses.th}>{reportDict.gainLoss}</th>
                <th className={tableClasses.th}>{reportDict.status}</th>
              </tr>
            </thead>
            <tbody>
              {disposals.data.length === 0 ? (
                <tr>
                  <td colSpan={8} className={`${tableClasses.td} text-center text-[var(--text-secondary)]`}>
                    {reportDict.noDisposalRows}
                  </td>
                </tr>
              ) : (
                disposals.data.map((item) => (
                  <tr key={item.id}>
                    <td className={`${tableClasses.td} font-mono font-semibold`}>
                      <Link href={`/fixed-assets-disposals/${item.id}`} className="text-[var(--primary)] hover:underline">
                        {item.number}
                      </Link>
                    </td>
                    <td className={tableClasses.td}>
                      {item.asset ? (
                        <Link href={`/fixed-assets/${item.asset.id}`} className="text-[var(--primary)] hover:underline">
                          {localizedName(item.asset.name, locale)} {fallbackText(item.asset.asset_number, reportDict.notAvailable)}
                        </Link>
                      ) : (
                        reportDict.notAvailable
                      )}
                    </td>
                    <td className={tableClasses.td}>{item.disposal_date}</td>
                    <td className={tableClasses.td}>{disposalTypeLabel(item.disposal_type, dict)}</td>
                    <td className={`${tableClasses.td} font-mono`}>
                      {formatMinor(item.proceeds_minor, item.asset?.currency)}
                    </td>
                    <td className={`${tableClasses.td} font-mono`}>
                      {formatMinor(item.net_book_value_minor, item.asset?.currency)}
                    </td>
                    <td className={`${tableClasses.td} font-mono font-semibold`}>
                      {item.gain_minor > 0
                        ? formatMinor(item.gain_minor, item.asset?.currency)
                        : item.loss_minor > 0
                          ? formatMinor(-item.loss_minor, item.asset?.currency)
                          : formatMinor(0, item.asset?.currency)}
                    </td>
                    <td className={tableClasses.td}>
                      <StatusBadge tone={statusTone(item.status)}>
                        {runStatusLabel(item.status, dict)}
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
