/**
 * pg-boss adapter (production job queue). Imported by the worker composition root,
 * not by unit tests. Wires pg-boss to the queue-agnostic JobRunner: registration,
 * retries with exponential backoff, graceful shutdown, health.
 */
import PgBoss from 'pg-boss';
import { JobRunner, JobDefinition, backoffMs, Queue } from './runner';

export class PgBossQueue implements Queue {
  constructor(private readonly boss: PgBoss) {}
  async publish(name: string, payload: unknown, opts?: { idempotencyKey?: string }): Promise<void> {
    await this.boss.send(name, payload as object, {
      // pg-boss singletonKey de-dupes concurrent publishes with the same key
      singletonKey: opts?.idempotencyKey,
      retryLimit: 5,
      retryBackoff: true,
    });
  }
}

export interface WorkerHandle {
  boss: PgBoss;
  stop: () => Promise<void>;
  isHealthy: () => boolean;
}

/** Boots pg-boss, registers all job definitions on the runner, returns a handle. */
export async function startWorker(
  connectionString: string,
  runner: JobRunner,
  defs: JobDefinition[],
): Promise<WorkerHandle> {
  const boss = new PgBoss({ connectionString });
  let healthy = false;
  boss.on('error', () => {
    healthy = false;
  });
  await boss.start();
  healthy = true;

  for (const def of defs) {
    runner.register(def);
    await boss.work(def.name, { newJobCheckIntervalSeconds: 5 }, async (job) => {
      const attempt = (job as unknown as { retryCount?: number }).retryCount ?? 0;
      await runner.execute(def.name, job.data, {
        jobId: job.id,
        attempt: attempt + 1,
        idempotencyKey: (job.data as { idempotencyKey?: string })?.idempotencyKey,
      });
    });
  }

  return {
    boss,
    isHealthy: () => healthy,
    stop: async () => {
      await boss.stop({ graceful: true });
    },
  };
}

export { backoffMs };
