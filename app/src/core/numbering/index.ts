/**
 * Document numbering engine — concurrency-safe, configurable, never duplicates.
 * Format e.g. INV-2026-00001. The pure `formatDocNumber` is unit-tested; the
 * atomic sequence allocation is delegated to a SequenceStore whose Prisma impl
 * uses SELECT ... FOR UPDATE inside a transaction (see prisma impl notes).
 */
export type ResetPolicy = 'never' | 'yearly' | 'monthly';

export interface SequenceConfig {
  docType: string;
  prefix: string;
  includeYear: boolean;
  includeBranch: boolean;
  padding: number;
  resetPolicy: ResetPolicy;
}

export interface NumberContext {
  year: number;
  month?: number;
  branchCode?: string;
}

export function sequenceKey(cfg: SequenceConfig, ctx: NumberContext): string {
  // The key identifies the counter bucket. `includeYear/includeBranch` affect the
  // DISPLAY format only; the bucket is driven by resetPolicy (+ branch for scope).
  const parts = [cfg.docType];
  if (cfg.includeBranch && ctx.branchCode) parts.push(ctx.branchCode);
  if (cfg.resetPolicy === 'yearly') parts.push(String(ctx.year));
  else if (cfg.resetPolicy === 'monthly') parts.push(`${ctx.year}-${String(ctx.month ?? 1).padStart(2, '0')}`);
  return parts.join('|');
}

export function formatDocNumber(cfg: SequenceConfig, ctx: NumberContext, seq: number): string {
  const segs: string[] = [cfg.prefix];
  if (cfg.includeYear) segs.push(String(ctx.year));
  if (cfg.includeBranch && ctx.branchCode) segs.push(ctx.branchCode);
  segs.push(String(seq).padStart(cfg.padding, '0'));
  return segs.join('-');
}

/** Atomic counter store. The real implementation locks the row in a DB transaction. */
export interface SequenceStore {
  /** Atomically increments and returns the next sequence value for `key`, starting at 1. */
  nextValue(key: string): Promise<number>;
}

export class NumberingService {
  constructor(private readonly store: SequenceStore) {}

  async allocate(cfg: SequenceConfig, ctx: NumberContext): Promise<string> {
    const key = sequenceKey(cfg, ctx);
    const seq = await this.store.nextValue(key);
    return formatDocNumber(cfg, ctx, seq);
  }
}

/**
 * Reference Prisma implementation (documented; requires generated client + DB):
 *
 *   class PrismaSequenceStore implements SequenceStore {
 *     constructor(private prisma: PrismaClient) {}
 *     async nextValue(key: string): Promise<number> {
 *       return this.prisma.$transaction(async (tx) => {
 *         // atomic upsert-and-increment; row lock via UPDATE ... RETURNING
 *         const row = await tx.$queryRaw<{ next_value: number }[]>`
 *           INSERT INTO number_sequence (key, next_value) VALUES (${key}, 1)
 *           ON CONFLICT (key) DO UPDATE SET next_value = number_sequence.next_value + 1
 *           RETURNING next_value`;
 *         return row[0].next_value;
 *       });
 *     }
 *   }
 *
 * The INSERT ... ON CONFLICT DO UPDATE ... RETURNING is a single atomic statement,
 * so concurrent callers can never receive the same value (Postgres serializes the
 * conflicting row update). Unique constraint on the document `number` is the backstop.
 */
