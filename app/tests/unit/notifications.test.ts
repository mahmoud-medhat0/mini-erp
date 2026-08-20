import { describe, it, expect } from 'vitest';
import { NotificationService, NotificationRepository, Notification } from '../../src/core/notifications/service';
import { CrossTenantError } from '../../src/core/errors';

const ctx = { userId: 'u1', companyId: 'c1' };

class MemNotif implements NotificationRepository {
  rows: Notification[] = [];
  async create(n: Omit<Notification, 'id'>) {
    const row = { ...n, id: `n${this.rows.length + 1}` };
    this.rows.push(row);
    return row;
  }
  async markRead(companyId: string, userId: string, id: string) {
    const n = this.rows.find((r) => r.id === id && r.companyId === companyId && r.userId === userId);
    if (!n) return null;
    n.read = true;
    return n;
  }
  async listForUser(companyId: string, userId: string, opts?: { unreadOnly?: boolean }) {
    return this.rows.filter((r) => r.companyId === companyId && r.userId === userId && (!opts?.unreadOnly || !r.read));
  }
}

describe('Notifications — create/list/read + company scope', () => {
  it('creates, lists unread, marks read; blocks cross-company mark', async () => {
    const svc = new NotificationService(new MemNotif(), () => new Date('2026-08-20T00:00:00Z'));
    const n = await svc.notify(ctx, { userId: 'u1', type: 'approval_pending', targetRef: 'inv1' });
    expect((await svc.list(ctx, { unreadOnly: true })).length).toBe(1);
    await svc.markRead(ctx, n.id);
    expect((await svc.list(ctx, { unreadOnly: true })).length).toBe(0);
    await expect(svc.markRead({ userId: 'z', companyId: 'c2' }, n.id)).rejects.toBeInstanceOf(CrossTenantError);
  });
});
