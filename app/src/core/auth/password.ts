/**
 * Password hashing abstraction. The domain depends on this interface only; the
 * concrete Argon2 adapter lives in password.argon2.ts so tests never need the
 * native module. Plaintext never appears in logs, errors, or audit.
 */
export interface PasswordHasher {
  hash(plain: string): Promise<string>;
  verify(hash: string, plain: string): Promise<boolean>;
}

/**
 * A precomputed dummy hash used to keep authentication timing roughly constant
 * whether or not the email exists (mitigates user-enumeration via timing).
 * Real adapters override this with a hash in their own format.
 */
export const DUMMY_HASH =
  '$argon2id$v=19$m=19456,t=2,p=1$c29tZS1kdW1teS1zYWx0$0000000000000000000000000000000000000000000';
