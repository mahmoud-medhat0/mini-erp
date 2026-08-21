import { Head, Link } from '@inertiajs/react';

import AppLayout from '../Components/AppLayout';
import { Card, EmptyState, PageHeader, StatusBadge } from '../Components/Primitives';
import { getDictionary } from '../lib/i18n';
import type { NotificationItem, SharedPageProps } from '../Types';

type DashboardProps = SharedPageProps & {
  counts: Record<'companies' | 'branches' | 'users' | 'roles' | 'permissions' | 'numberSequences' | 'unreadNotifications', number>;
  recentNotifications?: NotificationItem[];
};

const metricConfig = [
  {
    key: 'companies' as const,
    icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5m3 0h1m-1-4h1m-1-4h1m-1-4h1m-5 8h1m-1-4h1m-1-4h1',
    gradient: 'from-blue-600 to-indigo-600',
    href: '/settings/company',
  },
  {
    key: 'branches' as const,
    icon: 'M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z',
    gradient: 'from-emerald-600 to-teal-600',
    href: '/settings/branches',
  },
  {
    key: 'users' as const,
    icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
    gradient: 'from-violet-600 to-purple-600',
    href: '/settings/users',
  },
  {
    key: 'roles' as const,
    icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
    gradient: 'from-amber-600 to-orange-600',
    href: '/settings/users',
  },
  {
    key: 'permissions' as const,
    icon: 'M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z',
    gradient: 'from-sky-600 to-cyan-600',
    href: '/settings/users',
  },
  {
    key: 'numberSequences' as const,
    icon: 'M7 20l4-16m2 16l4-16M6 9h14M4 15h14',
    gradient: 'from-fuchsia-600 to-pink-600',
    href: '/settings/numbering',
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
  const isAr = locale === 'ar';
  const userName = auth?.user?.name || 'User';

  const formatter = new Intl.DateTimeFormat(isAr ? 'ar-EG' : 'en-US', {
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
                {isAr ? `مرحباً، ${userName}` : `Welcome back, ${userName}`}
              </h2>
              <span className="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 px-2.5 py-0.5 text-[10px] font-extrabold text-emerald-600 dark:text-emerald-400">
                <span className="size-1.5 rounded-full bg-emerald-500 animate-pulse" />
                <span>{dict.app.header.systemOnline}</span>
              </span>
            </div>
            <p className="m-0 text-xs text-[var(--text-secondary)]">
              {isAr ? 'مركز قيادة النظام والمعاملات المالية وإدارة الصلاحيات والمؤسسة.' : 'Unified ERP core command center for financial ledgers, scopes, and administration.'}
            </p>
          </div>

          <div className="flex items-center gap-3 self-start sm:self-center">
            <div className="rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2 text-xs font-bold text-[var(--text-secondary)]">
              <span className="text-[var(--text-muted)] font-normal">{isAr ? 'المحرك: ' : 'Engine: '}</span>
              <span className="font-mono text-[var(--primary)]">PostgreSQL 16</span>
            </div>
          </div>
        </div>
      </Card>

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
                {isAr ? 'أحدث التنبيهات والأنشطة' : 'Recent Activity Feed'}
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
                  label: dict.app.settings.sections.company.title,
                  desc: isAr ? 'إدارة الشركات والعملة الأساسية' : 'Corporate entities & base currency',
                  icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5m3 0h1m-1-4h1m-1-4h1m-1-4h1m-5 8h1m-1-4h1m-1-4h1 M14 7h1m-1 4h1m-1 4h1',
                  badgeColor: 'bg-blue-500/10 text-blue-500 border-blue-500/20',
                },
                {
                  href: '/settings/branches',
                  label: dict.app.settings.sections.branches.title,
                  desc: isAr ? 'إدارة الفروع وتخصيص المقرات' : 'Operational branch locations',
                  icon: 'M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z',
                  badgeColor: 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20',
                },
                {
                  href: '/settings/numbering',
                  label: dict.app.settings.sections.numbering.title,
                  desc: isAr ? 'ضبط المتتابعات وبادئات المستندات' : 'Sequence formats & document keys',
                  icon: 'M7 20l4-16m2 16l4-16M6 9h14M4 15h14',
                  badgeColor: 'bg-fuchsia-500/10 text-fuchsia-500 border-fuchsia-500/20',
                },
                {
                  href: '/settings/users',
                  label: dict.app.settings.sections.users.title,
                  desc: isAr ? 'إدارة المستخدمين والأدوار والصلاحيات' : 'Users, roles & Spatie permissions',
                  icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
                  badgeColor: 'bg-violet-500/10 text-violet-500 border-violet-500/20',
                },
                {
                  href: '/notifications',
                  label: dict.app.nav.notifications,
                  desc: isAr ? 'مركز التنبيهات وسجل النشاط' : 'Real-time alerts & activity stream',
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
