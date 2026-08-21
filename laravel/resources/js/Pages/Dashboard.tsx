import { Head } from '@inertiajs/react';

import AppLayout from '../Components/AppLayout';
import { Card, EmptyState, PageHeader } from '../Components/Primitives';
import { getDictionary } from '../lib/i18n';
import type { SharedPageProps } from '../Types/page';

type DashboardProps = SharedPageProps & {
  counts: Record<'companies' | 'branches' | 'users' | 'roles' | 'permissions' | 'numberSequences' | 'unreadNotifications', number>;
};

const countKeys = ['companies', 'branches', 'users', 'roles', 'permissions', 'numberSequences', 'unreadNotifications'] as const;

export default function Dashboard({ counts, locale }: DashboardProps) {
  const dict = getDictionary(locale);

  return (
    <AppLayout active="dashboard">
      <Head title={dict.app.nav.dashboard} />
      <PageHeader title={dict.app.nav.dashboard} description={dict.app.dashboard.description} />

      <div className="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        {countKeys.map((key) => (
          <Card key={key} className="p-4">
            <p className="m-0 text-xs font-bold uppercase text-[var(--text-muted)]">{dict.app.dashboard.counts[key]}</p>
            <p className="m-0 mt-2 text-2xl font-bold text-[var(--text-primary)]">{counts[key]}</p>
          </Card>
        ))}
      </div>

      <EmptyState title={dict.app.dashboard.noKpisTitle} description={dict.app.dashboard.noKpisDescription} />
    </AppLayout>
  );
}
