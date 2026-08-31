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
    // pg-boss v10 delivers a batch (Job[]); includeMetadata exposes retryCount.
    await boss.work<{ idempotencyKey?: string }>(
      def.name,
      { pollingIntervalSeconds: 5, includeMetadata: true },
      async (jobs) => {
        for (const job of jobs) {
          await runner.execute(def.name, job.data, {
            jobId: job.id,
            attempt: (job.retryCount ?? 0) + 1,
            idempotencyKey: job.data?.idempotencyKey,
          });
        }
      },
    );
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
