import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AppLayout from '../../Components/AppLayout';
import { Button, Card, EmptyState, PageHeader, SearchableSelect, tableClasses } from '../../Components/Primitives';
import { formatMoney, getLocalizedName } from '../../lib/accountingHelpers';
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
  branch_id?: string | null;
  fixed_asset_location_id?: string | null;
  branch?: { id: string; code: string; name: Record<string, string> | string } | null;
  location?: { id: string; code: string; name: Record<string, string> | string } | null;
};

type IndexProps = SharedPageProps & {
  assets: {
    data: AssetRow[];
    links: any[];
  };
  categories: CategoryOption[];
  branches: Array<{ id: string; code: string; name: Record<string, string> | string }>;
  locations: Array<{ id: string; code: string; name: Record<string, string> | string }>;
  filters: {
    search?: string;
    category_id?: string;
    status?: string;
    branch_id?: string;
    location_id?: string;
  };
  can: {
    create: boolean;
    edit: boolean;
    delete: boolean;
    post: boolean;
    transfer: boolean;
    export: boolean;
    view_financials: boolean;
  };
};

export default function FixedAssetsIndex({ locale, assets, categories, branches = [], locations = [], filters, can }: IndexProps) {
  const dict = getDictionary(locale);
  const appDict = dict.app.accounting;

  const [search, setSearch] = useState(filters.search || '');
  const [selectedCat, setSelectedCat] = useState(filters.category_id || '');
  const [selectedStatus, setSelectedStatus] = useState(filters.status || '');
  const [selectedBranch, setSelectedBranch] = useState(filters.branch_id || '');
  const [selectedLocation, setSelectedLocation] = useState(filters.location_id || '');
  const categoryOptions = useMemo(() => categories.map((cat) => ({
    value: cat.id,
    label: `${formatName(cat.name)} (${cat.code})`,
  })), [categories, locale]);
  const branchOptions = useMemo(() => branches.map((branch) => ({
    value: branch.id,
    label: `${branch.code} - ${getLocalizedName(branch.name, locale)}`,
  })), [branches, locale]);
  const locationOptions = useMemo(() => locations.map((location) => ({
    value: location.id,
    label: `${location.code} - ${getLocalizedName(location.name, locale)}`,
  })), [locations, locale]);
  const statusOptions = [
    { value: 'draft', label: appDict.fixedAssetStatusDraft },
    { value: 'active', label: appDict.fixedAssetStatusActive },
    { value: 'fully_depreciated', label: appDict.fixedAssetStatusFullyDepreciated },
    { value: 'disposed', label: appDict.fixedAssetStatusDisposed },
  ];
  const activeFilterCount = [search, selectedCat, selectedStatus, selectedBranch, selectedLocation].filter(Boolean).length;

  function handleFilter() {
    router.get('/fixed-assets', {
      search,
      category_id: selectedCat,
      status: selectedStatus,
      branch_id: selectedBranch,
      location_id: selectedLocation,
    }, { preserveState: true, preserveScroll: true });
  }

  function clearFilters() {
    setSearch('');
    setSelectedCat('');
    setSelectedStatus('');
    setSelectedBranch('');
    setSelectedLocation('');
    router.get('/fixed-assets', {}, { preserveState: true, preserveScroll: true });
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
              <Link
                href="/fixed-asset-locations"
                className="px-3 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-md hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-200 dark:border-slate-700"
              >
                {appDict.fixedAssetLocations}
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
            <SearchableSelect options={[{ value: '', label: appDict.allAssetCategories }, ...categoryOptions]} value={selectedCat || null} onChange={(value) => setSelectedCat(value || '')} label={appDict.assetCategory} />
            <SearchableSelect options={[{ value: '', label: appDict.allStatuses }, ...statusOptions]} value={selectedStatus || null} onChange={(value) => setSelectedStatus(value || '')} label={appDict.status} />
            <SearchableSelect options={[{ value: '', label: appDict.allBranches }, ...branchOptions]} value={selectedBranch || null} onChange={(value) => setSelectedBranch(value || '')} label={appDict.branch} />
            <SearchableSelect options={[{ value: '', label: appDict.allAssetLocations }, ...locationOptions]} value={selectedLocation || null} onChange={(value) => setSelectedLocation(value || '')} label={appDict.assetLocation} />
            <Button onClick={handleFilter}>{appDict.filter}</Button>
            <Button variant="secondary" onClick={clearFilters} disabled={activeFilterCount === 0}>{appDict.clearFilters}</Button>
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
                    <th className={tableClasses.th}>{appDict.branch}</th>
                    <th className={tableClasses.th}>{appDict.assetLocation}</th>
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
                          {asset.category ? formatName(asset.category.name) : appDict.notAvailable}
                        </td>
                        <td className={tableClasses.td}>
                          {asset.branch ? `${asset.branch.code} - ${getLocalizedName(asset.branch.name, locale)}` : appDict.notAssigned}
                        </td>
                        <td className={tableClasses.td}>
                          {asset.location ? `${asset.location.code} - ${getLocalizedName(asset.location.name, locale)}` : appDict.notAssigned}
                        </td>
                        <td className={tableClasses.td}>{asset.acquisition_date}</td>
                        <td className={tableClasses.td}>
                          {can.view_financials ? formatMoney(asset.cost_minor, asset.currency) : appDict.restrictedValue}
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
