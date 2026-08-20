import { describe, it, expect } from 'vitest';
import { JobRunner, Queue, IdempotencyStore, backoffMs } from '../../src/core/jobs/runner';

class MemQueue implements Queue {
  published: { name: string; payload: unknown }[] = [];
  async publish(name: string, payload: unknown) {
    this.published.push({ name, payload });
  }
}
class MemIdem implements IdempotencyStore {
  done = new Set<string>();
  async wasProcessed(k: string) {
    return this.done.has(k);
  }
  async markProcessed(k: string) {
    this.done.add(k);
  }
}

describe('Jobs — idempotency, retry backoff, failure handling', () => {
  it('runs a job once and skips re-delivery with the same idempotency key', async () => {
    const runner = new JobRunner(new MemQueue(), new MemIdem());
    let runs = 0;
    runner.register({ name: 'depreciation', handler: async () => { runs += 1; } });
    const r1 = await runner.execute('depreciation', {}, { jobId: 'j1', attempt: 1, idempotencyKey: '2026-08' });
    const r2 = await runner.execute('depreciation', {}, { jobId: 'j2', attempt: 1, idempotencyKey: '2026-08' });
    expect([r1, r2]).toEqual(['done', 'skipped']);
    expect(runs).toBe(1);
  });

  it('does not mark processed when the handler throws (queue will retry)', async () => {
    const idem = new MemIdem();
    const runner = new JobRunner(new MemQueue(), idem);
    runner.register({ name: 'flaky', handler: async () => { throw new Error('boom'); } });
    await expect(runner.execute('flaky', {}, { jobId: 'j1', attempt: 1, idempotencyKey: 'k1' })).rejects.toThrow('boom');
    expect(await idem.wasProcessed('k1')).toBe(false);
  });

  it('rejects duplicate registration and unknown job', async () => {
    const runner = new JobRunner(new MemQueue(), new MemIdem());
    runner.register({ name: 'x', handler: async () => {} });
    expect(() => runner.register({ name: 'x', handler: async () => {} })).toThrow();
    await expect(runner.enqueue('nope', {})).rejects.toThrow('Unknown job');
  });

  it('exponential backoff grows and caps', () => {
    expect(backoffMs(1, 1000)).toBe(1000);
    expect(backoffMs(2, 1000)).toBe(2000);
    expect(backoffMs(3, 1000)).toBe(4000);
    expect(backoffMs(50, 1000)).toBe(5 * 60_000);
  });
});
