import { getRequestConfig } from 'next-intl/server';
import { routing } from './routing';

async function loadMessages(locale: string) {
  const [common, navigation] = await Promise.all([
    import(`../locales/${locale}/common.json`).then((m) => m.default),
    import(`../locales/${locale}/navigation.json`).then((m) => m.default),
  ]);
  return { ...common, ...navigation };
}

export default getRequestConfig(async ({ requestLocale }) => {
  let locale = await requestLocale;
  if (!locale || !routing.locales.includes(locale as 'en' | 'ar')) locale = routing.defaultLocale;
  return { locale, messages: await loadMessages(locale) };
});
