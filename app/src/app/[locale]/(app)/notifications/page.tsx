import { getTranslations } from 'next-intl/server';
import { requireAuth } from '@/core/auth/server';
import { PrismaNotificationRepository } from '@/core/db/repositories/notificationRepo';
import { NotificationService } from '@/core/notifications/service';
import { tenantFromSession } from '@/core/tenant/context';
import { Button } from '@/ui/Button';
import { Card, EmptyState, PageHeader } from '@/ui/primitives';
import { StatusBadge } from '@/ui/StatusBadge';
import { markNotificationRead } from './actions';

export default async function NotificationsPage({ params }: { params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  const session = await requireAuth(locale);
  const t = await getTranslations();
  const notifications = await new NotificationService(new PrismaNotificationRepository()).list(tenantFromSession(session));
  const action = markNotificationRead.bind(null, locale);
  const formatter = new Intl.DateTimeFormat(locale, { dateStyle: 'medium', timeStyle: 'short' });

  return (
    <div style={{ maxWidth: 840 }}>
      <PageHeader title={t('notifications.title')} />
      {notifications.length === 0 ? (
        <EmptyState title={t('notifications.none')} />
      ) : (
        <Card style={{ padding: 0 }}>
          <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 'var(--text-sm)' }}>
            <thead>
              <tr>
                <th style={thStyle}>{t('notifications.title')}</th>
                <th style={thStyle}>{t('notifications.target')}</th>
                <th style={thStyle}>{t('status.posted')}</th>
                <th style={thStyle} />
              </tr>
            </thead>
            <tbody>
              {notifications.map((notification) => (
                <tr key={notification.id} style={{ borderBlockStart: '1px solid var(--border)' }}>
                  <td style={{ padding: '10px var(--space-4)' }}>
                    <strong>{notification.type.replaceAll('_', ' ')}</strong>
                    <div style={{ color: 'var(--text-muted)', fontSize: 'var(--text-xs)' }}>{formatter.format(notification.at)}</div>
                  </td>
                  <td className="code" style={{ padding: '10px var(--space-4)' }}>{notification.targetRef}</td>
                  <td style={{ padding: '10px var(--space-4)' }}>
                    <StatusBadge tone={notification.read ? 'paid' : 'pending'}>
                      {notification.read ? t('notifications.read') : t('notifications.unread')}
                    </StatusBadge>
                  </td>
                  <td style={{ padding: '10px var(--space-4)', textAlign: 'end' }}>
                    {!notification.read && (
                      <form action={action}>
                        <input type="hidden" name="id" value={notification.id} />
                        <Button type="submit" variant="secondary">{t('notifications.markRead')}</Button>
                      </form>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </Card>
      )}
    </div>
  );
}

const thStyle = {
  textAlign: 'start' as const,
  padding: '10px var(--space-4)',
  color: 'var(--text-secondary)',
};
