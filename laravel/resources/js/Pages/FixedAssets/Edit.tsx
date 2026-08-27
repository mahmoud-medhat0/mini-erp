import { Head, useForm, Link } from '@inertiajs/react';
import { type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import { Card, PageHeader } from '../../Components/Primitives';
import { getDictionary } from '../../lib/i18n';
import type { SharedPageProps } from '../../Types/page';

type CategoryOption = {
  id: string;
  code: string;
  name: { en: string; ar: string } | string;
};

type CurrencyOption = {
  code: string;
  name: string;
  symbol: string;
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
};

type EditProps = SharedPageProps & {
  asset: AssetDetail;
  categories: CategoryOption[];
  currencies: CurrencyOption[];
};

export default function FixedAssetEdit({ locale, asset }: EditProps) {
  const dict = getDictionary(locale);
  const appDict = dict.app.accounting;

  const nameObj = typeof asset.name === 'object' && asset.name !== null ? asset.name : { en: String(asset.name), ar: String(asset.name) };

  const { data, setData, put, transform, processing, errors } = useForm({
    name_en: nameObj.en || '',
    name_ar: nameObj.ar || '',
    description: asset.description || '',
    acquisition_date: asset.acquisition_date,
    in_service_date: asset.in_service_date,
    cost_minor: asset.cost_minor,
    salvage_value_minor: asset.salvage_value_minor,
    useful_life_months: asset.useful_life_months,
    opening_accumulated_depreciation_minor: asset.opening_accumulated_depreciation_minor,
    serial_number: asset.serial_number || '',
  });

  function handleSubmit(e: FormEvent) {
    e.preventDefault();
    transform((formData) => ({
      name: { en: formData.name_en, ar: formData.name_ar },
      description: formData.description,
      acquisition_date: formData.acquisition_date,
      in_service_date: formData.in_service_date,
      cost_minor: formData.cost_minor,
      salvage_value_minor: formData.salvage_value_minor,
      useful_life_months: formData.useful_life_months,
      opening_accumulated_depreciation_minor: formData.opening_accumulated_depreciation_minor,
      serial_number: formData.serial_number,
    }));
    put(`/fixed-assets/${asset.id}`);
  }

  return (
    <AppLayout active="fixed-assets.index">
      <Head title={`${appDict.editFixedAsset} - ${asset.asset_number} - ${appDict.appName}`} />

      <div className="max-w-4xl mx-auto space-y-6">
        <PageHeader
          title={`${appDict.editFixedAsset} (${asset.asset_number})`}
          description={appDict.fixedAssets}
          actions={
            <Link
              href={`/fixed-assets/${asset.id}`}
              className="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-md hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-200 dark:border-slate-700"
            >
              {appDict.back}
            </Link>
          }
        />

        <Card>
          <form onSubmit={handleSubmit} className="p-6 space-y-6">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label className="block text-sm font-medium text-slate-700 dark:text-slate-300">
                  {appDict.englishName}
                </label>
                <input
                  type="text"
                  value={data.name_en}
                  onChange={(e) => setData('name_en', e.target.value)}
                  className="w-full mt-1 rounded-md border-slate-300 dark:bg-slate-900 dark:border-slate-700 text-sm"
                  required
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-slate-700 dark:text-slate-300">
                  {appDict.arabicName}
                </label>
                <input
                  type="text"
                  value={data.name_ar}
                  onChange={(e) => setData('name_ar', e.target.value)}
                  className="w-full mt-1 rounded-md border-slate-300 dark:bg-slate-900 dark:border-slate-700 text-sm"
                  required
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-slate-700 dark:text-slate-300">
                  {appDict.acquisitionDate}
                </label>
                <input
                  type="date"
                  value={data.acquisition_date}
                  onChange={(e) => setData('acquisition_date', e.target.value)}
                  className="w-full mt-1 rounded-md border-slate-300 dark:bg-slate-900 dark:border-slate-700 text-sm"
                  required
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-slate-700 dark:text-slate-300">
                  {appDict.inServiceDate}
                </label>
                <input
                  type="date"
                  value={data.in_service_date}
                  onChange={(e) => setData('in_service_date', e.target.value)}
                  className="w-full mt-1 rounded-md border-slate-300 dark:bg-slate-900 dark:border-slate-700 text-sm"
                  required
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-slate-700 dark:text-slate-300">
                  {appDict.historicalCost}
                </label>
                <input
                  type="number"
                  min="1"
                  value={data.cost_minor}
                  onChange={(e) => setData('cost_minor', parseInt(e.target.value, 10) || 0)}
                  className="w-full mt-1 rounded-md border-slate-300 dark:bg-slate-900 dark:border-slate-700 text-sm"
                  required
                />
                {errors.cost_minor && <p className="mt-1 text-xs text-rose-600">{errors.cost_minor}</p>}
              </div>

              <div>
                <label className="block text-sm font-medium text-slate-700 dark:text-slate-300">
                  {appDict.salvageValue}
                </label>
                <input
                  type="number"
                  min="0"
                  value={data.salvage_value_minor}
                  onChange={(e) => setData('salvage_value_minor', parseInt(e.target.value, 10) || 0)}
                  className="w-full mt-1 rounded-md border-slate-300 dark:bg-slate-900 dark:border-slate-700 text-sm"
                  required
                />
                {errors.salvage_value_minor && <p className="mt-1 text-xs text-rose-600">{errors.salvage_value_minor}</p>}
              </div>

              <div>
                <label className="block text-sm font-medium text-slate-700 dark:text-slate-300">
                  {appDict.usefulLifeMonths}
                </label>
                <input
                  type="number"
                  min="1"
                  value={data.useful_life_months}
                  onChange={(e) => setData('useful_life_months', parseInt(e.target.value, 10) || 1)}
                  className="w-full mt-1 rounded-md border-slate-300 dark:bg-slate-900 dark:border-slate-700 text-sm"
                  required
                />
                {errors.useful_life_months && <p className="mt-1 text-xs text-rose-600">{errors.useful_life_months}</p>}
              </div>

              <div>
                <label className="block text-sm font-medium text-slate-700 dark:text-slate-300">
                  {appDict.openingAccumulatedDepreciation}
                </label>
                <input
                  type="number"
                  min="0"
                  value={data.opening_accumulated_depreciation_minor}
                  onChange={(e) => setData('opening_accumulated_depreciation_minor', parseInt(e.target.value, 10) || 0)}
                  className="w-full mt-1 rounded-md border-slate-300 dark:bg-slate-900 dark:border-slate-700 text-sm"
                />
                {errors.opening_accumulated_depreciation_minor && <p className="mt-1 text-xs text-rose-600">{errors.opening_accumulated_depreciation_minor}</p>}
              </div>

              <div>
                <label className="block text-sm font-medium text-slate-700 dark:text-slate-300">
                  {appDict.serialNumber}
                </label>
                <input
                  type="text"
                  value={data.serial_number}
                  onChange={(e) => setData('serial_number', e.target.value)}
                  className="w-full mt-1 rounded-md border-slate-300 dark:bg-slate-900 dark:border-slate-700 text-sm"
                />
              </div>
            </div>

            <div>
              <label className="block text-sm font-medium text-slate-700 dark:text-slate-300">
                {appDict.description}
              </label>
              <textarea
                rows={3}
                value={data.description}
                onChange={(e) => setData('description', e.target.value)}
                className="w-full mt-1 rounded-md border-slate-300 dark:bg-slate-900 dark:border-slate-700 text-sm"
              />
            </div>

            <div className="flex justify-end space-x-3 rtl:space-x-reverse pt-4 border-t border-slate-200 dark:border-slate-700">
              <Link
                href={`/fixed-assets/${asset.id}`}
                className="px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 rounded-md hover:bg-slate-200"
              >
                {appDict.back}
              </Link>
              <button
                type="submit"
                disabled={processing}
                className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 disabled:opacity-50"
              >
                {appDict.saveChanges}
              </button>
            </div>
          </form>
        </Card>
      </div>
    </AppLayout>
  );
}
