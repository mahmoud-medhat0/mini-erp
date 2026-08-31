import { Head, Link, router, useForm } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';
import AppLayout from '../../../Components/AppLayout';
import { Card, PageHeader, SearchableSelect, SensitiveActionModal, StatusBadge } from '../../../Components/Primitives';
import { formatAccountingAmount } from '../../../lib/accountingHelpers';
import { getDictionary } from '../../../lib/i18n';
import type { SharedPageProps } from '../../../Types/page';

type FinancialPeriod = {
  id: string;
  month: number;
  start_date: string;
  end_date: string;
  status: string;
};

type DepreciationRun = {
  id: string;
  number: string;
  run_date: string;
  total_depreciation_minor: number;
  asset_count: number;
  status: 'posted' | 'reversed';
  financial_period?: FinancialPeriod | null;
  journal_entry?: { id: string; number: string } | null;
  poster?: { id: number; name: string } | null;
  created_at: string;
};

type PaginatedRuns = {
  data: DepreciationRun[];
  current_page: number;
  last_page: number;
  total: number;
};

type IndexProps = SharedPageProps & {
  runs: PaginatedRuns;
  openPeriods: FinancialPeriod[];
  can: {
    post: boolean;
    reverse: boolean;
  };
};

export default function DepreciationRunsIndex({ locale, runs, openPeriods, can }: IndexProps) {
  const dict = getDictionary(locale);
  const appDict = dict.app.accounting;
  const formatAmount = (amountMinor: number) => formatAccountingAmount(amountMinor, '', { zeroAsDash: false, showCurrency: false });
  const canPostDepreciationRuns = can.post;
  const canReverseDepreciationRuns = can.reverse;

  const [showPostModal, setShowPostModal] = useState(false);
  const [reversingRun, setReversingRun] = useState<DepreciationRun | null>(null);
  const [reverseProcessing, setReverseProcessing] = useState(false);

  const { data, setData, post, processing, errors } = useForm({
    financial_period_id: openPeriods[0]?.id || '',
    reason: '',
    confirm_action: 'STORE_FIXED_ASSET_DEPRECIATION_RUN',
  });

  function handlePostRun(e: FormEvent) {
    e.preventDefault();
    post('/fixed-assets-depreciation-runs', {
      onSuccess: () => setShowPostModal(false),
    });
  }

  function handleReverseRun(run: DepreciationRun) {
    setReversingRun(run);
  }

  function reverseRun(payload: { confirm_action: string; reason?: string }) {
    if (!reversingRun) return;

    setReverseProcessing(true);
    router.post(`/fixed-assets-depreciation-runs/${reversingRun.id}/reverse`, payload, {
      preserveScroll: true,
      onSuccess: () => setReversingRun(null),
      onFinish: () => setReverseProcessing(false),
    });
  }

  function formatRunStatus(status: DepreciationRun['status']) {
    return status === 'posted' ? appDict.scheduleStatusPosted : appDict.scheduleStatusReversed;
  }

  function runStatusTone(status: DepreciationRun['status']): 'ok' | 'danger' {
    return status === 'posted' ? 'ok' : 'danger';
  }

  const getDepreciationRunActionState = (run: DepreciationRun) => {
    if (run.status !== 'posted' || canReverseDepreciationRuns) return null;

    return dict.app.actions.restricted;
  };

  function formatPeriodStatus(status: string) {
    if (status === 'open') return appDict.statusOpen;
    if (status === 'reopened') return appDict.statusReopened;
    return status;
  }

  const periodOptions = useMemo(
    () => openPeriods.map((period) => ({
      value: period.id,
      label: `${period.start_date} ${appDict.periodDateSeparator} ${period.end_date}`,
      sublabel: formatPeriodStatus(period.status),
    })),
    [appDict.periodDateSeparator, openPeriods],
  );

  return (
    <AppLayout active="fixed-assets.depreciation-runs.index">
      <Head title={`${appDict.depreciationRuns} - ${appDict.appName}`} />

      <div className="max-w-7xl mx-auto space-y-6">
        <PageHeader
          title={appDict.depreciationRuns}
          description={appDict.fixedAssets}
          actions={
            <div className="flex flex-wrap items-center gap-2">
              <Link
                href="/fixed-assets"
                title={appDict.back}
                aria-label={appDict.back}
                className="inline-flex h-9 items-center rounded-md border border-slate-200 px-3 text-xs font-semibold text-slate-700 transition-colors hover:bg-slate-50 dark:border-slate-800 dark:text-slate-300 dark:hover:bg-slate-900/50"
              >
                {appDict.back}
              </Link>
              {canPostDepreciationRuns && (
                <button
                  type="button"
                  onClick={() => setShowPostModal(true)}
                  title={appDict.newDepreciationRun}
                  aria-label={appDict.newDepreciationRun}
                  className="inline-flex h-9 items-center rounded-md border border-transparent bg-indigo-600 px-3 text-xs font-semibold text-white transition-colors hover:bg-indigo-700"
                >
                  {appDict.newDepreciationRun}
                </button>
              )}
            </div>
          }
        />

        <Card className="overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-xs text-left rtl:text-right text-slate-600 dark:text-slate-300">
              <thead className="bg-slate-50 dark:bg-slate-800/50 uppercase text-[10px] text-slate-500 dark:text-slate-400">
                <tr>
                  <th className="px-4 py-3">{appDict.runNumber}</th>
                  <th className="px-4 py-3">{appDict.runDate}</th>
                  <th className="px-4 py-3">{appDict.financialPeriod}</th>
                  <th className="px-4 py-3 text-right rtl:text-left">{appDict.assetCount}</th>
                  <th className="px-4 py-3 text-right rtl:text-left">{appDict.totalDepreciation}</th>
                  <th className="px-4 py-3 text-center">{appDict.status}</th>
                  <th className="px-4 py-3 text-right rtl:text-left">{appDict.actions}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-200 dark:divide-slate-700">
                {runs.data.length === 0 ? (
                  <tr>
                    <td colSpan={7} className="px-4 py-6 text-center text-slate-500 italic">
                      {appDict.noDataFound}
                    </td>
                  </tr>
                ) : (
                  runs.data.map((run) => {
                    const actionState = getDepreciationRunActionState(run);

                    return (
                      <tr key={run.id} className="hover:bg-slate-50 dark:hover:bg-slate-800/30">
                        <td className="px-4 py-3 font-mono font-semibold text-indigo-600 dark:text-indigo-400">
                          <Link href={`/fixed-assets-depreciation-runs/${run.id}`}>{run.number}</Link>
                        </td>
                        <td className="px-4 py-3 font-mono">{run.run_date}</td>
                        <td className="px-4 py-3">
                          {run.financial_period
                            ? `${run.financial_period.start_date} ${appDict.periodDateSeparator} ${run.financial_period.end_date}`
                            : appDict.notAvailable}
                        </td>
                        <td className="px-4 py-3 text-right rtl:text-left font-mono font-medium">{run.asset_count}</td>
                        <td className="px-4 py-3 text-right rtl:text-left font-mono font-bold text-slate-900 dark:text-slate-100">
                          {formatAmount(run.total_depreciation_minor)}
                        </td>
                        <td className="px-4 py-3 text-center capitalize">
                          <StatusBadge tone={runStatusTone(run.status)}>{formatRunStatus(run.status)}</StatusBadge>
                        </td>
                        <td className="px-4 py-3 text-right rtl:text-left">
                          <div className="flex flex-wrap items-center justify-end gap-2">
                            <Link
                              href={`/fixed-assets-depreciation-runs/${run.id}`}
                              title={appDict.viewDetail}
                              aria-label={appDict.viewDetail}
                              className="inline-flex h-8 items-center rounded-md border border-slate-200 px-2.5 text-xs font-semibold text-slate-700 transition-colors hover:bg-slate-50 dark:border-slate-800 dark:text-slate-300 dark:hover:bg-slate-900/50"
                            >
                              {appDict.viewDetail}
                            </Link>
                            {run.status === 'posted' && canReverseDepreciationRuns ? (
                              <button
                                type="button"
                                onClick={() => handleReverseRun(run)}
                                title={appDict.reverseDepreciationRun}
                                aria-label={appDict.reverseDepreciationRun}
                                className="inline-flex h-8 items-center rounded-md border border-amber-200 px-2.5 text-xs font-semibold text-amber-700 transition-colors hover:bg-amber-50 dark:border-amber-900/60 dark:text-amber-300 dark:hover:bg-amber-950/40"
                              >
                                {appDict.reverseDepreciationRun}
                              </button>
                            ) : null}
                            {actionState ? <StatusBadge tone="muted">{actionState}</StatusBadge> : null}
                          </div>
                        </td>
                      </tr>
                    );
                  })
                )}
              </tbody>
            </table>
          </div>
        </Card>
      </div>

      {showPostModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50">
          <div className="w-full max-w-md p-6 bg-white rounded-lg shadow-xl dark:bg-slate-800">
            <h3 className="text-lg font-semibold text-slate-900 dark:text-slate-100">{appDict.newDepreciationRun}</h3>

            <form onSubmit={handlePostRun} className="mt-4 space-y-4">
              <div>
                <label className="block text-sm font-medium text-slate-700 dark:text-slate-300">
                  {appDict.financialPeriod}
                </label>
                <SearchableSelect
                  value={data.financial_period_id}
                  options={periodOptions}
                  onChange={(value) => setData('financial_period_id', value || '')}
                  placeholder={appDict.selectOption}
                  className="mt-1"
                  required
                  error={errors.financial_period_id}
                />
                {errors.financial_period_id && (
                  <p className="mt-1 text-xs text-rose-600">{errors.financial_period_id}</p>
                )}
              </div>
              <div>
                <label className="block text-sm font-medium text-slate-700 dark:text-slate-300">
                  {dict.app.sensitiveActions.reasonLabel}
                </label>
                <textarea
                  value={data.reason}
                  onChange={(event) => setData('reason', event.target.value)}
                  placeholder={dict.app.sensitiveActions.reasonPlaceholder}
                  rows={3}
                  maxLength={1000}
                  required
                  className="mt-1 w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                />
                {errors.reason && (
                  <p className="mt-1 text-xs text-rose-600">{errors.reason}</p>
                )}
              </div>

              <div className="flex justify-end gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => setShowPostModal(false)}
                  title={appDict.cancel}
                  aria-label={appDict.cancel}
                  className="px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 rounded-md hover:bg-slate-200"
                >
                  {appDict.cancel}
                </button>
                <button
                  type="submit"
                  disabled={processing}
                  title={appDict.postDepreciationRun}
                  aria-label={appDict.postDepreciationRun}
                  className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 disabled:opacity-50"
                >
                  {appDict.postDepreciationRun}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      <SensitiveActionModal
        isOpen={reversingRun !== null}
        onClose={() => setReversingRun(null)}
        onConfirm={reverseRun}
        confirmCode="REVERSE_FIXED_ASSET_DEPRECIATION_RUN"
        reasonRequired
        isProcessing={reverseProcessing}
        locale={locale}
      />
    </AppLayout>
  );
}
