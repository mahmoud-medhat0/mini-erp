import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Components/AppLayout';
import { Card, EmptyState, PageHeader, tableClasses } from '../../Components/Primitives';
import { getDictionary } from '../../lib/i18n';
import type { SharedPageProps } from '../../Types/page';

type CategoryOption = {
  id: string;
  code: string;
  name: { en: string; ar: string } | string;
};

type AssetRow = {
  id: string;
  asset_number: string;
  name: { en: string; ar: string } | string;
  fixed_asset_category_id: string;
  currency: string;
  acquisition_date: string;
  in_service_date: string;
  cost_minor: number;
  salvage_value_minor: number;
  useful_life_months: number;
  depreciation_method: string;
  opening_accumulated_depreciation_minor: number;
  status: 'draft' | 'active' | 'fully_depreciated' | 'disposed';
  serial_number?: string | null;
  category?: CategoryOption | null;
};

type IndexProps = SharedPageProps & {
  assets: {
    data: AssetRow[];
    links: any[];
  };
  categories: CategoryOption[];
  filters: {
    search?: string;
    category_id?: string;
    status?: string;
  };
  can: {
    create: boolean;
    edit: boolean;
    delete: boolean;
    post: boolean;
    export: boolean;
    view_financials: boolean;
  };
};

export default function FixedAssetsIndex({ locale, assets, categories, filters, can }: IndexProps) {
  const dict = getDictionary(locale);
  const appDict = (dict.app as any).accounting || {};

  const [search, setSearch] = useState(filters.search || '');
  const [selectedCat, setSelectedCat] = useState(filters.category_id || '');
  const [selectedStatus, setSelectedStatus] = useState(filters.status || '');

  function handleFilter() {
    router.get('/fixed-assets', { search, category_id: selectedCat, status: selectedStatus }, { preserveState: true });
  }

  function formatName(name: { en: string; ar: string } | string): string {
    if (typeof name === 'object' && name !== null) {
      return locale === 'ar' ? name.ar || name.en : name.en || name.ar;
    }
    return String(name);
  }

  function formatStatus(status: string): { label: string; className: string } {
    switch (status) {
      case 'active':
        return { label: appDict.fixedAssetStatusActive, className: 'bg-emerald-100 text-emerald-800' };
      case 'draft':
        return { label: appDict.fixedAssetStatusDraft, className: 'bg-amber-100 text-amber-800' };
      case 'fully_depreciated':
        return { label: appDict.fixedAssetStatusFullyDepreciated, className: 'bg-blue-100 text-blue-800' };
      case 'disposed':
        return { label: appDict.fixedAssetStatusDisposed, className: 'bg-slate-100 text-slate-800' };
      default:
        return { label: appDict.statusUnknown, className: 'bg-slate-100 text-slate-800' };
    }
  }

  return (
    <AppLayout active="fixed-assets.index">
      <Head title={`${appDict.fixedAssets} - ${appDict.appName}`} />

      <div className="space-y-6">
        <PageHeader
          title={appDict.fixedAssets}
          description={appDict.fixedAssets}
          actions={
            <div className="flex items-center space-x-2 rtl:space-x-reverse">
              <Link
                href="/fixed-asset-categories"
                className="px-3 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-md hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-200 dark:border-slate-700"
              >
                {appDict.fixedAssetCategories}
              </Link>
              {can.create && (
                <Link
                  href="/fixed-assets/create"
                  className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700"
                >
                  {appDict.createFixedAsset}
                </Link>
              )}
            </div>
          }
        />

        <Card>
          <div className="p-4 border-b border-slate-200 dark:border-slate-700 flex flex-wrap gap-4 items-center">
            <input
              type="text"
              placeholder={appDict.searchFixedAssetsPlaceholder}
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="rounded-md border-slate-300 dark:bg-slate-900 dark:border-slate-700 text-sm"
            />
            <select
              value={selectedCat}
              onChange={(e) => setSelectedCat(e.target.value)}
              className="rounded-md border-slate-300 dark:bg-slate-900 dark:border-slate-700 text-sm"
            >
              <option value="">{appDict.allAssetCategories}</option>
              {categories.map((cat) => (
                <option key={cat.id} value={cat.id}>
                  {formatName(cat.name)} ({cat.code})
                </option>
              ))}
            </select>
            <select
              value={selectedStatus}
              onChange={(e) => setSelectedStatus(e.target.value)}
              className="rounded-md border-slate-300 dark:bg-slate-900 dark:border-slate-700 text-sm"
            >
              <option value="">{appDict.allStatuses}</option>
              <option value="draft">{appDict.fixedAssetStatusDraft}</option>
              <option value="active">{appDict.fixedAssetStatusActive}</option>
              <option value="fully_depreciated">{appDict.fixedAssetStatusFullyDepreciated}</option>
              <option value="disposed">{appDict.fixedAssetStatusDisposed}</option>
            </select>
            <button
              type="button"
              onClick={handleFilter}
              className="px-3 py-2 text-sm font-medium text-slate-700 bg-slate-100 rounded-md hover:bg-slate-200"
            >
              {appDict.filter}
            </button>
          </div>

          {assets.data.length === 0 ? (
            <EmptyState
              title={appDict.noFixedAssets}
              description={appDict.noFixedAssets}
            />
          ) : (
            <div className="overflow-x-auto">
              <table className={tableClasses.table}>
                <thead>
                  <tr>
                    <th className={tableClasses.th}>{appDict.assetNumber}</th>
                    <th className={tableClasses.th}>{appDict.name}</th>
                    <th className={tableClasses.th}>{appDict.assetCategory}</th>
                    <th className={tableClasses.th}>{appDict.acquisitionDate}</th>
                    <th className={tableClasses.th}>{appDict.cost}</th>
                    <th className={tableClasses.th}>{appDict.status}</th>
                    <th className={tableClasses.th}>{appDict.actions}</th>
                  </tr>
                </thead>
                <tbody>
                  {assets.data.map((asset) => {
                    const st = formatStatus(asset.status);
                    return (
                      <tr key={asset.id}>
                        <td className={`${tableClasses.td} font-mono`}>
                          <Link href={`/fixed-assets/${asset.id}`} className="text-indigo-600 hover:text-indigo-900 font-mono">
                            {asset.asset_number}
                          </Link>
                        </td>
                        <td className={tableClasses.td}>{formatName(asset.name)}</td>
                        <td className={tableClasses.td}>
                          {asset.category ? formatName(asset.category.name) : '-'}
                        </td>
                        <td className={tableClasses.td}>{asset.acquisition_date}</td>
                        <td className={tableClasses.td}>
                          {can.view_financials ? `${asset.cost_minor} ${asset.currency}` : '***'}
                        </td>
                        <td className={tableClasses.td}>
                          <span className={`inline-flex px-2 py-0.5 text-xs font-semibold rounded-full ${st.className}`}>
                            {st.label}
                          </span>
                        </td>
                        <td className={tableClasses.td}>
                          <div className="flex items-center space-x-2 rtl:space-x-reverse">
                            <Link
                              href={`/fixed-assets/${asset.id}`}
                              className="text-xs font-medium text-slate-600 hover:text-slate-900"
                            >
                              {appDict.view}
                            </Link>
                            {can.edit && (
                              <Link
                                href={`/fixed-assets/${asset.id}/edit`}
                                className="text-xs font-medium text-indigo-600 hover:text-indigo-900"
                              >
                                {appDict.editFixedAsset}
                              </Link>
                            )}
                          </div>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
        </Card>
      </div>
    </AppLayout>
  );
}
