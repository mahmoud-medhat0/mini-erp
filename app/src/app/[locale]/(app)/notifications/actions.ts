'use server';

import { redirect } from 'next/navigation';
import { requireAuth } from '@/core/auth/server';
import { PrismaNotificationRepository } from '@/core/db/repositories/notificationRepo';
import { DomainError } from '@/core/errors';
import { NotificationService } from '@/core/notifications/service';
import { tenantFromSession } from '@/core/tenant/context';

export async function markNotificationRead(locale: string, formData: FormData): Promise<void> {
  const session = await requireAuth(locale);
  const service = new NotificationService(new PrismaNotificationRepository());
  try {
    await service.markRead(tenantFromSession(session), String(formData.get('id') ?? ''));
  } catch (e) {
    if (e instanceof DomainError) redirect(`/${locale}/notifications?error=1`);
    throw e;
  }
  redirect(`/${locale}/notifications`);
}
