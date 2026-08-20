/**
 * Accounting kernel primitives shared by the posting engine (Phase 2 builds on this).
 * Pure functions — no DB. Enforces the core double-entry invariant so it can be
 * unit-tested and reused by every posting rule.
 */
import { UnbalancedEntryError } from '../errors';

export interface DraftLine {
  accountId: string;
  /** exactly one of debit/credit is non-zero, both in base-currency minor units */
  debitMinor: bigint;
  creditMinor: bigint;
  costCenterId?: string | null;
  projectId?: string | null;
  branchId?: string | null;
  taxId?: string | null;
  memo?: string | null;
}

export interface DraftEntry {
  sourceType: string;
  sourceId: string;
  date: Date;
  currency: string;
  fxRate: bigint; // scaled fixed-point (see FX docs); base amounts already computed on lines
  description?: string;
  lines: DraftLine[];
}

export function sumDebits(lines: DraftLine[]): bigint {
  return lines.reduce((a, l) => a + l.debitMinor, 0n);
}
export function sumCredits(lines: DraftLine[]): bigint {
  return lines.reduce((a, l) => a + l.creditMinor, 0n);
}

/** Each line must have exactly one non-zero side, and both sides non-negative. */
export function assertWellFormedLines(lines: DraftLine[]): void {
  if (lines.length < 2) throw new UnbalancedEntryError(sumDebits(lines), sumCredits(lines));
  for (const l of lines) {
    if (l.debitMinor < 0n || l.creditMinor < 0n) {
      throw new UnbalancedEntryError(sumDebits(lines), sumCredits(lines));
    }
    if (l.debitMinor > 0n && l.creditMinor > 0n) {
      throw new UnbalancedEntryError(sumDebits(lines), sumCredits(lines));
    }
    if (l.debitMinor === 0n && l.creditMinor === 0n) {
      throw new UnbalancedEntryError(sumDebits(lines), sumCredits(lines));
    }
  }
}

/** THE invariant: Σ debit === Σ credit. Throws UnbalancedEntryError otherwise. */
export function assertBalanced(entry: DraftEntry): void {
  assertWellFormedLines(entry.lines);
  const d = sumDebits(entry.lines);
  const c = sumCredits(entry.lines);
  if (d !== c) throw new UnbalancedEntryError(d, c);
}

export function isBalanced(entry: DraftEntry): boolean {
  try {
    assertBalanced(entry);
    return true;
  } catch {
    return false;
  }
}

/** Deterministic idempotency key for a posting so re-runs never double-post. */
export function postingIdempotencyKey(sourceType: string, sourceId: string, action = 'post'): string {
  return `${sourceType}:${sourceId}:${action}`;
}
