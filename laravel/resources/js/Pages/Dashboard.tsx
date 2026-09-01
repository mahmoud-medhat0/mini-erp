import { Head, Link } from '@inertiajs/react';

import AppLayout from '../Components/AppLayout';
import { Card, EmptyState, PageHeader } from '../Components/Primitives';
import { getDictionary, interpolate } from '../lib/i18n';
import { useCanAny } from '../lib/permissions';
import type { SharedPageProps } from '../Types';

type DashboardCountKey =
  | 'accounts'
  | 'postedJournals'
  | 'ledgerEntries'
  | 'currencies'
  | 'customers'
  | 'suppliers';

type DashboardHealth = {
  ledgerBalanced?: boolean;
  openPeriods?: number;
  pendingJournals?: number;
  activeBranches?: number;
  companyName?: string | null;
  baseCurrency?: string | null;
  latestPostingAt?: string | null;
};

type DashboardProps = SharedPageProps & {
  counts?: Partial<Record<DashboardCountKey, number>>;
  health?: DashboardHealth;
};

const metricConfig = [
  {
    key: 'accounts' as const,
    icon: 'M9 7h6m-6 4h6m-6 4h3m-7 6h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z',
    tone: 'bg-blue-500/10 text-blue-600 dark:text-blue-400',
    href: '/accounting/coa',
    permissions: ['accounting.view', 'settings.configure'],
  },
  {
    key: 'postedJournals' as const,
    icon: 'M9 12l2 2 4-4M7 4h10a2 2 0 012 2v12a2 2 0 01-2 2H7a2 2 0 01-2-2V6a2 2 0 012-2z',
    tone: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
    href: '/accounting/journal?status=posted',
    permissions: ['accounting.view', 'settings.configure'],
  },
  {
    key: 'ledgerEntries' as const,
    icon: 'M4 6h16M4 10h16M4 14h10M4 18h8',
    tone: 'bg-violet-500/10 text-violet-600 dark:text-violet-400',
    href: '/accounting/ledger',
    permissions: ['accounting.view', 'settings.configure'],
  },
  {
    key: 'currencies' as const,
    icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8V6m0 10v2m8-6a8 8 0 11-16 0 8 8 0 0116 0z',
    tone: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
    href: '/accounting/currencies',
    permissions: ['accounting.view', 'accounting.currencies', 'manage_currencies', 'settings.configure'],
  },
  {
    key: 'customers' as const,
    icon: 'M17 20h5v-2a4 4 0 00-4-4h-1M9 20H4v-2a4 4 0 014-4h1m4-6a4 4 0 11-8 0 4 4 0 018 0zm8 2a3 3 0 11-6 0 3 3 0 016 0z',
    tone: 'bg-sky-500/10 text-sky-600 dark:text-sky-400',
    href: '/customers',
    permissions: ['customers.view'],
  },
  {
    key: 'suppliers' as const,
    icon: 'M3 7h18M5 7l1 12h12l1-12M9 7V5a3 3 0 016 0v2',
    tone: 'bg-fuchsia-500/10 text-fuchsia-600 dark:text-fuchsia-400',
    href: '/suppliers',
    permissions: ['suppliers.view'],
  },
] as const;

const focusClasses = 'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--primary)] focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--background)]';

