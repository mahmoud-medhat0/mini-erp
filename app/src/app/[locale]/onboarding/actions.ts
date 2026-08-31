'use server';

import { redirect } from 'next/navigation';
import { requireIdentity } from '@/core/auth/server';
import { PrismaCompanyRepository } from '@/core/db/repositories/companyRepo';
import { DomainError } from '@/core/errors';
import { CompanyService } from '@/modules/company/application/companyService';

export async function createCompany(locale: string, formData: FormData): Promise<void> {
  const identity = await requireIdentity(locale);
  if (identity.companyId) redirect(`/${locale}/dashboard`);

  const service = new CompanyService(new PrismaCompanyRepository());
  try {
    await service.createCompany({
      nameEn: String(formData.get('companyNameEn') ?? ''),
      nameAr: String(formData.get('companyNameAr') ?? ''),
      ownerUserId: identity.userId,
      firstBranch: {
        code: String(formData.get('branchCode') ?? '').toUpperCase(),
        nameEn: String(formData.get('branchNameEn') ?? ''),
        nameAr: String(formData.get('branchNameAr') ?? ''),
      },
    });
  } catch (e) {
    if (e instanceof DomainError) redirect(`/${locale}/onboarding?error=1`);
    throw e;
  }

  redirect(`/${locale}/dashboard`);
}
