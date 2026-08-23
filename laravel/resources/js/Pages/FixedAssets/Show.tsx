import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import { Card, PageHeader } from '../../Components/Primitives';
import { getDictionary } from '../../lib/i18n';
import type { SharedPageProps } from '../../Types/page';

type CategoryOption = {
  id: string;
  code: string;
  name: { en: string; ar: string } | string;
};

type JournalInfo = {
  id: string;
  number: string;
  status: string;
  entry_date: string;
};

type ScheduleRow = {
  id: string;
  period_number: number;
  financial_period_id: string;
  period_start_date: string;
  period_end_date: string;
  depreciation_minor: number;
  accumulated_depreciation_minor: number;
  net_book_value_minor: number;
  status: 'planned' | 'posted' | 'reversed' | 'skipped';
  financial_period?: {
    id: string;
    month: number;
    start_date: string;
    end_date: string;
    status: string;
  } | null;
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
  capitalization_mode?: 'opening_already_capitalized' | 'manual_capitalization' | null;
  capitalization_date?: string | null;
  journal_entry_id?: string | null;
  capitalized_at?: string | null;
  serial_number?: string | null;
  created_at: string;
  category?: CategoryOption | null;
  journal_entry?: JournalInfo | null;
  capitalizer?: { id: number; name: string } | null;
  creator?: { id: number; name: string } | null;
  updater?: { id: number; name: string } | null;
  depreciation_schedules?: ScheduleRow[];
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
    reverse: boolean;
    generate_schedule: boolean;
    view_financials: boolean;
  };
};