export default function Dashboard({ counts = {}, health = {}, auth, locale, notifications }: DashboardProps) {
  const dict = getDictionary(locale);
  const canAny = useCanAny();
  const canAll = (permissions: string[]) => permissions.every((permission) => auth.permissions.includes(permission));
  const userName = auth?.user?.name || dict.app.header.unknownUser;
  const recentNotifications = notifications?.recent ?? [];
  const numberFormatter = new Intl.NumberFormat(locale === 'ar' ? 'ar-EG' : 'en-US');
  const dateFormatter = new Intl.DateTimeFormat(locale === 'ar' ? 'ar-EG' : 'en-US', {
    dateStyle: 'medium',
    timeStyle: 'short',
  });
  const dayFormatter = new Intl.DateTimeFormat(locale === 'ar' ? 'ar-EG' : 'en-US', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
  });

  const formatDateTime = (value?: string | null): string => {
    if (!value) return dict.app.dashboard.noPosting;
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? dict.app.dashboard.unavailableTime : dateFormatter.format(date);
  };

  const visibleMetrics = metricConfig.filter(
    (metric) => canAny([...metric.permissions]) && counts[metric.key] !== undefined,
  );
  const canOpenSettings = canAny(['settings.view', 'settings.configure']);

  const accountingShortcuts = [
    {
      href: '/accounting/journal/create',
      label: dict.app.dashboard.shortcuts.createJournal,
      desc: dict.app.dashboard.shortcuts.createJournalDesc,
      permissionsAny: ['accounting.create', 'settings.configure'],
      icon: 'M12 4v16m8-8H4',
    },
    {
      href: '/accounting/ledger',
      label: dict.app.dashboard.shortcuts.generalLedger,
      desc: dict.app.dashboard.shortcuts.generalLedgerDesc,
      permissionsAny: ['accounting.view', 'settings.configure'],
      icon: 'M4 6h16M4 10h16M4 14h10M4 18h8',
    },
    {
      href: '/accounting/trial-balance',
      label: dict.app.dashboard.shortcuts.trialBalance,
      desc: dict.app.dashboard.shortcuts.trialBalanceDesc,
      permissionsAny: ['accounting.view', 'settings.configure'],
      icon: 'M7 11h10M9 7h6M5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z',
    },
    {
      href: '/reports',
      label: dict.app.dashboard.shortcuts.reports,
      desc: dict.app.dashboard.shortcuts.reportsDesc,
      permissionsAll: ['reports.view', 'view_financials'],
      icon: 'M9 17v-6m4 6V7m4 10v-4M5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z',
    },
  ].filter((shortcut) => (
    'permissionsAll' in shortcut
      ? canAll(shortcut.permissionsAll ?? [])
      : canAny(shortcut.permissionsAny ?? [])
  ));

  const managementActions = [
    {
      href: '/settings/company',
      label: dict.app.dashboard.businessProfile,
      desc: dict.app.pages.dashboard.corporateEntitiesBaseCurrency,
      permissions: ['settings.company', 'settings.configure'],
      icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5m3 0h1m-1-4h1m-1-4h1m-1-4h1m-5 8h1m-1-4h1m-1-4h1',
    },
    {
      href: '/settings/branches',
      label: dict.app.dashboard.referenceBranches,
      desc: dict.app.pages.dashboard.operationalBranchLocations,
      permissions: ['settings.branches', 'settings.configure'],
      icon: 'M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z',
    },
    {
      href: '/settings/numbering',
      label: dict.app.settings.sections.numbering.title,
      desc: dict.app.pages.dashboard.sequenceFormatsDocumentKeys,
      permissions: ['settings.numbering', 'settings.configure'],
      icon: 'M7 20l4-16m2 16l4-16M6 9h14M4 15h14',
    },
    {
      href: '/settings/users',
      label: dict.app.settings.sections.users.title,
      desc: dict.app.pages.dashboard.usersRolesSpatiePermissions,
      permissions: ['users.configure', 'settings.configure'],
      icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
    },
  ].filter((action) => canAny(action.permissions));

  const operationalCards = [
    health.ledgerBalanced !== undefined ? {
      key: 'ledger',
      label: dict.app.dashboard.ledgerState,
      value: health.ledgerBalanced ? dict.app.dashboard.ledgerBalanced : dict.app.dashboard.ledgerNeedsReview,
      tone: health.ledgerBalanced ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400',
      dot: health.ledgerBalanced ? 'bg-emerald-500' : 'bg-red-500',
    } : null,
    health.openPeriods !== undefined ? {
      key: 'periods',
      label: dict.app.dashboard.openPeriods,
      value: numberFormatter.format(health.openPeriods),
      tone: 'text-[var(--text-primary)]',
      dot: health.openPeriods > 0 ? 'bg-emerald-500' : 'bg-amber-500',
    } : null,
    health.pendingJournals !== undefined ? {
      key: 'journals',
      label: dict.app.dashboard.pendingJournals,
      value: numberFormatter.format(health.pendingJournals),
      tone: 'text-[var(--text-primary)]',
      dot: health.pendingJournals > 0 ? 'bg-amber-500' : 'bg-emerald-500',
    } : null,
    health.activeBranches !== undefined ? {
      key: 'branches',
      label: dict.app.dashboard.activeBranches,
      value: numberFormatter.format(health.activeBranches),
      tone: 'text-[var(--text-primary)]',
      dot: health.activeBranches > 0 ? 'bg-emerald-500' : 'bg-amber-500',
    } : null,
  ].filter((item): item is NonNullable<typeof item> => item !== null);

  return (
    <AppLayout active="dashboard">
      <Head title={dict.app.nav.dashboard} />

      <PageHeader
        title={dict.app.nav.dashboard}
        description={dict.app.dashboard.description}
        actions={canOpenSettings ? (
          <Link
            href="/settings"
            className={`inline-flex items-center gap-2 rounded-xl bg-[var(--primary)] px-4 py-2.5 text-xs font-bold text-white shadow-md shadow-blue-500/20 no-underline transition-all hover:bg-[var(--primary-hover)] ${focusClasses}`}
          >
            <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2} aria-hidden="true">
              <path strokeLinecap="round" strokeLinejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            {dict.app.nav.settings}
          </Link>
        ) : undefined}
      />

      <section className="relative mb-6 overflow-hidden rounded-3xl border border-blue-500/20 bg-gradient-to-br from-slate-950 via-blue-950 to-indigo-900 p-6 text-white shadow-xl shadow-blue-950/10 sm:p-8" data-tour="dashboard-welcome">
        <div className="pointer-events-none absolute -end-16 -top-24 size-72 rounded-full bg-blue-400/15 blur-3xl" />
        <div className="pointer-events-none absolute -bottom-28 start-1/3 size-64 rounded-full bg-violet-400/10 blur-3xl" />
        <div className="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
          <div className="min-w-0">
            <span className="text-sm font-bold text-blue-200">{dayFormatter.format(new Date())}</span>
            <h2 className="mb-0 mt-2 text-2xl font-black leading-tight text-white sm:text-3xl">
              {interpolate(dict.app.pages.dashboard.welcomeBack, { name: userName })}
            </h2>
            <p className="mb-0 mt-3 max-w-2xl text-sm leading-7 text-slate-300">
              {dict.app.dashboard.operationalSnapshotDescription}
            </p>
          </div>

          {(health.companyName !== undefined || health.baseCurrency !== undefined || health.latestPostingAt !== undefined) ? (
            <div className="grid min-w-0 gap-2 sm:grid-cols-2 lg:min-w-[360px]">
              {(health.companyName !== undefined || health.baseCurrency !== undefined) ? (
                <div className="rounded-2xl border border-white/10 bg-white/10 p-3.5 backdrop-blur-sm">
                  <span className="block text-[11px] font-bold text-blue-200">{health.companyName || dict.app.dashboard.notConfigured}</span>
                  <strong className="mt-1 block text-sm text-white">
                    {dict.app.dashboard.baseCurrency}: <span className="font-mono" dir="ltr">{health.baseCurrency || dict.app.dashboard.notConfigured}</span>
                  </strong>
                </div>
              ) : null}
              {health.latestPostingAt !== undefined ? (
                <div className="rounded-2xl border border-white/10 bg-white/10 p-3.5 backdrop-blur-sm">
                  <span className="block text-[11px] font-bold text-blue-200">{dict.app.dashboard.latestPosting}</span>
                  <strong className="mt-1 block text-sm text-white">{formatDateTime(health.latestPostingAt)}</strong>
                </div>
              ) : null}
            </div>
          ) : null}
        </div>
      </section>

      {(visibleMetrics.length > 0 || notifications.unreadCount > 0) ? (
        <section className="mb-7" data-tour="dashboard-metrics">
          <div className="mb-3">
            <h2 className="m-0 text-base font-extrabold text-[var(--text-primary)]">{dict.app.dashboard.operationalSnapshot}</h2>
          </div>
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            {visibleMetrics.map((metric) => (
              <Link
                key={metric.key}
                href={metric.href}
                className={`group rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-5 no-underline shadow-xs transition-all hover:-translate-y-0.5 hover:border-[var(--primary)] hover:shadow-lg motion-reduce:transform-none ${focusClasses}`}
              >
                <div className="flex items-start justify-between gap-3">
                  <span className="text-sm font-bold text-[var(--text-secondary)]">{dict.app.dashboard.counts[metric.key]}</span>
                  <span className={`flex size-10 shrink-0 items-center justify-center rounded-2xl ${metric.tone}`}>
                    <svg className="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2} aria-hidden="true">
                      <path strokeLinecap="round" strokeLinejoin="round" d={metric.icon} />
                    </svg>
                  </span>
                </div>
                <strong className="mt-4 block text-3xl font-black text-[var(--text-primary)]">
                  {numberFormatter.format(counts[metric.key] ?? 0)}
                </strong>
              </Link>
            ))}

            <Link
              href="/notifications?tab=unread"
              className={`group rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-5 no-underline shadow-xs transition-all hover:-translate-y-0.5 hover:border-[var(--primary)] hover:shadow-lg motion-reduce:transform-none ${focusClasses}`}
            >
              <div className="flex items-start justify-between gap-3">
                <span className="text-sm font-bold text-[var(--text-secondary)]">{dict.app.dashboard.counts.unreadNotifications}</span>
                <span className="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-rose-500/10 text-rose-600 dark:text-rose-400">
                  <svg className="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2} aria-hidden="true">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                  </svg>
                </span>
              </div>
              <strong className="mt-4 block text-3xl font-black text-[var(--text-primary)]">
                {numberFormatter.format(notifications.unreadCount)}
              </strong>
            </Link>
          </div>
        </section>
      ) : null}

      <div className="grid gap-6 xl:grid-cols-3">
        <div className="space-y-6 xl:col-span-2">
          {accountingShortcuts.length > 0 ? (
            <Card className="rounded-2xl p-5 sm:p-6" data-tour="dashboard-workspace">
              <div className="mb-4">
                <h2 className="m-0 text-base font-extrabold text-[var(--text-primary)]">{dict.app.dashboard.accountingWorkspace}</h2>
                <p className="mb-0 mt-1 text-sm leading-6 text-[var(--text-secondary)]">{dict.app.dashboard.accountingWorkspaceDescription}</p>
              </div>
              <div className="grid gap-3 sm:grid-cols-2">
                {accountingShortcuts.map((shortcut) => (
                  <Link
                    key={shortcut.href}
                    href={shortcut.href}
                    className={`group flex min-h-24 items-start gap-3 rounded-2xl border border-[var(--border)] bg-[var(--background)] p-4 no-underline transition-all hover:border-[var(--primary)] hover:bg-[var(--surface)] hover:shadow-md ${focusClasses}`}
                  >
                    <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400">
                      <svg className="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2} aria-hidden="true">
                        <path strokeLinecap="round" strokeLinejoin="round" d={shortcut.icon} />
                      </svg>
                    </span>
                    <span className="min-w-0">
                      <strong className="block text-sm text-[var(--text-primary)] transition-colors group-hover:text-[var(--primary)]">{shortcut.label}</strong>
                      <span className="mt-1 block text-xs leading-5 text-[var(--text-secondary)]">{shortcut.desc}</span>
                    </span>
                  </Link>
                ))}
              </div>
            </Card>
          ) : null}

          <Card className="rounded-2xl p-5 sm:p-6" data-tour="dashboard-activity">
            <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
              <div>
                <h2 className="m-0 text-base font-extrabold text-[var(--text-primary)]">{dict.app.dashboard.latestNotifications}</h2>
                <p className="mb-0 mt-1 text-sm text-[var(--text-secondary)]">{dict.app.dashboard.latestNotificationsDescription}</p>
              </div>
              <Link href="/notifications" className={`text-xs font-bold text-[var(--primary)] no-underline hover:underline ${focusClasses}`}>
                {dict.app.notifications.viewAll}
              </Link>
            </div>

            {recentNotifications.length === 0 ? (
              <EmptyState title={dict.app.notifications.emptyRecent} />
            ) : (
              <div className="space-y-2">
                {recentNotifications.map((notification) => (
                  <div key={notification.id} className="grid gap-2 rounded-xl border border-[var(--border)] bg-[var(--background)] p-3 sm:grid-cols-[1fr_auto] sm:items-center">
                    <div className="flex min-w-0 items-center gap-3">
                      <span className={`size-2 shrink-0 rounded-full ${notification.read ? 'bg-slate-400' : 'bg-blue-500 motion-safe:animate-pulse'}`} />
                      <div className="min-w-0">
                        <strong className="block truncate text-sm text-[var(--text-primary)]">
                          {notification.type.replaceAll('_', ' ')}
                        </strong>
                        <span className="identifier mt-0.5 block truncate text-xs text-[var(--text-secondary)]" dir="ltr">
                          {notification.targetRef || dict.app.dashboard.noReference}
                        </span>
                      </div>
                    </div>
                    <time className="text-xs font-medium text-[var(--text-secondary)]" dateTime={notification.at || undefined}>
                      {notification.at ? formatDateTime(notification.at) : dict.app.dashboard.unavailableTime}
                    </time>
                  </div>
                ))}
              </div>
            )}
          </Card>
        </div>

        <div className="space-y-6">
          {operationalCards.length > 0 ? (
            <Card className="rounded-2xl p-5 sm:p-6" data-tour="dashboard-health">
              <div className="mb-4">
                <h2 className="m-0 text-base font-extrabold text-[var(--text-primary)]">{dict.app.dashboard.noKpisTitle}</h2>
                <p className="mb-0 mt-1 text-xs leading-5 text-[var(--text-secondary)]">{dict.app.dashboard.noKpisDescription}</p>
              </div>
              <div className="space-y-2.5">
                {operationalCards.map((item) => (
                  <div key={item.key} className="flex items-center justify-between gap-3 rounded-xl border border-[var(--border)] bg-[var(--background)] p-3.5">
                    <span className="flex items-center gap-2 text-xs font-bold text-[var(--text-secondary)]">
                      <span className={`size-2 rounded-full ${item.dot}`} />
                      {item.label}
                    </span>
                    <strong className={`text-sm ${item.tone}`}>{item.value}</strong>
                  </div>
                ))}
              </div>
            </Card>
          ) : null}

          {managementActions.length > 0 ? (
            <Card className="rounded-2xl p-5 sm:p-6" data-tour="dashboard-management">
              <div className="mb-4">
                <h2 className="m-0 text-base font-extrabold text-[var(--text-primary)]">{dict.app.dashboard.quickActions}</h2>
                <p className="mb-0 mt-1 text-xs leading-5 text-[var(--text-secondary)]">{dict.app.dashboard.quickActionsDescription}</p>
              </div>
              <div className="space-y-2.5">
                {managementActions.map((action) => (
                  <Link key={action.href} href={action.href} className={`group flex items-center justify-between gap-3 rounded-xl border border-[var(--border)] bg-[var(--background)] p-3 no-underline transition-all hover:border-[var(--primary)] hover:bg-[var(--surface)] ${focusClasses}`}>
                    <span className="flex min-w-0 items-center gap-3">
                      <span className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400">
                        <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2} aria-hidden="true">
                          <path strokeLinecap="round" strokeLinejoin="round" d={action.icon} />
                        </svg>
                      </span>
                      <span className="min-w-0">
                        <strong className="block truncate text-xs text-[var(--text-primary)] group-hover:text-[var(--primary)]">{action.label}</strong>
                        <span className="mt-0.5 block truncate text-[11px] text-[var(--text-secondary)]">{action.desc}</span>
                      </span>
                    </span>
                    <svg className="size-4 shrink-0 text-[var(--text-muted)] transition-transform group-hover:translate-x-1 rtl:rotate-180 rtl:group-hover:-translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2} aria-hidden="true">
                      <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                  </Link>
                ))}
              </div>
            </Card>
          ) : null}
        </div>
      </div>
    </AppLayout>
  );
}
