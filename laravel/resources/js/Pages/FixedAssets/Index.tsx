import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AppLayout from '../../Components/AppLayout';
import { PageHeader, SearchableSelect, StatusBadge } from '../../Components/Primitives';
import ServerDataTable, { type DataTableSlots } from '../../Components/ServerDataTable';
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
  assets?: any;
  categories: CategoryOption[];
  branches?: Array<{ id: string; code: string; name: Record<string, string> | string }>;
  locations?: Array<{ id: string; code: string; name: Record<string, string> | string }>;
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

export default function FixedAssetsIndex({
  locale,
  categories = [],
  branches = [],
  locations = [],
  filters = {},
  can = { create: false, edit: false, delete: false, post: false, transfer: false, export: false, view_financials: false },
}: IndexProps) {
  const dict = getDictionary(locale);
  const appDict = dict.app.accounting;

  const [selectedCat, setSelectedCat] = useState(filters.category_id || '');
  const [selectedStatus, setSelectedStatus] = useState(filters.status || '');
  const [selectedBranch, setSelectedBranch] = useState(filters.branch_id || '');
  const [selectedLocation, setSelectedLocation] = useState(filters.location_id || '');

  const categoryOptions = useMemo(() => [
    { value: '', label: appDict.allAssetCategories },
    ...categories.map((cat) => ({
      value: cat.id,
      label: `${getLocalizedName(cat.name, locale)} (${cat.code})`,
    })),
  ], [categories, locale, appDict.allAssetCategories]);

  const branchOptions = useMemo(() => [
    { value: '', label: appDict.allBranches },
    ...branches.map((branch) => ({
      value: branch.id,
      label: `${branch.code} - ${getLocalizedName(branch.name, locale)}`,
    })),
  ], [branches, locale, appDict.allBranches]);

  const locationOptions = useMemo(() => [
    { value: '', label: appDict.allAssetLocations },
    ...locations.map((location) => ({
      value: location.id,
      label: `${location.code} - ${getLocalizedName(location.name, locale)}`,
    })),
  ], [locations, locale, appDict.allAssetLocations]);

  const statusOptions = useMemo(() => [
    { value: '', label: appDict.allStatuses },
    { value: 'draft', label: appDict.fixedAssetStatusDraft },
    { value: 'active', label: appDict.fixedAssetStatusActive },
    { value: 'fully_depreciated', label: appDict.fixedAssetStatusFullyDepreciated },
    { value: 'disposed', label: appDict.fixedAssetStatusDisposed },
  ], [appDict]);

  const extraFilters = useMemo(() => ({
    category_id: selectedCat,
    status: selectedStatus,
    branch_id: selectedBranch,
    location_id: selectedLocation,
  }), [selectedCat, selectedStatus, selectedBranch, selectedLocation]);

  function getStatusTone(status: string): 'ok' | 'warning' | 'info' | 'muted' {
    switch (status) {
      case 'active':
        return 'ok';
      case 'draft':
        return 'warning';
      case 'fully_depreciated':
        return 'info';
      case 'disposed':
      default:
        return 'muted';
    }
  }

  function getStatusLabel(status: string): string {
    switch (status) {
      case 'active':
        return appDict.fixedAssetStatusActive;
      case 'draft':
        return appDict.fixedAssetStatusDraft;
      case 'fully_depreciated':
        return appDict.fixedAssetStatusFullyDepreciated;
      case 'disposed':
        return appDict.fixedAssetStatusDisposed;
      default:
        return status;
    }
  }

  const columns = useMemo(
    () => [
      {
        data: 'asset_number',
        name: 'asset_number',
        title: appDict.assetNumber,
        className: 'font-mono text-sm',
      },
      {
        data: 'name_text',
        name: 'name_text',
        title: appDict.name,
      },
      {
        data: 'category_name',
        name: 'category_name',
        title: appDict.assetCategory,
      },
      {
        data: 'branch_name',
        name: 'branch_name',
        title: appDict.branch,
      },
      {
        data: 'location_name',
        name: 'location_name',
        title: appDict.assetLocation,
      },
      {
        data: 'acquisition_date',
        name: 'acquisition_date',
        title: appDict.acquisitionDate,
      },
      {
        data: 'cost_minor',
        name: 'cost_minor',
        title: appDict.cost,
      },
      {
        data: 'status',
        name: 'status',
        title: appDict.status,
      },
      {
        data: 'id',
        name: 'id',
        title: appDict.actions,
        orderable: false,
        searchable: false,
      },
    ],
    [appDict],
  );

  const slots: DataTableSlots = useMemo(
    () => ({
      asset_number: (_data: any, _type: any, row: AssetRow) => (
        <Link
          href={`/fixed-assets/${row.id}`}
          className="font-mono text-sm text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 font-semibold"
        >
          {row.asset_number}
        </Link>
      ),
      name_text: (_data: any, _type: any, row: AssetRow) => getLocalizedName(row.name, locale),
      category_name: (_data: any, _type: any, row: AssetRow) => (row.category ? getLocalizedName(row.category.name, locale) : appDict.notAvailable),
      branch_name: (_data: any, _type: any, row: AssetRow) => (row.branch ? `${row.branch.code} - ${getLocalizedName(row.branch.name, locale)}` : appDict.notAssigned),
      location_name: (_data: any, _type: any, row: AssetRow) => (row.location ? `${row.location.code} - ${getLocalizedName(row.location.name, locale)}` : appDict.notAssigned),
      cost_minor: (_data: any, _type: any, row: AssetRow) => (can.view_financials ? formatMoney(row.cost_minor, row.currency) : appDict.restrictedValue),
      status: (_data: any, _type: any, row: AssetRow) => (
        <StatusBadge tone={getStatusTone(row.status)}>
          {getStatusLabel(row.status)}
        </StatusBadge>
      ),
      id: (_data: any, _type: any, row: AssetRow) => (
        <div className="flex items-center gap-2.5">
          <Link
            href={`/fixed-assets/${row.id}`}
            className="inline-flex items-center gap-1 text-xs font-medium text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200"
          >
            <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
            <span>{appDict.view}</span>
          </Link>
          {can.edit && row.status === 'draft' && (
            <Link
              href={`/fixed-assets/${row.id}/edit`}
              className="inline-flex items-center gap-1 text-xs font-medium text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300"
            >
              <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
              </svg>
              <span>{appDict.editFixedAsset}</span>
            </Link>
          )}
        </div>
      ),
    }),
    [locale, can, appDict],
  );

  const toolbar = (
    <div className="flex flex-wrap items-center gap-2">
      <div className="w-52 shrink-0">
        <SearchableSelect
          value={selectedCat}
          options={categoryOptions}
          onChange={(val) => setSelectedCat(val || '')}
          placeholder={appDict.allAssetCategories}
          isClearable={false}
        />
      </div>
      <div className="w-40 shrink-0">
        <SearchableSelect
          value={selectedStatus}
          options={statusOptions}
          onChange={(val) => setSelectedStatus(val || '')}
          placeholder={appDict.allStatuses}
          isClearable={false}
          isSearchable={false}
        />
      </div>
      <div className="w-48 shrink-0">
        <SearchableSelect
          value={selectedBranch}
          options={branchOptions}
          onChange={(val) => setSelectedBranch(val || '')}
          placeholder={appDict.allBranches}
          isClearable={false}
        />
      </div>
      <div className="w-48 shrink-0">
        <SearchableSelect
          value={selectedLocation}
          options={locationOptions}
          onChange={(val) => setSelectedLocation(val || '')}
          placeholder={appDict.allAssetLocations}
          isClearable={false}
        />
      </div>
    </div>
  );

  return (
    <AppLayout active="fixed-assets.index">
      <Head title={`${appDict.fixedAssets} - ${appDict.appName}`} />

      <div className="space-y-6">
        <PageHeader
          title={appDict.fixedAssets}
          description={appDict.fixedAssets}
          actions={
            <div className="flex items-center gap-2.5">
              <Link
                href="/fixed-asset-categories"
                className="px-3.5 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-md hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-200 dark:border-slate-700"
              >
                {appDict.fixedAssetCategories}
              </Link>
              <Link
                href="/fixed-asset-locations"
                className="px-3.5 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-md hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-200 dark:border-slate-700"
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

        <ServerDataTable
          ajaxUrl="/fixed-assets/data"
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
