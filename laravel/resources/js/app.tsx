import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/react';
import type { ResolvedComponent } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

import { loadLocale, syncDomLocale } from './lib/i18n';

// Synchronize locale and direction with HTML element on client navigation & requests
router.on('navigate', (event) => {
  const pageProps = event.detail.page.props as { locale?: string; direction?: string };
  syncDomLocale(pageProps?.locale, pageProps?.direction);
  if (pageProps?.locale) {
    loadLocale(pageProps.locale);
  }
});

router.on('success', (event) => {
  const pageProps = event.detail.page.props as { locale?: string; direction?: string };
  syncDomLocale(pageProps?.locale, pageProps?.direction);
});

const appName = import.meta.env.VITE_APP_NAME || 'Mini ERP';

createInertiaApp({
  title: (title) => (title ? `${title} - ${appName}` : appName),
  resolve: (name) =>
    resolvePageComponent(
      `./Pages/${name}.tsx`,
      import.meta.glob<{ default: ResolvedComponent }>('./Pages/**/*.tsx'),
    ).then((module) => module.default),
  setup({ el, App, props }) {
    if (!el) {
      throw new Error('Missing Inertia root element');
    }

    // Apply initial locale & direction
    syncDomLocale(
      props.initialPage.props.locale as string,
      props.initialPage.props.direction as string,
    );

    // Apply & sync system theme preference dynamically
    const themePreference = props.initialPage.props.theme as string | undefined;
    if (!themePreference || themePreference === 'system') {
      const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
      const applySystemTheme = (e: MediaQueryList | MediaQueryListEvent) => {
        document.documentElement.setAttribute('data-theme', e.matches ? 'dark' : 'light');
      };
      applySystemTheme(mediaQuery);
      mediaQuery.addEventListener('change', applySystemTheme);
    } else {
      document.documentElement.setAttribute('data-theme', themePreference);
    }

    createRoot(el).render(<App {...props} />);
  },
  progress: {
    color: '#2563a8',
  },
});
