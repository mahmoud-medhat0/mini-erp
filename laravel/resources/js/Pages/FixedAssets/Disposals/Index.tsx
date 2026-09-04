import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AppLayout from '../../../Components/AppLayout';
import { Button, PageHeader, SearchableSelect, StatusBadge } from '../../../Components/Primitives';
import ServerDataTable, { type DataTableSlots } from '../../../Components/ServerDataTable';
import { formatMoney } from '../../../lib/accountingHelpers';
import { getDictionary } from '../../../lib/i18n';
import type { SharedPageProps } from '../../../Types/page';

type FixedAsset = {
  id: string;
  asset_number: string;
  name: Record<string, string> | string;
  currency: string;
};

type FixedAssetDisposalRow = {
  id: string;
  number: string;
  fixed_asset_id: string;
  disposal_date: string;
  disposal_type: 'sale' | 'scrap' | 'retirement';
  proceeds_minor: number;
  net_book_value_minor: number;
  gain_minor: number;
  loss_minor: number;
  status: 'posted' | 'reversed';
  asset?: FixedAsset | null;
  journal_entry?: { id: string; number: string } | null;
  created_at: string;
};

type IndexProps = SharedPageProps & {
  disposals?: any;
  filters?: {
    search?: string;
    status?: string;
    disposal_type?: string;
  };
};

