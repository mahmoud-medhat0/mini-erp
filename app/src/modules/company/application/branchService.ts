/**
 * Branch management (application service). Validates code/names, enforces unique
 * branch code per company, persists via a repository interface.
 */
import { ValidationError } from '../../../core/errors';
import type { TenantContext } from '../../../core/tenant/context';

export interface Branch {
  id: string;
  companyId: string;
  code: string;
  nameEn: string;
  nameAr: string;
  isActive: boolean;
}

export interface BranchRepository {
  list(companyId: string): Promise<Branch[]>;
  codeExists(companyId: string, code: string): Promise<boolean>;
  create(companyId: string, input: { code: string; nameEn: string; nameAr: string }): Promise<Branch>;
}

const CODE = /^[A-Za-z0-9_-]{1,16}$/;

export class BranchService {
  constructor(private readonly repo: BranchRepository) {}

  async list(ctx: TenantContext): Promise<Branch[]> {
    return this.repo.list(ctx.companyId);
  }

  async create(ctx: TenantContext, input: { code: string; nameEn: string; nameAr: string }): Promise<Branch> {
    if (!CODE.test(input.code)) throw new ValidationError('Invalid branch code');
    if (!input.nameEn?.trim() || !input.nameAr?.trim()) throw new ValidationError('Branch name (EN and AR) is required');
    if (await this.repo.codeExists(ctx.companyId, input.code))
      throw new ValidationError(`Branch code already exists: ${input.code}`);
    return this.repo.create(ctx.companyId, input);
  }
}
