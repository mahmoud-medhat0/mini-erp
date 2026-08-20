'use server';
import { redirect } from 'next/navigation';
import { requireAuth } from '@/core/auth/server';
import { tenantFromSession } from '@/core/tenant/context';
import { BranchService } from '@/modules/company/application/branchService';
import { PrismaBranchRepository } from '@/core/db/repositories/branchRepo';
import { DomainError } from '@/core/errors';

export async function addBranch(locale: string, formData: FormData): Promise<void> {
  const session = await requireAuth(locale);
  const svc = new BranchService(new PrismaBranchRepository());
  try {
    await svc.create(tenantFromSession(session), {
      code: String(formData.get('code') ?? ''),
      nameEn: String(formData.get('nameEn') ?? ''),
      nameAr: String(formData.get('nameAr') ?? ''),
    });
  } catch (e) {
    if (e instanceof DomainError) redirect(`/${locale}/settings/branches?error=1`);
    throw e;
  }
  redirect(`/${locale}/settings/branches?saved=1`);
}
