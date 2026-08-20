import { describe, it, expect } from 'vitest';
import { AuditService, AuditRepository } from '../../src/core/audit/service';
import { AuditRecord } from '../../src/core/audit';
import { assertSameCompany, tenantFromSession } from '../../src/core/tenant/context';
import { CrossTenantError } from '../../src/core/errors';

class MemoryAudit implements AuditRepository {
  rows: AuditRecord[] = [];
  async append(r: AuditRecord) {
    this.rows.push(r);
  }
  async list(companyId: string) {
    return this.rows.filter((r) => r.companyId === companyId);
  }
}

const ctx = { userId: 'u1', companyId: 'c1', branchId: 'b1' };

describe('Audit — append-only + redaction', () => {
  it('appends a record with actor/company/action and a timestamp', async () => {
    const repo = new MemoryAudit();
    const svc = new AuditService(repo, () => new Date('2026-08-20T00:00:00Z'));
    await svc.record(ctx, { action: 'post', entityType: 'JournalEntry', entityId: 'je1' });
    expect(repo.rows).toHaveLength(1);
    expect(repo.rows[0]).toMatchObject({ companyId: 'c1', actorUserId: 'u1', action: 'post', entityType: 'JournalEntry' });
    expect(repo.rows[0].at.toISOString()).toBe('2026-08-20T00:00:00.000Z');
  });

  it('redacts sensitive fields and diffs only changed fields', async () => {
    const repo = new MemoryAudit();
    const svc = new AuditService(repo);
    await svc.record(ctx, {
      action: 'update',
      entityType: 'User',
      entityId: 'u9',
      before: { name: 'A', passwordHash: 'H:old', email: 'x@y' },
      after: { name: 'B', passwordHash: 'H:new', email: 'x@y' },
    });
    const rec = repo.rows[0];
    // passwordHash redacted out; email unchanged so excluded; only name diffed
    expect(rec.after).toEqual({ name: 'B' });
    expect(JSON.stringify(rec)).not.toContain('H:new');
  });

  it('repository exposes no update/delete (append-only by construction)', () => {
    const repo = new MemoryAudit() as unknown as Record<string, unknown>;
    expect(repo['update']).toBeUndefined();
    expect(repo['delete']).toBeUndefined();
  });
});

describe('Tenant — company isolation', () => {
  it('derives context from session, not the browser', () => {
    const t = tenantFromSession({ userId: 'u1', email: 'a', companyId: 'c1', branchId: 'b1', grants: [] });
    expect(t).toEqual({ userId: 'u1', companyId: 'c1', branchId: 'b1' });
  });
  it('rejects cross-company entity access', () => {
    expect(() => assertSameCompany({ userId: 'u1', companyId: 'c1' }, 'c2')).toThrow(CrossTenantError);
    expect(() => assertSameCompany({ userId: 'u1', companyId: 'c1' }, 'c1')).not.toThrow();
  });
});
