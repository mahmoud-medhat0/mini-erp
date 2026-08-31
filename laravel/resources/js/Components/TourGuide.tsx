import {
  useCallback,
  useEffect,
  useRef,
  useState,
  type CSSProperties,
} from 'react';

import { interpolate, type Dictionary } from '../lib/i18n';

type TourCopy = Dictionary['app']['tour'];
type TourVariant = 'app' | 'login';
type SectionKey = keyof TourCopy['sections'];

type TourGuideProps = {
  copy: TourCopy;
  locale: 'en' | 'ar';
  pageKey: string;
  sectionKey?: string;
  variant?: TourVariant;
};

type TourStep = {
  description: string;
  id: string;
  target: HTMLElement;
  title: string;
};

type HighlightRect = {
  height: number;
  left: number;
  top: number;
  width: number;
};

type TooltipPosition = {
  left: number;
  top: number;
  width: number;
};

const VIEWPORT_GAP = 16;
const TARGET_PADDING = 8;
const TOOLTIP_MAX_WIDTH = 368;
const TOOLTIP_ESTIMATED_HEIGHT = 286;

function isVisible(element: HTMLElement): boolean {
  const style = window.getComputedStyle(element);
  const rect = element.getBoundingClientRect();

  return (
    style.display !== 'none' &&
    style.visibility !== 'hidden' &&
    Number(style.opacity) !== 0 &&
    rect.width > 8 &&
    rect.height > 8 &&
    rect.right > 0 &&
    rect.left < window.innerWidth
  );
}

function findVisible(selectors: string[]): HTMLElement | null {
  for (const selector of selectors) {
    const elements = document.querySelectorAll<HTMLElement>(selector);
    for (const element of elements) {
      if (isVisible(element)) {
        return element;
      }
    }
  }

  return null;
}

function sectionForPage(pageKey: string, sectionKey: string): SectionKey {
  const component = pageKey.toLowerCase();

  if (component.startsWith('auth/')) return 'login';
  if (component === 'dashboard') return 'dashboard';
  if (component.startsWith('accounting/')) return 'accounting';
  if (
    component.startsWith('customers/') ||
    component.startsWith('customeropeningbalances/') ||
    component.startsWith('customerreceipts/') ||
    component.startsWith('receivableallocations/') ||
    component === 'sales/receivablesettlements'
  ) {
    return 'receivables';
  }
  if (
    component.startsWith('suppliers/') ||
    component.startsWith('supplieropeningbalances/') ||
    component.startsWith('supplierpayments/') ||
    component.startsWith('payableallocations/') ||
    component === 'purchasing/payablesettlements'
  ) {
    return 'payables';
  }
  if (component.startsWith('expenses/')) return 'expenses';
  if (component.startsWith('payroll/')) return 'payroll';
  if (component.startsWith('rentals/')) return 'rentals';
  if (
    component.startsWith('cashaccounts/') ||
    component.startsWith('bankaccounts/') ||
    component.startsWith('bankreconciliations/') ||
    component.startsWith('incomingcheques/') ||
    component.startsWith('outgoingcheques/') ||
    component.startsWith('treasurytransfers/')
  ) {
    return 'treasury';
  }
  if (component.startsWith('catalog/') || component.startsWith('sales/')) return 'sales';
  if (component.startsWith('purchasing/')) return 'purchasing';
  if (component.startsWith('inventory/')) return 'inventory';
  if (component.startsWith('fixedassets/')) return 'fixedAssets';
  if (component.startsWith('reports/')) return 'reports';
  if (component.startsWith('taxes/')) return 'taxes';
  if (
    component.startsWith('projects/') ||
    component.startsWith('costcenters/') ||
    component.startsWith('budgeting/')
  ) {
    return 'projects';
  }
  if (component.startsWith('settings/') || component.startsWith('auditlog/')) return 'settings';
  if (component === 'notifications') return 'notifications';
  if (component === 'foundation') return 'diagnostics';

  const key = sectionKey.toLowerCase();
  if (key === 'login') return 'login';
  if (key === 'dashboard') return 'dashboard';
  if (key.startsWith('accounting')) return 'accounting';
  if (
    key.startsWith('customer-invoices') ||
    key.startsWith('customer-credit-notes') ||
    key.startsWith('products') ||
    key.startsWith('product-categories') ||
    key.startsWith('uoms') ||
    key.startsWith('sales') ||
    key.startsWith('delivery') ||
    key.startsWith('invoice-revisions')
  ) {
    return 'sales';
  }
  if (
    key.startsWith('supplier-bills') ||
    key.startsWith('supplier-adjustment-notes') ||
    key.startsWith('purchase') ||
    key.startsWith('goods-receipts') ||
    key.startsWith('landed-costs')
  ) {
    return 'purchasing';
  }
  if (key.startsWith('customer') || key.startsWith('receivable')) return 'receivables';
  if (key.startsWith('supplier') || key.startsWith('payable')) return 'payables';
  if (key.startsWith('expense')) return 'expenses';
  if (key.startsWith('payroll')) return 'payroll';
  if (key.startsWith('rentals')) return 'rentals';
  if (
    key.startsWith('cash') ||
    key.startsWith('bank') ||
    key.startsWith('incoming-cheques') ||
    key.startsWith('outgoing-cheques') ||
    key.startsWith('treasury')
  ) {
    return 'treasury';
  }
  if (key.startsWith('inventory') || key.startsWith('warehouses') || key.startsWith('stock')) return 'inventory';
  if (key.startsWith('fixed-asset')) return 'fixedAssets';
  if (key.startsWith('reports')) return 'reports';
  if (key.startsWith('taxes')) return 'taxes';
  if (key.startsWith('projects') || key.startsWith('cost-centers') || key.startsWith('budgeting')) return 'projects';
  if (key.startsWith('settings') || key.startsWith('audit')) return 'settings';
  if (key.startsWith('notifications')) return 'notifications';
  if (key.startsWith('foundation')) return 'diagnostics';

  return 'general';
}

