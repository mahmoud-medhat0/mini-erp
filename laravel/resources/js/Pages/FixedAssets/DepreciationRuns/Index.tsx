import { Head, Link, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AppLayout from '../../../Components/AppLayout';
import { Card, PageHeader } from '../../../Components/Primitives';
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
  const appDict = (dict.app as any).accounting || {};

  const [showPostModal, setShowPostModal] = useState(false);

  const { data, setData, post, processing, errors } = useForm({
    financial_period_id: openPeriods[0]?.id || '',
  });

  function handlePostRun(e: FormEvent) {
    e.preventDefault();
    post('/fixed-assets-depreciation-runs', {
      onSuccess: () => setShowPostModal(false),
    });
  }

  function formatRunStatus(status: DepreciationRun['status']) {
    return status === 'posted' ? appDict.scheduleStatusPosted : appDict.scheduleStatusReversed;
  }

  function formatPeriodStatus(status: string) {
    if (status === 'open') return appDict.statusOpen;
    if (status === 'reopened') return appDict.statusReopened;
    return status;
  }

  return (
    <AppLayout active="fixed-assets.depreciation-runs.index">
      <Head title={`${appDict.depreciationRuns} - ${appDict.appName}`} />

      <div className="max-w-7xl mx-auto space-y-6">
        <PageHeader
          title={appDict.depreciationRuns}
          description={appDict.fixedAssets}
          actions={
            <div className="flex items-center space-x-2 rtl:space-x-reverse">
              <Link
                href="/fixed-assets"
                className="px-3 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-md hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-200 dark:border-slate-700"
              >
                {appDict.back}
              </Link>
              {can.post && (
                <button
                  type="button"
                  onClick={() => setShowPostModal(true)}
                  className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700"
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
                  runs.data.map((run) => (
                    <tr key={run.id} className="hover:bg-slate-50 dark:hover:bg-slate-800/30">
                      <td className="px-4 py-3 font-mono font-semibold text-indigo-600 dark:text-indigo-400">
                        <Link href={`/fixed-assets-depreciation-runs/${run.id}`}>{run.number}</Link>
                      </td>
                      <td className="px-4 py-3 font-mono">{run.run_date}</td>
                      <td className="px-4 py-3">
                        {run.financial_period
                          ? `${run.financial_period.start_date} ${appDict.periodDateSeparator} ${run.financial_period.end_date}`
                          : '-'}
                      </td>
                      <td className="px-4 py-3 text-right rtl:text-left font-mono font-medium">{run.asset_count}</td>
                      <td className="px-4 py-3 text-right rtl:text-left font-mono font-bold text-slate-900 dark:text-slate-100">
                        {run.total_depreciation_minor}
                      </td>
                      <td className="px-4 py-3 text-center capitalize">
                        <span
                          className={`inline-flex px-2 py-0.5 text-[10px] font-semibold rounded-full ${
                            run.status === 'posted'
                              ? 'bg-emerald-100 text-emerald-800'
                              : 'bg-rose-100 text-rose-800 dark:bg-rose-900/30 dark:text-rose-400'
                          }`}
                        >
                          {formatRunStatus(run.status)}
                        </span>
                      </td>
                      <td className="px-4 py-3 text-right rtl:text-left">
                        <Link
                          href={`/fixed-assets-depreciation-runs/${run.id}`}
                          className="text-xs font-medium text-indigo-600 hover:text-indigo-900 dark:text-indigo-400"
                        >
                          {appDict.viewDetails}
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

      {showPostModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50">
          <div className="w-full max-w-md p-6 bg-white rounded-lg shadow-xl dark:bg-slate-800">
            <h3 className="text-lg font-semibold text-slate-900 dark:text-slate-100">{appDict.newDepreciationRun}</h3>

            <form onSubmit={handlePostRun} className="mt-4 space-y-4">
              <div>
                <label className="block text-sm font-medium text-slate-700 dark:text-slate-300">
                  {appDict.financialPeriod}
                </label>
                <select
                  value={data.financial_period_id}
                  onChange={(e) => setData('financial_period_id', e.target.value)}
                  className="w-full mt-1 text-sm rounded-md border-slate-300 dark:bg-slate-900 dark:border-slate-700"
                  required
                >
                  <option value="" disabled>
                    {appDict.selectOption}
                  </option>
                  {openPeriods.map((period) => (
                    <option key={period.id} value={period.id}>
                      {period.start_date} {appDict.periodDateSeparator} {period.end_date} ({formatPeriodStatus(period.status)})
                    </option>
                  ))}
                </select>
                {errors.financial_period_id && (
                  <p className="mt-1 text-xs text-rose-600">{errors.financial_period_id}</p>
                )}
              </div>

              <div className="flex justify-end space-x-2 rtl:space-x-reverse pt-2">
                <button
                  type="button"
                  onClick={() => setShowPostModal(false)}
                  className="px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 rounded-md hover:bg-slate-200"
                >
                  {appDict.cancel}
                </button>
                <button
                  type="submit"
                  disabled={processing}
                  className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 disabled:opacity-50"
                >
                  {appDict.postDepreciationRun}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </AppLayout>
  );
}
