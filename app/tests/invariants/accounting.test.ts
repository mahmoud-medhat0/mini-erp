import { describe, it, expect } from 'vitest';
import { assertBalanced, isBalanced, sumDebits, sumCredits, postingIdempotencyKey, DraftEntry } from '../../src/core/accounting-kernel';
import { UnbalancedEntryError } from '../../src/core/errors';

function entry(lines: { d?: bigint; c?: bigint; acc?: string }[]): DraftEntry {
  return {
    sourceType: 'SalesInvoice',
    sourceId: 'inv-1',
    date: new Date('2026-08-20'),
    currency: 'EGP',
    fxRate: 1_000_000n,
    lines: lines.map((l, i) => ({ accountId: l.acc ?? `acc-${i}`, debitMinor: l.d ?? 0n, creditMinor: l.c ?? 0n })),
  };
}

describe('Accounting invariant — Σ debit = Σ credit', () => {
  it('accepts a balanced sales-invoice entry (AR / Revenue + VAT)', () => {
    // Dr AR 114.00 ; Cr Revenue 100.00 ; Cr Output VAT 14.00
    const e = entry([{ d: 11400n }, { c: 10000n }, { c: 1400n }]);
    expect(() => assertBalanced(e)).not.toThrow();
    expect(sumDebits(e.lines)).toBe(sumCredits(e.lines));
  });

  it('rejects an unbalanced entry', () => {
    const e = entry([{ d: 11400n }, { c: 10000n }]);
    expect(() => assertBalanced(e)).toThrow(UnbalancedEntryError);
    expect(isBalanced(e)).toBe(false);
  });

  it('rejects a line with both debit and credit non-zero', () => {
    const e = entry([{ d: 100n, c: 100n }, { c: 100n }]);
    expect(() => assertBalanced(e)).toThrow(UnbalancedEntryError);
  });

  it('rejects a single-line entry', () => {
    const e = entry([{ d: 100n }]);
    expect(() => assertBalanced(e)).toThrow(UnbalancedEntryError);
  });

  it('idempotency key is deterministic per source+action', () => {
    expect(postingIdempotencyKey('SalesInvoice', 'inv-1')).toBe('SalesInvoice:inv-1:post');
    expect(postingIdempotencyKey('SalesInvoice', 'inv-1', 'reverse')).toBe('SalesInvoice:inv-1:reverse');
  });
});
