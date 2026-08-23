import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../Components/AppLayout';
import { Card, PageHeader } from '../../Components/Primitives';
import { getDictionary } from '../../lib/i18n';
import type { SharedPageProps } from '../../Types/page';

type CategoryOption = {
  id: string;
  code: string;
  name: { en: string; ar: string } | string;
};

type AssetDetail = {
  id: string;
  asset_number: string;
  name: { en: string; ar: string } | string;
  description?: string | null;
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
  created_at: string;
  category?: CategoryOption | null;
  creator?: { id: number; name: string } | null;
  updater?: { id: number; name: string } | null;
};

type AttachmentRow = {
  id: string;
  name: string;
  mime: string;
  size: number;
  at: string;
};

type ShowProps = SharedPageProps & {
  asset: AssetDetail;
  attachments?: AttachmentRow[];
  can: {
    edit: boolean;
    delete: boolean;
    post: boolean;
    view_financials: boolean;
  };
};

export default function FixedAssetShow({ locale, asset, attachments = [], can }: ShowProps) {
  const dict = getDictionary(locale);
  const appDict = (dict.app as any).accounting || {};

  function handleDelete() {
    if (confirm(appDict.confirmDeleteDraftAsset)) {
      router.delete(`/fixed-assets/${asset.id}`);
    }
  }

  function formatName(name: { en: string; ar: string } | string): string {
    if (typeof name === 'object' && name !== null) {
      return locale === 'ar' ? name.ar || name.en : name.en || name.ar;
    }
    return String(name);
  }

  function formatStatus(status: string): string {
    switch (status) {
      case 'draft':
        return appDict.fixedAssetStatusDraft;
      case 'active':
        return appDict.fixedAssetStatusActive;
      case 'fully_depreciated':
        return appDict.fixedAssetStatusFullyDepreciated;
      case 'disposed':
        return appDict.fixedAssetStatusDisposed;
      default:
        return appDict.statusUnknown;
    }
  }

  const depreciableBase = asset.cost_minor - asset.salvage_value_minor;
  const netBookValue = asset.cost_minor - asset.opening_accumulated_depreciation_minor;

  return (
    <AppLayout active="fixed-assets.index">
      <Head title={`${asset.asset_number} - ${appDict.fixedAssets} - ${appDict.appName}`} />

      <div className="max-w-4xl mx-auto space-y-6">
        <PageHeader
          title={`${asset.asset_number} - ${formatName(asset.name)}`}
          description={appDict.fixedAssets}
          actions={
            <div className="flex items-center space-x-2 rtl:space-x-reverse">
              <Link
                href="/fixed-assets"
                className="px-3 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-md hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-200 dark:border-slate-700"
              >
                {appDict.back}
              </Link>
              {can.edit && (
                <Link
                  href={`/fixed-assets/${asset.id}/edit`}
                  className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700"
                >
                  {appDict.editFixedAsset}
                </Link>
              )}
              {can.delete && (
                <button
                  type="button"
                  onClick={handleDelete}
                  className="px-4 py-2 text-sm font-medium text-white bg-rose-600 rounded-md hover:bg-rose-700"
                >
                  {appDict.delete}
                </button>
              )}
            </div>
          }
        />

        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          <Card className="md:col-span-2 p-6 space-y-6">
            <h3 className="text-lg font-semibold text-slate-900 dark:text-slate-100 border-b pb-2 border-slate-200 dark:border-slate-700">
              {appDict.assetInformation}
            </h3>

            <dl className="grid grid-cols-2 gap-4 text-sm">
              <div>
                <dt className="text-slate-500 dark:text-slate-400">{appDict.assetNumber}</dt>
                <dd className="font-mono font-medium text-slate-900 dark:text-slate-100">{asset.asset_number}</dd>
              </div>

              <div>
                <dt className="text-slate-500 dark:text-slate-400">{appDict.assetCategory}</dt>
                <dd className="font-medium text-slate-900 dark:text-slate-100">
                  {asset.category ? formatName(asset.category.name) : '-'}
                </dd>
              </div>

              <div>
                <dt className="text-slate-500 dark:text-slate-400">{appDict.acquisitionDate}</dt>
                <dd className="font-medium text-slate-900 dark:text-slate-100">{asset.acquisition_date}</dd>
              </div>

              <div>
                <dt className="text-slate-500 dark:text-slate-400">{appDict.inServiceDate}</dt>
                <dd className="font-medium text-slate-900 dark:text-slate-100">{asset.in_service_date}</dd>
              </div>

              <div>
                <dt className="text-slate-500 dark:text-slate-400">{appDict.depreciationMethod}</dt>
                <dd className="font-medium text-slate-900 dark:text-slate-100">{appDict.straightLine}</dd>
              </div>

              <div>
                <dt className="text-slate-500 dark:text-slate-400">{appDict.usefulLifeMonths}</dt>
                <dd className="font-medium text-slate-900 dark:text-slate-100">{asset.useful_life_months}</dd>
              </div>

              <div>
                <dt className="text-slate-500 dark:text-slate-400">{appDict.serialNumber}</dt>
                <dd className="font-mono text-slate-900 dark:text-slate-100">{asset.serial_number || '-'}</dd>
              </div>

              <div>
                <dt className="text-slate-500 dark:text-slate-400">{appDict.status}</dt>
                <dd className="font-medium text-slate-900 dark:text-slate-100">{formatStatus(asset.status)}</dd>
              </div>
            </dl>

            {asset.description && (
              <div className="pt-4 border-t border-slate-200 dark:border-slate-700">
                <dt className="text-xs font-medium text-slate-500 dark:text-slate-400">{appDict.description}</dt>
                <dd className="mt-1 text-sm text-slate-800 dark:text-slate-200 whitespace-pre-line">{asset.description}</dd>
              </div>
            )}
          </Card>

          <Card className="p-6 space-y-4">
            <h3 className="text-lg font-semibold text-slate-900 dark:text-slate-100 border-b pb-2 border-slate-200 dark:border-slate-700">
              {appDict.financialValues}
            </h3>

            {can.view_financials ? (
              <dl className="space-y-3 text-sm">
                <div className="flex justify-between">
                  <dt className="text-slate-500">{appDict.historicalCost}</dt>
                  <dd className="font-semibold text-slate-900 dark:text-slate-100">{asset.cost_minor} {asset.currency}</dd>
                </div>
                <div className="flex justify-between">
                  <dt className="text-slate-500">{appDict.salvageValue}</dt>
                  <dd className="font-medium text-slate-900 dark:text-slate-100">{asset.salvage_value_minor} {asset.currency}</dd>
                </div>
                <div className="flex justify-between">
                  <dt className="text-slate-500">{appDict.depreciableBase}</dt>
                  <dd className="font-medium text-slate-900 dark:text-slate-100">{depreciableBase} {asset.currency}</dd>
                </div>
                <div className="flex justify-between">
                  <dt className="text-slate-500">{appDict.openingAccumulatedDepreciation}</dt>
                  <dd className="font-medium text-slate-900 dark:text-slate-100">{asset.opening_accumulated_depreciation_minor} {asset.currency}</dd>
                </div>
                <div className="flex justify-between pt-2 border-t border-slate-200 dark:border-slate-700">
                  <dt className="font-semibold text-slate-900 dark:text-slate-100">{appDict.netBookValue}</dt>
                  <dd className="font-bold text-indigo-600 dark:text-indigo-400">{netBookValue} {asset.currency}</dd>
                </div>
              </dl>
            ) : (
              <p className="text-sm text-slate-500 italic">{appDict.financialValuesRestricted}</p>
            )}

            {attachments.length > 0 && (
              <div className="pt-4 border-t border-slate-200 dark:border-slate-700">
                <h4 className="text-sm font-semibold text-slate-900 dark:text-slate-100 mb-2">{appDict.attachments}</h4>
                <ul className="space-y-1 text-xs">
                  {attachments.map((att) => (
                    <li key={att.id} className="text-indigo-600 truncate">{att.name} ({att.size} {appDict.bytesSuffix})</li>
                  ))}
                </ul>
              </div>
            )}
          </Card>
        </div>
      </div>
    </AppLayout>
  );
}
