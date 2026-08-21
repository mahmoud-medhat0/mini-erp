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
