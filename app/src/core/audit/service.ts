/**
 * Audit service — append-only. The repository exposes only `append` and reads;
 * there is no update/delete path, so historical audit entries are immutable by
 * construction. Sensitive fields are redacted before persistence.
 */
import type { AuditAction, AuditRecord } from './index';
import { diffFields } from './index';
import type { TenantContext } from '../tenant/context';

/** Repository contract: append-only + read. No update/delete on purpose. */
export interface AuditRepository {
  append(record: AuditRecord, tx?: unknown): Promise<void>;
  list(companyId: string, filter?: { entityType?: string; entityId?: string }): Promise<AuditRecord[]>;
}

const REDACT = ['password', 'passwordHash', 'token', 'secret', 'authorization'];

export interface AuditInput {
  action: AuditAction;
  entityType: string;
  entityId: string;
  before?: Record<string, unknown>;
  after?: Record<string, unknown>;
  reason?: string;
  requestId?: string;
  ip?: string;
  device?: string;
}

export class AuditService {
  constructor(
    private readonly repo: AuditRepository,
    private readonly now: () => Date = () => new Date(),
  ) {}

  async record(ctx: TenantContext, input: AuditInput, tx?: unknown): Promise<void> {
    let before = input.before;
    let after = input.after;
    if (before && after) {
      const d = diffFields(before, after, REDACT);
      before = d.before as Record<string, unknown>;
      after = d.after as Record<string, unknown>;
    } else {
      before = redact(before);
      after = redact(after);
    }
    const record: AuditRecord = {
      companyId: ctx.companyId,
      branchId: ctx.branchId ?? null,
      actorUserId: ctx.userId,
      action: input.action,
      entityType: input.entityType,
      entityId: input.entityId,
      before,
      after,
      reason: input.reason ?? null,
      requestId: input.requestId ?? null,
      ip: input.ip ?? null,
      device: input.device ?? null,
      at: this.now(),
    };
    await this.repo.append(record, tx);
  }
}

function redact(obj?: Record<string, unknown>): Record<string, unknown> | undefined {
  if (!obj) return obj;
  const out: Record<string, unknown> = {};
  for (const [k, v] of Object.entries(obj)) {
    out[k] = REDACT.includes(k) ? '[redacted]' : v;
  }
  return out;
}
