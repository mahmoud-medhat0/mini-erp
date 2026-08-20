/**
 * Credentials authentication (application service). Depends on a UserRepository
 * and a PasswordHasher; performs no DB access itself. Never logs or returns the
 * password or hash; errors are generic to avoid user enumeration.
 */
import { InvalidCredentialsError } from '../errors';
import { PasswordHasher, DUMMY_HASH } from './password';

export interface AuthUser {
  id: string;
  email: string;
  name: string;
  passwordHash: string;
  isActive: boolean;
}

export interface UserRepository {
  findByEmail(email: string): Promise<AuthUser | null>;
}

export interface AuthenticatedUser {
  id: string;
  email: string;
  name: string;
}

export class CredentialsAuthService {
  constructor(
    private readonly users: UserRepository,
    private readonly hasher: PasswordHasher,
  ) {}

  async authenticate(email: string, password: string): Promise<AuthenticatedUser> {
    const normalized = email.trim().toLowerCase();
    const user = await this.users.findByEmail(normalized);

    // Always run a verify to keep timing similar whether or not the user exists.
    if (!user || !user.isActive) {
      await this.hasher.verify(DUMMY_HASH, password).catch(() => false);
      throw new InvalidCredentialsError();
    }

    const ok = await this.hasher.verify(user.passwordHash, password);
    if (!ok) throw new InvalidCredentialsError();

    return { id: user.id, email: user.email, name: user.name };
  }
}
