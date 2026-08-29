import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../../Components/AppLayout';
import { Card, PageHeader, SensitiveActionModal } from '../../../Components/Primitives';
import { formatAccountingAmount } from '../../../lib/accountingHelpers';
import { getDictionary } from '../../../lib/i18n';
import type { SharedPageProps } from '../../../Types/page';

type FinancialPeriod = {
  id: string;
  start_date: string;
  end_date: string;
};

type SchedulePreview = {
  id: string;
  period_number: number;
  depreciation_minor: number;
  accumulated_depreciation_minor: number;
  net_book_value_minor: number;
  asset?: {
    id: string;
    asset_number: string;
    name: { en: string; ar: string } | string;
    category?: { name: { en: string; ar: string } | string } | null;
  } | null;
};

type PreviewProps = SharedPageProps & {
  period: FinancialPeriod;
  schedules: SchedulePreview[];
  totalDepreciationMinor: number;
  assetCount: number;
  can: {
    post: boolean;
  };
};

export default function DepreciationRunPreview({
  locale,
  period,
  schedules,
  totalDepreciationMinor,
  assetCount,
  can,
}: PreviewProps) {
  const dict = getDictionary(locale);
  const appDict = dict.app.accounting;
  const formatAmount = (amountMinor: number) => formatAccountingAmount(amountMinor, '', { zeroAsDash: false, showCurrency: false });
  const [showPostModal, setShowPostModal] = useState(false);
  const [processing, setProcessing] = useState(false);

  function postDepreciationRun(payload: { confirm_action: string; reason?: string }) {
    setProcessing(true);
    router.post('/fixed-assets-depreciation-runs', {
      financial_period_id: period.id,
      ...payload,
    }, {
      preserveScroll: true,
      onSuccess: () => setShowPostModal(false),
      onFinish: () => setProcessing(false),
    });
  }

  function formatName(name?: { en: string; ar: string } | string | null): string {
    if (!name) return appDict.notAvailable;
    if (typeof name === 'object' && name !== null) {
      return locale === 'ar' ? name.ar || name.en : name.en || name.ar;
    }
    return String(name);
  }

  const periodLabel = `${period.start_date} ${appDict.periodDateSeparator} ${period.end_date}`;

  return (
    <AppLayout active="fixed-assets.depreciation-runs.index">
      <Head title={`${appDict.previewDepreciationRun} - ${appDict.appName}`} />

      <div className="max-w-6xl mx-auto space-y-6">
        <PageHeader
          title={appDict.previewDepreciationRun}
          description={periodLabel}
          actions={
            <div className="flex items-center space-x-2 rtl:space-x-reverse">
              <Link
                href="/fixed-assets-depreciation-runs"
                className="px-3 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-md hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-200 dark:border-slate-700"
              >
                {appDict.back}
              </Link>
              {can.post && schedules.length > 0 && (
                <button
                  type="button"
                  onClick={() => setShowPostModal(true)}
                  disabled={processing}
                  className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 disabled:opacity-50"
                  title={appDict.postDepreciationRun}
                  aria-label={appDict.postDepreciationRun}
                >
                  {appDict.postDepreciationRun}
                </button>
              )}
            </div>
          }
        />

        <div className="grid gap-4 md:grid-cols-3">
          <Card className="p-4">
            <div className="text-xs font-semibold uppercase text-slate-500">{appDict.financialPeriod}</div>
            <div className="mt-2 font-mono text-sm text-slate-900 dark:text-slate-100">{periodLabel}</div>
          </Card>
          <Card className="p-4">
            <div className="text-xs font-semibold uppercase text-slate-500">{appDict.assetCount}</div>
            <div className="mt-2 font-mono text-xl font-bold text-slate-900 dark:text-slate-100">{assetCount}</div>
          </Card>
          <Card className="p-4">
            <div className="text-xs font-semibold uppercase text-slate-500">{appDict.totalDepreciation}</div>
            <div className="mt-2 font-mono text-xl font-bold text-indigo-600 dark:text-indigo-400">
              {formatAmount(totalDepreciationMinor)}
            </div>
          </Card>
        </div>

        <Card className="overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-xs text-left rtl:text-right text-slate-600 dark:text-slate-300">
              <thead className="bg-slate-50 dark:bg-slate-800/50 uppercase text-[10px] text-slate-500 dark:text-slate-400">
                <tr>
                  <th className="px-3 py-2">{appDict.assetNumber}</th>
                  <th className="px-3 py-2">{appDict.assetName}</th>
                  <th className="px-3 py-2">{appDict.assetCategory}</th>
                  <th className="px-3 py-2">{appDict.periodNumber}</th>
                  <th className="px-3 py-2 text-right rtl:text-left">{appDict.depreciationAmount}</th>
                  <th className="px-3 py-2 text-right rtl:text-left">{appDict.accumulatedDepreciation}</th>
                  <th className="px-3 py-2 text-right rtl:text-left">{appDict.netBookValue}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-200 dark:divide-slate-700">
                {schedules.length === 0 ? (
                  <tr>
                    <td colSpan={7} className="px-4 py-6 text-center text-slate-500 italic">
                      {appDict.noDataFound}
                    </td>
                  </tr>
                ) : (
                  schedules.map((row) => (
                    <tr key={row.id} className="hover:bg-slate-50 dark:hover:bg-slate-800/30">
                      <td className="px-3 py-2 font-mono font-semibold text-indigo-600 dark:text-indigo-400">
                        {row.asset ? (
                          <Link href={`/fixed-assets/${row.asset.id}`}>{row.asset.asset_number}</Link>
                        ) : (
                          appDict.notAvailable
                        )}
                      </td>
                      <td className="px-3 py-2">{row.asset ? formatName(row.asset.name) : appDict.notAvailable}</td>
                      <td className="px-3 py-2">
                        {row.asset?.category ? formatName(row.asset.category.name) : appDict.notAvailable}
                      </td>
                      <td className="px-3 py-2 font-mono">{row.period_number}</td>
                      <td className="px-3 py-2 text-right rtl:text-left font-mono font-medium">
                        {formatAmount(row.depreciation_minor)}
                      </td>
                      <td className="px-3 py-2 text-right rtl:text-left font-mono">
                        {formatAmount(row.accumulated_depreciation_minor)}
                      </td>
                      <td className="px-3 py-2 text-right rtl:text-left font-mono font-bold text-slate-900 dark:text-slate-100">
                        {formatAmount(row.net_book_value_minor)}
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </Card>
      </div>

      <SensitiveActionModal
        isOpen={showPostModal}
        onClose={() => setShowPostModal(false)}
        onConfirm={postDepreciationRun}
        confirmCode="STORE_FIXED_ASSET_DEPRECIATION_RUN"
        reasonRequired
        isProcessing={processing}
        locale={locale}
      />
    </AppLayout>
  );
}