export default function DisposalsIndex({ locale, filters = {} }: IndexProps) {
  const dict = getDictionary(locale);
  const appDict = dict.app.fixedAssetsDisposals;

  const [statusFilter, setStatusFilter] = useState(filters.status || '');
  const [typeFilter, setTypeFilter] = useState(filters.disposal_type || '');

  const typeOptions = useMemo(() => [
    { value: '', label: appDict.allTypes },
    { value: 'sale', label: appDict.sale },
    { value: 'scrap', label: appDict.scrap },
    { value: 'retirement', label: appDict.retirement },
  ], [appDict]);

  const statusOptions = useMemo(() => [
    { value: '', label: appDict.allStatuses },
    { value: 'posted', label: appDict.statusPosted },
    { value: 'reversed', label: appDict.statusReversed },
  ], [appDict]);

  const extraFilters = useMemo(() => ({
    status: statusFilter,
    disposal_type: typeFilter,
  }), [statusFilter, typeFilter]);

  const activeFilterCount = [statusFilter, typeFilter].filter(Boolean).length;

  function clearFilters() {
    setStatusFilter('');
    setTypeFilter('');
  }

  function getAssetName(asset?: FixedAsset | null): string {
    if (!asset) return appDict.notAvailable;
    if (typeof asset.name === 'string') return asset.name;
    return asset.name[locale] || asset.name.en || asset.asset_number;
  }

  function formatDisposalType(type: FixedAssetDisposalRow['disposal_type']): string {
    const labels: Record<FixedAssetDisposalRow['disposal_type'], string> = {
      sale: appDict.sale,
      scrap: appDict.scrap,
      retirement: appDict.retirement,
    };

    return labels[type] || type;
  }

  function formatDisposalStatus(status: FixedAssetDisposalRow['status']): string {
    return status === 'posted' ? appDict.statusPosted : appDict.statusReversed;
  }

  function formatAssetMoney(amountMinor: number, asset?: FixedAsset | null): string {
    return asset?.currency ? formatMoney(amountMinor, asset.currency) : appDict.notAvailable;
  }

  const columns = useMemo(
    () => [
      {
        data: 'number',
        name: 'number',
        title: appDict.disposalNumber,
        className: 'font-mono text-sm',
      },
      {
        data: 'asset_name',
        name: 'asset_name',
        title: appDict.fixedAsset,
      },
      {
        data: 'disposal_date',
        name: 'disposal_date',
        title: appDict.disposalDate,
      },
      {
        data: 'disposal_type',
        name: 'disposal_type',
        title: appDict.disposalType,
      },
      {
        data: 'proceeds_minor',
        name: 'proceeds_minor',
        title: appDict.proceeds,
        className: 'text-right font-mono',
      },
      {
        data: 'net_book_value_minor',
        name: 'net_book_value_minor',
        title: appDict.netBookValue,
        className: 'text-right font-mono',
      },
      {
        data: 'gain_minor',
        name: 'gain_minor',
        title: appDict.gainLoss,
        className: 'text-right font-mono',
      },
      {
        data: 'status',
        name: 'status',
        title: appDict.status,
      },
      {
        data: 'id',
        name: 'id',
        title: appDict.action,
        orderable: false,
        searchable: false,
        className: 'text-right',
      },
    ],
    [appDict],
  );

  const slots: DataTableSlots = useMemo(
    () => ({
      number: (_data: any, _type: any, row: FixedAssetDisposalRow) => (
        <Link
          href={`/fixed-assets-disposals/${row.id}`}
          className="font-mono text-sm text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 font-semibold"
        >
          {row.number}
        </Link>
      ),
      asset_name: (_data: any, _type: any, row: FixedAssetDisposalRow) => (
        <div>
          <div className="font-medium text-slate-900 dark:text-slate-100">{getAssetName(row.asset)}</div>
          <div className="text-xs font-mono text-slate-500">{row.asset?.asset_number}</div>
        </div>
      ),
      disposal_date: (_data: any, _type: any, row: FixedAssetDisposalRow) => (
        row.disposal_date ? String(row.disposal_date).split('T')[0] : ''
      ),
      disposal_type: (_data: any, _type: any, row: FixedAssetDisposalRow) => (
        <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 capitalize">
          {formatDisposalType(row.disposal_type)}
        </span>
      ),
      proceeds_minor: (_data: any, _type: any, row: FixedAssetDisposalRow) => formatAssetMoney(row.proceeds_minor, row.asset),
      net_book_value_minor: (_data: any, _type: any, row: FixedAssetDisposalRow) => formatAssetMoney(row.net_book_value_minor, row.asset),
      gain_minor: (_data: any, _type: any, row: FixedAssetDisposalRow) => (
        <span className="font-semibold">
          {row.gain_minor > 0 ? (
            <span className="text-emerald-600 dark:text-emerald-400">+{formatAssetMoney(row.gain_minor, row.asset)}</span>
          ) : row.loss_minor > 0 ? (
            <span className="text-rose-600 dark:text-rose-400">-{formatAssetMoney(row.loss_minor, row.asset)}</span>
          ) : (
            <span className="text-slate-500">{formatAssetMoney(0, row.asset)}</span>
          )}
        </span>
      ),
      status: (_data: any, _type: any, row: FixedAssetDisposalRow) => (
        <StatusBadge tone={row.status === 'posted' ? 'ok' : 'danger'}>
          {formatDisposalStatus(row.status)}
        </StatusBadge>
      ),
      id: (_data: any, _type: any, row: FixedAssetDisposalRow) => (
        <div className="flex justify-end">
          <Link
            href={`/fixed-assets-disposals/${row.id}`}
            className="inline-flex items-center gap-1 text-xs font-medium text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300"
          >
            <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
            <span>{appDict.disposalDetails}</span>
          </Link>
        </div>
      ),
    }),
    [locale, appDict],
  );

  const toolbar = (
    <div className="flex flex-wrap items-center gap-2">
      <div className="w-48 shrink-0">
        <SearchableSelect
          value={typeFilter}
          options={typeOptions}
          onChange={(val) => setTypeFilter(val || '')}
          placeholder={appDict.allTypes}
          isClearable={false}
          isSearchable={false}
        />
      </div>
      <div className="w-40 shrink-0">
        <SearchableSelect
          value={statusFilter}
          options={statusOptions}
          onChange={(val) => setStatusFilter(val || '')}
          placeholder={appDict.allStatuses}
          isClearable={false}
          isSearchable={false}
        />
      </div>
      <Button variant="secondary" onClick={clearFilters} disabled={activeFilterCount === 0}>
        {appDict.clearFilters}
      </Button>
    </div>
  );

  return (
    <AppLayout active="fixed-assets-disposals.index">
      <Head title={appDict.title} />

      <div className="space-y-6">
        <PageHeader
          title={appDict.title}
          description={appDict.description}
          actions={
            <Link
              href="/fixed-assets"
              className="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 transition"
            >
              <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 11H5m7 7l-7-7 7-7" />
              </svg>
              <span>{appDict.fixedAssetRegister}</span>
            </Link>
          }
        />

        <ServerDataTable
          ajaxUrl="/fixed-assets-disposals/data"
          columns={columns}
          slots={slots}
          toolbar={toolbar}
          filters={extraFilters}
          locale={locale}
        />
      </div>
    </AppLayout>
  );
}
