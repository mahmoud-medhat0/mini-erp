/**
 * Money — exact integer-minor-unit value object. NEVER uses IEEE-754 floats for
 * authoritative math (BUSINESS_RULES BR-A8). Amount is a bigint in the currency's
 * minor unit; currency is an ISO code. All arithmetic is exact.
 */
import { CurrencyMismatchError } from '../errors';
import { currencyExponent } from '../currency';

export interface Money {
  readonly amountMinor: bigint;
  readonly currency: string;
}

export function money(amountMinor: bigint | number, currency: string): Money {
  return { amountMinor: typeof amountMinor === 'number' ? BigInt(Math.trunc(amountMinor)) : amountMinor, currency };
}

export function zero(currency: string): Money {
  return { amountMinor: 0n, currency };
}

/** Parse a decimal string like "1234.50" into minor units for the currency. Exact, no float. */
export function fromMajorString(value: string, currency: string): Money {
  const exp = currencyExponent(currency);
  const trimmed = value.trim();
  const neg = trimmed.startsWith('-');
  const unsigned = neg ? trimmed.slice(1) : trimmed;
  if (!/^\d+(\.\d+)?$/.test(unsigned)) throw new Error(`Invalid money string: ${value}`);
  const [intPart, fracRaw = ''] = unsigned.split('.');
  if (fracRaw.length > exp) {
    // more precision than the currency allows -> reject rather than silently round
    throw new Error(`Too many decimals for ${currency} (max ${exp}): ${value}`);
  }
  const frac = fracRaw.padEnd(exp, '0');
  const combined = (intPart + frac).replace(/^0+(?=\d)/, '');
  const magnitude = BigInt(combined === '' ? '0' : combined);
  return { amountMinor: neg ? -magnitude : magnitude, currency };
}

function assertSameCurrency(a: Money, b: Money): void {
  if (a.currency !== b.currency) throw new CurrencyMismatchError(a.currency, b.currency);
}

export function add(a: Money, b: Money): Money {
  assertSameCurrency(a, b);
  return { amountMinor: a.amountMinor + b.amountMinor, currency: a.currency };
}

export function subtract(a: Money, b: Money): Money {
  assertSameCurrency(a, b);
  return { amountMinor: a.amountMinor - b.amountMinor, currency: a.currency };
}

export function negate(a: Money): Money {
  return { amountMinor: -a.amountMinor, currency: a.currency };
}

export function sumMoney(items: Money[], currency: string): Money {
  return items.reduce((acc, m) => add(acc, m), zero(currency));
}

export function isZero(a: Money): boolean {
  return a.amountMinor === 0n;
}
export function isNegative(a: Money): boolean {
  return a.amountMinor < 0n;
}
export function compare(a: Money, b: Money): number {
  assertSameCurrency(a, b);
  return a.amountMinor < b.amountMinor ? -1 : a.amountMinor > b.amountMinor ? 1 : 0;
}
export function equals(a: Money, b: Money): boolean {
  return a.currency === b.currency && a.amountMinor === b.amountMinor;
}

/**
 * Allocate an amount across integer weights with NO rounding loss: the returned
 * parts always sum EXACTLY to the input (largest-remainder distribution).
 * Used for landed-cost, tax splits, and payment allocation.
 */
export function allocate(amount: Money, weights: number[]): Money[] {
  if (weights.length === 0) throw new Error('allocate: weights required');
  if (weights.some((w) => w < 0)) throw new Error('allocate: weights must be non-negative');
  const total = weights.reduce((a, b) => a + b, 0);
  if (total === 0) throw new Error('allocate: weights sum to zero');

  const totalMinor = amount.amountMinor;
  const sign = totalMinor < 0n ? -1n : 1n;
  const magnitude = totalMinor < 0n ? -totalMinor : totalMinor;

  const rawParts = weights.map((w) => (magnitude * BigInt(w)) / BigInt(total));
  const distributed = rawParts.reduce((a, b) => a + b, 0n);
  let remainder = magnitude - distributed;

  // distribute the remaining minor units one-by-one to the largest fractional remainders
  const remainders = weights.map((w, i) => ({
    i,
    frac: magnitude * BigInt(w) - rawParts[i] * BigInt(total),
  }));
  remainders.sort((a, b) => (b.frac < a.frac ? -1 : b.frac > a.frac ? 1 : a.i - b.i));

  const parts = rawParts.slice();
  let idx = 0;
  while (remainder > 0n) {
    parts[remainders[idx % remainders.length].i] += 1n;
    remainder -= 1n;
    idx += 1;
  }
  return parts.map((p) => ({ amountMinor: sign * p, currency: amount.currency }));
}

/** Locale-aware formatting for display only (never for authoritative math). */
export function formatMoney(m: Money, _locale: 'en' | 'ar' = 'en'): string {
  const exp = currencyExponent(m.currency);
  const neg = m.amountMinor < 0n;
  const mag = neg ? -m.amountMinor : m.amountMinor;
  const s = mag.toString().padStart(exp + 1, '0');
  const intPart = s.slice(0, s.length - exp) || '0';
  const frac = exp > 0 ? '.' + s.slice(s.length - exp) : '';
  const grouped = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  const body = `${grouped}${frac}`;
  return neg ? `-${body}` : body;
}