export default function FixedAssetShow({ locale, asset, attachments = [], can }: ShowProps) {
  const dict = getDictionary(locale);
  const appDict = (dict.app as any).accounting || {};

  const [showCapitalizeModal, setShowCapitalizeModal] = useState(false);
  const [generatingSchedule, setGeneratingSchedule] = useState(false);

  const { data, setData, post, processing, errors, reset } = useForm({
    capitalization_mode: 'manual_capitalization' as 'opening_already_capitalized' | 'manual_capitalization',
    capitalization_date: asset.in_service_date || new Date().toISOString().split('T')[0],
  });

  function handleDelete() {
    if (confirm(appDict.confirmDeleteDraftAsset)) {
      router.delete(`/fixed-assets/${asset.id}`);
    }
  }

  function handleCapitalize(e: FormEvent) {
    e.preventDefault();
    post(`/fixed-assets/${asset.id}/capitalize`, {
      onSuccess: () => {
        setShowCapitalizeModal(false);
        reset();
      },
    });
  }

  function handleReverseCapitalization() {
    if (confirm(appDict.confirmReverseCapitalization)) {
      router.post(`/fixed-assets/${asset.id}/reverse-capitalization`);
    }
  }

  function handleGenerateSchedule() {
    setGeneratingSchedule(true);
    router.post(`/fixed-assets/${asset.id}/generate-schedule`, {}, {
      onFinish: () => setGeneratingSchedule(false),
    });
  }

  const [showDisposeModal, setShowDisposeModal] = useState(false);
  const disposalDict = (dict.app as any).fixedAssetsDisposals;

  const disposeForm = useForm({
    disposal_date: new Date().toISOString().split('T')[0],
    disposal_type: 'scrap',
    proceeds_minor: 0,
  });

  function handlePostDisposal(e: FormEvent) {
    e.preventDefault();
    disposeForm.post(`/fixed-assets/${asset.id}/disposals`, {
      onSuccess: () => setShowDisposeModal(false),
    });
  }

  function formatName(name: { en: string; ar: string } | string): string {
    if (typeof name === 'object' && name !== null) {
      return locale === 'ar' ? name.ar || name.en : name.en || name.ar;
    }
    return String(name);
  }

  function formatStatus(status: AssetDetail['status']): string {
    switch (status) {
      case 'active':
        return appDict.fixedAssetStatusActive;
      case 'fully_depreciated':
        return appDict.fixedAssetStatusFullyDepreciated;
      case 'disposed':
        return appDict.fixedAssetStatusDisposed;
      case 'draft':
      default:
        return appDict.fixedAssetStatusDraft;
    }
  }

  function formatScheduleStatus(status: ScheduleRow['status']): string {
    switch (status) {
      case 'posted':
        return appDict.scheduleStatusPosted;
      case 'reversed':
        return appDict.scheduleStatusReversed;
      case 'skipped':
        return appDict.scheduleStatusSkipped;
      case 'planned':
      default:
        return appDict.scheduleStatusPlanned;
    }
  }

  const depreciableBase = asset.cost_minor - asset.salvage_value_minor;
  const netBookValue = asset.cost_minor - asset.opening_accumulated_depreciation_minor;
  const schedules = asset.depreciation_schedules || [];

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
              {can.generate_schedule && (
                <button
                  type="button"
                  onClick={handleGenerateSchedule}
                  disabled={generatingSchedule}
                  className="px-3 py-2 text-sm font-medium text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-md hover:bg-indigo-100 dark:bg-indigo-950 dark:text-indigo-300 dark:border-indigo-800 disabled:opacity-50"
                >
                  {schedules.length > 0 ? appDict.regenerateSchedule : appDict.generateSchedule}
                </button>
              )}
              {can.edit && asset.status === 'draft' && (
                <Link
                  href={`/fixed-assets/${asset.id}/edit`}
                  className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700"
                >
                  {appDict.editFixedAsset}
                </Link>
              )}
              {can.post && asset.status === 'draft' && (
                <button
                  type="button"
                  onClick={() => setShowCapitalizeModal(true)}
                  className="px-4 py-2 text-sm font-medium text-white bg-emerald-600 rounded-md hover:bg-emerald-700"
                >
                  {appDict.capitalizeAsset}
                </button>
              )}
              {can.post && asset.status === 'active' && (
                <button
                  type="button"
                  onClick={() => setShowDisposeModal(true)}
                  className="px-4 py-2 text-sm font-medium text-white bg-rose-600 rounded-md hover:bg-rose-700"
                >
                  {disposalDict.disposeAsset}
                </button>
              )}
              {can.reverse && asset.status === 'active' && asset.capitalization_mode === 'manual_capitalization' && (
                <button
                  type="button"
                  onClick={handleReverseCapitalization}
                  className="px-4 py-2 text-sm font-medium text-white bg-amber-600 rounded-md hover:bg-amber-700"
                >
                  {appDict.reverseCapitalization}
                </button>
              )}
              {can.delete && asset.status === 'draft' && (
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
                <dd className="capitalize font-medium text-slate-900 dark:text-slate-100">
                  <span className={`inline-flex px-2 py-0.5 text-xs font-semibold rounded-full ${asset.status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'}`}>
                    {formatStatus(asset.status)}
                  </span>
                </dd>
              </div>

              {asset.capitalization_mode && (
                <>
                  <div>
                    <dt className="text-slate-500 dark:text-slate-400">{appDict.capitalizationMode}</dt>
                    <dd className="font-medium text-slate-900 dark:text-slate-100">
                      {asset.capitalization_mode === 'opening_already_capitalized'
                        ? appDict.openingAlreadyCapitalized
                        : appDict.manualCapitalization}
                    </dd>
                  </div>

                  <div>
                    <dt className="text-slate-500 dark:text-slate-400">{appDict.capitalizationDate}</dt>
                    <dd className="font-medium text-slate-900 dark:text-slate-100">{asset.capitalization_date || '-'}</dd>
                  </div>
                </>
              )}

              {asset.journal_entry && (
                <div className="col-span-2">
                  <dt className="text-slate-500 dark:text-slate-400">{appDict.linkedJournal}</dt>
                  <dd className="font-mono font-medium text-indigo-600 dark:text-indigo-400">
                    <Link href={`/accounting/journal/${asset.journal_entry.id}`}>
                      {asset.journal_entry.number} ({asset.journal_entry.entry_date})
                    </Link>
                  </dd>
                </div>
              )}
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

        {/* Depreciation Schedule Card */}
        {can.view_financials && (
          <Card className="p-6 space-y-4">
            <div className="flex items-center justify-between border-b pb-3 border-slate-200 dark:border-slate-700">
              <h3 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
                {appDict.depreciationSchedule}
              </h3>
              {can.generate_schedule && (
                <button
                  type="button"
                  onClick={handleGenerateSchedule}
                  disabled={generatingSchedule}
                  className="px-3 py-1.5 text-xs font-medium text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-md hover:bg-indigo-100 dark:bg-indigo-950 dark:text-indigo-300 dark:border-indigo-800 disabled:opacity-50"
                >
                  {schedules.length > 0 ? appDict.regenerateSchedule : appDict.generateSchedule}
                </button>
              )}
            </div>

            {schedules.length === 0 ? (
              <p className="text-sm text-slate-500 italic py-4 text-center">{appDict.noScheduleGenerated}</p>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-xs text-left rtl:text-right text-slate-600 dark:text-slate-300">
                  <thead className="bg-slate-50 dark:bg-slate-800/50 uppercase text-[10px] text-slate-500 dark:text-slate-400">
                    <tr>
                      <th className="px-3 py-2">{appDict.periodNumber}</th>
                      <th className="px-3 py-2">{appDict.periodBounds}</th>
                      <th className="px-3 py-2 text-right rtl:text-left">{appDict.depreciationAmount}</th>
                      <th className="px-3 py-2 text-right rtl:text-left">{appDict.accumulatedDepreciation}</th>
                      <th className="px-3 py-2 text-right rtl:text-left">{appDict.netBookValue}</th>
                      <th className="px-3 py-2 text-center">{appDict.status}</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-200 dark:divide-slate-700">
                    {schedules.map((row) => (
                      <tr key={row.id} className="hover:bg-slate-50 dark:hover:bg-slate-800/30">
                        <td className="px-3 py-2 font-mono font-semibold">{row.period_number}</td>
                        <td className="px-3 py-2 font-mono">
                          {row.period_start_date} {appDict.periodDateSeparator} {row.period_end_date}
                        </td>
                        <td className="px-3 py-2 text-right rtl:text-left font-mono font-medium">
                          {row.depreciation_minor} {asset.currency}
                        </td>
                        <td className="px-3 py-2 text-right rtl:text-left font-mono">
                          {row.accumulated_depreciation_minor} {asset.currency}
                        </td>
                        <td className="px-3 py-2 text-right rtl:text-left font-mono font-bold text-slate-900 dark:text-slate-100">
                          {row.net_book_value_minor} {asset.currency}
                        </td>
                        <td className="px-3 py-2 text-center capitalize">
                          <span className={`inline-flex px-2 py-0.5 text-[10px] font-semibold rounded-full ${row.status === 'posted' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300'}`}>
                            {formatScheduleStatus(row.status)}
                          </span>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </Card>
        )}
      </div>

      {showCapitalizeModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50">
          <div className="w-full max-w-md p-6 bg-white rounded-lg shadow-xl dark:bg-slate-800">
            <h3 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
              {appDict.capitalizeAsset} ({asset.asset_number})
            </h3>

            <form onSubmit={handleCapitalize} className="mt-4 space-y-4">
              <div>
                <label className="block text-sm font-medium text-slate-700 dark:text-slate-300">
                  {appDict.capitalizationMode}
                </label>
                <div className="mt-2 space-y-2">
                  <label className="flex items-start space-x-2 rtl:space-x-reverse cursor-pointer">
                    <input
                      type="radio"
                      name="mode"
                      value="manual_capitalization"
                      checked={data.capitalization_mode === 'manual_capitalization'}
                      onChange={() => setData('capitalization_mode', 'manual_capitalization')}
                      className="mt-0.5 text-indigo-600"
                    />
                    <span className="text-sm text-slate-800 dark:text-slate-200">
                      {appDict.manualCapitalization}
                    </span>
                  </label>
                  <label className="flex items-start space-x-2 rtl:space-x-reverse cursor-pointer">
                    <input
                      type="radio"
                      name="mode"
                      value="opening_already_capitalized"
                      checked={data.capitalization_mode === 'opening_already_capitalized'}
                      onChange={() => setData('capitalization_mode', 'opening_already_capitalized')}
                      className="mt-0.5 text-indigo-600"
                    />
                    <span className="text-sm text-slate-800 dark:text-slate-200">
                      {appDict.openingAlreadyCapitalized}
                    </span>
                  </label>
                </div>
              </div>

              <div>
                <label className="block text-sm font-medium text-slate-700 dark:text-slate-300">
                  {appDict.capitalizationDate}
                </label>
                <input
                  type="date"
                  value={data.capitalization_date}
                  onChange={(e) => setData('capitalization_date', e.target.value)}
                  className="w-full mt-1 rounded-md border-slate-300 dark:bg-slate-900 dark:border-slate-700 text-sm"
                  required
                />
                {errors.capitalization_date && <p className="mt-1 text-xs text-rose-600">{errors.capitalization_date}</p>}
              </div>

              <div className="flex justify-end space-x-2 rtl:space-x-reverse pt-2">
                <button
                  type="button"
                  onClick={() => setShowCapitalizeModal(false)}
                  className="px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 rounded-md hover:bg-slate-200"
                >
                  {appDict.cancel}
                </button>
                <button
                  type="submit"
                  disabled={processing}
                  className="px-4 py-2 text-sm font-medium text-white bg-emerald-600 rounded-md hover:bg-emerald-700 disabled:opacity-50"
                >
                  {appDict.capitalizeAsset}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
      {/* Dispose Asset Modal */}
      {showDisposeModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm">
          <div className="w-full max-w-md bg-white dark:bg-slate-800 rounded-lg shadow-xl p-6 border border-slate-200 dark:border-slate-700">
            <h3 className="text-lg font-semibold text-slate-900 dark:text-slate-100 mb-4">
              {disposalDict.disposeAsset}
            </h3>

            <form onSubmit={handlePostDisposal} className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-slate-700 dark:text-slate-300">
                  {disposalDict.disposalType}
                </label>
                <select
                  value={disposeForm.data.disposal_type}
                  onChange={(e) => disposeForm.setData('disposal_type', e.target.value)}
                  className="w-full mt-1 rounded-md border-slate-300 dark:bg-slate-900 dark:border-slate-700 text-sm"
                  required
                >
                  <option value="scrap">{disposalDict.scrap}</option>
                  <option value="sale">{disposalDict.sale}</option>
                  <option value="retirement">{disposalDict.retirement}</option>
                </select>
                {disposeForm.errors.disposal_type && (
                  <p className="mt-1 text-xs text-rose-600">{disposeForm.errors.disposal_type}</p>
                )}
              </div>

              <div>
                <label className="block text-sm font-medium text-slate-700 dark:text-slate-300">
                  {disposalDict.disposalDate}
                </label>
                <input
                  type="date"
                  value={disposeForm.data.disposal_date}
                  onChange={(e) => disposeForm.setData('disposal_date', e.target.value)}
                  className="w-full mt-1 rounded-md border-slate-300 dark:bg-slate-900 dark:border-slate-700 text-sm"
                  required
                />
                {disposeForm.errors.disposal_date && (
                  <p className="mt-1 text-xs text-rose-600">{disposeForm.errors.disposal_date}</p>
                )}
              </div>

              {disposeForm.data.disposal_type === 'sale' && (
                <div>
                  <label className="block text-sm font-medium text-slate-700 dark:text-slate-300">
                    {disposalDict.proceedsMinorUnits}
                  </label>
                  <input
                    type="number"
                    min="0"
                    value={disposeForm.data.proceeds_minor}
                    onChange={(e) => disposeForm.setData('proceeds_minor', parseInt(e.target.value, 10) || 0)}
                    className="w-full mt-1 rounded-md border-slate-300 dark:bg-slate-900 dark:border-slate-700 text-sm font-mono"
                    required
                  />
                  {disposeForm.errors.proceeds_minor && (
                    <p className="mt-1 text-xs text-rose-600">{disposeForm.errors.proceeds_minor}</p>
                  )}
                </div>
              )}

              <p className="text-xs text-slate-500">
                {disposalDict.confirmDisposal}
              </p>

              <div className="flex justify-end space-x-2 rtl:space-x-reverse pt-2">
                <button
                  type="button"
                  onClick={() => setShowDisposeModal(false)}
                  className="px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 rounded-md hover:bg-slate-200"
                >
                  {disposalDict.cancel}
                </button>
                <button
                  type="submit"
                  disabled={disposeForm.processing}
                  className="px-4 py-2 text-sm font-medium text-white bg-rose-600 rounded-md hover:bg-rose-700 disabled:opacity-50"
                >
                  {disposalDict.postDisposal}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </AppLayout>
  );
}
