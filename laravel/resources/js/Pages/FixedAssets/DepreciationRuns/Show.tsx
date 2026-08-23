import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../../Components/AppLayout';
import { Card, PageHeader } from '../../../Components/Primitives';
import { getDictionary } from '../../../lib/i18n';
import type { SharedPageProps } from '../../../Types/page';

type ScheduleDetail = {
  id: string;
  period_number: number;
  depreciation_minor: number;
  accumulated_depreciation_minor: number;
  net_book_value_minor: number;
  status: string;
  asset?: {
    id: string;
    asset_number: string;
    name: { en: string; ar: string } | string;
    category?: { name: { en: string; ar: string } | string } | null;
  } | null;
};

type DepreciationRunDetail = {
  id: string;
  number: string;
  run_date: string;
  total_depreciation_minor: number;
  asset_count: number;
  status: 'posted' | 'reversed';
  financial_period?: { start_date: string; end_date: string } | null;
  journal_entry?: { id: string; number: string; entry_date: string } | null;
  poster?: { id: number; name: string } | null;
  schedules?: ScheduleDetail[];
};

type ShowProps = SharedPageProps & {
  run: DepreciationRunDetail;
  can: {
    reverse: boolean;
  };
};

export default function DepreciationRunShow({ locale, run, can }: ShowProps) {
  const dict = getDictionary(locale);
  const appDict = (dict.app as any).accounting || {};

  function handleReverse() {
    if (confirm(appDict.confirmReverseDepreciationRun)) {
      router.post(`/fixed-assets-depreciation-runs/${run.id}/reverse`);
    }
  }

  function formatName(name?: { en: string; ar: string } | string | null): string {
    if (!name) return '-';
    if (typeof name === 'object' && name !== null) {
      return locale === 'ar' ? name.ar || name.en : name.en || name.ar;
    }
    return String(name);
  }

  function formatRunStatus(status: DepreciationRunDetail['status']) {
    return status === 'posted' ? appDict.scheduleStatusPosted : appDict.scheduleStatusReversed;
  }

  const schedules = run.schedules || [];

  return (
    <AppLayout active="fixed-assets.depreciation-runs.index">
      <Head title={`${run.number} - ${appDict.depreciationRuns} - ${appDict.appName}`} />

      <div className="max-w-5xl mx-auto space-y-6">
        <PageHeader
          title={`${appDict.runNumber}: ${run.number}`}
          description={appDict.depreciationRuns}
          actions={
            <div className="flex items-center space-x-2 rtl:space-x-reverse">
              <Link
                href="/fixed-assets-depreciation-runs"
                className="px-3 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-md hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-200 dark:border-slate-700"
              >
                {appDict.back}
              </Link>
              {can.reverse && run.status === 'posted' && (
                <button
                  type="button"
                  onClick={handleReverse}
                  className="px-4 py-2 text-sm font-medium text-white bg-amber-600 rounded-md hover:bg-amber-700"
                >
                  {appDict.reverseDepreciationRun}
                </button>
              )}
            </div>
          }
        />

        <Card className="p-6 space-y-4">
          <dl className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div>
              <dt className="text-slate-500">{appDict.runNumber}</dt>
              <dd className="font-mono font-bold text-slate-900 dark:text-slate-100">{run.number}</dd>
            </div>
            <div>
              <dt className="text-slate-500">{appDict.runDate}</dt>
              <dd className="font-mono text-slate-900 dark:text-slate-100">{run.run_date}</dd>
            </div>
            <div>
              <dt className="text-slate-500">{appDict.assetCount}</dt>
              <dd className="font-mono font-semibold text-slate-900 dark:text-slate-100">{run.asset_count}</dd>
            </div>
            <div>
              <dt className="text-slate-500">{appDict.totalDepreciation}</dt>
              <dd className="font-mono font-bold text-indigo-600 dark:text-indigo-400">
                {run.total_depreciation_minor}
              </dd>
            </div>
            <div>
              <dt className="text-slate-500">{appDict.status}</dt>
              <dd className="capitalize font-semibold text-slate-900 dark:text-slate-100">
                <span
                  className={`inline-flex px-2 py-0.5 text-xs font-semibold rounded-full ${
                    run.status === 'posted'
                      ? 'bg-emerald-100 text-emerald-800'
                      : 'bg-rose-100 text-rose-800 dark:bg-rose-900/30 dark:text-rose-400'
                  }`}
                >
                  {formatRunStatus(run.status)}
                </span>
              </dd>
            </div>
            {run.journal_entry && (
              <div className="col-span-2">
                <dt className="text-slate-500">{appDict.linkedJournal}</dt>
                <dd className="font-mono font-medium text-indigo-600 dark:text-indigo-400">
                  <Link href={`/accounting/journal/${run.journal_entry.id}`}>
                    {run.journal_entry.number} ({run.journal_entry.entry_date})
                  </Link>
                </dd>
              </div>
            )}
          </dl>
        </Card>

        <Card className="p-6 space-y-4">
          <h3 className="text-lg font-semibold text-slate-900 dark:text-slate-100 border-b pb-2 border-slate-200 dark:border-slate-700">
            {appDict.depreciationSchedule} ({schedules.length})
          </h3>

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
                {schedules.map((row) => (
                  <tr key={row.id} className="hover:bg-slate-50 dark:hover:bg-slate-800/30">
                    <td className="px-3 py-2 font-mono font-semibold text-indigo-600 dark:text-indigo-400">
                      {row.asset ? (
                        <Link href={`/fixed-assets/${row.asset.id}`}>{row.asset.asset_number}</Link>
                      ) : (
                        '-'
                      )}
                    </td>
                    <td className="px-3 py-2">{row.asset ? formatName(row.asset.name) : '-'}</td>
                    <td className="px-3 py-2">{row.asset?.category ? formatName(row.asset.category.name) : '-'}</td>
                    <td className="px-3 py-2 font-mono">{row.period_number}</td>
                    <td className="px-3 py-2 text-right rtl:text-left font-mono font-medium">
                      {row.depreciation_minor}
                    </td>
                    <td className="px-3 py-2 text-right rtl:text-left font-mono">
                      {row.accumulated_depreciation_minor}
                    </td>
                    <td className="px-3 py-2 text-right rtl:text-left font-mono font-bold text-slate-900 dark:text-slate-100">
                      {row.net_book_value_minor}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </Card>
      </div>
    </AppLayout>
  );
}
