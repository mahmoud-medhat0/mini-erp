import { Head, Link } from '@inertiajs/react';

import AppLayout from '../Components/AppLayout';
import { Card, EmptyState, PageHeader } from '../Components/Primitives';
import { getDictionary, interpolate } from '../lib/i18n';
import { useCanAny } from '../lib/permissions';
import type { NotificationItem, SharedPageProps } from '../Types';

type DashboardCountKey =
  | 'accounts'
  | 'postedJournals'
  | 'ledgerEntries'
  | 'currencies'
  | 'customers'
  | 'suppliers'
  | 'unreadNotifications';

type DashboardProps = SharedPageProps & {
  counts: Record<DashboardCountKey, number>;
  recentNotifications?: NotificationItem[];
};

const metricConfig = [
  {
    key: 'accounts' as const,
    icon: 'M9 7h6m-6 4h6m-6 4h3m-7 6h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z',
    gradient: 'from-blue-600 to-indigo-600',
    href: '/accounting/coa',
  },
  {
    key: 'postedJournals' as const,
    icon: 'M9 12l2 2 4-4M7 4h10a2 2 0 012 2v12a2 2 0 01-2 2H7a2 2 0 01-2-2V6a2 2 0 012-2z',
    gradient: 'from-emerald-600 to-teal-600',
    href: '/accounting/journal',
  },
  {
    key: 'ledgerEntries' as const,
    icon: 'M4 6h16M4 10h16M4 14h10M4 18h8',
    gradient: 'from-violet-600 to-purple-600',
    href: '/accounting/ledger',
  },
  {
    key: 'currencies' as const,
    icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8V6m0 10v2m8-6a8 8 0 11-16 0 8 8 0 0116 0z',
    gradient: 'from-amber-600 to-orange-600',
    href: '/accounting/currencies',
  },
  {
    key: 'customers' as const,
    icon: 'M17 20h5v-2a4 4 0 00-4-4h-1M9 20H4v-2a4 4 0 014-4h1m4-6a4 4 0 11-8 0 4 4 0 018 0zm8 2a3 3 0 11-6 0 3 3 0 016 0z',
    gradient: 'from-sky-600 to-cyan-600',
    href: '/customers',
  },
  {
    key: 'suppliers' as const,
    icon: 'M3 7h18M5 7l1 12h12l1-12M9 7V5a3 3 0 016 0v2',
    gradient: 'from-fuchsia-600 to-pink-600',
    href: '/suppliers',
  },
  {
    key: 'unreadNotifications' as const,
    icon: 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9',
    gradient: 'from-rose-600 to-red-600',
    href: '/notifications',
  },
];

