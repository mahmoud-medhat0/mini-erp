import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../../Components/AppLayout';
import { Card, PageHeader, StatusBadge } from '../../../Components/Primitives';
import { formatAccountingAmount } from '../../../lib/accountingHelpers';
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
  const appDict = dict.app.accounting;
  const formatAmount = (amountMinor: number) => formatAccountingAmount(amountMinor, '', { zeroAsDash: false, showCurrency: false });
  const canReverseDepreciationRuns = can.reverse;

  function handleReverse() {
    if (confirm(appDict.confirmReverseDepreciationRun)) {
      router.post(`/fixed-assets-depreciation-runs/${run.id}/reverse`, {}, { preserveScroll: true });
    }
  }

  function formatName(name?: { en: string; ar: string } | string | null): string {
    if (!name) return appDict.notAvailable;
    if (typeof name === 'object' && name !== null) {
      return locale === 'ar' ? name.ar || name.en : name.en || name.ar;
    }
    return String(name);
  }

  function formatRunStatus(status: DepreciationRunDetail['status']) {
    return status === 'posted' ? appDict.scheduleStatusPosted : appDict.scheduleStatusReversed;
  }

  function runStatusTone(status: DepreciationRunDetail['status']): 'ok' | 'danger' {
    return status === 'posted' ? 'ok' : 'danger';
  }

  const schedules = run.schedules || [];
  const actionState = run.status === 'posted' && !canReverseDepreciationRuns ? dict.app.actions.restricted : null;

  return (
    <AppLayout active="fixed-assets.depreciation-runs.index">
      <Head title={`${run.number} - ${appDict.depreciationRuns} - ${appDict.appName}`} />

      <div className="max-w-5xl mx-auto space-y-6">
        <PageHeader
          title={`${appDict.runNumber}: ${run.number}`}
          description={appDict.depreciationRuns}
          actions={
            <div className="flex flex-wrap items-center gap-2">
              <Link
                href="/fixed-assets-depreciation-runs"
                title={appDict.back}
                aria-label={appDict.back}
                className="inline-flex h-9 items-center rounded-md border border-slate-200 px-3 text-xs font-semibold text-slate-700 transition-colors hover:bg-slate-50 dark:border-slate-800 dark:text-slate-300 dark:hover:bg-slate-900/50"
              >
                {appDict.back}
              </Link>
              {canReverseDepreciationRuns && run.status === 'posted' && (
                <button
                  type="button"
                  onClick={handleReverse}
                  title={appDict.reverseDepreciationRun}
                  aria-label={appDict.reverseDepreciationRun}
                  className="inline-flex h-9 items-center rounded-md border border-amber-200 px-3 text-xs font-semibold text-amber-700 transition-colors hover:bg-amber-50 dark:border-amber-900/60 dark:text-amber-300 dark:hover:bg-amber-950/40"
                >
                  {appDict.reverseDepreciationRun}
                </button>
              )}
              {actionState ? <StatusBadge tone="muted">{actionState}</StatusBadge> : null}
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
                {formatAmount(run.total_depreciation_minor)}
              </dd>
            </div>
            <div>
              <dt className="text-slate-500">{appDict.status}</dt>
              <dd className="capitalize font-semibold text-slate-900 dark:text-slate-100">
                <StatusBadge tone={runStatusTone(run.status)}>{formatRunStatus(run.status)}</StatusBadge>
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
                        appDict.notAvailable
                      )}
                    </td>
                    <td className="px-3 py-2">{row.asset ? formatName(row.asset.name) : appDict.notAvailable}</td>
                    <td className="px-3 py-2">{row.asset?.category ? formatName(row.asset.category.name) : appDict.notAvailable}</td>
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
                ))}
              </tbody>
            </table>
          </div>
        </Card>
      </div>
    </AppLayout>
  );
}
