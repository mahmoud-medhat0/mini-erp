/**
 * NextAuth route handler — makes the credentials flow end-to-end. Node.js runtime
 * (accounting/DB + argon2/Prisma are never Edge-safe).
 */
import { handlers } from '@/auth';

export const runtime = 'nodejs';
export const { GET, POST } = handlers;