export default function Dashboard({ counts, recentNotifications = [], auth, locale }: DashboardProps) {
  const dict = getDictionary(locale);
  const canAny = useCanAny();
  const userName = auth?.user?.name || 'User';
  const accountingShortcuts = [
    {
      href: '/accounting/journal/create',
      label: dict.app.dashboard.shortcuts.createJournal,
      desc: dict.app.dashboard.shortcuts.createJournalDesc,
      permissions: ['accounting.create'],
      icon: 'M12 4v16m8-8H4',
    },
    {
      href: '/accounting/ledger',
      label: dict.app.dashboard.shortcuts.generalLedger,
      desc: dict.app.dashboard.shortcuts.generalLedgerDesc,
      permissions: ['accounting.view'],
      icon: 'M4 6h16M4 10h16M4 14h10M4 18h8',
    },
    {
      href: '/accounting/trial-balance',
      label: dict.app.dashboard.shortcuts.trialBalance,
      desc: dict.app.dashboard.shortcuts.trialBalanceDesc,
      permissions: ['accounting.view'],
      icon: 'M7 11h10M9 7h6M5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z',
    },
    {
      href: '/reports',
      label: dict.app.dashboard.shortcuts.reports,
      desc: dict.app.dashboard.shortcuts.reportsDesc,
      permissions: ['reports.view'],
      icon: 'M9 17v-6m4 6V7m4 10v-4M5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z',
    },
  ].filter((shortcut) => canAny(shortcut.permissions));

  const formatter = new Intl.DateTimeFormat(dict.app.pages.dashboard.enUs, {
    dateStyle: 'medium',
    timeStyle: 'short',
  });

  return (
    <AppLayout active="dashboard">
      <Head title={dict.app.nav.dashboard} />

      <PageHeader
        title={dict.app.nav.dashboard}
        description={dict.app.dashboard.description}
        actions={
          <Link
            href="/settings"
            className="inline-flex items-center gap-2 rounded-xl bg-[var(--primary)] px-4 py-2.5 text-xs font-bold text-white shadow-md shadow-blue-500/20 no-underline transition-all hover:bg-[var(--primary-hover)] active:scale-[0.99]"
          >
            <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <span>{dict.app.nav.settings}</span>
          </Link>
        }
      />

      {/* Hero Welcome Banner */}
      <Card className="mb-8 overflow-hidden border-blue-500/20 bg-gradient-to-r from-blue-900/20 via-indigo-900/10 to-[var(--surface)] p-6 shadow-md">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div className="space-y-1">
            <div className="flex items-center gap-2">
              <h2 className="m-0 text-xl font-extrabold text-[var(--text-primary)]">
                {interpolate(dict.app.pages.dashboard.welcomeBack, { name: userName })}
              </h2>
              <span className="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 px-2.5 py-0.5 text-[10px] font-extrabold text-emerald-600 dark:text-emerald-400">
                <span className="size-1.5 rounded-full bg-emerald-500 animate-pulse" />
                <span>{dict.app.header.systemOnline}</span>
              </span>
            </div>
            <p className="m-0 text-xs text-[var(--text-secondary)]">
              {dict.app.pages.dashboard.unifiedErpCoreCommandCenterFor}
            </p>
          </div>

          <div className="flex items-center gap-3 self-start sm:self-center">
            <div className="rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2 text-xs font-bold text-[var(--text-secondary)]">
              <span className="text-[var(--text-muted)] font-normal">{dict.app.pages.dashboard.engine}</span>
              <span className="font-mono text-[var(--primary)]">PostgreSQL 16</span>
            </div>
          </div>
        </div>
      </Card>

      {accountingShortcuts.length > 0 ? (
        <Card className="mb-8 p-5">
          <div className="mb-4 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
              <h2 className="m-0 text-base font-extrabold text-[var(--text-primary)]">
                {dict.app.dashboard.accountingWorkspace}
              </h2>
              <p className="m-0 text-xs text-[var(--text-secondary)]">
                {dict.app.dashboard.accountingWorkspaceDescription}
              </p>
            </div>
          </div>

          <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            {accountingShortcuts.map((shortcut) => (
              <Link
                key={shortcut.href}
                href={shortcut.href}
                className="group flex min-h-24 items-start gap-3 rounded-lg border border-[var(--border)] bg-[var(--background)] p-4 no-underline transition-all hover:border-[var(--primary)] hover:bg-[var(--surface)] hover:shadow-sm"
              >
                <span className="flex size-9 shrink-0 items-center justify-center rounded-lg border border-blue-500/20 bg-blue-500/10 text-blue-600 dark:text-blue-400">
                  <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" strokeLinejoin="round" d={shortcut.icon} />
                  </svg>
                </span>
                <span className="min-w-0">
                  <span className="block text-sm font-extrabold text-[var(--text-primary)] group-hover:text-[var(--primary)]">
                    {shortcut.label}
                  </span>
                  <span className="mt-1 block text-xs leading-relaxed text-[var(--text-muted)]">
                    {shortcut.desc}
                  </span>
                </span>
              </Link>
            ))}
          </div>
        </Card>
      ) : null}

      {/* Metrics Grid */}
      <div className="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {metricConfig.map((metric) => (
          <Link
            key={metric.key}
            href={metric.href}
            className="group relative overflow-hidden rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-5 no-underline shadow-xs transition-all hover:-translate-y-0.5 hover:border-[var(--primary)] hover:shadow-md"
          >
            <div className="flex items-center justify-between">
              <span className="text-xs font-bold uppercase tracking-wider text-[var(--text-muted)]">
                {dict.app.dashboard.counts[metric.key]}
              </span>
              <div className={`flex size-9 items-center justify-center rounded-xl bg-gradient-to-tr ${metric.gradient} text-white shadow-xs`}>
                <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d={metric.icon} />
                </svg>
              </div>
            </div>

            <div className="mt-4 flex items-baseline justify-between">
              <span className="text-3xl font-extrabold text-[var(--text-primary)]">
                {counts[metric.key]}
              </span>
              <span className="inline-flex items-center gap-1 rounded-full bg-blue-500/10 px-2 py-0.5 text-[10px] font-bold text-blue-600 dark:text-blue-400">
                {dict.app.dashboard.liveDb}
              </span>
            </div>
          </Link>
        ))}
      </div>

      {/* Overview & Activity Grid */}
      <div className="grid gap-6 lg:grid-cols-3">
        {/* Accounting Core Status Card */}
        <Card className="p-6 lg:col-span-2 space-y-6">
          <div className="flex items-start gap-4 border-b border-[var(--border)] pb-5">
            <div className="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-blue-500/10 text-blue-600 dark:text-blue-400">
              <svg className="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
              </svg>
            </div>
            <div className="space-y-1">
              <div className="flex items-center gap-2">
                <h3 className="m-0 text-base font-bold text-[var(--text-primary)]">
                  {dict.app.dashboard.noKpisTitle}
                </h3>
                <span className="rounded-full bg-amber-500/10 px-2.5 py-0.5 text-[10px] font-bold text-amber-600 dark:text-amber-400 border border-amber-500/20">
                  {dict.app.dashboard.migrationReady}
                </span>
              </div>
              <p className="text-xs leading-relaxed text-[var(--text-secondary)]">
                {dict.app.dashboard.noKpisDescription}
              </p>
            </div>
          </div>

          <div className="grid gap-3 text-xs sm:grid-cols-3">
            <div className="rounded-xl bg-[var(--background)] p-3.5 border border-[var(--border)]">
              <span className="text-[var(--text-muted)] block text-[10px] uppercase font-bold">
                {dict.app.dashboard.ledgerState}
              </span>
              <span className="font-bold text-emerald-600 dark:text-emerald-400 mt-1 block">
                {dict.app.dashboard.ledgerBalanced}
              </span>
            </div>
            <div className="rounded-xl bg-[var(--background)] p-3.5 border border-[var(--border)]">
              <span className="text-[var(--text-muted)] block text-[10px] uppercase font-bold">
                {dict.app.dashboard.securityEngine}
              </span>
              <span className="font-bold text-[var(--text-primary)] mt-1 block">Argon2id Hashing</span>
            </div>
            <div className="rounded-xl bg-[var(--background)] p-3.5 border border-[var(--border)]">
              <span className="text-[var(--text-muted)] block text-[10px] uppercase font-bold">
                {dict.app.dashboard.databaseEngine}
              </span>
              <span className="font-bold text-[var(--text-primary)] mt-1 block">PostgreSQL 16</span>
            </div>
          </div>

          {/* Recent Activity Stream Widget */}
          <div className="border-t border-[var(--border)] pt-5 space-y-3">
            <div className="flex items-center justify-between">
              <h4 className="m-0 text-xs font-bold uppercase tracking-wider text-[var(--text-muted)]">
                {dict.app.pages.dashboard.recentActivityFeed}
              </h4>
              <Link
                href="/notifications"
                className="text-xs font-bold text-blue-600 dark:text-blue-400 hover:underline no-underline"
              >
                {dict.app.notifications.viewAll}
              </Link>
            </div>

            {recentNotifications.length === 0 ? (
              <EmptyState title={dict.app.notifications.emptyRecent} />
            ) : (
              <div className="space-y-2">
                {recentNotifications.map((notif) => (
                  <div
                    key={notif.id}
                    className="flex items-center justify-between rounded-xl border border-[var(--border)] bg-[var(--background)] p-3 text-xs"
                  >
                    <div className="flex items-center gap-3 min-w-0">
                      <span className={`size-2 shrink-0 rounded-full ${notif.read ? 'bg-[var(--text-muted)]' : 'bg-blue-500 animate-pulse'}`} />
                      <span className="font-bold text-[var(--text-primary)] capitalize truncate">
                        {notif.type.replaceAll('_', ' ')}
                      </span>
                      <span className="font-mono text-[10px] text-[var(--text-muted)] bg-[var(--surface)] border border-[var(--border)] px-2 py-0.5 rounded truncate">
                        {notif.targetRef}
                      </span>
                    </div>

                    <span className="text-[11px] text-[var(--text-muted)] shrink-0 font-medium">
                      {formatter.format(new Date(notif.at))}
                    </span>
                  </div>
                ))}
              </div>
            )}
          </div>
        </Card>

        {/* Quick Management Shortcuts */}
        <Card className="p-6 flex flex-col justify-between">
          <div>
            <h3 className="m-0 text-xs font-bold uppercase tracking-wider text-[var(--text-muted)] mb-4">
              {dict.app.dashboard.quickActions}
            </h3>

            <div className="space-y-2.5">
              {[
                {
                  href: '/settings/company',
                  label: dict.app.dashboard.businessProfile,
                  desc: dict.app.pages.dashboard.corporateEntitiesBaseCurrency,
                  icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5m3 0h1m-1-4h1m-1-4h1m-1-4h1m-5 8h1m-1-4h1m-1-4h1 M14 7h1m-1 4h1m-1 4h1',
                  badgeColor: 'bg-blue-500/10 text-blue-500 border-blue-500/20',
                },
                {
                  href: '/settings/branches',
                  label: dict.app.dashboard.referenceBranches,
                  desc: dict.app.pages.dashboard.operationalBranchLocations,
                  icon: 'M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z',
                  badgeColor: 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20',
                },
                {
                  href: '/settings/numbering',
                  label: dict.app.settings.sections.numbering.title,
                  desc: dict.app.pages.dashboard.sequenceFormatsDocumentKeys,
                  icon: 'M7 20l4-16m2 16l4-16M6 9h14M4 15h14',
                  badgeColor: 'bg-fuchsia-500/10 text-fuchsia-500 border-fuchsia-500/20',
                },
                {
                  href: '/settings/users',
                  label: dict.app.settings.sections.users.title,
                  desc: dict.app.pages.dashboard.usersRolesSpatiePermissions,
                  icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
                  badgeColor: 'bg-violet-500/10 text-violet-500 border-violet-500/20',
                },
                {
                  href: '/notifications',
                  label: dict.app.nav.notifications,
                  desc: dict.app.pages.dashboard.realTimeAlertsActivityStream,
                  icon: 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9',
                  badgeColor: 'bg-rose-500/10 text-rose-500 border-rose-500/20',
                },
              ].map((action, idx) => (
                <Link
                  key={idx}
                  href={action.href}
                  className="group flex items-center justify-between rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs no-underline transition-all hover:border-[var(--primary)] hover:bg-[var(--surface)] hover:shadow-xs"
                >
                  <div className="flex items-center gap-2.5 min-w-0">
                    <div className={`flex size-8 shrink-0 items-center justify-center rounded-lg border ${action.badgeColor}`}>
                      <svg className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d={action.icon} />
                      </svg>
                    </div>

                    <div className="flex flex-col min-w-0">
                      <span className="font-bold text-[var(--text-primary)] text-xs group-hover:text-[var(--primary)] transition-colors truncate">
                        {action.label}
                      </span>
                      <span className="text-[10px] text-[var(--text-muted)] truncate">{action.desc}</span>
                    </div>
                  </div>

                  <svg
                    className="size-3.5 shrink-0 text-[var(--text-muted)] group-hover:text-[var(--primary)] group-hover:translate-x-1 rtl:group-hover:-translate-x-1 transition-all"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    strokeWidth={2}
                  >
                    <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
                  </svg>
                </Link>
              ))}
            </div>
          </div>

          <div className="mt-4 border-t border-[var(--border)] pt-3 text-center">
            <span className="text-[11px] font-semibold text-[var(--text-muted)]">
              Mini ERP Core v1.0 • Built with Laravel & React
            </span>
          </div>
        </Card>
      </div>
    </AppLayout>
  );
}
