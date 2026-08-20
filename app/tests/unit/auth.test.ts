import { describe, it, expect, vi } from 'vitest';
import { CredentialsAuthService, UserRepository, AuthUser } from '../../src/core/auth/authService';
import { PasswordHasher } from '../../src/core/auth/password';
import { InMemoryRateLimiter } from '../../src/core/auth/rateLimit';
import { requireSession } from '../../src/core/auth/session';
import { InvalidCredentialsError, UnauthenticatedError } from '../../src/core/errors';

const fakeHasher = (): PasswordHasher => ({
  hash: async (p) => `H:${p}`,
  verify: async (h, p) => h === `H:${p}`,
});

function repoWith(users: AuthUser[]): UserRepository {
  return { findByEmail: async (e) => users.find((u) => u.email === e) ?? null };
}

const alice: AuthUser = { id: 'u1', email: 'alice@acme.test', name: 'Alice', passwordHash: 'H:secret', isActive: true };

describe('Auth — credentials', () => {
  it('authenticates a valid user and never returns the hash', async () => {
    const svc = new CredentialsAuthService(repoWith([alice]), fakeHasher());
    const res = await svc.authenticate('Alice@Acme.test', 'secret');
    expect(res).toEqual({ id: 'u1', email: 'alice@acme.test', name: 'Alice' });
    expect((res as Record<string, unknown>).passwordHash).toBeUndefined();
  });

  it('rejects a wrong password with a generic error', async () => {
    const svc = new CredentialsAuthService(repoWith([alice]), fakeHasher());
    await expect(svc.authenticate('alice@acme.test', 'nope')).rejects.toBeInstanceOf(InvalidCredentialsError);
  });

  it('rejects unknown user with the SAME generic error and still runs verify (anti-enumeration)', async () => {
    const hasher = fakeHasher();
    const spy = vi.spyOn(hasher, 'verify');
    const svc = new CredentialsAuthService(repoWith([alice]), hasher);
    await expect(svc.authenticate('ghost@acme.test', 'x')).rejects.toBeInstanceOf(InvalidCredentialsError);
    expect(spy).toHaveBeenCalled(); // dummy verify executed to equalize timing
  });

  it('rejects an inactive user', async () => {
    const svc = new CredentialsAuthService(repoWith([{ ...alice, isActive: false }]), fakeHasher());
    await expect(svc.authenticate('alice@acme.test', 'secret')).rejects.toBeInstanceOf(InvalidCredentialsError);
  });
});

describe('Auth — rate limiter', () => {
  it('allows up to max then blocks within the window, resets after', () => {
    let t = 0;
    const rl = new InMemoryRateLimiter(3, 1000, () => t);
    expect(rl.consume('ip').allowed).toBe(true);
    expect(rl.consume('ip').allowed).toBe(true);
    expect(rl.consume('ip').allowed).toBe(true);
    const blocked = rl.consume('ip');
    expect(blocked.allowed).toBe(false);
    expect(blocked.retryAfterMs).toBeGreaterThan(0);
    t = 1001;
    expect(rl.consume('ip').allowed).toBe(true);
  });
});

describe('Auth — session guard', () => {
  it('requireSession throws when unauthenticated', () => {
    expect(() => requireSession(null)).toThrow(UnauthenticatedError);
    expect(requireSession({ userId: 'u1', email: 'a', companyId: 'c1', grants: [] }).userId).toBe('u1');
  });
});
