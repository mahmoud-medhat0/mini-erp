export type AiWidgetPageProps = {
  auth?: {
    user?: { id: number | string } | null;
  };
  locale?: string;
  csrfToken?: string;
  aiAssistant?: {
    enabled?: boolean;
    scriptUrl?: string | null;
    apiUrl?: string | null;
    contextId?: string | null;
    voiceEnabled?: boolean;
    visionEnabled?: boolean;
  };
};

type AiWidgetHistoryMessage = {
  role: 'user' | 'assistant';
  content: string;
};

type MiniErpAiConfig = {
  apiUrl: string;
  contextId: string;
  lang: 'ar' | 'en';
  title: string;
  theme: 'light' | 'dark';
  position: 'left' | 'right';
  primaryColor: string;
  secondaryColor: string;
  accentColor: string;
  fontFamily: string;
  persistHistory: boolean;
  autoTour: boolean;
  enableVoice: boolean;
  enableVision: boolean;
  initialHistory: AiWidgetHistoryMessage[];
};

declare global {
  interface Window {
    MiniErpAiConfig?: MiniErpAiConfig;
    MiniErpAiWidgetLoaded?: boolean;
    MiniErpAiWidgetDestroy?: () => void;
    MiniErpAiWidgetGetHistory?: () => unknown;
  }
}

const SCRIPT_ID = 'mini-erp-ai-widget-script';

let activeSignature: string | null = null;
let activeContextId: string | null = null;
let transientHistory: AiWidgetHistoryMessage[] = [];
let latestPageProps: AiWidgetPageProps | null = null;
let watchingTheme = false;

function resolvedTheme(): 'light' | 'dark' {
  return document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
}

function normalizedHistory(value: unknown): AiWidgetHistoryMessage[] {
  if (!Array.isArray(value)) return [];

  return value
    .filter((message): message is AiWidgetHistoryMessage => (
      typeof message === 'object'
      && message !== null
      && (
        (message as { role?: unknown }).role === 'user'
        || (message as { role?: unknown }).role === 'assistant'
      )
      && typeof (message as { content?: unknown }).content === 'string'
    ))
    .map((message) => ({
      role: message.role,
      content: message.content.slice(0, 4000),
    }))
    .slice(-20);
}

function syncCsrfToken(token: string | undefined): void {
  if (!token) return;

  let meta = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]');
  if (!meta) {
    meta = document.createElement('meta');
    meta.name = 'csrf-token';
    document.head.appendChild(meta);
  }
  meta.content = token;
}

function cleanupWidget(preserveHistory = false): void {
  if (preserveHistory && typeof window.MiniErpAiWidgetGetHistory === 'function') {
    try {
      transientHistory = normalizedHistory(window.MiniErpAiWidgetGetHistory());
    } catch {
      transientHistory = [];
    }
  } else if (!preserveHistory) {
    transientHistory = [];
  }

  if (typeof window.MiniErpAiWidgetDestroy === 'function') {
    window.MiniErpAiWidgetDestroy();
  } else {
    document.querySelectorAll('.ai-widget-wrapper').forEach((element) => element.remove());
    document.getElementById('ai-widget-styles')?.remove();
    document.getElementById('ai-widget-font')?.remove();
    window.MiniErpAiWidgetLoaded = false;
    delete window.MiniErpAiConfig;
  }

  document.getElementById(SCRIPT_ID)?.remove();
  activeSignature = null;
  if (!preserveHistory) activeContextId = null;
}

function renderWidget(pageProps: AiWidgetPageProps): void {
  const config = pageProps.aiAssistant;
  if (!config?.enabled || !pageProps.auth?.user || !config.contextId || !config.scriptUrl || !config.apiUrl) {
    if (activeSignature !== null || window.MiniErpAiWidgetLoaded) cleanupWidget();
    return;
  }

  let scriptUrl: URL;
  let apiUrl: URL;
  try {
    scriptUrl = new URL(config.scriptUrl, window.location.origin);
    apiUrl = new URL(config.apiUrl, window.location.origin);
  } catch {
    cleanupWidget();
    return;
  }

  // Both executable code and browser requests stay on the ERP origin.
  if (scriptUrl.origin !== window.location.origin || apiUrl.origin !== window.location.origin) {
    cleanupWidget();
    return;
  }

  const lang = pageProps.locale === 'en' ? 'en' : 'ar';
  const theme = resolvedTheme();
  const signature = JSON.stringify([
    scriptUrl.href,
    apiUrl.href,
    config.contextId,
    lang,
    theme,
    config.voiceEnabled === true,
    config.visionEnabled !== false,
  ]);

  if (
    signature === activeSignature
    && (window.MiniErpAiWidgetLoaded || document.getElementById(SCRIPT_ID))
  ) return;

  const preserveHistory = activeContextId === config.contextId;
  cleanupWidget(preserveHistory);
  activeSignature = signature;
  activeContextId = config.contextId;
  window.MiniErpAiConfig = {
    apiUrl: apiUrl.pathname.replace(/\/$/, ''),
    contextId: config.contextId,
    lang,
    title: lang === 'ar' ? 'مساعد Mini ERP' : 'Mini ERP Assistant',
    theme,
    // Keep the assistant opposite the RTL/LTR navigation sidebar.
    position: lang === 'ar' ? 'left' : 'right',
    primaryColor: '#2563EB',
    secondaryColor: '#1D4ED8',
    accentColor: '#4F46E5',
    fontFamily: "'Alexandria', 'Instrument Sans', system-ui, sans-serif",
    persistHistory: false,
    autoTour: false,
    enableVoice: config.voiceEnabled === true,
    enableVision: config.visionEnabled !== false,
    initialHistory: transientHistory,
  };

  const script = document.createElement('script');
  script.id = SCRIPT_ID;
  script.src = scriptUrl.href;
  script.async = true;
  script.referrerPolicy = 'strict-origin';
  script.addEventListener('error', () => {
    cleanupWidget();
    console.error('Mini ERP AI widget could not be loaded.');
  }, { once: true });
  document.body.appendChild(script);
}

export function syncAiWidget(pageProps: AiWidgetPageProps): void {
  latestPageProps = pageProps;
  syncCsrfToken(pageProps.csrfToken);
  renderWidget(pageProps);

  if (watchingTheme) return;
  watchingTheme = true;

  const observer = new MutationObserver(() => {
    if (latestPageProps) renderWidget(latestPageProps);
  });
  observer.observe(document.documentElement, {
    attributes: true,
    attributeFilter: ['data-theme'],
  });
}
