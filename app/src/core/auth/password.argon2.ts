/**
 * Production Argon2id password hasher. Imported only by the composition root
 * (not by unit tests), so the native `argon2` module is not required to run the
 * test suite. Parameters follow OWASP guidance (argon2id, m=19456, t=2, p=1).
 */
import argon2 from 'argon2';
import type { PasswordHasher } from './password';

const OPTIONS = {
  type: argon2.argon2id,
  memoryCost: 19456,
  timeCost: 2,
  parallelism: 1,
} as const;

export class Argon2PasswordHasher implements PasswordHasher {
  async hash(plain: string): Promise<string> {
    return argon2.hash(plain, OPTIONS);
  }
  async verify(hash: string, plain: string): Promise<boolean> {
    try {
      return await argon2.verify(hash, plain);
    } catch {
      return false;
    }
  }
}
