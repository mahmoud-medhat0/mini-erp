/**
 * Audit trail — append-only record of meaningful actions. Financial audit is
 * immutable. The writer is injected so services never touch the DB directly.
 */
export type AuditAction =
  | 'create'
  | 'update'
  | 'submit'
  | 'approve'
  | 'reject'
  | 'post'
  | 'reverse'
  | 'cancel'
  | 'delete'
  | 'reopen_period'
  | 'close_period'
  | 'override';

export interface AuditRecord {
  companyId: string;
  branchId?: string | null;
  actorUserId: string;
  action: AuditAction;
  entityType: string;
  entityId: string;
  before?: unknown;
  after?: unknown;
  reason?: string | null;
  requestId?: string | null;
  ip?: string | null;
  device?: string | null;
  at: Date;
}

export interface AuditWriter {
  /** Persists an audit record. Must be called inside the same transaction as the change. */
  write(record: AuditRecord, tx?: unknown): Promise<void>;
}

/** Shallow before/after diff of changed fields only (no secrets). */
export function diffFields<T extends Record<string, unknown>>(
  before: T,
  after: T,
  redact: string[] = [],
): { before: Partial<T>; after: Partial<T> } {
  const b: Partial<T> = {};
  const a: Partial<T> = {};
  const keys = new Set([...Object.keys(before), ...Object.keys(after)]);
  for (const k of keys) {
    if (redact.includes(k)) continue;
    if (before[k] !== after[k]) {
      (b as Record<string, unknown>)[k] = before[k];
      (a as Record<string, unknown>)[k] = after[k];
    }
  }
  return { before: b, after: a };
}
