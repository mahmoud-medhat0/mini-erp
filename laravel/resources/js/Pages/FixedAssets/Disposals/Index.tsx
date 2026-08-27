import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../../Components/AppLayout';
import { Button, Card, PageHeader, SearchableSelect } from '../../../Components/Primitives';
import { formatMoney } from '../../../lib/accountingHelpers';
import { getDictionary } from '../../../lib/i18n';
import type { SharedPageProps } from '../../../Types/page';

type FixedAsset = {
  id: string;
  asset_number: string;
  name: Record<string, string> | string;
  currency: string;
};

type FixedAssetDisposal = {
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

type PaginatedDisposals = {
  data: FixedAssetDisposal[];
  current_page: number;
  last_page: number;
  total: number;
};

type IndexProps = SharedPageProps & {
  disposals: PaginatedDisposals;
  filters: {
    search?: string;
    status?: string;
    disposal_type?: string;
  };
};

export default function DisposalsIndex({ locale, disposals, filters }: IndexProps) {
  const dict = getDictionary(locale);
  const appDict = dict.app.fixedAssetsDisposals;

  const [search, setSearch] = useState(filters.search || '');
  const [statusFilter, setStatusFilter] = useState(filters.status || '');
  const [typeFilter, setTypeFilter] = useState(filters.disposal_type || '');
  const typeOptions = [
    { value: 'sale', label: appDict.sale },
    { value: 'scrap', label: appDict.scrap },
    { value: 'retirement', label: appDict.retirement },
  ];
  const statusOptions = [
    { value: 'posted', label: appDict.statusPosted },
    { value: 'reversed', label: appDict.statusReversed },
  ];
  const activeFilterCount = [search, statusFilter, typeFilter].filter(Boolean).length;

  function getAssetName(asset?: FixedAsset | null): string {
    if (!asset) return appDict.notAvailable;
    if (typeof asset.name === 'string') return asset.name;
    return asset.name[locale] || asset.name.en || asset.asset_number;
  }

  function formatDisposalType(type: FixedAssetDisposal['disposal_type']): string {
    const labels: Record<FixedAssetDisposal['disposal_type'], string> = {
      sale: appDict.sale,
      scrap: appDict.scrap,
      retirement: appDict.retirement,
    };

    return labels[type];
  }

  function formatDisposalStatus(status: FixedAssetDisposal['status']): string {
    return status === 'posted' ? appDict.statusPosted : appDict.statusReversed;
  }

  function formatAssetMoney(amountMinor: number, asset?: FixedAsset | null): string {
    return asset?.currency ? formatMoney(amountMinor, asset.currency) : appDict.notAvailable;
  }

  function handleFilterChange() {
    router.get(
      '/fixed-assets-disposals',
      { search, status: statusFilter, disposal_type: typeFilter },
      { preserveState: true, preserveScroll: true, replace: true }
    );
  }

  function clearFilters() {
    setSearch('');
    setStatusFilter('');
    setTypeFilter('');
    router.get('/fixed-assets-disposals', {}, { preserveState: true, preserveScroll: true, replace: true });
  }

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

        {/* Filter Card */}
        <Card className="p-4">
          <div className="grid grid-cols-1 gap-4 md:grid-cols-[minmax(0,1fr)_220px_220px_auto_auto] md:items-end">
            <div>
              <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                {appDict.search}
              </label>
              <input
                type="text"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                onKeyDown={(e) => e.key === 'Enter' && handleFilterChange()}
                placeholder={appDict.searchPlaceholder}
                className="w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900 text-sm"
              />
            </div>

            <SearchableSelect options={[{ value: '', label: appDict.allTypes }, ...typeOptions]} value={typeFilter || null} onChange={(value) => setTypeFilter(value || '')} label={appDict.disposalType} />
            <SearchableSelect options={[{ value: '', label: appDict.allStatuses }, ...statusOptions]} value={statusFilter || null} onChange={(value) => setStatusFilter(value || '')} label={appDict.status} />
            <Button onClick={handleFilterChange}>{appDict.applyFilters}</Button>
            <Button variant="secondary" onClick={clearFilters} disabled={activeFilterCount === 0}>{appDict.clearFilters}</Button>
          </div>
        </Card>

        {/* Disposals Table */}
        <Card className="overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-sm text-left rtl:text-right">
              <thead className="bg-slate-50 dark:bg-slate-800/50 text-slate-700 dark:text-slate-300 uppercase text-xs">
                <tr>
                  <th className="px-4 py-3">{appDict.disposalNumber}</th>
                  <th className="px-4 py-3">{appDict.fixedAsset}</th>
                  <th className="px-4 py-3">{appDict.disposalDate}</th>
                  <th className="px-4 py-3">{appDict.disposalType}</th>
                  <th className="px-4 py-3 text-right">{appDict.proceeds}</th>
                  <th className="px-4 py-3 text-right">{appDict.netBookValue}</th>
                  <th className="px-4 py-3 text-right">{appDict.gainLoss}</th>
                  <th className="px-4 py-3">{appDict.status}</th>
                  <th className="px-4 py-3 text-right">{appDict.action}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-200 dark:divide-slate-700">
                {disposals.data.length === 0 ? (
                  <tr>
                    <td colSpan={9} className="px-4 py-8 text-center text-slate-500">
                      {appDict.empty}
                    </td>
                  </tr>
                ) : (
                  disposals.data.map((item) => (
                    <tr key={item.id} className="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                      <td className="px-4 py-3 font-mono font-medium text-indigo-600 dark:text-indigo-400">
                        <Link href={`/fixed-assets-disposals/${item.id}`}>{item.number}</Link>
                      </td>
                      <td className="px-4 py-3">
                        <div className="font-medium text-slate-900 dark:text-slate-100">{getAssetName(item.asset)}</div>
                        <div className="text-xs font-mono text-slate-500">{item.asset?.asset_number}</div>
                      </td>
                      <td className="px-4 py-3">{item.disposal_date}</td>
                      <td className="px-4 py-3 capitalize">
                        <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200">
                          {formatDisposalType(item.disposal_type)}
                        </span>
                      </td>
                      <td className="px-4 py-3 text-right font-mono">{formatAssetMoney(item.proceeds_minor, item.asset)}</td>
                      <td className="px-4 py-3 text-right font-mono">{formatAssetMoney(item.net_book_value_minor, item.asset)}</td>
                      <td className="px-4 py-3 text-right font-mono font-semibold">
                        {item.gain_minor > 0 ? (
                          <span className="text-emerald-600 dark:text-emerald-400">+{formatAssetMoney(item.gain_minor, item.asset)}</span>
                        ) : item.loss_minor > 0 ? (
                          <span className="text-rose-600 dark:text-rose-400">-{formatAssetMoney(item.loss_minor, item.asset)}</span>
                        ) : (
                          <span className="text-slate-500">{formatAssetMoney(0, item.asset)}</span>
                        )}
                      </td>
                      <td className="px-4 py-3">
                        <span
                          className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                            item.status === 'posted'
                              ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300'
                              : 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300'
                          }`}
                        >
                          {formatDisposalStatus(item.status)}
                        </span>
                      </td>
                      <td className="px-4 py-3 text-right">
                        <Link
                          href={`/fixed-assets-disposals/${item.id}`}
                          className="text-indigo-600 dark:text-indigo-400 hover:underline font-medium"
                        >
                          {appDict.disposalDetails}
                        </Link>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </Card>
      </div>
    </AppLayout>
  );
}
