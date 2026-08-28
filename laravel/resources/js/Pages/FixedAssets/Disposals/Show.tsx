import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../../Components/AppLayout';
import { Card, PageHeader } from '../../../Components/Primitives';
import { formatMoney } from '../../../lib/accountingHelpers';
import { getDictionary } from '../../../lib/i18n';
import type { SharedPageProps } from '../../../Types/page';

type Account = {
  id: string;
  code: string;
  name: Record<string, string> | string;
};

type JournalLine = {
  id: string;
  line_no: number;
  account?: Account | null;
  debit_minor: number;
  credit_minor: number;
  memo: string;
};

type JournalEntry = {
  id: string;
  number: string;
  entry_date: string;
  status: string;
  lines: JournalLine[];
};

type FixedAsset = {
  id: string;
  asset_number: string;
  name: Record<string, string> | string;
  cost_minor: number;
  currency: string;
  category?: {
    id: string;
    code: string;
    name: Record<string, string> | string;
  } | null;
};

type FixedAssetDisposal = {
  id: string;
  number: string;
  fixed_asset_id: string;
  disposal_date: string;
  disposal_type: 'sale' | 'scrap' | 'retirement';
  proceeds_minor: number;
  net_book_value_minor: number;
  gain_minor: number;
  loss_minor: number;
  status: 'posted' | 'reversed';
  asset?: FixedAsset | null;
  financialPeriod?: {
    id: string;
    month: number;
    start_date: string;
    end_date: string;
  } | null;
  journalEntry?: JournalEntry | null;
  reversalJournalEntry?: { id: string; number: string } | null;
  poster?: { id: number; name: string } | null;
  created_at: string;
};

type ShowProps = SharedPageProps & {
  disposal: FixedAssetDisposal;
};

