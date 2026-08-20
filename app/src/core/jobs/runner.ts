/**
 * Job runner abstraction. Guarantees idempotency (a job with the same key runs
 * at most once to completion) and defines the retry/backoff policy. The concrete
 * queue is pg-boss (jobs/pgboss.ts); this layer is queue-agnostic and testable.
 * Financial handlers must be transaction-safe: partial work must roll back.
 */
export interface JobContext {
  jobId: string;
  attempt: number;
  idempotencyKey?: string;
}

export type JobHandler<T> = (payload: T, ctx: JobContext) => Promise<void>;

export interface JobDefinition<T = unknown> {
  name: string;
  handler: JobHandler<T>;
  maxAttempts?: number;
}

/** Records which idempotency keys have completed, so re-delivery is a no-op. */
export interface IdempotencyStore {
  wasProcessed(key: string): Promise<boolean>;
  markProcessed(key: string): Promise<void>;
}

/** Minimal queue surface the runner needs. */
export interface Queue {
  publish(name: string, payload: unknown, opts?: { idempotencyKey?: string }): Promise<void>;
}

/** Exponential backoff with a cap, in milliseconds. attempt is 1-based. */
export function backoffMs(attempt: number, baseMs = 1000, capMs = 5 * 60_000): number {
  const exp = Math.min(capMs, baseMs * 2 ** (attempt - 1));
  return exp;
}

export class JobRunner {
  private defs = new Map<string, JobDefinition>();

  constructor(
    private readonly queue: Queue,
    private readonly idem: IdempotencyStore,
  ) {}

  register<T>(def: JobDefinition<T>): void {
    if (this.defs.has(def.name)) throw new Error(`Job already registered: ${def.name}`);
    this.defs.set(def.name, def as JobDefinition);
  }

  async enqueue<T>(name: string, payload: T, opts?: { idempotencyKey?: string }): Promise<void> {
    if (!this.defs.has(name)) throw new Error(`Unknown job: ${name}`);
    await this.queue.publish(name, payload, opts);
  }

  /**
   * Executes a delivered job with idempotency. Returns 'skipped' when the key was
   * already processed, 'done' on success. Throws (for the queue to retry) on failure.
   */
  async execute(name: string, payload: unknown, ctx: JobContext): Promise<'done' | 'skipped'> {
    const def = this.defs.get(name);
    if (!def) throw new Error(`Unknown job: ${name}`);

    if (ctx.idempotencyKey && (await this.idem.wasProcessed(ctx.idempotencyKey))) {
      return 'skipped';
    }
    await def.handler(payload, ctx);
    if (ctx.idempotencyKey) await this.idem.markProcessed(ctx.idempotencyKey);
    return 'done';
  }

  maxAttempts(name: string): number {
    return this.defs.get(name)?.maxAttempts ?? 5;
  }
}