function clamp(value: number, min: number, max: number): number {
  return Math.min(Math.max(value, min), Math.max(min, max));
}

function calculateTooltipPosition(rect: DOMRect): TooltipPosition {
  const width = Math.min(TOOLTIP_MAX_WIDTH, window.innerWidth - VIEWPORT_GAP * 2);
  const maxTop = window.innerHeight - TOOLTIP_ESTIMATED_HEIGHT - VIEWPORT_GAP;

  if (rect.right + VIEWPORT_GAP + width <= window.innerWidth) {
    return {
      left: rect.right + VIEWPORT_GAP,
      top: clamp(rect.top, VIEWPORT_GAP, maxTop),
      width,
    };
  }

  if (rect.left - VIEWPORT_GAP - width >= 0) {
    return {
      left: rect.left - VIEWPORT_GAP - width,
      top: clamp(rect.top, VIEWPORT_GAP, maxTop),
      width,
    };
  }

  const belowTop = rect.bottom + VIEWPORT_GAP;
  const top = belowTop + TOOLTIP_ESTIMATED_HEIGHT <= window.innerHeight
    ? belowTop
    : rect.top - TOOLTIP_ESTIMATED_HEIGHT - VIEWPORT_GAP;

  return {
    left: clamp(
      rect.left + rect.width / 2 - width / 2,
      VIEWPORT_GAP,
      window.innerWidth - width - VIEWPORT_GAP,
    ),
    top: clamp(top, VIEWPORT_GAP, maxTop),
    width,
  };
}

