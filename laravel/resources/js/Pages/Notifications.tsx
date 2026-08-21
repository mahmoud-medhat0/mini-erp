import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import AppLayout from '../Components/AppLayout';
import { EmptyState, PageHeader, StatusBadge, tableClasses } from '../Components/Primitives';
import { getDictionary } from '../lib/i18n';
import type { SharedPageProps } from '../Types/page';

type NotificationRow = {
  id: string;
  type: string;
  targetRef: string;
  read: boolean;
  at: string;
  companyName: string;
};

type NotificationsProps = SharedPageProps & {
  items: NotificationRow[];
};

function MarkReadButton({ id, label }: { id: string; label: string }) {
  const { post, processing } = useForm({});

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    post(`/notifications/${id}/read`, { preserveScroll: true });
  }

  return (
    <form onSubmit={submit}>
      <button
        type="submit"
        disabled={processing}
        className="rounded-md border border-[var(--border)] bg-[var(--surface)] px-3 py-1.5 text-xs font-semibold text-[var(--text-secondary)] disabled:cursor-not-allowed disabled:opacity-60"
      >
        {label}
      </button>
    </form>
  );
}

export default function Notifications({ items, locale }: NotificationsProps) {
  const dict = getDictionary(locale);
  const formatter = new Intl.DateTimeFormat(locale === 'ar' ? 'ar-EG' : 'en-US', {
    dateStyle: 'medium',
    timeStyle: 'short',
  });

  return (
    <AppLayout active="notifications">
      <Head title={dict.app.nav.notifications} />
      <PageHeader title={dict.app.nav.notifications} description={dict.app.notifications.description} />

      {items.length === 0 ? (
        <EmptyState title={dict.app.notifications.empty} />
      ) : (
        <div className={tableClasses.wrap}>
          <table className={tableClasses.table}>
            <thead>
              <tr>
                <th className={tableClasses.th}>{dict.app.fields.notification}</th>
                <th className={tableClasses.th}>{dict.app.fields.company}</th>
                <th className={tableClasses.th}>{dict.app.fields.target}</th>
                <th className={tableClasses.th}>{dict.app.fields.status}</th>
                <th className={tableClasses.th} />
              </tr>
            </thead>
            <tbody>
              {items.map((item) => (
                <tr key={item.id}>
                  <td className={tableClasses.td}>
                    <strong>{item.type.replaceAll('_', ' ')}</strong>
                    <div className="mt-1 text-xs text-[var(--text-muted)]">{formatter.format(new Date(item.at))}</div>
                  </td>
                  <td className={tableClasses.td}>{item.companyName}</td>
                  <td className={`${tableClasses.td} code`}>{item.targetRef}</td>
                  <td className={tableClasses.td}>
                    <StatusBadge tone={item.read ? 'muted' : 'ok'}>
                      {item.read ? dict.app.notifications.read : dict.app.notifications.unread}
                    </StatusBadge>
                  </td>
                  <td className={tableClasses.td}>
                    {!item.read ? <MarkReadButton id={item.id} label={dict.app.notifications.markRead} /> : null}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </AppLayout>
  );
}
