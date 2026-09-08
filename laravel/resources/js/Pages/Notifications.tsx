import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, type FormEvent, type ReactElement } from 'react';

import AppLayout from '../Components/AppLayout';
import ServerDataTable, { type DataTableSlots } from '../Components/ServerDataTable';
import { Card, PageHeader, StatusBadge } from '../Components/Primitives';
import { getDictionary } from '../lib/i18n';
import type { NotificationRow, PaginationLink, SharedPageProps } from '../Types';

type NotificationsProps = SharedPageProps & {
  items: {
    data: NotificationRow[];
    links: PaginationLink[];
    total: number;
  };
  counts: { all: number; unread: number; read: number };
  filters: { tab: 'all' | 'unread' | 'read' };
};

function NotificationTypeIcon({ type }: { type: string }) {
  const normalized = type.toLowerCase();

  if (normalized.includes('user') || normalized.includes('role') || normalized.includes('permission')) {
    return (
      <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20">
        <svg className="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
          <path strokeLinecap="round" strokeLinejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
        </svg>
      </div>
    );
  }

  if (normalized.includes('invoice') || normalized.includes('payment') || normalized.includes('accounting') || normalized.includes('sequence')) {
    return (
      <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
        <svg className="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
          <path strokeLinecap="round" strokeLinejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
      </div>
    );
  }

  if (normalized.includes('warning') || normalized.includes('alert') || normalized.includes('error')) {
    return (
      <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
        <svg className="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
          <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
      </div>
    );
  }

  // Default system notification icon
  return (
    <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20">
      <svg className="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
      </svg>
    </div>
  );
}

function MarkReadButton({ id, label }: { id: string; label: string }) {
  const { post, processing } = useForm({});

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    post(`/notifications/${id}/read`, { preserveScroll: true });
  }

  return (
    <form onSubmit={submit} className="inline-flex">
      <button
        type="submit"
        disabled={processing}
        title={label}
        aria-label={label}
        className="inline-flex items-center gap-1.5 rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-1.5 text-xs font-bold text-[var(--text-secondary)] hover:border-[var(--primary)] hover:text-[var(--text-primary)] transition-colors disabled:opacity-50"
      >
        <svg className="size-3.5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
          <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
        </svg>
        <span>{label}</span>
      </button>
    </form>
  );
}

function MarkAllReadButton({ label }: { label: string }) {
  const { post, processing } = useForm({});

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    post('/notifications/read-all', { preserveScroll: true });
  }

  return (
    <form onSubmit={submit} className="inline-flex">
      <button
        type="submit"
        disabled={processing}
        title={label}
        aria-label={label}
        className="inline-flex items-center gap-2 rounded-xl bg-[var(--primary)] px-4 py-2 text-xs font-bold text-white shadow-md shadow-blue-500/20 hover:bg-[var(--primary-hover)] transition-colors disabled:opacity-60"
      >
        {processing ? (
          <svg className="size-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
          </svg>
        ) : (
          <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        )}
        <span>{label}</span>
      </button>
    </form>
  );
}