export default function TourGuide({
  copy,
  locale,
  pageKey,
  sectionKey = pageKey,
  variant = 'app',
}: TourGuideProps) {
  const [isOpen, setIsOpen] = useState(false);
  const [steps, setSteps] = useState<TourStep[]>([]);
  const [currentStep, setCurrentStep] = useState(0);
  const [highlightRect, setHighlightRect] = useState<HighlightRect | null>(null);
  const [tooltipPosition, setTooltipPosition] = useState<TooltipPosition | null>(null);
  const triggerRef = useRef<HTMLButtonElement>(null);
  const nextButtonRef = useRef<HTMLButtonElement>(null);
  const isOpenRef = useRef(false);
  const isRtl = locale === 'ar';

  const closeTour = useCallback(() => {
    const shouldRestoreFocus = isOpenRef.current;
    isOpenRef.current = false;
    setIsOpen(false);
    setHighlightRect(null);
    setTooltipPosition(null);
    if (shouldRestoreFocus) {
      window.requestAnimationFrame(() => triggerRef.current?.focus());
    }
  }, []);

  const buildSteps = useCallback((): TourStep[] => {
    const nextSteps: TourStep[] = [];
    const usedTargets = new Set<HTMLElement>();
    const addStep = (
      id: string,
      target: HTMLElement | null,
      title: string,
      description: string,
    ) => {
      if (!target || usedTargets.has(target)) return;
      usedTargets.add(target);
      nextSteps.push({ id, target, title, description });
    };

    if (variant === 'login') {
      addStep(
        'login-brand',
        findVisible(['[data-tour="login-brand"]']),
        copy.login.brandTitle,
        copy.login.brandDescription,
      );
      addStep(
        'login-form',
        findVisible(['[data-tour="login-form"]']),
        copy.login.formTitle,
        copy.login.formDescription,
      );
      addStep(
        'login-preferences',
        findVisible(['[data-tour="login-preferences"]']),
        copy.login.preferencesTitle,
        copy.login.preferencesDescription,
      );
      addStep(
        'login-features',
        findVisible(['[data-tour="login-features"]']),
        copy.login.featuresTitle,
        copy.login.featuresDescription,
      );

      return nextSteps;
    }

    const pageHeading = findVisible([
      '[data-tour="page-header"] h1',
      '[data-tour="page-content"] h1',
      '[data-tour="page-content"] h2',
    ]);
    const pageTarget = findVisible(['[data-tour="page-header"]']) || pageHeading ||
      findVisible(['[data-tour="page-content"]']);
    const pageTitle = pageHeading?.textContent?.trim() ||
      document.title.split(' - ')[0]?.trim() ||
      copy.fallbackPageTitle;
    const sectionDescription = copy.sections[sectionForPage(pageKey, sectionKey)];

    addStep(
      'page',
      pageTarget,
      copy.steps.page.title,
      interpolate(copy.steps.page.description, {
        page: pageTitle,
        section: sectionDescription,
      }),
    );

    addStep(
      'actions',
      findVisible(['[data-tour="page-actions"]']),
      copy.steps.actions.title,
      copy.steps.actions.description,
    );

    addStep(
      'filters',
      findVisible([
        '[data-tour="filters"]',
        '[data-tour="page-content"] form',
      ]),
      copy.steps.filters.title,
      copy.steps.filters.description,
    );

    const recordsTarget = findVisible([
      '[data-tour="records"]',
      '[data-tour="page-content"] .accounting-table-wrap',
      '[data-tour="page-content"] table',
    ]);

    if (recordsTarget) {
      addStep(
        'records',
        recordsTarget,
        copy.steps.records.title,
        copy.steps.records.description,
      );
    } else {
      addStep(
        'workspace',
        findVisible([
          '[data-tour="workspace"]',
          '[data-tour="page-content"] section',
          '[data-tour="page-content"] [class*="grid"]',
        ]),
        copy.steps.workspace.title,
        copy.steps.workspace.description,
      );
    }

    addStep(
      'navigation',
      findVisible(['[data-tour="sidebar"]']),
      copy.steps.navigation.title,
      copy.steps.navigation.description,
    );

    addStep(
      'topbar',
      findVisible(['[data-tour="topbar"]']),
      copy.steps.topbar.title,
      copy.steps.topbar.description,
    );

    return nextSteps;
  }, [copy, pageKey, sectionKey, variant]);

  const startTour = useCallback(() => {
    const nextSteps = buildSteps();
    if (nextSteps.length === 0) return;

    setSteps(nextSteps);
    setCurrentStep(0);
    isOpenRef.current = true;
    setIsOpen(true);
  }, [buildSteps]);

  const updatePosition = useCallback(() => {
    const target = steps[currentStep]?.target;
    if (!target || !document.body.contains(target)) {
      closeTour();
      return;
    }

    const rect = target.getBoundingClientRect();
    const left = Math.max(4, rect.left - TARGET_PADDING);
    const top = Math.max(4, rect.top - TARGET_PADDING);

    setHighlightRect({
      height: Math.min(window.innerHeight - top - 4, rect.height + TARGET_PADDING * 2),
      left,
      top,
      width: Math.min(window.innerWidth - left - 4, rect.width + TARGET_PADDING * 2),
    });
    setTooltipPosition(calculateTooltipPosition(rect));
  }, [closeTour, currentStep, steps]);

  useEffect(() => {
    if (!isOpen) return;

    const target = steps[currentStep]?.target;
    if (!target) return;

    const currentRect = target.getBoundingClientRect();
    if (currentRect.top < 76 || currentRect.bottom > window.innerHeight - 24) {
      const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      target.scrollIntoView({
        behavior: reduceMotion ? 'auto' : 'smooth',
        block: 'center',
        inline: 'nearest',
      });
    }

    const timer = window.setTimeout(updatePosition, 220);
    window.addEventListener('resize', updatePosition);
    window.addEventListener('scroll', updatePosition, true);

    return () => {
      window.clearTimeout(timer);
      window.removeEventListener('resize', updatePosition);
      window.removeEventListener('scroll', updatePosition, true);
    };
  }, [currentStep, isOpen, steps, updatePosition]);

  useEffect(() => {
    if (!isOpen) return;

    const timer = window.setTimeout(() => nextButtonRef.current?.focus(), 260);
    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        closeTour();
        return;
      }

      if (event.key === 'Tab') {
        const dialog = document.querySelector<HTMLElement>('[data-tour-dialog]');
        const controls = dialog
          ? Array.from(dialog.querySelectorAll<HTMLButtonElement>('button:not(:disabled)'))
          : [];
        const firstControl = controls[0];
        const lastControl = controls[controls.length - 1];

        if (firstControl && lastControl) {
          if (event.shiftKey && document.activeElement === firstControl) {
            event.preventDefault();
            lastControl.focus();
          } else if (!event.shiftKey && document.activeElement === lastControl) {
            event.preventDefault();
            firstControl.focus();
          }
        }
      }

      const moveForward = (!isRtl && event.key === 'ArrowRight') ||
        (isRtl && event.key === 'ArrowLeft');
      const moveBackward = (!isRtl && event.key === 'ArrowLeft') ||
        (isRtl && event.key === 'ArrowRight');

      if (moveForward) {
        event.preventDefault();
        setCurrentStep((stepIndex) => Math.min(stepIndex + 1, steps.length - 1));
      }

      if (moveBackward) {
        event.preventDefault();
        setCurrentStep((stepIndex) => Math.max(stepIndex - 1, 0));
      }
    };

    document.addEventListener('keydown', handleKeyDown);
    return () => {
      window.clearTimeout(timer);
      document.removeEventListener('keydown', handleKeyDown);
    };
  }, [closeTour, isOpen, isRtl, steps.length]);

  useEffect(() => {
    closeTour();
  }, [closeTour, pageKey]);

  const step = steps[currentStep];
  const isLastStep = currentStep === steps.length - 1;
  const highlightStyle: CSSProperties | undefined = highlightRect
    ? {
        height: highlightRect.height,
        left: highlightRect.left,
        top: highlightRect.top,
        width: highlightRect.width,
      }
    : undefined;
  const tooltipStyle: CSSProperties | undefined = tooltipPosition
    ? {
        left: tooltipPosition.left,
        top: tooltipPosition.top,
        width: tooltipPosition.width,
      }
    : undefined;

  return (
    <>
      <button
        ref={triggerRef}
        type="button"
        data-tour="tour-button"
        onClick={startTour}
        title={copy.start}
        aria-label={copy.start}
        className="inline-flex h-9 items-center justify-center gap-2 rounded-xl border border-blue-500/30 bg-blue-500/10 px-2.5 text-xs font-extrabold text-blue-700 shadow-xs transition-all hover:border-blue-500 hover:bg-blue-500 hover:text-white dark:text-blue-300 sm:px-3"
      >
        <svg className="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2} aria-hidden="true">
          <path strokeLinecap="round" strokeLinejoin="round" d="M12 18h.01M9.172 9a3 3 0 115.656 1.408c-.724.836-1.828 1.267-2.314 2.264-.172.352-.264.75-.264 1.328M12 3a9 9 0 100 18 9 9 0 000-18z" />
        </svg>
        <span className="hidden xl:inline">{copy.start}</span>
      </button>

      {isOpen && step ? (
        <div dir={isRtl ? 'rtl' : 'ltr'}>
          <button
            type="button"
            className="fixed inset-0 z-[70] cursor-default"
            onClick={closeTour}
            title={copy.close}
            aria-label={copy.close}
          />

          {highlightStyle ? (
            <div
              className="pointer-events-none fixed z-[80] rounded-xl border-2 border-blue-400 bg-transparent shadow-[0_0_0_9999px_rgba(2,6,23,0.72)] ring-4 ring-blue-400/25 transition-all duration-200"
              style={highlightStyle}
              aria-hidden="true"
            />
          ) : null}

          {tooltipStyle ? (
            <aside
              data-tour-dialog
              role="dialog"
              aria-modal="true"
              aria-label={copy.dialogLabel}
              aria-live="polite"
              className="fixed z-[90] overflow-hidden rounded-2xl border border-blue-400/30 bg-[var(--surface)] text-[var(--text-primary)] shadow-2xl shadow-slate-950/40"
              style={tooltipStyle}
            >
              <div className="flex items-start justify-between gap-4 border-b border-[var(--border)] bg-gradient-to-r from-blue-600/10 to-indigo-500/5 px-5 py-4">
                <div className="min-w-0">
                  <span className="inline-flex rounded-full bg-blue-500/15 px-2.5 py-1 text-[10px] font-extrabold text-blue-700 dark:text-blue-300">
                    {interpolate(copy.stepCounter, {
                      current: currentStep + 1,
                      total: steps.length,
                    })}
                  </span>
                  <h2 className="mt-2 text-base font-extrabold leading-6 text-[var(--text-primary)]">
                    {step.title}
                  </h2>
                </div>
                <button
                  type="button"
                  onClick={closeTour}
                  title={copy.close}
                  aria-label={copy.close}
                  className="flex size-8 shrink-0 items-center justify-center rounded-lg text-[var(--text-muted)] transition-colors hover:bg-[var(--background)] hover:text-[var(--text-primary)]"
                >
                  <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5} aria-hidden="true">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>

              <div className="px-5 py-4">
                <p className="m-0 text-sm leading-7 text-[var(--text-secondary)]">
                  {step.description}
                </p>

                <div className="mt-4 flex gap-1.5" aria-hidden="true">
                  {steps.map((tourStep, index) => (
                    <span
                      key={tourStep.id}
                      className={`h-1.5 flex-1 rounded-full transition-colors ${
                        index <= currentStep ? 'bg-blue-500' : 'bg-[var(--border)]'
                      }`}
                    />
                  ))}
                </div>
              </div>

              <div className="flex items-center justify-between gap-3 border-t border-[var(--border)] bg-[var(--background)]/60 px-5 py-3.5">
                <button
                  type="button"
                  onClick={() => setCurrentStep((index) => Math.max(index - 1, 0))}
                  disabled={currentStep === 0}
                  title={copy.previous}
                  className="inline-flex items-center gap-1.5 rounded-xl border border-[var(--border)] bg-[var(--surface)] px-3.5 py-2 text-xs font-bold text-[var(--text-secondary)] transition-colors hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-40"
                >
                  <svg className={`size-3.5 ${isRtl ? 'rotate-180' : ''}`} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5} aria-hidden="true">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7" />
                  </svg>
                  {copy.previous}
                </button>

                <button
                  ref={nextButtonRef}
                  type="button"
                  onClick={() => {
                    if (isLastStep) {
                      closeTour();
                    } else {
                      setCurrentStep((index) => Math.min(index + 1, steps.length - 1));
                    }
                  }}
                  title={isLastStep ? copy.finish : copy.next}
                  className="inline-flex items-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2 text-xs font-extrabold text-white shadow-md shadow-blue-500/20 transition-colors hover:bg-blue-700"
                >
                  {isLastStep ? copy.finish : copy.next}
                  <svg className={`size-3.5 ${isRtl ? 'rotate-180' : ''}`} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5} aria-hidden="true">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
                  </svg>
                </button>
              </div>
            </aside>
          ) : null}
        </div>
      ) : null}
    </>
  );
}
