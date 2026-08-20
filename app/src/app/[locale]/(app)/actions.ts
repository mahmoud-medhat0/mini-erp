'use server';
import { signOut } from '@/auth';

export async function signOutAction(locale: string): Promise<void> {
  await signOut({ redirectTo: `/${locale}/login` });
}
