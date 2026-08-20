/**
 * Prisma-backed audit repository — append + read only. No update/delete exists,
 * so historical audit rows are immutable by construction.
 */
import { prisma, Tx } from '../prisma';
import type { AuditRepository } from '../../audit/service';
import type { AuditRecord } from '../../audit';

export class PrismaAuditRepository implements AuditRepository {
  async append(record: AuditRecord, tx?: unknown): Promise<void> {
    const client = (tx as Tx) ?? prisma;
    await client.auditLog.create({
      data: {
        companyId: record.companyId,
        branchId: record.branchId ?? null,
        actorId: record.actorUserId,
        action: record.action,
        entityType: record.entityType,
        entityId: record.entityId,
        beforeJson: (record.before as object) ?? undefined,
        afterJson: (record.after as object) ?? undefined,
        reason: record.reason ?? null,
        requestId: record.requestId ?? null,
        ip: record.ip ?? null,
        device: record.device ?? null,
        at: record.at,
      },
    });
  }

  async list(companyId: string, filter?: { entityType?: string; entityId?: string }): Promise<AuditRecord[]> {
    const rows = await prisma.auditLog.findMany({
      where: { companyId, entityType: filter?.entityType, entityId: filter?.entityId },
      orderBy: { at: 'desc' },
      take: 500,
    });
    return rows.map((r) => ({
      companyId: r.companyId,
      branchId: r.branchId,
      actorUserId: r.actorId,
      action: r.action as AuditRecord['action'],
      entityType: r.entityType,
      entityId: r.entityId,
      before: r.beforeJson,
      after: r.afterJson,
      reason: r.reason,
      requestId: r.requestId,
      ip: r.ip,
      device: r.device,
      at: r.at,
    }));
  }
}
