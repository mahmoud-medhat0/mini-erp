/**
 * DB-backed integration test for numbering atomicity. Skipped unless DATABASE_URL
 * is set (CI provisions Postgres). Proves the real repository's INSERT ... ON
 * CONFLICT DO UPDATE RETURNING yields unique, contiguous values under concurrency.
 */
import { describe, it, expect, beforeAll, afterAll } from 'vitest';

const HAS_DB = !!process.env.DATABASE_URL;
const d = HAS_DB ? describe : describe.skip;

d('Numbering repository (Postgres) — concurrency', () => {
  // Imports are dynamic so the suite loads without @prisma/client when skipped.
  // Typed as unknown here to avoid importing Prisma types at collection time.
  let repo: { nextValue(companyId: string, key: string): Promise<number> };
  let prisma: { $disconnect(): Promise<void> };

  beforeAll(async () => {
    prisma = (await import('../../src/core/db/prisma')).prisma as unknown as { $disconnect(): Promise<void> };
    const mod = await import('../../src/core/db/repositories/numberSequenceRepo');
    repo = new mod.PrismaNumberSequenceRepository();
  });

  afterAll(async () => {
    if (HAS_DB) await prisma.$disconnect();
  });

  it('1000 concurrent nextValue calls are unique and contiguous', async () => {
    const key = `TEST-${Date.now()}`;
    const companyId = 'itest-company';
    const results = await Promise.all(Array.from({ length: 1000 }, () => repo.nextValue(companyId, key)));
    const set = new Set(results);
    expect(set.size).toBe(1000);
    expect(Math.min(...results)).toBe(1);
    expect(Math.max(...results)).toBe(1000);
  });
});
