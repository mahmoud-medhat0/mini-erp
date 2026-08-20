/**
 * Prisma-backed branch repository.
 */
import { prisma } from '../prisma';
import type { BranchRepository, Branch } from '../../../modules/company/application/branchService';

export class PrismaBranchRepository implements BranchRepository {
  async list(companyId: string): Promise<Branch[]> {
    return prisma.branch.findMany({ where: { companyId }, orderBy: { code: 'asc' } });
  }
  async codeExists(companyId: string, code: string): Promise<boolean> {
    const row = await prisma.branch.findUnique({ where: { companyId_code: { companyId, code } } });
    return !!row;
  }
  async create(companyId: string, input: { code: string; nameEn: string; nameAr: string }): Promise<Branch> {
    return prisma.branch.create({ data: { companyId, ...input } });
  }
}
