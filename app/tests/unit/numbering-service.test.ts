import { describe, it, expect } from 'vitest';
import { NumberingConfigService, NumberSequenceRepository, validateConfig } from '../../src/core/numbering/service';
import { SequenceConfig } from '../../src/core/numbering';
import { ValidationError } from '../../src/core/errors';

const invCfg: SequenceConfig = {
  docType: 'INV',
  prefix: 'INV',
  includeYear: true,
  includeBranch: false,
  padding: 5,
  resetPolicy: 'yearly',
};

class MemoryRepo implements NumberSequenceRepository {
  configs = new Map<string, SequenceConfig>();
  counters = new Map<string, number>();
  async getConfig(companyId: string, docType: string) {
    return this.configs.get(`${companyId}:${docType}`) ?? null;
  }
  async upsertConfig(companyId: string, cfg: SequenceConfig) {
    this.configs.set(`${companyId}:${cfg.docType}`, cfg);
  }
  async listConfigs(companyId: string) {
    return [...this.configs.entries()].filter(([k]) => k.startsWith(`${companyId}:`)).map(([, v]) => v);
  }
  async nextValue(companyId: string, key: string) {
    await Promise.resolve();
    const k = `${companyId}:${key}`;
    const n = (this.counters.get(k) ?? 0) + 1;
    this.counters.set(k, n);
    return n;
  }
}

const ctx = { userId: 'u1', companyId: 'c1' };

describe('Numbering config service', () => {
  it('validates config (padding, prefix, reset policy)', () => {
    expect(() => validateConfig({ ...invCfg, padding: 0 })).toThrow(ValidationError);
    expect(() => validateConfig({ ...invCfg, prefix: '' })).toThrow(ValidationError);
    expect(() => validateConfig(invCfg)).not.toThrow();
  });

  it('persists config and previews without consuming', async () => {
    const repo = new MemoryRepo();
    const svc = new NumberingConfigService(repo);
    await svc.saveConfig(ctx, invCfg);
    expect(await svc.preview(ctx, 'INV', { year: 2026 })).toBe('INV-2026-00001');
    expect(await svc.preview(ctx, 'INV', { year: 2026 })).toBe('INV-2026-00001'); // preview does not increment
    expect((await svc.listConfigs(ctx)).length).toBe(1);
  });

  it('allocation is concurrency-safe: 1000 parallel -> unique & contiguous', async () => {
    const repo = new MemoryRepo();
    const svc = new NumberingConfigService(repo);
    await svc.saveConfig(ctx, invCfg);
    const nums = await Promise.all(Array.from({ length: 1000 }, () => svc.allocate(ctx, 'INV', { year: 2026 })));
    expect(new Set(nums).size).toBe(1000);
    const seqs = nums.map((n) => Number(n.split('-')[2])).sort((a, b) => a - b);
    expect(seqs[0]).toBe(1);
    expect(seqs[999]).toBe(1000);
  });

  it('company isolation: separate counters per company', async () => {
    const repo = new MemoryRepo();
    const svc = new NumberingConfigService(repo);
    await svc.saveConfig(ctx, invCfg);
    await svc.saveConfig({ userId: 'u2', companyId: 'c2' }, invCfg);
    expect(await svc.allocate(ctx, 'INV', { year: 2026 })).toBe('INV-2026-00001');
    expect(await svc.allocate({ userId: 'u2', companyId: 'c2' }, 'INV', { year: 2026 })).toBe('INV-2026-00001');
  });
});
