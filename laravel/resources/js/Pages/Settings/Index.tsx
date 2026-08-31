import { Head, Link } from '@inertiajs/react';

import AppLayout from '../../Components/AppLayout';
import { Card, PageHeader } from '../../Components/Primitives';
import { getDictionary, interpolate } from '../../lib/i18n';
import { useCanAny } from '../../lib/permissions';
import type { SharedPageProps } from '../../Types/page';

type SettingsOverview = {
  companyRecords: number | null;
  activeBranches: number | null;
  totalBranches: number | null;
  numberSequences: number | null;
  activeUsers: number | null;
  totalUsers: number | null;
  activeApprovalRules: number | null;
  totalApprovalRules: number | null;
  completedEssentials: number;
  totalEssentials: number;
};

type SettingsProps = SharedPageProps & {
  overview: SettingsOverview;
};

const sections = [
  {
    key: 'company',
    href: '/settings/company',
    group: 'business',
    permissions: ['settings.company', 'settings.configure'],
    icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5m3 0h1m-1-4h1m-1-4h1m-1-4h1m-5 8h1m-1-4h1m-1-4h1',
    tone: 'border-blue-500/20 bg-blue-500/10 text-blue-600 dark:text-blue-400',
  },
  {
    key: 'branches',
    href: '/settings/branches',
    group: 'business',
    permissions: ['settings.branches', 'settings.configure'],
    icon: 'M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z',
    tone: 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
  },
  {
    key: 'numbering',
    href: '/settings/numbering',
    group: 'business',
    permissions: ['settings.numbering', 'settings.configure'],
    icon: 'M7 20l4-16m2 16l4-16M6 9h14M4 15h14',
    tone: 'border-violet-500/20 bg-violet-500/10 text-violet-600 dark:text-violet-400',
  },
  {
    key: 'users',
    href: '/settings/users',
    group: 'governance',
    permissions: ['users.configure', 'settings.configure'],
    icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
    tone: 'border-sky-500/20 bg-sky-500/10 text-sky-600 dark:text-sky-400',
  },
  {
    key: 'branchApprovalRules',
    href: '/settings/branch-approval-rules',
    group: 'governance',
    permissions: ['approvals.configure', 'settings.configure'],
    icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
    tone: 'border-amber-500/20 bg-amber-500/10 text-amber-600 dark:text-amber-400',
  },
] as const;

