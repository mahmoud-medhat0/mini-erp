import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import type { ComponentType } from 'react';
import { createRoot } from 'react-dom/client';

createInertiaApp({
  title: (title) => (title ? `${title} - Mini ERP` : 'Mini ERP'),
  resolve: async (name) => {
    const pages = import.meta.glob<{ default: ComponentType<unknown> }>('./Pages/**/*.tsx');
    const page = pages[`./Pages/${name}.tsx`];

    if (!page) {
      throw new Error(`Unknown Inertia page: ${name}`);
    }

    return (await page()).default;
  },
  setup({ el, App, props }) {
    if (!el) {
      throw new Error('Missing Inertia root element');
    }

    createRoot(el).render(<App {...props} />);
  },
  progress: {
    color: '#2563a8',
  },
});
