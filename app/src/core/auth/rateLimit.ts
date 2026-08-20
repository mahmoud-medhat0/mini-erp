/**
 * Fixed-window in-memory rate limiter for auth attempts. Clock is injectable so
 * it is deterministically testable. In production, back this with Redis for
 * multi-instance correctness (same interface).
 */
export interface RateLimiter {
  consume(key: string): { allowed: boolean; remaining: number; retryAfterMs: number };
}

export class InMemoryRateLimiter implements RateLimiter {
  private hits = new Map<string, { count: number; resetAt: number }>();

  constructor(
    private readonly max: number,
    private readonly windowMs: number,
    private readonly now: () => number = () => Date.now(),
  ) {}

  consume(key: string): { allowed: boolean; remaining: number; retryAfterMs: number } {
    const t = this.now();
    const entry = this.hits.get(key);
    if (!entry || t >= entry.resetAt) {
      this.hits.set(key, { count: 1, resetAt: t + this.windowMs });
      return { allowed: true, remaining: this.max - 1, retryAfterMs: 0 };
    }
    if (entry.count >= this.max) {
      return { allowed: false, remaining: 0, retryAfterMs: entry.resetAt - t };
    }
    entry.count += 1;
    return { allowed: true, remaining: this.max - entry.count, retryAfterMs: 0 };
  }
}