export default function Notifications({ items, counts, filters, locale }: NotificationsProps) {
  const dict = getDictionary(locale);
  const filter = filters.tab;

  const formatter = new Intl.DateTimeFormat(dict.app.pages.notifications.enUs, {
    dateStyle: 'medium',
    timeStyle: 'short',
  });

  const columns = useMemo(() => [
    { data: 'type', name: 'type', title: dict.app.notifications.type },
    { data: 'target_ref', name: 'target_ref', title: dict.app.notifications.reference },
    { data: 'at', name: 'at', title: dict.app.notifications.date },
    { data: 'read', name: 'read', title: dict.app.notifications.status, searchable: false },
    { data: 'actions', name: 'actions', title: dict.app.notifications.actions, orderable: false, searchable: false },
  ], [dict]);

  const slots = useMemo<DataTableSlots>(() => ({
    type: (data: string): ReactElement => {
      const formattedType = data
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');

      return (
        <div className="flex min-w-0 items-center gap-3">
          <NotificationTypeIcon type={data} />
          <span className="truncate text-sm font-bold text-[var(--text-primary)]">{formattedType}</span>
        </div>
      );
    },
    target_ref: (data: string | null): ReactElement => (
      <span className="inline-block max-w-xs truncate rounded-md border border-[var(--border)] bg-[var(--background)] px-2 py-0.5 font-mono text-xs text-[var(--text-muted)]">
        {data || dict.app.dashboard.noReference}
      </span>
    ),
    at: (data: string | null): ReactElement => (
      <span className="whitespace-nowrap text-xs font-medium text-[var(--text-muted)]">
        {data && !Number.isNaN(new Date(data).getTime())
          ? formatter.format(new Date(data))
          : dict.app.dashboard.unavailableTime}
      </span>
    ),
    read: (data: boolean): ReactElement => (
      <StatusBadge tone={data ? 'muted' : 'ok'}>
        {data ? dict.app.notifications.read : dict.app.notifications.unread}
      </StatusBadge>
    ),
    actions: (_data: unknown, _type: unknown, row: NotificationRow): ReactElement | null => (
      row.read ? null : <MarkReadButton id={row.id} label={dict.app.notifications.markRead} />
    ),
  } as DataTableSlots), [dict, formatter]);

  const changeFilter = (tab: 'all' | 'unread' | 'read') => {
    router.get('/notifications', { tab: tab === 'all' ? undefined : tab }, {
      preserveScroll: true,
      preserveState: true,
      replace: true,
    });
  };

  return (
    <AppLayout active="notifications" pagination="none">
      <Head title={dict.app.nav.notifications} />

      <PageHeader
        title={dict.app.nav.notifications}
        description={dict.app.notifications.description}
        actions={counts.unread > 0 ? <MarkAllReadButton label={dict.app.notifications.markAllRead} /> : undefined}
      />

      {/* Tabs Filter Bar */}
      <div className="flex border-b border-[var(--border)] mb-6 gap-2" role="tablist" aria-label={dict.app.nav.notifications}>
        <button
          type="button"
          role="tab"
          aria-selected={filter === 'all'}
          onClick={() => changeFilter('all')}
          title={dict.app.notifications.all}
          aria-label={dict.app.notifications.all}
          className={`flex items-center gap-2 border-b-2 px-4 py-3 text-xs font-extrabold transition-all ${
            filter === 'all'
              ? 'border-[var(--primary)] text-[var(--primary)]'
              : 'border-transparent text-[var(--text-muted)] hover:text-[var(--text-primary)]'
          }`}
        >
          <span>{dict.app.notifications.all}</span>
          <span className="rounded-full bg-[var(--surface)] border border-[var(--border)] px-2 py-0.5 text-[10px] font-mono text-[var(--text-secondary)]">
            {counts.all}
          </span>
        </button>

        <button
          type="button"
          role="tab"
          aria-selected={filter === 'unread'}
          onClick={() => changeFilter('unread')}
          title={dict.app.notifications.unread}
          aria-label={dict.app.notifications.unread}
          className={`flex items-center gap-2 border-b-2 px-4 py-3 text-xs font-extrabold transition-all ${
            filter === 'unread'
              ? 'border-[var(--primary)] text-[var(--primary)]'
              : 'border-transparent text-[var(--text-muted)] hover:text-[var(--text-primary)]'
          }`}
        >
          <span>{dict.app.notifications.unread}</span>
          {counts.unread > 0 ? (
            <span className="rounded-full bg-blue-500 text-white px-2 py-0.5 text-[10px] font-mono font-bold motion-safe:animate-pulse">
              {counts.unread}
            </span>
          ) : (
            <span className="rounded-full bg-[var(--surface)] border border-[var(--border)] px-2 py-0.5 text-[10px] font-mono text-[var(--text-muted)]">
              0
            </span>
          )}
        </button>

        <button
          type="button"
          role="tab"
          aria-selected={filter === 'read'}
          onClick={() => changeFilter('read')}
          title={dict.app.notifications.read}
          aria-label={dict.app.notifications.read}
          className={`flex items-center gap-2 border-b-2 px-4 py-3 text-xs font-extrabold transition-all ${
            filter === 'read'
              ? 'border-[var(--primary)] text-[var(--primary)]'
              : 'border-transparent text-[var(--text-muted)] hover:text-[var(--text-primary)]'
          }`}
        >
          <span>{dict.app.notifications.read}</span>
          <span className="rounded-full bg-[var(--surface)] border border-[var(--border)] px-2 py-0.5 text-[10px] font-mono text-[var(--text-muted)]">
            {counts.read}
          </span>
        </button>
      </div>

      <Card className="overflow-hidden p-0">
        <ServerDataTable
          ajaxUrl="/notifications/data"
          columns={columns}
          filters={{ tab: filter }}
          locale={locale}
          order={[[2, 'desc']]}
          pageLength={25}
          reloadToken={`${filter}:${counts.unread}:${counts.read}`}
          slots={slots}
          tableId="notifications-table"
        />
      </Card>
    </AppLayout>
  );
}
