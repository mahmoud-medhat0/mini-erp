import { router } from '@inertiajs/react';
import en from '../locales/en.json';
import ar from '../locales/ar.json';

const dictionaries = { en, ar };

export type Locale = 'en' | 'ar';
export type Dictionary = typeof en;

export function getDictionary(locale: string = 'en'): Dictionary {
  const lang = locale === 'ar' ? 'ar' : 'en';
  return dictionaries[lang];
}

/**
 * No-op stub kept for API compatibility with app.tsx which imports it.
 * With static imports the dictionary is always available synchronously.
 */
export async function loadLocale(_locale: string): Promise<void> {
  // Static imports are resolved at bundle time — nothing to load at runtime.
}

/**
 * Replaces `:placeholder` tokens in a dictionary string with runtime values.
 */
export function interpolate(template: string, params: Record<string, string | number>): string {
  return template.replace(/:(\w+)/g, (match, key: string) =>
    key in params ? String(params[key]) : match,
  );
}

/**
 * Synchronizes HTML `dir` and `lang` attributes on the DOM document element.
 */
export function syncDomLocale(locale: string = 'en', direction?: string) {
  const loc = locale === 'ar' ? 'ar' : 'en';
  const dir = direction || (loc === 'ar' ? 'rtl' : 'ltr');

  if (typeof document !== 'undefined') {
    document.documentElement.setAttribute('lang', loc);
    document.documentElement.setAttribute('dir', dir);
  }
}

/**
 * Centralized global action to switch application locale across any component/page.
 * Instantly syncs DOM direction attributes and sends an Inertia POST request.
 */
export function changeLocale(newLocale: 'en' | 'ar') {
  syncDomLocale(newLocale);
  router.post(
    '/locale',
    { locale: newLocale },
    {
      preserveScroll: true,
      preserveState: false,
    },
  );
}
