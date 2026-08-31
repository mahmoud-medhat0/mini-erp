'use server';

import { redirect } from 'next/navigation';
import { requireAuth } from '@/core/auth/server';
import { authorize } from '@/core/auth/session';
import { PrismaUserAdminRepository } from '@/core/db/repositories/userAdminRepo';
import { DomainError } from '@/core/errors';
import { tenantFromSession } from '@/core/tenant/context';
import { UserAdminService } from '@/modules/company/application/userAdminService';

export async function assignUserRole(locale: string, formData: FormData): Promise<void> {
  const session = await requireAuth(locale);
  authorize(session, 'users.configure');
  const service = new UserAdminService(new PrismaUserAdminRepository());
  try {
    await service.assignRole(tenantFromSession(session), {
      userId: String(formData.get('userId') ?? ''),
      roleId: String(formData.get('roleId') ?? ''),
    });
  } catch (e) {
    if (e instanceof DomainError) redirect(`/${locale}/settings/users?error=1`);
    throw e;
  }
  redirect(`/${locale}/settings/users?saved=1`);
}

export async function revokeUserRole(locale: string, formData: FormData): Promise<void> {
  const session = await requireAuth(locale);
  authorize(session, 'users.configure');
  const service = new UserAdminService(new PrismaUserAdminRepository());
  try {
    await service.revokeRole(tenantFromSession(session), {
      userId: String(formData.get('userId') ?? ''),
      roleId: String(formData.get('roleId') ?? ''),
    });
  } catch (e) {
    if (e instanceof DomainError) redirect(`/${locale}/settings/users?error=1`);
    throw e;
  }
  redirect(`/${locale}/settings/users?saved=1`);
}
