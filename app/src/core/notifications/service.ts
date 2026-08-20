/**
 * In-app notification foundation. Persisted per user + company; read/unread
 * tracked. External delivery (email/SMS/push) is a separate adapter interface —
 * not faked here.
 */
import { CrossTenantError } from '../errors';
import type { TenantContext } from '../tenant/context';

export type NotificationType =
  | 'invoice_overdue'
  | 'payment_due'
  | 'low_stock'
  | 'rental_ending'
  | 'rental_overdue'
  | 'approval_pending'
  | 'reconciliation_pending'
  | 'budget_exceeded'
  | 'tax_deadline'
  | 'recurring_due'
  | 'cheque_due'
  | 'cheque_returned'
  | 'system';

export interface Notification {
  id: string;
  companyId: string;
  userId: string;
  type: NotificationType;
  targetRef: string;
  read: boolean;
  at: Date;
}

export interface NotificationRepository {
  create(n: Omit<Notification, 'id'>): Promise<Notification>;
  markRead(companyId: string, userId: string, id: string): Promise<Notification | null>;
  listForUser(companyId: string, userId: string, opts?: { unreadOnly?: boolean }): Promise<Notification[]>;
}

/** Optional external delivery channel — real adapters implement this. */
export interface NotificationChannel {
  send(n: Notification): Promise<void>;
}

export class NotificationService {
  constructor(
    private readonly repo: NotificationRepository,
    private readonly now: () => Date = () => new Date(),
    private readonly channels: NotificationChannel[] = [],
  ) {}

  async notify(
    ctx: TenantContext,
    input: { userId: string; type: NotificationType; targetRef: string },
  ): Promise<Notification> {
    const n = await this.repo.create({
      companyId: ctx.companyId,
      userId: input.userId,
      type: input.type,
      targetRef: input.targetRef,
      read: false,
      at: this.now(),
    });
    await Promise.all(this.channels.map((c) => c.send(n).catch(() => undefined)));
    return n;
  }

  async list(ctx: TenantContext, opts?: { unreadOnly?: boolean }): Promise<Notification[]> {
    return this.repo.listForUser(ctx.companyId, ctx.userId, opts);
  }

  async markRead(ctx: TenantContext, id: string): Promise<Notification> {
    const n = await this.repo.markRead(ctx.companyId, ctx.userId, id);
    if (!n) throw new CrossTenantError();
    return n;
  }
}
