/**
 * Prisma-backed company user/role administration.
 */
import { prisma } from '../prisma';
import { ValidationError } from '../../errors';
import type { CompanyRole, CompanyUser, UserAdminRepository } from '../../../modules/company/application/userAdminService';

export class PrismaUserAdminRepository implements UserAdminRepository {
  async listUsers(companyId: string): Promise<CompanyUser[]> {
    const users = await prisma.user.findMany({
      where: { companies: { some: { companyId } } },
      include: {
        roles: {
          where: { role: { companyId } },
          include: { role: true },
          orderBy: { role: { name: 'asc' } },
        },
      },
      orderBy: { email: 'asc' },
    });

    return users.map((user) => ({
      id: user.id,
      email: user.email,
      name: user.name,
      isActive: user.isActive,
      roles: user.roles.map((assignment) => ({ id: assignment.role.id, name: assignment.role.name })),
    }));
  }

  async listRoles(companyId: string): Promise<CompanyRole[]> {
    const roles = await prisma.role.findMany({
      where: { companyId },
      include: { permissions: { include: { permission: true } } },
      orderBy: { name: 'asc' },
    });

    return roles.map((role) => ({
      id: role.id,
      name: role.name,
      isTemplate: role.isTemplate,
      permissions: role.permissions
        .map((row) => `${row.permission.module}.${row.permission.action}`)
        .sort((a, b) => a.localeCompare(b)),
    }));
  }

  async assignRole(companyId: string, userId: string, roleId: string): Promise<void> {
    await prisma.$transaction(async (tx) => {
      const [membership, role] = await Promise.all([
        tx.userCompany.findUnique({ where: { userId_companyId: { userId, companyId } } }),
        tx.role.findFirst({ where: { id: roleId, companyId } }),
      ]);
      if (!membership || !role) throw new ValidationError('User or role not found in this company');

      await tx.userRole.upsert({
        where: { userId_roleId: { userId, roleId } },
        update: { scopeJson: { companyId } },
        create: { userId, roleId, scopeJson: { companyId } },
      });
    });
  }

  async revokeRole(companyId: string, userId: string, roleId: string): Promise<void> {
    const role = await prisma.role.findFirst({ where: { id: roleId, companyId } });
    if (!role) throw new ValidationError('Role not found in this company');
    await prisma.userRole.deleteMany({ where: { userId, roleId } });
  }
}
