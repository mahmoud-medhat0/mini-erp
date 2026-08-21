import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../Components/AppLayout';
import { Card, PageHeader, StatusBadge } from '../../Components/Primitives';
import { getDictionary } from '../../lib/i18n';
import type { SharedPageProps } from '../../Types/page';

type AccountingIndexProps = SharedPageProps & {
  activeFiscalYear?: {
    id: string;
    year: number;
    start_date: string;
    end_date: string;
    status: string;
  } | null;
  recentJournals?: {
    id: string;
    number?: string | null;
    entry_date: string;
    description?: string | null;
    status: string;
    period?: { month: number } | null;
  }[];
  counts?: {
    accounts: number;
    postedJournals: number;
    draftJournals: number;
  };
};

export default function AccountingIndex({ locale, activeFiscalYear, recentJournals = [], counts }: AccountingIndexProps) {
  const dict = getDictionary(locale);
  const accDict = (dict.app as any).accounting || {};

  return (
    <AppLayout active="accounting.index">
      <Head title={accDict.title || 'Accounting Core'} />

      <PageHeader
        title={accDict.title || 'Accounting Core'}
        description={accDict.subtitle || 'General Ledger spine, Journal Vouchers, Chart of Accounts, Period Close and Trial Balance.'}
        actions={
          <div className="flex items-center gap-3">
            <Link
              href="/accounting/journal/create"
              className="inline-flex items-center gap-2 rounded-xl bg-[var(--primary)] px-4 py-2.5 text-xs font-bold text-white shadow-md shadow-blue-500/20 hover:bg-[var(--primary-hover)] transition-colors"
            >
              <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
              </svg>
              <span>{accDict.createVoucher || 'Create Journal Voucher'}</span>
            </Link>
          </div>
        }
      />

      {/* Metrics Row */}
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <Card className="p-5 flex items-center justify-between border-l-4 border-l-blue-500">
          <div>
            <span className="block text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)]">
              {accDict.activeAccounts || 'Active Accounts'}
            </span>
            <span className="text-2xl font-extrabold text-[var(--text-primary)] font-mono">
              {counts?.accounts ?? 0}
            </span>
          </div>
          <div className="flex size-11 items-center justify-center rounded-xl bg-blue-500/10 text-blue-500">
            <svg className="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
            </svg>
          </div>
        </Card>

        <Card className="p-5 flex items-center justify-between border-l-4 border-l-emerald-500">
          <div>
            <span className="block text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)]">
              {accDict.postedJournals || 'Posted Journals'}
            </span>
            <span className="text-2xl font-extrabold text-[var(--text-primary)] font-mono">
              {counts?.postedJournals ?? 0}
            </span>
          </div>
          <div className="flex size-11 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-500">
            <svg className="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
          </div>
        </Card>

        <Card className="p-5 flex items-center justify-between border-l-4 border-l-amber-500">
          <div>
            <span className="block text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)]">
              {accDict.draftVouchers || 'Draft Vouchers'}
            </span>
            <span className="text-2xl font-extrabold text-[var(--text-primary)] font-mono">
              {counts?.draftJournals ?? 0}
            </span>
          </div>
          <div className="flex size-11 items-center justify-center rounded-xl bg-amber-500/10 text-amber-500">
            <svg className="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
          </div>
        </Card>

        <Card className="p-5 flex items-center justify-between border-l-4 border-l-purple-500">
          <div>
            <span className="block text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)]">
              {accDict.activeFiscalYear || 'Active Fiscal Year'}
            </span>
            <span className="text-2xl font-extrabold text-[var(--text-primary)] font-mono">
              {activeFiscalYear?.year ?? 'None'}
            </span>
          </div>
          <div className="flex size-11 items-center justify-center rounded-xl bg-purple-500/10 text-purple-500">
            <svg className="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
          </div>
        </Card>
      </div>

      {/* Accounting Quick Actions Navigation Grid */}
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 mb-6">
        <Link href="/accounting/coa">
          <Card className="p-5 hover:border-blue-500/40 transition-all group cursor-pointer">
            <div className="flex items-center gap-3 mb-2">
              <div className="flex size-10 items-center justify-center rounded-xl bg-blue-500/10 text-blue-500 group-hover:bg-blue-500 group-hover:text-white transition-colors">
                <svg className="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                </svg>
              </div>
              <h3 className="m-0 text-sm font-bold text-[var(--text-primary)]">{accDict.coa || 'Chart of Accounts'}</h3>
            </div>
            <p className="m-0 text-xs text-[var(--text-muted)]">{accDict.coaDesc || 'Manage hierarchical account groups and active GL accounts.'}</p>
          </Card>
        </Link>

        <Link href="/accounting/journal">
          <Card className="p-5 hover:border-blue-500/40 transition-all group cursor-pointer">
            <div className="flex items-center gap-3 mb-2">
              <div className="flex size-10 items-center justify-center rounded-xl bg-indigo-500/10 text-indigo-500 group-hover:bg-indigo-500 group-hover:text-white transition-colors">
                <svg className="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
              </div>
              <h3 className="m-0 text-sm font-bold text-[var(--text-primary)]">{accDict.journal || 'General Journal'}</h3>
            </div>
            <p className="m-0 text-xs text-[var(--text-muted)]">{accDict.journalDesc || 'View and edit manual journal vouchers across approval lifecycle.'}</p>
          </Card>
        </Link>

        <Link href="/accounting/ledger">
          <Card className="p-5 hover:border-blue-500/40 transition-all group cursor-pointer">
            <div className="flex items-center gap-3 mb-2">
              <div className="flex size-10 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-500 group-hover:bg-emerald-500 group-hover:text-white transition-colors">
                <svg className="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
              </div>
              <h3 className="m-0 text-sm font-bold text-[var(--text-primary)]">{accDict.ledger || 'General Ledger'}</h3>
            </div>
            <p className="m-0 text-xs text-[var(--text-muted)]">{accDict.ledgerDesc || 'Immutable posted transactions stream by account and period.'}</p>
          </Card>
        </Link>

        <Link href="/accounting/trial-balance">
          <Card className="p-5 hover:border-blue-500/40 transition-all group cursor-pointer">
            <div className="flex items-center gap-3 mb-2">
              <div className="flex size-10 items-center justify-center rounded-xl bg-cyan-500/10 text-cyan-500 group-hover:bg-cyan-500 group-hover:text-white transition-colors">
                <svg className="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M3 6l9-4 9 4v14l-9 4-9-4V6z" />
                </svg>
              </div>
              <h3 className="m-0 text-sm font-bold text-[var(--text-primary)]">{accDict.trialBalance || 'Trial Balance'}</h3>
            </div>
            <p className="m-0 text-xs text-[var(--text-muted)]">{accDict.trialBalanceDesc || 'Derived account balances verification and debit/credit total match.'}</p>
          </Card>
        </Link>

        <Link href="/accounting/periods">
          <Card className="p-5 hover:border-blue-500/40 transition-all group cursor-pointer">
            <div className="flex items-center gap-3 mb-2">
              <div className="flex size-10 items-center justify-center rounded-xl bg-purple-500/10 text-purple-500 group-hover:bg-purple-500 group-hover:text-white transition-colors">
                <svg className="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
              </div>
              <h3 className="m-0 text-sm font-bold text-[var(--text-primary)]">{accDict.periods || 'Fiscal Periods'}</h3>
            </div>
            <p className="m-0 text-xs text-[var(--text-muted)]">{accDict.periodsDesc || 'Manage fiscal calendar years and close/reopen financial periods.'}</p>
          </Card>
        </Link>

        <Link href="/accounting/opening-balances">
          <Card className="p-5 hover:border-blue-500/40 transition-all group cursor-pointer">
            <div className="flex items-center gap-3 mb-2">
              <div className="flex size-10 items-center justify-center rounded-xl bg-amber-500/10 text-amber-500 group-hover:bg-amber-500 group-hover:text-white transition-colors">
                <svg className="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <h3 className="m-0 text-sm font-bold text-[var(--text-primary)]">{accDict.openingBalances || 'Opening Balances'}</h3>
            </div>
            <p className="m-0 text-xs text-[var(--text-muted)]">{accDict.openingBalancesDesc || 'Set account-level initial balances for new fiscal years.'}</p>
          </Card>
        </Link>
      </div>

      {/* Recent Journal Activity Table */}
      <Card className="p-6">
        <h3 className="m-0 text-sm font-bold text-[var(--text-primary)] mb-4">{accDict.recentJournals || 'Recent Journal Vouchers'}</h3>
        {recentJournals.length === 0 ? (
          <p className="text-xs text-[var(--text-muted)] italic">No journal vouchers created yet.</p>
        ) : (
          <div className="divide-y divide-[var(--border)]">
            {recentJournals.map((j) => (
              <div key={j.id} className="flex items-center justify-between py-3">
                <div className="flex items-center gap-3">
                  <div className="flex size-9 items-center justify-center rounded-xl bg-blue-500/10 text-blue-500 font-mono text-xs font-bold">
                    {j.number ? j.number : 'DRAFT'}
                  </div>
                  <div>
                    <span className="font-bold text-xs text-[var(--text-primary)] block">{j.description || 'Manual Journal'}</span>
                    <span className="text-[11px] text-[var(--text-muted)] font-mono">{j.entry_date}</span>
                  </div>
                </div>
                <div className="flex items-center gap-3">
                  <StatusBadge tone={j.status === 'posted' ? 'ok' : j.status === 'reversed' ? 'danger' : 'warning'}>
                    {j.status}
                  </StatusBadge>
                  <Link
                    href={`/accounting/journal/${j.id}`}
                    className="text-xs font-bold text-blue-600 dark:text-blue-400 hover:underline"
                  >
                    View
                  </Link>
                </div>
              </div>
            ))}
          </div>
        )}
      </Card>
    </AppLayout>
  );
}
