import { expect, type Page } from '@playwright/test';

/**
 * Credentials come from the environment, provisioned by `php artisan e2e:prepare-user`.
 * Nothing is defaulted: a missing value fails loudly rather than silently testing
 * against some other account.
 */
export function e2eCredentials(): { email: string; password: string } {
  const email = process.env.E2E_EMAIL;
  const password = process.env.E2E_PASSWORD;

  if (!email || !password) {
    throw new Error(
      'E2E_EMAIL and E2E_PASSWORD must be set. Run: php artisan e2e:prepare-user --password="<value>"',
    );
  }

  return { email, password };
}

export async function signIn(page: Page): Promise<void> {
  const { email, password } = e2eCredentials();

  await page.goto('/login');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', password);
  await page.click('button[type="submit"]');

  await page.waitForURL((url) => !url.pathname.startsWith('/login'), { timeout: 20_000 });
}

/**
 * Fails the calling test if the browser surfaced a React render error or a failed
 * asset/XHR request. A page can return HTTP 200 and still be broken for the user;
 * this is the class of defect browser tests exist to catch.
 */
export function collectPageErrors(page: Page): { assertClean: () => void } {
  const errors: string[] = [];

  page.on('pageerror', (error) => {
    errors.push(`Uncaught: ${error.message}`);
  });

  page.on('console', (message) => {
    if (message.type() === 'error') {
      const text = message.text();

      // Browser-level noise that does not indicate an application defect.
      if (text.includes('favicon') || text.includes('Download the React DevTools')) {
        return;
      }

      errors.push(`Console error: ${text}`);
    }
  });

  page.on('response', (response) => {
    const status = response.status();
    const url = response.url();

    if (status >= 500) {
      errors.push(`Server error ${status} on ${url}`);
    }
  });

  return {
    assertClean() {
      expect(errors, `Browser reported errors:\n${errors.join('\n')}`).toEqual([]);
    },
  };
}
