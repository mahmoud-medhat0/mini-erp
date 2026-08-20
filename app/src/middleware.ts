import createMiddleware from 'next-intl/middleware';
import { routing } from './i18n/routing';

export default createMiddleware(routing);

export const config = {
  // Run on all paths except api, static, and files
  matcher: ['/((?!api|_next|_vercel|.*\\..*).*)'],
};
