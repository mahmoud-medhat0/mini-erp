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
  let repo: { nextValue(companyId: string, key: string): Promise<number> };
  let prisma: typeof import('../../src/core/db/prisma').prisma;

  beforeAll(async () => {
    prisma = (await import('../../src/core/db/prisma')).prisma;
    const mod = await import('../../src/core/db/repositories/numberSequenceRepo');
    repo = new mod.PrismaNumberSequenceRepository();
  });

  afterAll(async () => {
    if (HAS_DB) await prisma.$disconnect();
  });

  it('1000 concurrent nextValue calls are unique and contiguous', async () => {
    const key = `TEST-${Date.now()}`;
    const company = await prisma.company.create({
      data: {
        nameEn: 'Numbering Test Co',
        nameAr: 'Numbering Test Co',
        baseCurrency: 'EGP',
        settingsJson: {},
      },
    });
    const companyId = company.id;
    const results = await Promise.all(Array.from({ length: 1000 }, () => repo.nextValue(companyId, key)));
    const set = new Set(results);
    expect(set.size).toBe(1000);
    expect(Math.min(...results)).toBe(1);
    expect(Math.max(...results)).toBe(1000);
  });
});
