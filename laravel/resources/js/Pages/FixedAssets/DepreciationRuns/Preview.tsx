import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../../Components/AppLayout';
import { Card, PageHeader, SensitiveActionModal } from '../../../Components/Primitives';
import { formatAccountingAmount } from '../../../lib/accountingHelpers';
import { getDictionary } from '../../../lib/i18n';
import type { SharedPageProps } from '../../../Types/page';
import ScheduleDataTable from './ScheduleDataTable';

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

        <Card className="overflow-hidden p-0">
          <ScheduleDataTable
            ajaxUrl={`/fixed-assets-depreciation-runs/preview/${period.id}/data`}
            locale={locale}
            tableId="fixed-asset-depreciation-preview-table"
          />
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
