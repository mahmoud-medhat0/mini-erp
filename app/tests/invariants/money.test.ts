import { describe, it, expect } from 'vitest';
import { money, fromMajorString, add, subtract, allocate, formatMoney, sumMoney, zero } from '../../src/core/money';
import { CurrencyMismatchError } from '../../src/core/errors';

describe('Money — exactness (no float)', () => {
  it('parses decimal strings to exact minor units', () => {
    expect(fromMajorString('1234.50', 'EGP').amountMinor).toBe(123450n);
    expect(fromMajorString('0.01', 'EGP').amountMinor).toBe(1n);
    expect(fromMajorString('-12500.00', 'EGP').amountMinor).toBe(-1250000n);
    expect(fromMajorString('100', 'KWD').amountMinor).toBe(100000n); // 3 decimals
  });

  it('rejects excess precision instead of silently rounding', () => {
    expect(() => fromMajorString('1.005', 'EGP')).toThrow();
  });

  it('adds/subtracts exactly and never drifts (0.1 + 0.2 problem)', () => {
    const a = fromMajorString('0.10', 'EGP');
    const b = fromMajorString('0.20', 'EGP');
    expect(add(a, b).amountMinor).toBe(30n);
    expect(formatMoney(add(a, b))).toBe('0.30');
  });

  it('blocks cross-currency arithmetic', () => {
    expect(() => add(money(1n, 'EGP'), money(1n, 'USD'))).toThrow(CurrencyMismatchError);
  });

  it('formats with thousands separators and sign', () => {
    expect(formatMoney(money(128450000n, 'EGP'))).toBe('1,284,500.00');
    expect(formatMoney(money(-1250000n, 'EGP'))).toBe('-12,500.00');
  });
});

describe('Money.allocate — sum is EXACT (no rounding loss)', () => {
  it('distributes a non-divisible amount so parts sum to the original', () => {
    const total = money(100n, 'EGP'); // 1.00 EGP over 3 ways
    const parts = allocate(total, [1, 1, 1]);
    expect(parts.map((p) => p.amountMinor)).toEqual([34n, 33n, 33n]);
    expect(sumMoney(parts, 'EGP').amountMinor).toBe(total.amountMinor);
  });

  it('weighted allocation (landed cost) is exact', () => {
    const total = money(1000n, 'EGP');
    const parts = allocate(total, [5, 3, 2]);
    expect(sumMoney(parts, 'EGP').amountMinor).toBe(1000n);
  });

  it('handles negatives (credit notes) exactly', () => {
    const total = money(-100n, 'EGP');
    const parts = allocate(total, [1, 1, 1]);
    expect(sumMoney(parts, 'EGP').amountMinor).toBe(-100n);
  });

  it('property: random amounts/weights always sum exactly', () => {
    for (let t = 0; t < 500; t++) {
      const amt = BigInt(Math.floor(Math.random() * 2_000_000) - 1_000_000);
      const n = 1 + Math.floor(Math.random() * 6);
      const weights = Array.from({ length: n }, () => 1 + Math.floor(Math.random() * 9));
      const parts = allocate(money(amt, 'EGP'), weights);
      expect(sumMoney(parts, 'EGP').amountMinor).toBe(amt);
    }
  });
});
