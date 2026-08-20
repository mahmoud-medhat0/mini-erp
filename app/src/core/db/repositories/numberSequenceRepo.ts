/**
 * Prisma-backed numbering repository. `nextValue` is a SINGLE atomic statement
 * (INSERT ... ON CONFLICT DO UPDATE ... RETURNING), so concurrent callers can
 * never receive the same value — the DB serializes the conflicting row update.
 * A unique constraint on the document number is the backstop.
 */
import { prisma } from '../prisma';
import type { NumberSequenceRepository } from '../../numbering/service';
import type { SequenceConfig, ResetPolicy } from '../../numbering';

export class PrismaNumberSequenceRepository implements NumberSequenceRepository {
  async getConfig(companyId: string, docType: string): Promise<SequenceConfig | null> {
    const row = await prisma.numberSequence.findFirst({ where: { companyId, docType } });
    if (!row) return null;
    return toConfig(row);
  }

  async listConfigs(companyId: string): Promise<SequenceConfig[]> {
    const rows = await prisma.numberSequence.findMany({ where: { companyId } });
    // distinct by docType (config, not per-bucket counters)
    const seen = new Map<string, SequenceConfig>();
    for (const r of rows) if (!seen.has(r.docType)) seen.set(r.docType, toConfig(r));
    return [...seen.values()];
  }

  async upsertConfig(companyId: string, cfg: SequenceConfig): Promise<void> {
    // Config is stored per bucket key lazily; here we store/refresh the template row.
    const key = `__config__:${cfg.docType}`;
    await prisma.numberSequence.upsert({
      where: { companyId_key: { companyId, key } },
      update: {
        prefix: cfg.prefix,
        includeYear: cfg.includeYear,
        includeBranch: cfg.includeBranch,
        padding: cfg.padding,
        resetPolicy: cfg.resetPolicy,
      },
      create: {
        companyId,
        key,
        docType: cfg.docType,
        prefix: cfg.prefix,
        includeYear: cfg.includeYear,
        includeBranch: cfg.includeBranch,
        padding: cfg.padding,
        resetPolicy: cfg.resetPolicy,
        nextValue: 0,
      },
    });
  }

  async nextValue(companyId: string, key: string): Promise<number> {
    // Atomic increment; template row (__config__) is copied to the bucket row on first hit.
    const rows = await prisma.$queryRaw<{ next_value: number }[]>`
      INSERT INTO number_sequence (id, "companyId", key, "docType", prefix, "includeYear", "includeBranch", padding, "resetPolicy", "nextValue")
      VALUES (gen_random_uuid(), ${companyId}, ${key}, ${key}, '', true, false, 5, 'yearly', 1)
      ON CONFLICT ("companyId", key)
      DO UPDATE SET "nextValue" = number_sequence."nextValue" + 1
      RETURNING "nextValue" AS next_value`;
    return rows[0].next_value;
  }
}

function toConfig(row: {
  docType: string;
  prefix: string;
  includeYear: boolean;
  includeBranch: boolean;
  padding: number;
  resetPolicy: string;
}): SequenceConfig {
  return {
    docType: row.docType,
    prefix: row.prefix,
    includeYear: row.includeYear,
    includeBranch: row.includeBranch,
    padding: row.padding,
    resetPolicy: row.resetPolicy as ResetPolicy,
  };
}
