/**
 * Prisma-backed notification repository. Every query is scoped by company +
 * user; mark-read returns null when the row is outside that scope.
 */
import { prisma } from '../prisma';
import type { Notification, NotificationRepository, NotificationType } from '../../notifications/service';

export class PrismaNotificationRepository implements NotificationRepository {
  async create(n: Omit<Notification, 'id'>): Promise<Notification> {
    const row = await prisma.notification.create({
      data: {
        companyId: n.companyId,
        userId: n.userId,
        type: n.type,
        targetRef: n.targetRef,
        read: n.read,
        at: n.at,
      },
    });
    return toNotification(row);
  }

  async markRead(companyId: string, userId: string, id: string): Promise<Notification | null> {
    const row = await prisma.notification.findFirst({ where: { id, companyId, userId } });
    if (!row) return null;
    return toNotification(await prisma.notification.update({ where: { id: row.id }, data: { read: true } }));
  }

  async listForUser(companyId: string, userId: string, opts?: { unreadOnly?: boolean }): Promise<Notification[]> {
    const rows = await prisma.notification.findMany({
      where: { companyId, userId, ...(opts?.unreadOnly ? { read: false } : {}) },
      orderBy: { at: 'desc' },
      take: 100,
    });
    return rows.map(toNotification);
  }
}

function toNotification(row: {
  id: string;
  companyId: string;
  userId: string;
  type: string;
  targetRef: string;
  read: boolean;
  at: Date;
}): Notification {
  return {
    id: row.id,
    companyId: row.companyId,
    userId: row.userId,
    type: row.type as NotificationType,
    targetRef: row.targetRef,
    read: row.read,
    at: row.at,
  };
}