export default function DisposalShow({ locale, disposal }: ShowProps) {
  const dict = getDictionary(locale);
  const appDict = dict.app.fixedAssetsDisposals;

  function getTransName(nameObj?: Record<string, string> | string | null): string {
    if (!nameObj) return appDict.notAvailable;
    if (typeof nameObj === 'string') return nameObj;
    return nameObj[locale] || nameObj.en || appDict.notAvailable;
  }

  function formatDisposalType(type: FixedAssetDisposal['disposal_type']): string {
    const labels: Record<FixedAssetDisposal['disposal_type'], string> = {
      sale: appDict.sale,
      scrap: appDict.scrap,
      retirement: appDict.retirement,
    };

    return labels[type];
  }

  function formatDisposalStatus(status: FixedAssetDisposal['status']): string {
    return status === 'posted' ? appDict.statusPosted : appDict.statusReversed;
  }

  function handleReverse() {
    if (confirm(appDict.confirmReverse)) {
      router.post(`/fixed-assets-disposals/${disposal.id}/reverse`, {}, { preserveScroll: true });
    }
  }

  const currency = disposal.asset?.currency || appDict.noCurrency;

  return (
    <AppLayout active="fixed-assets-disposals.index">
      <Head title={`${appDict.disposalDetails} - ${disposal.number}`} />

      <div className="space-y-6">
        <PageHeader
          title={`${appDict.disposalDetails}: ${disposal.number}`}
          description={`${appDict.fixedAsset}: ${getTransName(disposal.asset?.name)} (${disposal.asset?.asset_number})`}
          actions={
            <div className="flex items-center gap-3">
              <Link
                href="/fixed-assets-disposals"
                className="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 rounded-lg hover:bg-slate-200 transition text-sm font-medium"
              >
                {appDict.backToDisposals}
              </Link>
              {disposal.status === 'posted' && (
                <button
                  type="button"
                  onClick={handleReverse}
                  className="px-4 py-2 bg-rose-600 text-white rounded-lg hover:bg-rose-700 transition text-sm font-medium"
                  title={appDict.reverseDisposal}
                  aria-label={appDict.reverseDisposal}
                >
                  {appDict.reverseDisposal}
                </button>
              )}
            </div>
          }
        />

        {/* Stats Cards */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <Card className="p-4 border-l-4 border-l-indigo-500">
            <div className="text-xs text-slate-500 uppercase font-semibold">{appDict.disposalType}</div>
            <div className="text-xl font-bold text-slate-900 dark:text-slate-100 capitalize mt-1">
              {formatDisposalType(disposal.disposal_type)}
            </div>
            <div className="text-xs text-slate-500 mt-1">{disposal.disposal_date}</div>
          </Card>

          <Card className="p-4 border-l-4 border-l-blue-500">
            <div className="text-xs text-slate-500 uppercase font-semibold">{appDict.proceeds}</div>
            <div className="text-xl font-bold font-mono text-slate-900 dark:text-slate-100 mt-1">
              {formatMoney(disposal.proceeds_minor, currency)}
            </div>
          </Card>

          <Card className="p-4 border-l-4 border-l-amber-500">
            <div className="text-xs text-slate-500 uppercase font-semibold">{appDict.netBookValue}</div>
            <div className="text-xl font-bold font-mono text-slate-900 dark:text-slate-100 mt-1">
              {formatMoney(disposal.net_book_value_minor, currency)}
            </div>
          </Card>

          <Card
            className={`p-4 border-l-4 ${
              disposal.gain_minor > 0
                ? 'border-l-emerald-500'
                : disposal.loss_minor > 0
                ? 'border-l-rose-500'
                : 'border-l-slate-400'
            }`}
          >
            <div className="text-xs text-slate-500 uppercase font-semibold">{appDict.gainLoss}</div>
            <div className="text-xl font-bold font-mono mt-1">
              {disposal.gain_minor > 0 ? (
                <span className="text-emerald-600 dark:text-emerald-400">+{formatMoney(disposal.gain_minor, currency)} ({appDict.gain})</span>
              ) : disposal.loss_minor > 0 ? (
                <span className="text-rose-600 dark:text-rose-400">-{formatMoney(disposal.loss_minor, currency)} ({appDict.loss})</span>
              ) : (
                <span className="text-slate-500">{formatMoney(0, currency)}</span>
              )}
            </div>
          </Card>
        </div>

        {/* Metadata Details */}
        <Card className="p-6">
          <h3 className="text-base font-semibold text-slate-900 dark:text-slate-100 mb-4">
            {appDict.metadataTitle}
          </h3>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6 text-sm">
            <div>
              <span className="text-slate-500 block">{appDict.disposalNumber}</span>
              <span className="font-mono font-medium text-slate-900 dark:text-slate-100">{disposal.number}</span>
            </div>
            <div>
              <span className="text-slate-500 block">{appDict.disposalStatus}</span>
              <span
                className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                  disposal.status === 'posted'
                    ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300'
                    : 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300'
                }`}
              >
                {formatDisposalStatus(disposal.status)}
              </span>
            </div>
            <div>
              <span className="text-slate-500 block">{appDict.linkedJournal}</span>
              {disposal.journalEntry ? (
                <Link
                  href={`/journal-entries/${disposal.journalEntry.id}`}
                  className="font-mono font-medium text-indigo-600 dark:text-indigo-400 hover:underline"
                >
                  {disposal.journalEntry.number}
                </Link>
              ) : (
                <span className="text-slate-400">{appDict.notAvailable}</span>
              )}
            </div>
            {disposal.reversalJournalEntry && (
              <div>
                <span className="text-slate-500 block">{appDict.reversalJournal}</span>
                <Link
                  href={`/journal-entries/${disposal.reversalJournalEntry.id}`}
                  className="font-mono font-medium text-rose-600 dark:text-rose-400 hover:underline"
                >
                  {disposal.reversalJournalEntry.number}
                </Link>
              </div>
            )}
            <div>
              <span className="text-slate-500 block">{appDict.fixedAsset}</span>
              <Link
                href={`/fixed-assets/${disposal.fixed_asset_id}`}
                className="font-medium text-indigo-600 dark:text-indigo-400 hover:underline"
              >
                {getTransName(disposal.asset?.name)} ({disposal.asset?.asset_number})
              </Link>
            </div>
            <div>
              <span className="text-slate-500 block">{appDict.assetCost}</span>
              <span className="font-mono font-medium">{formatMoney(disposal.asset?.cost_minor || 0, currency)}</span>
            </div>
          </div>
        </Card>

        {/* Linked Journal Entry Lines Table */}
        {disposal.journalEntry && (
          <Card className="p-6">
            <h3 className="text-base font-semibold text-slate-900 dark:text-slate-100 mb-4">
              {appDict.postedJournalLines} ({disposal.journalEntry.number})
            </h3>
            <div className="overflow-x-auto">
              <table className="w-full text-sm text-left rtl:text-right">
                <thead className="bg-slate-50 dark:bg-slate-800/50 text-slate-700 dark:text-slate-300 uppercase text-xs">
                  <tr>
                    <th className="px-4 py-3">#</th>
                    <th className="px-4 py-3">{appDict.account}</th>
                    <th className="px-4 py-3">{appDict.memo}</th>
                    <th className="px-4 py-3 text-right">{appDict.debit}</th>
                    <th className="px-4 py-3 text-right">{appDict.credit}</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-200 dark:divide-slate-700">
                  {disposal.journalEntry.lines?.map((line) => (
                    <tr key={line.id} className="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                      <td className="px-4 py-3 font-mono text-slate-500">{line.line_no}</td>
                      <td className="px-4 py-3">
                        <div className="font-medium text-slate-900 dark:text-slate-100">
                          {getTransName(line.account?.name)}
                        </div>
                        <div className="text-xs font-mono text-slate-500">{line.account?.code}</div>
                      </td>
                      <td className="px-4 py-3 text-slate-600 dark:text-slate-400 font-mono text-xs">
                        {line.memo || appDict.notAvailable}
                      </td>
                      <td className="px-4 py-3 text-right font-mono font-medium">
                        {line.debit_minor > 0 ? formatMoney(line.debit_minor, currency) : appDict.notAvailable}
                      </td>
                      <td className="px-4 py-3 text-right font-mono font-medium">
                        {line.credit_minor > 0 ? formatMoney(line.credit_minor, currency) : appDict.notAvailable}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </Card>
        )}
      </div>
    </AppLayout>
  );
}
