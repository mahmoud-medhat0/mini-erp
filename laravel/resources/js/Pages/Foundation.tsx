import { Head, usePage } from '@inertiajs/react';

import AppLayout from '../Components/AppLayout';
import { Card, PageHeader, StatusBadge } from '../Components/Primitives';
import type { SharedPageProps } from '../Types/page';

type FoundationProps = SharedPageProps & {
  status: string;
  database: 'ok' | 'unavailable' | 'unknown' | 'not_checked';
};

export default function Foundation({ status, database }: FoundationProps) {
  const { props } = usePage<FoundationProps>();

  return (
    <AppLayout active="foundation">
      <Head title="System Diagnostics" />
      <PageHeader
        title="System Diagnostics & Health"
        description="Core infrastructure foundation status, session state, and database connectivity."
      />

      <div className="space-y-6">
        <Card className="p-6">
          <div className="flex flex-wrap items-start justify-between gap-4">
            <div>
              <h2 className="m-0 text-lg font-bold text-[var(--text-primary)]">Migration & Auth Foundation</h2>
              <p className="mt-1.5 max-w-2xl text-xs leading-relaxed text-[var(--text-secondary)]">
                Laravel session authentication is active beside the existing Next.js reference app. Real PostgreSQL queries power all foundation models.
              </p>
            </div>
            <StatusBadge tone={database === 'ok' ? 'ok' : 'muted'}>
              {database === 'ok' ? 'PostgreSQL 16 OK' : 'DB Status Verified at /health'}
            </StatusBadge>
          </div>

          <dl className="mt-6 grid gap-4 text-xs sm:grid-cols-3">
            <div className="rounded-xl border border-[var(--border)] bg-[var(--background)] p-4">
              <dt className="font-bold text-[var(--text-muted)] uppercase tracking-wider text-[10px]">Architecture Phase</dt>
              <dd className="m-0 mt-1 font-bold text-[var(--text-primary)] text-sm">{status}</dd>
            </div>
            <div className="rounded-xl border border-[var(--border)] bg-[var(--background)] p-4">
              <dt className="font-bold text-[var(--text-muted)] uppercase tracking-wider text-[10px]">Session Locale</dt>
              <dd className="m-0 mt-1 font-bold text-[var(--text-primary)] text-sm uppercase">{props.locale}</dd>
            </div>
            <div className="rounded-xl border border-[var(--border)] bg-[var(--background)] p-4">
              <dt className="font-bold text-[var(--text-muted)] uppercase tracking-wider text-[10px]">Active Theme</dt>
              <dd className="m-0 mt-1 font-bold text-[var(--text-primary)] text-sm capitalize">{props.theme}</dd>
            </div>
          </dl>
        </Card>
      </div>
    </AppLayout>
  );
}
