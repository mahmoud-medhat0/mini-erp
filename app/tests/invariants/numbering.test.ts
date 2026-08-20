import { describe, it, expect } from 'vitest';
import { formatDocNumber, sequenceKey, NumberingService, SequenceStore, SequenceConfig } from '../../src/core/numbering';

const invCfg: SequenceConfig = {
  docType: 'INV',
  prefix: 'INV',
  includeYear: true,
  includeBranch: false,
  padding: 5,
  resetPolicy: 'yearly',
};

describe('Numbering — format', () => {
  it('formats INV-2026-00001', () => {
    expect(formatDocNumber(invCfg, { year: 2026 }, 1)).toBe('INV-2026-00001');
    expect(formatDocNumber(invCfg, { year: 2026 }, 128)).toBe('INV-2026-00128');
  });
  it('includes branch when configured', () => {
    const cfg = { ...invCfg, includeBranch: true };
    expect(formatDocNumber(cfg, { year: 2026, branchCode: 'CAI' }, 7)).toBe('INV-2026-CAI-00007');
  });
  it('sequence key resets yearly vs monthly', () => {
    expect(sequenceKey(invCfg, { year: 2026 })).toBe('INV|2026');
    expect(sequenceKey({ ...invCfg, resetPolicy: 'monthly' }, { year: 2026, month: 3 })).toBe('INV|2026-03');
  });
});

/** In-memory atomic store — models the DB's single-statement increment. */
class MemoryStore implements SequenceStore {
  private counters = new Map<string, number>();
  async nextValue(key: string): Promise<number> {
    // simulate async DB roundtrip; JS event loop keeps map ops atomic like the DB row-lock
    await Promise.resolve();
    const next = (this.counters.get(key) ?? 0) + 1;
    this.counters.set(key, next);
    return next;
  }
}

describe('Numbering — concurrency safety (no duplicates)', () => {
  it('1000 concurrent allocations yield unique, contiguous numbers', async () => {
    const svc = new NumberingService(new MemoryStore());
    const results = await Promise.all(
      Array.from({ length: 1000 }, () => svc.allocate(invCfg, { year: 2026 })),
    );
    const set = new Set(results);
    expect(set.size).toBe(1000); // no duplicates
    // contiguous 1..1000
    const seqs = results.map((r) => Number(r.split('-')[2])).sort((a, b) => a - b);
    expect(seqs[0]).toBe(1);
    expect(seqs[999]).toBe(1000);
  });
});
