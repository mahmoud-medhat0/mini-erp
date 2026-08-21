/**
 * Company-scoped user and role administration. Application service validates
 * input and delegates persistence to a repository; routes enforce RBAC before
 * calling it.
 */
import { ValidationError } from '../../../core/errors';
import type { TenantContext } from '../../../core/tenant/context';

export interface CompanyUser {
  id: string;
  email: string;
  name: string;
  isActive: boolean;
  roles: { id: string; name: string }[];
}

export interface CompanyRole {
  id: string;
  name: string;
  isTemplate: boolean;
  permissions: string[];
}

export interface UserAdminRepository {
  listUsers(companyId: string): Promise<CompanyUser[]>;
  listRoles(companyId: string): Promise<CompanyRole[]>;
  assignRole(companyId: string, userId: string, roleId: string): Promise<void>;
  revokeRole(companyId: string, userId: string, roleId: string): Promise<void>;
}

export class UserAdminService {
  constructor(private readonly repo: UserAdminRepository) {}

  async listUsers(ctx: TenantContext): Promise<CompanyUser[]> {
    return this.repo.listUsers(ctx.companyId);
  }

  async listRoles(ctx: TenantContext): Promise<CompanyRole[]> {
    return this.repo.listRoles(ctx.companyId);
  }

  async assignRole(ctx: TenantContext, input: { userId: string; roleId: string }): Promise<void> {
    if (!input.userId || !input.roleId) throw new ValidationError('User and role are required');
    await this.repo.assignRole(ctx.companyId, input.userId, input.roleId);
  }

  async revokeRole(ctx: TenantContext, input: { userId: string; roleId: string }): Promise<void> {
    if (!input.userId || !input.roleId) throw new ValidationError('User and role are required');
    await this.repo.revokeRole(ctx.companyId, input.userId, input.roleId);
  }
}
