'use server';
import { redirect } from 'next/navigation';
import { requireAuth } from '@/core/auth/server';
import { tenantFromSession } from '@/core/tenant/context';
import { NumberingConfigService } from '@/core/numbering/service';
import { PrismaNumberSequenceRepository } from '@/core/db/repositories/numberSequenceRepo';
import { DomainError } from '@/core/errors';
import type { ResetPolicy } from '@/core/numbering';

export async function saveNumbering(locale: string, formData: FormData): Promise<void> {
  const session = await requireAuth(locale);
  const svc = new NumberingConfigService(new PrismaNumberSequenceRepository());
  try {
    await svc.saveConfig(tenantFromSession(session), {
      docType: String(formData.get('docType') ?? '').toUpperCase(),
      prefix: String(formData.get('prefix') ?? '').toUpperCase(),
      padding: Number(formData.get('padding') ?? 5),
      resetPolicy: (String(formData.get('resetPolicy') ?? 'yearly') as ResetPolicy),
      includeYear: formData.get('includeYear') === 'on',
      includeBranch: formData.get('includeBranch') === 'on',
    });
  } catch (e) {
    if (e instanceof DomainError) redirect(`/${locale}/settings/numbering?error=1`);
    throw e;
  }
  redirect(`/${locale}/settings/numbering?saved=1`);
}
