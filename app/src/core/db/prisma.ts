/**
 * Prisma client singleton. The ONLY module family allowed to import this is
 * core/db/repositories/* — repositories are the sole layer that touches the DB.
 * Application/domain code depends on repository interfaces, never on Prisma.
 */
import { PrismaClient } from '@prisma/client';

const globalForPrisma = globalThis as unknown as { prisma?: PrismaClient };

export const prisma: PrismaClient =
  globalForPrisma.prisma ??
  new PrismaClient({
    log: process.env.NODE_ENV === 'development' ? ['warn', 'error'] : ['error'],
  });

if (process.env.NODE_ENV !== 'production') globalForPrisma.prisma = prisma;

export type Tx = Parameters<Parameters<PrismaClient['$transaction']>[0]>[0];
