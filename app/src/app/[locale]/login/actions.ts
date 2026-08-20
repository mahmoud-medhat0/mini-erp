'use server';
/**
 * Login server action. Delegates to NextAuth's credentials sign-in. On success,
 * signIn throws an internal redirect (which must propagate). On invalid creds it
 * redirects back to the login page with a generic error flag — never revealing
 * whether the email exists.
 */
import { signIn } from '@/auth';
import { AuthError } from 'next-auth';
import { redirect } from 'next/navigation';

export async function loginAction(locale: string, formData: FormData): Promise<void> {
  const email = String(formData.get('email') ?? '');
  const password = String(formData.get('password') ?? '');
  try {
    await signIn('credentials', { email, password, redirectTo: `/${locale}` });
  } catch (e) {
    if (e instanceof AuthError) redirect(`/${locale}/login?error=1`);
    throw e; // success path throws NEXT_REDIRECT — must propagate
  }
}
