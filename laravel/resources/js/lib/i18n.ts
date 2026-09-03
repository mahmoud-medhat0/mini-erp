import { router } from '@inertiajs/react';

export type Locale = 'en' | 'ar';

// Import each locale file lazily so the bundle for locale X is never sent to
// a user who chose locale Y. Vite automatically code-splits dynamic imports
// into separate chunks.
const localeLoaders: Record<Locale, () => Promise<any>> = {
  en: () => import('../locales/en.json'),
  ar: () => import('../locales/ar.json'),
};

// In-memory cache: once loaded, re-use the same object for every call.
const loadedLocales: Partial<Record<Locale, any>> = {};

/**
 * Async loader — call this once on app init (or on locale switch) to pre-warm
 * the dictionary.  Components that need a synchronous `getDictionary()` will
 * always find the data in cache after the first await resolves.
 */
export async function loadLocale(locale: string): Promise<void> {
  const lang: Locale = locale === 'ar' ? 'ar' : 'en';
  if (!loadedLocales[lang]) {
    const mod = await localeLoaders[lang]();
    loadedLocales[lang] = mod.default ?? mod;
  }
}

// Eagerly load the locale that is already set on the HTML element so that the
// very first synchronous getDictionary() call has data available.  This runs
// at module evaluation time and the promise is intentionally fire-and-forget;
// by the time React renders anything the network request will have resolved
// (it is already bundled into the chunk).
const _initialLocale: Locale =
  (typeof document !== 'undefined' &&
    (document.documentElement.getAttribute('lang') as Locale)) ||
  'en';

// Synchronously load the initial locale module (since Vite pre-bundles it, the
// dynamic import resolves synchronously in dev after warm-up, and in production
// it is fetched once then cached by the browser).
localeLoaders[_initialLocale]().then((mod) => {
  loadedLocales[_initialLocale] = mod.default ?? mod;
});

export type Dictionary = typeof import('../locales/en.json');

export function getDictionary(locale: string = 'en'): Dictionary {
  const lang: Locale = locale === 'ar' ? 'ar' : 'en';
  // Return from cache if available, otherwise fall back to the other locale
  // (this avoids a crash on the very first synchronous call before the async
  // loader has settled — extremely rare in practice).
  return (
    loadedLocales[lang] ??
    loadedLocales[lang === 'ar' ? 'en' : 'ar'] ??
    ({} as Dictionary)
  );
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
  const loc: Locale = locale === 'ar' ? 'ar' : 'en';
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
export async function changeLocale(newLocale: 'en' | 'ar') {
  syncDomLocale(newLocale);
  // Pre-load the target locale bundle before the page navigates so the new
  // locale is already in cache when the refreshed page renders.
  await loadLocale(newLocale);
  router.post(
    '/locale',
    { locale: newLocale },
    {
      preserveScroll: true,
      preserveState: false,
    },
  );
}