export default function SettingsIndex({ locale, overview }: SettingsProps) {
  const dict = getDictionary(locale);
  const canAny = useCanAny();
  const progress = overview.totalEssentials > 0
    ? Math.round((overview.completedEssentials / overview.totalEssentials) * 100)
    : 0;
  const visibleSections = sections.filter((section) => canAny([...section.permissions]));
  const visibleMetrics = [
    canAny(['settings.branches', 'settings.view', 'settings.configure']) && overview.activeBranches !== null
      ? { label: dict.app.settings.overview.activeBranches, value: overview.activeBranches, total: overview.totalBranches, color: 'bg-emerald-500' }
      : null,
    canAny(['settings.numbering', 'settings.view', 'settings.configure']) && overview.numberSequences !== null
      ? { label: dict.app.settings.overview.numberSequences, value: overview.numberSequences, color: 'bg-violet-500' }
      : null,
    canAny(['users.configure', 'settings.view', 'settings.configure']) && overview.activeUsers !== null
      ? { label: dict.app.settings.overview.activeUsers, value: overview.activeUsers, total: overview.totalUsers, color: 'bg-sky-500' }
      : null,
    canAny(['approvals.configure', 'settings.view', 'settings.configure']) && overview.activeApprovalRules !== null
      ? { label: dict.app.settings.overview.activeApprovalRules, value: overview.activeApprovalRules, total: overview.totalApprovalRules, color: 'bg-amber-500' }
      : null,
  ].filter((metric): metric is { label: string; value: number; total?: number | null; color: string } => metric !== null);

  const sectionValue = (key: (typeof sections)[number]['key']): string => {
    if (key === 'company') return String(overview.companyRecords ?? 0);
    if (key === 'branches') return `${overview.activeBranches ?? 0}/${overview.totalBranches ?? 0}`;
    if (key === 'numbering') return String(overview.numberSequences ?? 0);
    if (key === 'users') return `${overview.activeUsers ?? 0}/${overview.totalUsers ?? 0}`;
    return `${overview.activeApprovalRules ?? 0}/${overview.totalApprovalRules ?? 0}`;
  };

  const sectionReady = (key: (typeof sections)[number]['key']): boolean => {
    if (key === 'company') return (overview.companyRecords ?? 0) > 0;
    if (key === 'branches') return (overview.activeBranches ?? 0) > 0;
    if (key === 'numbering') return (overview.numberSequences ?? 0) > 0;
    if (key === 'users') return (overview.activeUsers ?? 0) > 0;
    return true;
  };

  return (
    <AppLayout active="settings">
      <Head title={dict.app.settings.title} />
      <PageHeader
        title={dict.app.settings.title}
        description={dict.app.settings.description}
        actions={canAny(['audit.view', 'settings.configure']) ? (
          <Link
            href="/audit-log"
            className="inline-flex items-center gap-2 rounded-xl border border-[var(--border)] bg-[var(--surface)] px-4 py-2.5 text-xs font-bold text-[var(--text-primary)] no-underline shadow-xs transition-all hover:border-[var(--primary)] hover:text-[var(--primary)]"
          >
            <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2} aria-hidden="true">
              <path strokeLinecap="round" strokeLinejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            {dict.app.settings.overview.auditLog}
          </Link>
        ) : undefined}
      />

      <Card className="relative mb-6 overflow-hidden border-blue-500/20 bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 p-6 text-white shadow-lg shadow-blue-950/10 sm:p-8">
        <div className="pointer-events-none absolute -end-16 -top-20 size-64 rounded-full bg-white/10 blur-2xl" />
        <div className="pointer-events-none absolute -bottom-24 start-1/3 size-56 rounded-full bg-cyan-300/10 blur-3xl" />
        <div className="relative grid gap-6 lg:grid-cols-[1fr_280px] lg:items-center">
          <div>
            <span className="inline-flex rounded-full border border-white/20 bg-white/10 px-3 py-1 text-[11px] font-extrabold tracking-wide text-blue-50">
              {dict.app.settings.overview.controlCenter}
            </span>
            <h2 className="mb-0 mt-3 max-w-2xl text-2xl font-extrabold leading-tight text-white sm:text-3xl">
              {dict.app.settings.overview.heroTitle}
            </h2>
            <p className="mb-0 mt-3 max-w-2xl text-sm leading-7 text-blue-100">
              {dict.app.settings.overview.heroDescription}
            </p>
          </div>

          <div className="rounded-2xl border border-white/20 bg-white/10 p-4 backdrop-blur-sm">
            <div className="flex items-center justify-between gap-4">
              <div>
                <span className="block text-xs font-bold text-blue-100">{dict.app.settings.overview.readiness}</span>
                <strong className="mt-1 block text-3xl font-black text-white">{progress}%</strong>
              </div>
              <div className="flex size-14 items-center justify-center rounded-2xl bg-white/15 text-lg font-black shadow-inner">
                {overview.completedEssentials}/{overview.totalEssentials}
              </div>
            </div>
            <div className="mt-4 h-2 overflow-hidden rounded-full bg-slate-950/20">
              <div className="h-full rounded-full bg-white transition-all duration-500" style={{ width: `${progress}%` }} />
            </div>
            <p className="mb-0 mt-2 text-[11px] leading-5 text-blue-100">
              {interpolate(dict.app.settings.overview.readinessHint, {
                complete: overview.completedEssentials,
                total: overview.totalEssentials,
              })}
            </p>
          </div>
        </div>
      </Card>

      {visibleMetrics.length > 0 ? (
      <div className="mb-7 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        {visibleMetrics.map((metric) => (
          <Card key={metric.label} className="flex items-center justify-between gap-3 p-4 transition-all hover:-translate-y-0.5 hover:shadow-md">
            <div>
              <span className="block text-xs font-bold text-[var(--text-secondary)]">{metric.label}</span>
              <strong className="mt-1 block text-2xl font-black text-[var(--text-primary)]">
                {metric.value}{metric.total !== undefined ? <span className="text-sm font-bold text-[var(--text-muted)]">/{metric.total}</span> : null}
              </strong>
            </div>
            <span className={`size-3 rounded-full ${metric.color} shadow-[0_0_0_6px_rgba(148,163,184,0.1)]`} />
          </Card>
        ))}
      </div>
      ) : null}

      {(['business', 'governance'] as const).map((group) => {
        const groupSections = visibleSections.filter((section) => section.group === group);
        if (groupSections.length === 0) return null;

        return (
          <section key={group} className="mb-7" data-tour="settings-sections">
            <div className="mb-3 flex items-end justify-between gap-3">
              <div>
                <h2 className="m-0 text-base font-extrabold text-[var(--text-primary)]">
                  {dict.app.settings.overview.groups[group].title}
                </h2>
                <p className="mb-0 mt-1 text-xs leading-5 text-[var(--text-secondary)]">
                  {dict.app.settings.overview.groups[group].description}
                </p>
              </div>
            </div>

            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
              {groupSections.map((section) => {
                const ready = sectionReady(section.key);

                return (
                  <Link
                    key={section.href}
                    href={section.href}
                    className="group relative overflow-hidden rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-5 no-underline shadow-xs transition-all hover:-translate-y-0.5 hover:border-[var(--primary)] hover:shadow-lg"
                  >
                    <div className="flex items-start justify-between gap-4">
                      <span className={`flex size-11 shrink-0 items-center justify-center rounded-2xl border ${section.tone}`}>
                        <svg className="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2} aria-hidden="true">
                          <path strokeLinecap="round" strokeLinejoin="round" d={section.icon} />
                        </svg>
                      </span>
                      <span className={`rounded-full px-2.5 py-1 text-[10px] font-extrabold ${ready ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-amber-500/10 text-amber-600 dark:text-amber-400'}`}>
                        {ready ? dict.app.settings.overview.ready : dict.app.settings.overview.needsSetup}
                      </span>
                    </div>

                    <h3 className="mb-0 mt-4 text-base font-extrabold text-[var(--text-primary)] transition-colors group-hover:text-[var(--primary)]">
                      {dict.app.settings.sections[section.key].title}
                    </h3>
                    <p className="mb-0 mt-2 min-h-12 text-sm leading-6 text-[var(--text-secondary)]">
                      {dict.app.settings.sections[section.key].description}
                    </p>

                    <div className="mt-4 flex items-center justify-between border-t border-[var(--border)] pt-4">
                      <span className="font-mono text-sm font-black text-[var(--text-primary)]">{sectionValue(section.key)}</span>
                      <span className="inline-flex items-center gap-1 text-xs font-bold text-[var(--primary)]">
                        {dict.app.settings.overview.openSection}
                        <svg className="size-3.5 transition-transform group-hover:translate-x-1 rtl:group-hover:-translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2} aria-hidden="true">
                          <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                      </span>
                    </div>
                  </Link>
                );
              })}
            </div>
          </section>
        );
      })}
    </AppLayout>
  );
}
