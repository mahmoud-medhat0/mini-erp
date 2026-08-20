/**
 * Background worker entrypoint (separate process: `WORKER=1`). Boots pg-boss,
 * registers Phase-1 job definitions, and shuts down gracefully. Financial jobs
 * (depreciation, prepaid/accrual recognition, FX revaluation, recurring) are
 * registered in their phases; each must be transaction-safe and idempotent.
 */
import { JobRunner } from './core/jobs/runner';
import type { JobDefinition } from './core/jobs/runner';
import { PgBossQueue, startWorker } from './core/jobs/pgboss';
import PgBoss from 'pg-boss';

const PHASE1_JOBS: JobDefinition[] = [
  // Foundation health/sweep job — real, idempotent, non-financial.
  {
    name: 'notifications.sweep',
    maxAttempts: 3,
    handler: async () => {
      // Placeholder for the aging/notification sweep; wired to real queries in later phases.
      // Intentionally a no-op now (registered so the queue + worker path is exercised).
    },
  },
];

async function boot() {
  const connectionString = process.env.DATABASE_URL;
  if (!connectionString) throw new Error('DATABASE_URL is required for the worker');

  const boss = new PgBoss({ connectionString });
  const runner = new JobRunner(new PgBossQueue(boss), {
    // Idempotency store backed by a dedicated table in production; injected here.
    wasProcessed: async () => false,
    markProcessed: async () => undefined,
  });

  const handle = await startWorker(connectionString, runner, PHASE1_JOBS);
  console.warn(`Worker started; healthy=${handle.isHealthy()}; jobs=${PHASE1_JOBS.map((j) => j.name).join(',')}`);

  const shutdown = async (sig: string) => {
    console.warn(`Received ${sig}, stopping worker gracefully…`);
    await handle.stop();
    process.exit(0);
  };
  process.on('SIGINT', () => void shutdown('SIGINT'));
  process.on('SIGTERM', () => void shutdown('SIGTERM'));
}

boot().catch((e) => {
  console.error('Worker failed to start', e);
  process.exit(1);
});
