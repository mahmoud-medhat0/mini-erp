import { Link, router, useForm, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState, type FormEvent, type ReactNode } from 'react';

import { changeLocale, getDictionary } from '../lib/i18n';
import { useCan } from '../lib/permissions';
import type { SharedPageProps } from '../Types/page';
import TourGuide from './TourGuide';
import UniversalPagination from './UniversalPagination';

export type NavKey =
  | 'dashboard'
  | 'settings'
  | 'settings.company'
  | 'settings.branches'
  | 'settings.numbering'
  | 'settings.users'
  | 'settings.branch_approval_rules'
  | 'audit.view'
  | 'notifications'
  | 'foundation'
  | 'accounting.index'
  | 'accounting.coa'
  | 'accounting.journal'
  | 'accounting.ledger'
  | 'accounting.trial_balance'
  | 'accounting.periods'
  | 'accounting.opening_balances'
  | 'accounting.fx_rates'
  | 'accounting.currencies'
  | 'accounting.account_types'
  | 'accounting.account_categories'
  | 'accounting.statement_mappings'
  | 'accounting.account_mappings'
  | 'customers.index'
  | 'suppliers.index'
  | 'expenses.index'
  | 'expense-categories.index'
  | 'prepaid-schedules.index'
  | 'accrual-schedules.index'
  | 'payroll.employees.index'
  | 'payroll.components.index'
  | 'payroll.runs.index'
  | 'rentals.contracts.index'
  | 'rentals.invoices.index'
  | 'rentals.handovers.index'
  | 'rentals.returns.index'
  | 'rentals.items.index'
  | 'cash-accounts.index'
  | 'bank-accounts.index'
  | 'treasury-transfers.index'
  | 'customer-opening-balances.index'
  | 'supplier-opening-balances.index'
  | 'customer-receipts.index'
  | 'supplier-payments.index'
  | 'receivable-allocations.index'
  | 'payable-allocations.index'
  | 'incoming-cheques.index'
  | 'outgoing-cheques.index'
  | 'bank-reconciliations.index'
  | 'bank-reconciliations.show'
  | 'products.index'
  | 'product-categories.index'
  | 'uoms.index'
  | 'sales-orders.index'
  | 'delivery-notes.index'
  | 'customer-invoices.index'
  | 'sales-returns.index'
  | 'customer-credit-notes.index'
  | 'invoice-revisions.index'
  | 'invoice-revisions.show'
  | 'purchase-orders.index'
  | 'goods-receipts.index'
  | 'landed-costs.index'
  | 'purchase-returns.index'
  | 'supplier-adjustment-notes.index'
  | 'supplier-bills.index'
  | 'warehouses.index'
  | 'stock-transfers.index'
  | 'stock-counts.index'
  | 'stock-adjustments.index'
  | 'stock-balances.index'
  | 'reports.index'
  | 'reports.customer-statement'
  | 'reports.supplier-statement'
  | 'reports.ar-aging'
  | 'reports.ap-aging'
  | 'reports.cash-book'
  | 'reports.bank-book'
  | 'reports.cheque-register'
  | 'reports.bank-reconciliations'
  | 'reports.sales-orders'
  | 'reports.purchase-orders'
  | 'reports.delivery-notes'
  | 'reports.goods-receipts'
  | 'reports.customer-invoices'
  | 'reports.supplier-bills'
  | 'reports.stock-movements'
  | 'reports.branch-operations'
  | 'reports.branch-profitability'
  | 'reports.project-profitability'
  | 'reports.cost-center-actuals'
  | 'reports.rentals'
  | 'reports.balance_sheet'
  | 'reports.income_statement'
  | 'reports.cash_flow'
  | 'reports.ar-gl-reconciliation'
  | 'reports.ap-gl-reconciliation'
  | 'reports.vat-register'
  | 'reports.vat-summary'
  | 'reports.vat-gl-reconciliation'
  | 'fixed-assets.index'
  | 'fixed-asset-categories.index'
  | 'fixed-asset-locations.index'
  | 'fixed-assets.depreciation-runs.index'
  | 'fixed-assets-disposals.index'
  | 'taxes.codes.index'
  | 'taxes.rates.index'
  | 'taxes.periods.index'
  | 'taxes.periods.show'
  | 'projects.index'
  | 'cost-centers.index'
  | 'budgeting.budgets'
  | 'budgeting.variance';

type AppLayoutProps = {
  active: NavKey;
  children: ReactNode;
  pagination?: 'auto' | 'manual' | 'none';
};

type FlashTone = 'success' | 'error';

type FlashNotice = {
  id: string;
  message: string;
  tone: FlashTone;
};

function buildFlashNotices(
  flash: SharedPageProps['flash'] | null | undefined,
  idPrefix: string,
): FlashNotice[] {
  const notices: FlashNotice[] = [];

  if (typeof flash?.success === 'string' && flash.success.trim() !== '') {
    notices.push({ id: `${idPrefix}-success`, message: flash.success, tone: 'success' });
  }

  if (typeof flash?.error === 'string' && flash.error.trim() !== '') {
    notices.push({ id: `${idPrefix}-error`, message: flash.error, tone: 'error' });
  }

  return notices;
}

function FlashFeedback({
  notice,
  dismissLabel,
  onDismiss,
  onManualDismiss,
}: {
  notice: FlashNotice;
  dismissLabel: string;
  onDismiss: (id: string) => void;
  onManualDismiss: (id: string, restoreFocus: boolean) => void;
}) {
  const dismissTimer = useRef<number | null>(null);
  const isError = notice.tone === 'error';
  const autoDismissAfter = isError ? 10_000 : 6_000;

  const clearDismissTimer = useCallback(() => {
    if (dismissTimer.current !== null) {
      window.clearTimeout(dismissTimer.current);
      dismissTimer.current = null;
    }
  }, []);

  const scheduleDismiss = useCallback(() => {
    clearDismissTimer();
    dismissTimer.current = window.setTimeout(() => onDismiss(notice.id), autoDismissAfter);
  }, [autoDismissAfter, clearDismissTimer, notice.id, onDismiss]);

  useEffect(() => {
    scheduleDismiss();

    return clearDismissTimer;
  }, [clearDismissTimer, scheduleDismiss]);

  return (
    <div
      data-flash-feedback
      data-flash-tone={notice.tone}
      role={isError ? 'alert' : 'status'}
      aria-live={isError ? 'assertive' : 'polite'}
      aria-atomic="true"
      onMouseEnter={clearDismissTimer}
      onMouseLeave={scheduleDismiss}
      onFocusCapture={clearDismissTimer}
      onBlurCapture={(event) => {
        const nextFocus = event.relatedTarget;
        if (!(nextFocus instanceof Node) || !event.currentTarget.contains(nextFocus)) {
          scheduleDismiss();
        }
      }}
      className={`pointer-events-auto flex items-start gap-3 rounded-2xl border p-4 shadow-xl shadow-slate-950/15 backdrop-blur-xl motion-safe:animate-in motion-safe:fade-in motion-safe:slide-in-from-top-2 ${
        isError
          ? 'border-red-500/30 bg-red-50/95 text-red-900 dark:bg-red-950/95 dark:text-red-100'
          : 'border-emerald-500/30 bg-emerald-50/95 text-emerald-900 dark:bg-emerald-950/95 dark:text-emerald-100'
      }`}
    >
      <span
        aria-hidden="true"
        className={`mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full ${
          isError
            ? 'bg-red-600 text-white dark:bg-red-500'
            : 'bg-emerald-600 text-white dark:bg-emerald-500'
        }`}
      >
        {isError ? (
          <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
          </svg>
        ) : (
          <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
          </svg>
        )}
      </span>

      <p dir="auto" className="m-0 min-w-0 flex-1 break-words pt-1 text-sm font-semibold leading-6">
        {notice.message}
      </p>

      <button
        data-flash-dismiss
        type="button"
        aria-label={dismissLabel}
        title={dismissLabel}
        onClick={(event) => onManualDismiss(notice.id, event.detail === 0)}
        className="flex size-8 shrink-0 items-center justify-center rounded-lg text-current opacity-70 transition hover:bg-black/5 hover:opacity-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-current focus-visible:ring-offset-2 focus-visible:ring-offset-transparent dark:hover:bg-white/10"
      >
        <span aria-hidden="true" className="text-xl leading-none">&times;</span>
      </button>
    </div>
  );
}

type NavPermission = string | string[];

const NAV_PERMS: Partial<Record<NavKey, NavPermission>> = {
  settings: [
    'settings.view',
    'settings.configure',
    'settings.company',
    'settings.branches',
    'settings.numbering',
    'users.configure',
    'approvals.configure',
    'audit.view',
  ],
  'settings.company': ['settings.company', 'settings.configure'],
  'settings.branches': ['settings.branches', 'settings.configure'],
  'settings.numbering': ['settings.numbering', 'settings.configure'],
  'settings.users': ['users.configure', 'settings.configure'],
  'projects.index': 'projects.view',
  'cost-centers.index': 'costCenters.view',
  'budgeting.budgets': 'budgeting.view',
  'budgeting.variance': 'budgeting.view',
  'accounting.index': 'accounting.view',
  'taxes.codes.index': 'taxes.view',
  'taxes.rates.index': 'taxes.view',
  'taxes.periods.index': 'taxes.view',
  'taxes.periods.show': 'taxes.view',
  'accounting.coa': 'accounting.view',
  'accounting.journal': 'accounting.view',
  'accounting.ledger': 'accounting.view',
  'accounting.trial_balance': 'accounting.view',
  'accounting.opening_balances': 'accounting.view',
  'accounting.fx_rates': 'accounting.view',
  'accounting.currencies': 'accounting.view',
  'accounting.periods': ['accounting.view', 'accounting.periods', 'settings.configure'],
  'accounting.account_types': 'accounting.account_types',
  'accounting.account_categories': 'accounting.account_categories',
  'accounting.statement_mappings': 'accounting.mappings',
  'accounting.account_mappings': 'accounting.mappings',
  'settings.branch_approval_rules': 'approvals.configure',
  'fixed-assets.index': 'fixedAssets.view',
  'fixed-asset-categories.index': 'fixedAssets.view',
  'fixed-asset-locations.index': 'fixedAssets.view',
  'fixed-assets.depreciation-runs.index': 'fixedAssets.view',
  'fixed-assets-disposals.index': 'fixedAssets.view',
  'customers.index': 'customers.view',
  'customer-opening-balances.index': 'customers.view',
  'customer-receipts.index': 'customers.view',
  'receivable-allocations.index': 'customers.view',
  'suppliers.index': 'suppliers.view',
  'supplier-opening-balances.index': 'suppliers.view',
  'supplier-payments.index': 'suppliers.view',
  'payable-allocations.index': 'suppliers.view',
  'expenses.index': 'expenses.view',
  'expense-categories.index': 'expenses.view',
  'prepaid-schedules.index': 'expenses.view',
  'accrual-schedules.index': 'expenses.view',
  'payroll.employees.index': 'view_payroll',
  'payroll.components.index': 'view_payroll',
  'payroll.runs.index': 'view_payroll',
  'rentals.contracts.index': 'rentals.view',
  'rentals.invoices.index': 'rentals.view',
  'rentals.handovers.index': 'rentals.view',
  'rentals.returns.index': 'rentals.view',
  'rentals.items.index': 'rentals.view',
  'cash-accounts.index': 'cash.view',
  'bank-accounts.index': 'banks.view',
  'treasury-transfers.index': 'cash.view',
  'incoming-cheques.index': 'cheques.view',
  'outgoing-cheques.index': 'cheques.view',
  'bank-reconciliations.index': 'banks.view',
  'products.index': 'products.view',
  'product-categories.index': 'products.view',
  'uoms.index': 'uom.view',
  'sales-orders.index': 'sales.view',
  'delivery-notes.index': 'sales.view',
  'customer-invoices.index': 'sales.view',
  'sales-returns.index': 'sales.view',
  'customer-credit-notes.index': 'sales.view',
  'invoice-revisions.index': 'sales.view',
  'purchase-orders.index': 'purchasing.view',
  'goods-receipts.index': 'purchasing.view',
  'landed-costs.index': 'purchasing.landed_costs',
  'purchase-returns.index': 'purchasing.view',
  'supplier-adjustment-notes.index': 'purchasing.view',
  'supplier-bills.index': 'purchasing.view',
  'warehouses.index': 'inventory.view',
  'stock-transfers.index': 'inventory.view',
  'stock-counts.index': 'inventory.view',
  'stock-adjustments.index': 'inventory.view',
  'stock-balances.index': 'inventory.view',
  'reports.index': 'reports.view',
  'reports.balance_sheet': 'view_financials',
  'reports.income_statement': 'view_financials',
  'reports.cash_flow': 'view_financials',
  'reports.branch-operations': 'view_financials',
  'reports.branch-profitability': 'view_financials',
  'reports.rentals': 'view_financials',
  'audit.view': 'audit.view',
};

const NAV_PERMS_FALLBACK: Partial<Record<NavKey, NavPermission>> = {
  'audit.view': 'settings.configure',
  'treasury-transfers.index': 'banks.view',
  'settings.branch_approval_rules': 'settings.configure',
};

export default function AppLayout({ active, children, pagination = 'auto' }: AppLayoutProps) {
  const page = usePage<SharedPageProps>();
  const { props } = page;
  const mainContentRef = useRef<HTMLElement>(null);
  const flashSequence = useRef(0);
  const [flashNotices, setFlashNotices] = useState<FlashNotice[]>(() =>
    buildFlashNotices(props.flash, 'flash-initial'),
  );
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  const [sidebarCollapsed, setSidebarCollapsed] = useState(false);
  const [notifDropdownOpen, setNotifDropdownOpen] = useState(false);
  const [userMenuOpen, setUserMenuOpen] = useState(false);
  const isAccountingActive = active.startsWith('accounting') || active.startsWith('taxes');
  const isArActive = active.startsWith('customer') || active.startsWith('receivable');
  const isApActive = active.startsWith('supplier') || active.startsWith('payable');
  const isExpensesActive = active.startsWith('expense') || active.startsWith('prepaid') || active.startsWith('accrual');
  const isPayrollActive = active.startsWith('payroll');
  const isRentalsActive = active.startsWith('rentals');
  const isCashBankActive = active.includes('cash') || active.includes('bank') || active.includes('cheque') || active.includes('treasury');
  const isCatalogActive = active.startsWith('catalog') || active.includes('product') || active.includes('uom');
  const isInventoryActive = active.includes('inventory') || active.includes('warehouse') || active.includes('stock-');
  const isFixedAssetsActive = active.startsWith('fixed-asset');
  const isProjectsActive = active.startsWith('project') || active.startsWith('cost-center') || active.startsWith('budgeting');
  const isReportsActive = active.startsWith('reports');
  const isAdminActive = active.startsWith('settings') || active.startsWith('audit');

  const [adminExpanded, setAdminExpanded] = useState(() => isAdminActive);
  const [accountingExpanded, setAccountingExpanded] = useState(() => isAccountingActive);
  const [arExpanded, setArExpanded] = useState(() => isArActive);
  const [apExpanded, setApExpanded] = useState(() => isApActive);
  const [expensesExpanded, setExpensesExpanded] = useState(() => isExpensesActive);
  const [payrollExpanded, setPayrollExpanded] = useState(() => isPayrollActive);
  const [rentalsExpanded, setRentalsExpanded] = useState(() => isRentalsActive);
  const [cashBankExpanded, setCashBankExpanded] = useState(() => isCashBankActive);
  const [catalogExpanded, setCatalogExpanded] = useState(() => isCatalogActive);
  const [inventoryExpanded, setInventoryExpanded] = useState(() => isInventoryActive);
  const [fixedAssetsExpanded, setFixedAssetsExpanded] = useState(() => isFixedAssetsActive);
  const [projectsCostCentersExpanded, setProjectsCostCentersExpanded] = useState(() => isProjectsActive);
  const [reportsExpanded, setReportsExpanded] = useState(() => isReportsActive);
  const [currentTheme, setCurrentTheme] = useState<string>(props.theme || 'system');
  const [isOnline, setIsOnline] = useState(() => (typeof navigator !== 'undefined' ? navigator.onLine : true));

  const locale = props.locale === 'ar' ? 'ar' : 'en';
  const isRtl = locale === 'ar';
  const dict = getDictionary(locale);
  const can = useCan();
  const accDict = dict.app.accounting;
  const taxesDict = dict.app.taxes;
  const { post, processing } = useForm({});

  const dismissFlash = useCallback((id: string) => {
    setFlashNotices((current) => current.filter((notice) => notice.id !== id));
  }, []);

  const dismissFlashManually = useCallback((id: string, restoreFocus: boolean) => {
    dismissFlash(id);

    if (restoreFocus) {
      window.requestAnimationFrame(() => mainContentRef.current?.focus({ preventScroll: true }));
    }
  }, [dismissFlash]);

  useEffect(() => router.on('success', (event) => {
    const nextProps = event.detail.page.props as { flash?: SharedPageProps['flash'] };
    flashSequence.current += 1;
    setFlashNotices(buildFlashNotices(nextProps.flash, `flash-${flashSequence.current}`));
  }), []);

  // Real live health check pinging backend /health endpoint
  useEffect(() => {
    let mounted = true;

    async function pingHealth() {
      if (typeof navigator !== 'undefined' && !navigator.onLine) {
        if (mounted) setIsOnline(false);
        return;
      }

      try {
        const response = await fetch('/health', {
          method: 'GET',
          headers: { Accept: 'application/json' },
          cache: 'no-store',
        });

        if (response.ok) {
          const data = await response.json();
          if (mounted) setIsOnline(data.status === 'ok' && data.database === 'ok');
        } else {
          if (mounted) setIsOnline(false);
        }
      } catch {
        if (mounted) setIsOnline(false);
      }
    }

    pingHealth();
    const interval = setInterval(pingHealth, 15000);

    window.addEventListener('online', pingHealth);
    window.addEventListener('offline', pingHealth);

    return () => {
      mounted = false;
      clearInterval(interval);
      window.removeEventListener('online', pingHealth);
      window.removeEventListener('offline', pingHealth);
    };
  }, []);

  // Load persistent sidebar collapse state from localStorage on mount
  useEffect(() => {
    const savedCollapse = localStorage.getItem('sidebar_collapsed');
    if (savedCollapse !== null) {
      setSidebarCollapsed(savedCollapse === 'true');
    }
  }, []);

  function toggleSidebarCollapse() {
    setSidebarCollapsed((prev) => {
      const next = !prev;
      localStorage.setItem('sidebar_collapsed', String(next));
      return next;
    });
  }

  function handleThemeChange(newTheme: 'light' | 'dark' | 'system') {
    setCurrentTheme(newTheme);
    if (newTheme === 'system') {
      const isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
      document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');
    } else {
      document.documentElement.setAttribute('data-theme', newTheme);
    }
  }

  function logout(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    post('/logout');
  }

  const unreadNotifications = props.notifications?.unreadCount ?? 0;
  const userInitials = props.auth.user?.name
    ? props.auth.user.name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .substring(0, 2)
        .toUpperCase()
    : 'ERP';

  const isSettingsActive = active.startsWith('settings');

  const hasNavPermission = (permission: NavPermission): boolean => {
    if (Array.isArray(permission)) {
      return permission.some((item) => hasNavPermission(item));
    }
    if (permission === 'view_financials') {
      return can('reports.view') && can('view_financials');
    }

    return can(permission);
  };

  const navAllowed = (key: NavKey): boolean => {
    if (key === 'budgeting.budgets') {
      return can('budgeting.view') && can('view_financials');
    }
    if (key === 'budgeting.variance') {
      return can('budgeting.view') && can('reports.view') && can('view_financials');
    }
    const primary = NAV_PERMS[key];
    if (!primary) return true;
    if (hasNavPermission(primary)) return true;
    const fallback = NAV_PERMS_FALLBACK[key];
    return !!fallback && hasNavPermission(fallback);
  };

  const showAccountingGroup =
    can('accounting.view') ||
    can('accounting.periods') ||
    can('settings.configure') ||
    can('accounting.account_types') ||
    can('accounting.account_categories') ||
    can('accounting.mappings');
  const showArGroup = can('customers.view');
  const showApGroup = can('suppliers.view');
  const showExpensesGroup = can('expenses.view');
  const showPayrollGroup = can('payroll.view') && can('view_payroll');
  const showRentalsGroup = can('rentals.view');
  const showCashBankGroup = can('cash.view') || can('banks.view') || can('cheques.view');
  const showCatalogGroup =
    can('products.view') || can('uom.view') || can('sales.view') || can('purchasing.view') || can('purchasing.landed_costs');
  const showInventoryGroup = can('inventory.view');
  const showFixedAssetsGroup = can('fixedAssets.view');
  const showProjectsCostCentersGroup = can('projects.view') || can('costCenters.view') || (can('budgeting.view') && can('view_financials'));
  const showReportsGroup = can('reports.view');
  const showAdministrationGroup = navAllowed('settings');

  return (
    <div className="min-h-screen bg-[var(--background)] text-[var(--text-primary)] transition-colors duration-200">
      <div
        data-flash-region
        className="pointer-events-none fixed end-4 top-20 z-[70] flex w-[min(calc(100vw-2rem),28rem)] flex-col gap-3"
      >
        {flashNotices.map((notice) => (
          <FlashFeedback
            key={notice.id}
            notice={notice}
            dismissLabel={isRtl ? 'إغلاق الرسالة' : 'Dismiss message'}
            onDismiss={dismissFlash}
            onManualDismiss={dismissFlashManually}
          />
        ))}
      </div>

      <div className="flex min-h-screen flex-col lg:flex-row">
        {/* Mobile backdrop overlay */}
        {mobileMenuOpen ? (
          <div
            onClick={() => setMobileMenuOpen(false)}
            className="fixed inset-0 z-40 bg-slate-900/60 backdrop-blur-xs lg:hidden"
          />
        ) : null}

        {/* Collapsible Sidebar Container */}
        <aside
          data-tour="sidebar"
          className={`fixed inset-y-0 start-0 z-50 flex flex-col border-e border-[var(--border)] bg-[var(--surface)] transition-all duration-300 ease-in-out lg:static ${
            sidebarCollapsed ? 'lg:w-20' : 'lg:w-64'
          } ${
            mobileMenuOpen
              ? 'w-72 translate-x-0'
              : isRtl
                ? 'w-72 translate-x-full lg:translate-x-0'
                : 'w-72 -translate-x-full lg:translate-x-0'
          }`}
        >
          {/* Brand Header & Desktop Collapse Toggle */}
          <div className={`flex h-16 items-center border-b border-[var(--border)] px-3.5 ${sidebarCollapsed ? 'justify-center' : 'justify-between'}`}>
            {!sidebarCollapsed ? (
              <>
                <Link href="/dashboard" className="flex items-center gap-3 no-underline overflow-hidden">
                  <div className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 text-white shadow-md shadow-blue-500/30">
                    <svg className="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                      <path strokeLinecap="round" strokeLinejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                  </div>
                  <div className="flex flex-col whitespace-nowrap transition-opacity duration-200">
                    <span className="text-base font-extrabold tracking-tight text-[var(--text-primary)]">Mini ERP</span>
                    <span className="text-[10px] font-semibold text-[var(--text-muted)] uppercase tracking-wider">Enterprise OS</span>
                  </div>
                </Link>

                {/* Desktop Collapse Toggle Button */}
                <button
                  type="button"
                  onClick={toggleSidebarCollapse}
                  title={dict.app.header.collapseSidebar}
                  className="hidden lg:flex size-8 items-center justify-center rounded-lg border border-[var(--border)] bg-[var(--background)] text-[var(--text-muted)] hover:text-[var(--text-primary)] hover:border-[var(--primary)] transition-all"
                >
                  <svg
                    className={`size-4 transition-transform duration-200 ${isRtl ? '' : 'rotate-180'}`}
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    strokeWidth={2}
                  >
                    <path strokeLinecap="round" strokeLinejoin="round" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
                  </svg>
                </button>
              </>
            ) : (
              <button
                type="button"
                onClick={toggleSidebarCollapse}
                title={dict.app.header.expandSidebar}
                className="group relative flex size-10 items-center justify-center rounded-xl bg-gradient-to-tr from-blue-600 to-indigo-500 text-white shadow-md shadow-blue-500/20 transition-all hover:scale-110 active:scale-95"
              >
                {/* Logo icon by default */}
                <svg className="size-5 transition-all group-hover:scale-0 group-hover:opacity-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
                {/* Expand arrow on hover */}
                <svg className={`absolute size-5 transition-all scale-0 opacity-0 group-hover:scale-100 group-hover:opacity-100 ${isRtl ? 'rotate-180' : ''}`} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M13 5l7 7-7 7M5 5l7 7-7 7" />
                </svg>
              </button>
            )}

            {/* Mobile Close Button */}
            <button
              type="button"
              onClick={() => setMobileMenuOpen(false)}
              className="rounded-lg p-1.5 text-[var(--text-muted)] hover:bg-[var(--background)] hover:text-[var(--text-primary)] lg:hidden"
            >
              <svg className="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          {/* Navigation Items List */}
          <div className="flex-1 overflow-y-auto px-3 py-4 space-y-6">
            {/* GROUP 1: STANDALONE INDIVIDUAL ITEMS */}
            <div className="space-y-1">
              {!sidebarCollapsed ? (
                <p className="px-3 text-[10px] font-extrabold uppercase tracking-widest text-[var(--text-muted)]">
                  {dict.app.nav.groups.overview}
                </p>
              ) : (
                <div className="my-1 border-t border-[var(--border)]" />
              )}

              <div className="space-y-1">
                {/* Dashboard Link */}
                <Link
                  href="/dashboard"
                  onClick={() => setMobileMenuOpen(false)}
                  title={sidebarCollapsed ? dict.app.nav.dashboard : undefined}
                  className={`group relative flex items-center gap-3 rounded-xl py-2.5 text-xs font-semibold no-underline transition-all ${
                    sidebarCollapsed ? 'size-10 justify-center mx-auto px-0' : 'px-3 justify-between'
                  } ${
                    active === 'dashboard'
                      ? 'bg-gradient-to-br from-blue-600 to-indigo-600 text-white shadow-md shadow-blue-500/30'
                      : 'text-[var(--text-secondary)] hover:bg-[var(--background)] hover:text-[var(--text-primary)]'
                  }`}
                >
                  <div className="flex items-center gap-3">
                    <svg
                      className={`size-4 shrink-0 transition-transform group-hover:scale-110 ${
                        active === 'dashboard' ? 'text-white' : 'text-[var(--text-muted)] group-hover:text-[var(--primary)]'
                      }`}
                      fill="none"
                      viewBox="0 0 24 24"
                      stroke="currentColor"
                      strokeWidth={2}
                    >
                      <path strokeLinecap="round" strokeLinejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    {!sidebarCollapsed ? <span>{dict.app.nav.dashboard}</span> : null}
                  </div>
                </Link>

                {/* Notifications Link */}
                <Link
                  href="/notifications"
                  onClick={() => setMobileMenuOpen(false)}
                  title={sidebarCollapsed ? dict.app.nav.notifications : undefined}
                  className={`group relative flex items-center gap-3 rounded-xl py-2.5 text-xs font-semibold no-underline transition-all ${
                    sidebarCollapsed ? 'size-10 justify-center mx-auto px-0' : 'px-3 justify-between'
                  } ${
                    active === 'notifications'
                      ? 'bg-gradient-to-br from-blue-600 to-indigo-600 text-white shadow-md shadow-blue-500/30'
                      : 'text-[var(--text-secondary)] hover:bg-[var(--background)] hover:text-[var(--text-primary)]'
                  }`}
                >
                  <div className="flex items-center gap-3">
                    <div className="relative">
                      <svg
                        className={`size-4 shrink-0 transition-transform group-hover:scale-110 ${
                          active === 'notifications' ? 'text-white' : 'text-[var(--text-muted)] group-hover:text-[var(--primary)]'
                        }`}
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        strokeWidth={2}
                      >
                        <path strokeLinecap="round" strokeLinejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                      </svg>
                      {sidebarCollapsed && unreadNotifications > 0 ? (
                        <span className="absolute -top-1 -end-1 size-2 rounded-full bg-red-500 ring-2 ring-[var(--surface)]" />
                      ) : null}
                    </div>
                    {!sidebarCollapsed ? <span>{dict.app.nav.notifications}</span> : null}
                  </div>
                  {!sidebarCollapsed && unreadNotifications > 0 ? (
                    <span
                      className={`rounded-full px-2 py-0.5 text-[10px] font-bold ${
                        active === 'notifications' ? 'bg-white/20 text-white' : 'bg-blue-500/15 text-blue-600 dark:text-blue-400'
                      }`}
                    >
                      {unreadNotifications}
                    </span>
                  ) : null}
                </Link>

                {/* System Diagnostics Link */}
                <Link
                  href="/foundation"
                  onClick={() => setMobileMenuOpen(false)}
                  title={sidebarCollapsed ? dict.app.nav.diagnostics : undefined}
                  className={`group relative flex items-center gap-3 rounded-xl py-2.5 text-xs font-semibold no-underline transition-all ${
                    sidebarCollapsed ? 'size-10 justify-center mx-auto px-0' : 'px-3 justify-between'
                  } ${
                    active === 'foundation'
                      ? 'bg-gradient-to-br from-blue-600 to-indigo-600 text-white shadow-md shadow-blue-500/30'
                      : 'text-[var(--text-secondary)] hover:bg-[var(--background)] hover:text-[var(--text-primary)]'
                  }`}
                >
                  <div className="flex items-center gap-3">
                    <svg
                      className={`size-4 shrink-0 transition-transform group-hover:scale-110 ${
                        active === 'foundation' ? 'text-white' : 'text-[var(--text-muted)] group-hover:text-[var(--primary)]'
                      }`}
                      fill="none"
                      viewBox="0 0 24 24"
                      stroke="currentColor"
                      strokeWidth={2}
                    >
                      <path strokeLinecap="round" strokeLinejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                    {!sidebarCollapsed ? <span>{dict.app.nav.diagnostics}</span> : null}
                  </div>
                </Link>
              </div>
            </div>

            {/* GROUP 2: MODULE GROUPS CONTAINING SUB-ELEMENTS */}
            <div className="space-y-3 pt-2 border-t border-[var(--border)]">
              {!sidebarCollapsed ? (
                <p className="px-3 text-[10px] font-extrabold uppercase tracking-widest text-[var(--text-muted)]">
                  {dict.app.nav.groups.modules}
                </p>
              ) : (
                <div className="my-1 border-t border-[var(--border)]" />
              )}

              <div className="space-y-2">
                {/* 1. Accounting Core Dropdown Group */}
                <div className={`space-y-1 ${showAccountingGroup ? '' : 'hidden'}`}>
                  <div
                    className={`group relative flex items-center justify-between rounded-xl py-2.5 text-xs font-semibold transition-all ${
                      sidebarCollapsed ? 'size-10 justify-center mx-auto px-0' : 'px-3'
                    } ${
                      isAccountingActive
                        ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400 font-bold border border-blue-500/20'
                        : 'text-[var(--text-secondary)] hover:bg-[var(--background)] hover:text-[var(--text-primary)]'
                    }`}
                  >
                    <Link
                      href="/accounting"
                      onClick={() => setMobileMenuOpen(false)}
                      title={sidebarCollapsed ? accDict.title : undefined}
                      className="flex flex-1 items-center gap-3 no-underline text-inherit"
                    >
                      <svg
                        className={`size-4 shrink-0 transition-transform group-hover:scale-110 ${
                          isAccountingActive ? 'text-blue-600 dark:text-blue-400' : 'text-[var(--text-muted)] group-hover:text-[var(--primary)]'
                        }`}
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        strokeWidth={2}
                      >
                        <path strokeLinecap="round" strokeLinejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                      </svg>
                      {!sidebarCollapsed ? <span>{accDict.title}</span> : null}
                    </Link>
                    {!sidebarCollapsed ? (
                      <button
                        type="button"
                        onClick={() => setAccountingExpanded(!accountingExpanded)}
                        className={`p-1 transition-colors cursor-pointer ${isAccountingActive ? 'text-blue-600 dark:text-blue-400 hover:text-blue-700' : 'text-[var(--text-muted)] hover:text-[var(--text-primary)]'}`}
                      >
                        <svg
                          className={`size-3.5 transition-transform duration-200 ${accountingExpanded ? 'rotate-180' : ''}`}
                          fill="none"
                          viewBox="0 0 24 24"
                          stroke="currentColor"
                          strokeWidth={2}
                        >
                          <path strokeLinecap="round" strokeLinejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                      </button>
                    ) : null}
                  </div>

                  {(accountingExpanded || sidebarCollapsed) ? (
                    <div className={sidebarCollapsed ? 'space-y-1 pt-1' : 'border-s-2 border-blue-500/20 ms-4 ps-2 space-y-1 pt-1 mt-1'}>
                      {[
                        { key: 'accounting.coa' as NavKey, href: '/accounting/coa', label: accDict.coa, icon: 'M4 6h16M4 10h16M4 14h16M4 18h16' },
                        { key: 'accounting.account_categories' as NavKey, href: '/accounting/account-categories', label: accDict.accountCategories, icon: 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10' },
                        { key: 'accounting.account_types' as NavKey, href: '/accounting/account-types', label: accDict.accountTypes, icon: 'M7 7h10M7 12h10M7 17h10' },
                        { key: 'accounting.statement_mappings' as NavKey, href: '/accounting/statement-mappings', label: accDict.statementMappings, icon: 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
                        { key: 'accounting.account_mappings' as NavKey, href: '/accounting/account-mappings', label: accDict.accountMappings, icon: 'M10.5 6h9m-9 6h9m-9 6h9M4.5 6h.01M4.5 12h.01M4.5 18h.01' },
                        { key: 'accounting.journal' as NavKey, href: '/accounting/journal', label: accDict.journal, icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
                        { key: 'accounting.ledger' as NavKey, href: '/accounting/ledger', label: accDict.ledger, icon: 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
                        { key: 'accounting.trial_balance' as NavKey, href: '/accounting/trial-balance', label: accDict.trialBalance, icon: 'M3 6l9-4 9 4v14l-9 4-9-4V6z' },
                        { key: 'accounting.periods' as NavKey, href: '/accounting/periods', label: accDict.periods, icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' },
                        { key: 'accounting.opening_balances' as NavKey, href: '/accounting/opening-balances', label: accDict.openingBalances, icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
                        { key: 'accounting.fx_rates' as NavKey, href: '/accounting/fx-rates', label: accDict.fxRates, icon: 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4' },
                        { key: 'accounting.currencies' as NavKey, href: '/accounting/currencies', label: accDict.currencies, icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
                        { key: 'taxes.codes.index' as NavKey, href: '/taxes/codes', label: taxesDict.title, icon: 'M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2z' },
                        { key: 'taxes.periods.index' as NavKey, href: '/taxes/periods', label: taxesDict.periods.title, icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' },
                       ].filter((subItem) => navAllowed(subItem.key)).map((subItem) => {
                        const isSubActive = active === subItem.key;
                        return (
                          <Link
                            key={subItem.key}
                            href={subItem.href}
                            onClick={() => setMobileMenuOpen(false)}
                            title={sidebarCollapsed ? subItem.label : undefined}
                            className={`group relative flex items-center gap-2.5 rounded-xl py-2 text-xs font-medium no-underline transition-all ${
                              sidebarCollapsed ? 'size-10 justify-center mx-auto px-0' : 'px-3'
                            } ${
                              isSubActive
                                ? 'bg-gradient-to-br from-blue-600 to-indigo-600 text-white shadow-md shadow-blue-500/30 font-bold'
                                : 'text-[var(--text-secondary)] hover:bg-[var(--background)] hover:text-[var(--text-primary)]'
                            }`}
                          >
                            <svg
                              className={`size-3.5 shrink-0 transition-transform group-hover:scale-110 ${
                                isSubActive ? 'text-white' : 'text-[var(--text-muted)] group-hover:text-[var(--primary)]'
                              }`}
                              fill="none"
                              viewBox="0 0 24 24"
                              stroke="currentColor"
                              strokeWidth={2}
                            >
                              <path strokeLinecap="round" strokeLinejoin="round" d={subItem.icon} />
                            </svg>
                            {!sidebarCollapsed ? <span className="truncate">{subItem.label}</span> : null}
                          </Link>
                        );
                      })}
                    </div>
                  ) : null}
                </div>

                {/* 2. AR / Customers Dropdown Group */}
                <div className={`space-y-1 ${showArGroup ? '' : 'hidden'}`}>
                  <div
                    className={`group relative flex items-center justify-between rounded-xl py-2.5 text-xs font-semibold transition-all ${
                      sidebarCollapsed ? 'size-10 justify-center mx-auto px-0' : 'px-3'
                    } ${
                      isArActive
                        ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400 font-bold border border-blue-500/20'
                        : 'text-[var(--text-secondary)] hover:bg-[var(--background)] hover:text-[var(--text-primary)]'
                    }`}
                  >
                    <Link
                      href="/customers"
                      onClick={() => setMobileMenuOpen(false)}
                      title={sidebarCollapsed ? dict.app.nav.layoutKeys.customersAr_2 : undefined}
                      className="flex flex-1 items-center gap-3 no-underline text-inherit"
                    >
                      <svg className={`size-4 shrink-0 transition-transform group-hover:scale-110 ${isArActive ? 'text-blue-600 dark:text-blue-400' : 'text-[var(--text-muted)] group-hover:text-[var(--primary)]'}`} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                      </svg>
                      {!sidebarCollapsed ? <span>{dict.app.nav.layoutKeys.customersAr}</span> : null}
                    </Link>
                    {!sidebarCollapsed ? (
                      <button type="button" onClick={() => setArExpanded(!arExpanded)} className={`p-1 transition-colors cursor-pointer ${isArActive ? 'text-blue-600 dark:text-blue-400 hover:text-blue-700' : 'text-[var(--text-muted)] hover:text-[var(--text-primary)]'}`}>
                        <svg className={`size-3.5 transition-transform duration-200 ${arExpanded ? 'rotate-180' : ''}`} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                          <path strokeLinecap="round" strokeLinejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                      </button>
                    ) : null}
                  </div>

                  {(arExpanded || sidebarCollapsed) ? (
                    <div className={sidebarCollapsed ? 'space-y-1 pt-1' : 'border-s-2 border-blue-500/20 ms-4 ps-2 space-y-1 pt-1 mt-1'}>
                      {[
                        { key: 'customers.index' as NavKey, href: '/customers', label: dict.app.nav.layoutKeys.customers },
                        { key: 'customer-opening-balances.index' as NavKey, href: '/customer-opening-balances', label: dict.app.nav.layoutKeys.customerOpeningBalances },
                        { key: 'customer-receipts.index' as NavKey, href: '/customer-receipts', label: dict.app.nav.layoutKeys.customerReceipts },
                         { key: 'receivable-allocations.index' as NavKey, href: '/receivable-allocations', label: dict.app.nav.layoutKeys.arAllocations },
                       ].filter((subItem) => navAllowed(subItem.key)).map((subItem) => (
                        <Link
                          key={subItem.key}
                          href={subItem.href}
                          onClick={() => setMobileMenuOpen(false)}
                          title={sidebarCollapsed ? subItem.label : undefined}
                          className={`group relative flex items-center gap-2.5 rounded-xl py-2 text-xs font-medium no-underline transition-all ${
                            sidebarCollapsed ? 'size-10 justify-center mx-auto px-0' : 'px-3'
                          } ${active === subItem.key ? 'bg-gradient-to-br from-blue-600 to-indigo-600 text-white font-bold shadow-md shadow-blue-500/30' : 'text-[var(--text-secondary)] hover:bg-[var(--background)] hover:text-[var(--text-primary)]'}`}
                        >
                          {!sidebarCollapsed ? <span className="truncate">{subItem.label}</span> : null}
                        </Link>
                      ))}
                    </div>
                  ) : null}
                </div>

                {/* 3. AP / Suppliers Dropdown Group */}
                <div className={`space-y-1 ${showApGroup ? '' : 'hidden'}`}>
                  <div
                    className={`group relative flex items-center justify-between rounded-xl py-2.5 text-xs font-semibold transition-all ${
                      sidebarCollapsed ? 'size-10 justify-center mx-auto px-0' : 'px-3'
                    } ${
                      isApActive
                        ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400 font-bold border border-blue-500/20'
                        : 'text-[var(--text-secondary)] hover:bg-[var(--background)] hover:text-[var(--text-primary)]'
                    }`}
                  >
                    <Link
                      href="/suppliers"
                      onClick={() => setMobileMenuOpen(false)}
                      title={sidebarCollapsed ? dict.app.nav.layoutKeys.suppliersAp_2 : undefined}
                      className="flex flex-1 items-center gap-3 no-underline text-inherit"
                    >
                      <svg className={`size-4 shrink-0 transition-transform group-hover:scale-110 ${isApActive ? 'text-blue-600 dark:text-blue-400' : 'text-[var(--text-muted)] group-hover:text-[var(--primary)]'}`} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5m3 0h1M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                      </svg>
                      {!sidebarCollapsed ? <span>{dict.app.nav.layoutKeys.suppliersAp}</span> : null}
                    </Link>
                    {!sidebarCollapsed ? (
                      <button type="button" onClick={() => setApExpanded(!apExpanded)} className={`p-1 transition-colors cursor-pointer ${isApActive ? 'text-blue-600 dark:text-blue-400 hover:text-blue-700' : 'text-[var(--text-muted)] hover:text-[var(--text-primary)]'}`}>
                        <svg className={`size-3.5 transition-transform duration-200 ${apExpanded ? 'rotate-180' : ''}`} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                          <path strokeLinecap="round" strokeLinejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                      </button>
                    ) : null}
                  </div>

                  {(apExpanded || sidebarCollapsed) ? (
                    <div className={sidebarCollapsed ? 'space-y-1 pt-1' : 'border-s-2 border-blue-500/20 ms-4 ps-2 space-y-1 pt-1 mt-1'}>
                      {[
                        { key: 'suppliers.index' as NavKey, href: '/suppliers', label: dict.app.nav.layoutKeys.suppliers },
                        { key: 'supplier-opening-balances.index' as NavKey, href: '/supplier-opening-balances', label: dict.app.nav.layoutKeys.supplierOpeningBalances },
                        { key: 'supplier-payments.index' as NavKey, href: '/supplier-payments', label: dict.app.nav.layoutKeys.supplierPayments },
                         { key: 'payable-allocations.index' as NavKey, href: '/payable-allocations', label: dict.app.nav.layoutKeys.apAllocations },
                       ].filter((subItem) => navAllowed(subItem.key)).map((subItem) => (
                        <Link
                          key={subItem.key}
                          href={subItem.href}
                          onClick={() => setMobileMenuOpen(false)}
                          title={sidebarCollapsed ? subItem.label : undefined}
                          className={`group relative flex items-center gap-2.5 rounded-xl py-2 text-xs font-medium no-underline transition-all ${
                            sidebarCollapsed ? 'size-10 justify-center mx-auto px-0' : 'px-3'
                          } ${active === subItem.key ? 'bg-gradient-to-br from-blue-600 to-indigo-600 text-white font-bold shadow-md shadow-blue-500/30' : 'text-[var(--text-secondary)] hover:bg-[var(--background)] hover:text-[var(--text-primary)]'}`}
                        >
                          {!sidebarCollapsed ? <span className="truncate">{subItem.label}</span> : null}
                        </Link>
                      ))}
                    </div>
                  ) : null}
                </div>

                {/* 4. Expenses Dropdown Group */}
                <div className={`space-y-1 ${showExpensesGroup ? '' : 'hidden'}`}>
                  <div
                    className={`group relative flex items-center justify-between rounded-xl py-2.5 text-xs font-semibold transition-all ${
                      sidebarCollapsed ? 'size-10 justify-center mx-auto px-0' : 'px-3'
                    } ${
                      isExpensesActive
                        ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400 font-bold border border-blue-500/20'
                        : 'text-[var(--text-secondary)] hover:bg-[var(--background)] hover:text-[var(--text-primary)]'
                    }`}
                  >
                    <Link
                      href="/expenses"
                      onClick={() => setMobileMenuOpen(false)}
                      title={sidebarCollapsed ? dict.app.nav.layoutKeys.expensesOperations : undefined}
                      className="flex flex-1 items-center gap-3 no-underline text-inherit"
                    >
                      <svg className={`size-4 shrink-0 transition-transform group-hover:scale-110 ${isExpensesActive ? 'text-blue-600 dark:text-blue-400' : 'text-[var(--text-muted)] group-hover:text-[var(--primary)]'}`} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M9 14h6m-6-4h6m-7 10h8a2 2 0 002-2V7.5L14.5 4H8a2 2 0 00-2 2v12a2 2 0 002 2z" />
                      </svg>
                      {!sidebarCollapsed ? <span>{dict.app.nav.layoutKeys.expensesOperations}</span> : null}
                    </Link>
                    {!sidebarCollapsed ? (
                      <button type="button" onClick={() => setExpensesExpanded(!expensesExpanded)} className={`p-1 transition-colors cursor-pointer ${isExpensesActive ? 'text-blue-600 dark:text-blue-400 hover:text-blue-700' : 'text-[var(--text-muted)] hover:text-[var(--text-primary)]'}`}>
                        <svg className={`size-3.5 transition-transform duration-200 ${expensesExpanded ? 'rotate-180' : ''}`} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                          <path strokeLinecap="round" strokeLinejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                      </button>
                    ) : null}
                  </div>

                  {(expensesExpanded || sidebarCollapsed) ? (
                    <div className={sidebarCollapsed ? 'space-y-1 pt-1' : 'border-s-2 border-blue-500/20 ms-4 ps-2 space-y-1 pt-1 mt-1'}>
                      {[
                        { key: 'expenses.index' as NavKey, href: '/expenses', label: dict.app.nav.layoutKeys.expenses },
                        { key: 'expense-categories.index' as NavKey, href: '/expenses/categories', label: dict.app.nav.layoutKeys.expenseCategories },
                        { key: 'prepaid-schedules.index' as NavKey, href: '/expenses/prepaids', label: dict.app.nav.layoutKeys.prepaidSchedules },
                        { key: 'accrual-schedules.index' as NavKey, href: '/expenses/accruals', label: dict.app.nav.layoutKeys.accrualSchedules },
                       ].filter((subItem) => navAllowed(subItem.key)).map((subItem) => (
                        <Link
                          key={subItem.key}
                          href={subItem.href}
                          onClick={() => setMobileMenuOpen(false)}
                          title={sidebarCollapsed ? subItem.label : undefined}
                          className={`group relative flex items-center gap-2.5 rounded-xl py-2 text-xs font-medium no-underline transition-all ${
                            sidebarCollapsed ? 'size-10 justify-center mx-auto px-0' : 'px-3'
                          } ${active === subItem.key ? 'bg-gradient-to-br from-blue-600 to-indigo-600 text-white font-bold shadow-md shadow-blue-500/30' : 'text-[var(--text-secondary)] hover:bg-[var(--background)] hover:text-[var(--text-primary)]'}`}
                        >
                          {!sidebarCollapsed ? <span className="truncate">{subItem.label}</span> : null}
                        </Link>
                      ))}
                    </div>
                  ) : null}
                </div>

                {/* 5. Payroll Dropdown Group */}
                <div className={`space-y-1 ${showPayrollGroup ? '' : 'hidden'}`}>
                  <div
                    className={`group relative flex items-center justify-between rounded-xl py-2.5 text-xs font-semibold transition-all ${
                      sidebarCollapsed ? 'size-10 justify-center mx-auto px-0' : 'px-3'
                    } ${
                      isPayrollActive
                        ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400 font-bold border border-blue-500/20'
                        : 'text-[var(--text-secondary)] hover:bg-[var(--background)] hover:text-[var(--text-primary)]'
                    }`}
                  >
                    <Link
                      href="/payroll/runs"
                      onClick={() => setMobileMenuOpen(false)}
                      title={sidebarCollapsed ? dict.app.nav.layoutKeys.payrollOperations : undefined}
                      className="flex flex-1 items-center gap-3 no-underline text-inherit"
                    >
                      <svg className={`size-4 shrink-0 transition-transform group-hover:scale-110 ${isPayrollActive ? 'text-blue-600 dark:text-blue-400' : 'text-[var(--text-muted)] group-hover:text-[var(--primary)]'}`} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14c-4.418 0-8 1.79-8 4v1h16v-1c0-2.21-3.582-4-8-4z" />
                      </svg>
                      {!sidebarCollapsed ? <span>{dict.app.nav.layoutKeys.payrollOperations}</span> : null}
                    </Link>
                    {!sidebarCollapsed ? (
                      <button type="button" onClick={() => setPayrollExpanded(!payrollExpanded)} className={`p-1 transition-colors cursor-pointer ${isPayrollActive ? 'text-blue-600 dark:text-blue-400 hover:text-blue-700' : 'text-[var(--text-muted)] hover:text-[var(--text-primary)]'}`}>
                        <svg className={`size-3.5 transition-transform duration-200 ${payrollExpanded ? 'rotate-180' : ''}`} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                          <path strokeLinecap="round" strokeLinejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                      </button>
                    ) : null}
                  </div>

                  {(payrollExpanded || sidebarCollapsed) ? (
                    <div className={sidebarCollapsed ? 'space-y-1 pt-1' : 'border-s-2 border-blue-500/20 ms-4 ps-2 space-y-1 pt-1 mt-1'}>
                      {[
                        { key: 'payroll.runs.index' as NavKey, href: '/payroll/runs', label: dict.app.nav.layoutKeys.payrollRuns },
                        { key: 'payroll.employees.index' as NavKey, href: '/payroll/employees', label: dict.app.nav.layoutKeys.employees },
                        { key: 'payroll.components.index' as NavKey, href: '/payroll/components', label: dict.app.nav.layoutKeys.payrollComponents },
                       ].filter((subItem) => navAllowed(subItem.key)).map((subItem) => (
                        <Link
                          key={subItem.key}
                          href={subItem.href}
                          onClick={() => setMobileMenuOpen(false)}
                          title={sidebarCollapsed ? subItem.label : undefined}
                          className={`group relative flex items-center gap-2.5 rounded-xl py-2 text-xs font-medium no-underline transition-all ${
                            sidebarCollapsed ? 'size-10 justify-center mx-auto px-0' : 'px-3'
                          } ${active === subItem.key ? 'bg-gradient-to-br from-blue-600 to-indigo-600 text-white font-bold shadow-md shadow-blue-500/30' : 'text-[var(--text-secondary)] hover:bg-[var(--background)] hover:text-[var(--text-primary)]'}`}
                        >
                          {!sidebarCollapsed ? <span className="truncate">{subItem.label}</span> : null}
                        </Link>
                      ))}
                    </div>
                  ) : null}
                </div>

                {/* 6. Rentals Dropdown Group */}
                <div className={`space-y-1 ${showRentalsGroup ? '' : 'hidden'}`}>
                  <div
                    className={`group relative flex items-center justify-between rounded-xl py-2.5 text-xs font-semibold transition-all ${
                      sidebarCollapsed ? 'size-10 justify-center mx-auto px-0' : 'px-3'
                    } ${
                      isRentalsActive
                        ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400 font-bold border border-blue-500/20'
                        : 'text-[var(--text-secondary)] hover:bg-[var(--background)] hover:text-[var(--text-primary)]'
                    }`}
                  >
                    <Link
                      href="/rentals/items"
                      onClick={() => setMobileMenuOpen(false)}
                      title={sidebarCollapsed ? dict.app.nav.layoutKeys.rentalsOperations : undefined}
                      className="flex flex-1 items-center gap-3 no-underline text-inherit"
                    >
                      <svg className={`size-4 shrink-0 transition-transform group-hover:scale-110 ${isRentalsActive ? 'text-blue-600 dark:text-blue-400' : 'text-[var(--text-muted)] group-hover:text-[var(--primary)]'}`} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M8 7h8m-9 4h10M7 15h10M5 4h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V5a1 1 0 01-1-1z" />
                      </svg>
                      {!sidebarCollapsed ? <span>{dict.app.nav.layoutKeys.rentalsOperations}</span> : null}
                    </Link>
                    {!sidebarCollapsed ? (
                      <button type="button" onClick={() => setRentalsExpanded(!rentalsExpanded)} className={`p-1 transition-colors cursor-pointer ${isRentalsActive ? 'text-blue-600 dark:text-blue-400 hover:text-blue-700' : 'text-[var(--text-muted)] hover:text-[var(--text-primary)]'}`}>
                        <svg className={`size-3.5 transition-transform duration-200 ${rentalsExpanded ? 'rotate-180' : ''}`} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                          <path strokeLinecap="round" strokeLinejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                      </button>
                    ) : null}
                  </div>

                  {(rentalsExpanded || sidebarCollapsed) ? (
                    <div className={sidebarCollapsed ? 'space-y-1 pt-1' : 'border-s-2 border-blue-500/20 ms-4 ps-2 space-y-1 pt-1 mt-1'}>
                      {[
                        { key: 'rentals.contracts.index' as NavKey, href: '/rentals/contracts', label: dict.app.nav.layoutKeys.rentalContracts },
                        { key: 'rentals.invoices.index' as NavKey, href: '/rentals/invoices', label: dict.app.nav.layoutKeys.rentalInvoices },
                        { key: 'rentals.handovers.index' as NavKey, href: '/rentals/handovers', label: dict.app.nav.layoutKeys.rentalHandovers },
                        { key: 'rentals.returns.index' as NavKey, href: '/rentals/returns', label: dict.app.nav.layoutKeys.rentalReturns },
                        { key: 'rentals.items.index' as NavKey, href: '/rentals/items', label: dict.app.nav.layoutKeys.rentalItems },
                       ].filter((subItem) => navAllowed(subItem.key)).map((subItem) => (
                        <Link
                          key={subItem.key}
                          href={subItem.href}
                          onClick={() => setMobileMenuOpen(false)}
                          title={sidebarCollapsed ? subItem.label : undefined}
                          className={`group relative flex items-center gap-2.5 rounded-xl py-2 text-xs font-medium no-underline transition-all ${
                            sidebarCollapsed ? 'size-10 justify-center mx-auto px-0' : 'px-3'
                          } ${active === subItem.key ? 'bg-gradient-to-br from-blue-600 to-indigo-600 text-white font-bold shadow-md shadow-blue-500/30' : 'text-[var(--text-secondary)] hover:bg-[var(--background)] hover:text-[var(--text-primary)]'}`}
                        >
                          {!sidebarCollapsed ? <span className="truncate">{subItem.label}</span> : null}
                        </Link>
                      ))}
                    </div>
                  ) : null}
                </div>
                            {/* 7. Cash, Bank & Cheques Dropdown Group */}
                <div className={`space-y-1 ${showCashBankGroup ? '' : 'hidden'}`}>
                  <div
                    className={`group relative flex items-center justify-between rounded-xl py-2.5 text-xs font-semibold transition-all ${
                      sidebarCollapsed ? 'size-10 justify-center mx-auto px-0' : 'px-3'
                    } ${
                      isCashBankActive
                        ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400 font-bold border border-blue-500/20'
                        : 'text-[var(--text-secondary)] hover:bg-[var(--background)] hover:text-[var(--text-primary)]'
                    }`}
                  >
                    <Link
                      href="/cash-accounts"
                      onClick={() => setMobileMenuOpen(false)}
                      title={sidebarCollapsed ? dict.app.nav.layoutKeys.cashBankCheques_2 : undefined}
                      className="flex flex-1 items-center gap-3 no-underline text-inherit"
                    >
                      <svg className={`size-4 shrink-0 transition-transform group-hover:scale-110 ${isCashBankActive ? 'text-blue-600 dark:text-blue-400' : 'text-[var(--text-muted)] group-hover:text-[var(--primary)]'}`} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                      </svg>
                      {!sidebarCollapsed ? <span>{dict.app.nav.layoutKeys.cashBankCheques}</span> : null}
                    </Link>
                    {!sidebarCollapsed ? (
                      <button type="button" onClick={() => setCashBankExpanded(!cashBankExpanded)} className={`p-1 transition-colors cursor-pointer ${isCashBankActive ? 'text-blue-600 dark:text-blue-400 hover:text-blue-700' : 'text-[var(--text-muted)] hover:text-[var(--text-primary)]'}`}>
                        <svg className={`size-3.5 transition-transform duration-200 ${cashBankExpanded ? 'rotate-180' : ''}`} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                          <path strokeLinecap="round" strokeLinejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                      </button>
                    ) : null}
                  </div>

                  {(cashBankExpanded || sidebarCollapsed) ? (
                    <div className={sidebarCollapsed ? 'space-y-1 pt-1' : 'border-s-2 border-blue-500/20 ms-4 ps-2 space-y-1 pt-1 mt-1'}>
                      {[
                        { key: 'cash-accounts.index' as NavKey, href: '/cash-accounts', label: dict.app.nav.layoutKeys.cashAccounts },
                        { key: 'bank-accounts.index' as NavKey, href: '/bank-accounts', label: dict.app.nav.layoutKeys.bankAccounts },
                        { key: 'treasury-transfers.index' as NavKey, href: '/treasury-transfers', label: dict.app.nav.layoutKeys.treasuryTransfers },
                        { key: 'incoming-cheques.index' as NavKey, href: '/incoming-cheques', label: dict.app.nav.layoutKeys.incomingCheques },
                        { key: 'outgoing-cheques.index' as NavKey, href: '/outgoing-cheques', label: dict.app.nav.layoutKeys.outgoingCheques },
                         { key: 'bank-reconciliations.index' as NavKey, href: '/bank-reconciliations', label: dict.app.nav.layoutKeys.bankReconciliations },
                        ].filter((subItem) => navAllowed(subItem.key)).map((subItem) => (
                        <Link
                          key={subItem.key}
                          href={subItem.href}
                          onClick={() => setMobileMenuOpen(false)}
                          title={sidebarCollapsed ? subItem.label : undefined}
                          className={`group relative flex items-center gap-2.5 rounded-xl py-2 text-xs font-medium no-underline transition-all ${
                            sidebarCollapsed ? 'size-10 justify-center mx-auto px-0' : 'px-3'
                          } ${active === subItem.key || (subItem.key === 'bank-reconciliations.index' && active === 'bank-reconciliations.show') ? 'bg-gradient-to-br from-blue-600 to-indigo-600 text-white font-bold shadow-md shadow-blue-500/30' : 'text-[var(--text-secondary)] hover:bg-[var(--background)] hover:text-[var(--text-primary)]'}`}
                        >
                          {!sidebarCollapsed ? <span className="truncate">{subItem.label}</span> : null}
                        </Link>
                      ))}
                    </div>
                  ) : null}
                </div>

                {/* 5. Catalog Dropdown Group */}
                <div className={`space-y-1 ${showCatalogGroup ? '' : 'hidden'}`}>
                  <div
                    className={`group relative flex items-center justify-between rounded-xl py-2.5 text-xs font-semibold transition-all ${
                      sidebarCollapsed ? 'size-10 justify-center mx-auto px-0' : 'px-3'
                    } ${
                      isCatalogActive
                        ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400 font-bold border border-blue-500/20'
                        : 'text-[var(--text-secondary)] hover:bg-[var(--background)] hover:text-[var(--text-primary)]'
                    }`}
                  >
                    <Link
                      href="/catalog/products"
                      onClick={() => setMobileMenuOpen(false)}
                      title={sidebarCollapsed ? dict.app.nav.layoutKeys.catalog_2 : undefined}
                      className="flex flex-1 items-center gap-3 no-underline text-inherit"
                    >
                      <svg className={`size-4 shrink-0 transition-transform group-hover:scale-110 ${isCatalogActive ? 'text-blue-600 dark:text-blue-400' : 'text-[var(--text-muted)] group-hover:text-[var(--primary)]'}`} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                      </svg>
                      {!sidebarCollapsed ? <span>{dict.app.nav.layoutKeys.catalog}</span> : null}
                    </Link>
                    {!sidebarCollapsed ? (
                      <button type="button" onClick={() => setCatalogExpanded(!catalogExpanded)} className={`p-1 transition-colors cursor-pointer ${isCatalogActive ? 'text-blue-600 dark:text-blue-400 hover:text-blue-700' : 'text-[var(--text-muted)] hover:text-[var(--primary)]'}`}>
                        <svg className={`size-3.5 transition-transform duration-200 ${catalogExpanded ? 'rotate-180' : ''}`} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                          <path strokeLinecap="round" strokeLinejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                      </button>
                    ) : null}
                  </div>

                  {(catalogExpanded || sidebarCollapsed) ? (
                    <div className={sidebarCollapsed ? 'space-y-1 pt-1' : 'border-s-2 border-blue-500/20 ms-4 ps-2 space-y-1 pt-1 mt-1'}>
                      {[
                        { key: 'products.index' as NavKey, href: '/catalog/products', label: dict.app.nav.layoutKeys.productsServices },
                        { key: 'product-categories.index' as NavKey, href: '/catalog/categories', label: dict.app.nav.layoutKeys.productCategories },
                        { key: 'uoms.index' as NavKey, href: '/catalog/uoms', label: dict.app.nav.layoutKeys.unitsOfMeasure },
                       ].filter((subItem) => navAllowed(subItem.key)).map((subItem) => (
                        <Link
                          key={subItem.key}
                          href={subItem.href}
                          onClick={() => setMobileMenuOpen(false)}
                          title={sidebarCollapsed ? subItem.label : undefined}
                          className={`group relative flex items-center gap-2.5 rounded-xl py-2 text-xs font-medium no-underline transition-all ${
                            sidebarCollapsed ? 'size-10 justify-center mx-auto px-0' : 'px-3'
                          } ${active === subItem.key ? 'bg-gradient-to-br from-blue-600 to-indigo-600 text-white font-bold shadow-md shadow-blue-500/30' : 'text-[var(--text-secondary)] hover:bg-[var(--background)] hover:text-[var(--text-primary)]'}`}
                        >
                          {!sidebarCollapsed ? <span className="truncate">{subItem.label}</span> : null}
                        </Link>
                      ))}
                    </div>
                  ) : null}
                </div>

                {/* 6. Inventory Dropdown Group */}
                <div className={`space-y-1 ${showInventoryGroup ? '' : 'hidden'}`}>
                  <div
                    className={`group relative flex items-center justify-between rounded-xl py-2.5 text-xs font-semibold transition-all ${
                      sidebarCollapsed ? 'size-10 justify-center mx-auto px-0' : 'px-3'
                    } ${
                      isInventoryActive
                        ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400 font-bold border border-blue-500/20'
                        : 'text-[var(--text-secondary)] hover:bg-[var(--background)] hover:text-[var(--text-primary)]'
                    }`}
                  >
                    <Link
                      href="/inventory/stock-balances"
                      onClick={() => setMobileMenuOpen(false)}
                      title={sidebarCollapsed ? dict.app.nav.layoutKeys.inventoryOperations : undefined}
                      className="flex flex-1 items-center gap-3 no-underline text-inherit"
                    >
                      <svg className={`size-4 shrink-0 transition-transform group-hover:scale-110 ${isInventoryActive ? 'text-blue-600 dark:text-blue-400' : 'text-[var(--text-muted)] group-hover:text-[var(--primary)]'}`} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                      </svg>
                      {!sidebarCollapsed ? <span>{dict.app.nav.layoutKeys.inventoryOperations}</span> : null}
                    </Link>
                    {!sidebarCollapsed ? (
                      <button type="button" onClick={() => setInventoryExpanded(!inventoryExpanded)} className={`p-1 transition-colors cursor-pointer ${isInventoryActive ? 'text-blue-600 dark:text-blue-400 hover:text-blue-700' : 'text-[var(--text-muted)] hover:text-[var(--text-primary)]'}`}>
                        <svg className={`size-3.5 transition-transform duration-200 ${inventoryExpanded ? 'rotate-180' : ''}`} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                          <path strokeLinecap="round" strokeLinejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                      </button>
                    ) : null}
                  </div>

                  {(inventoryExpanded || sidebarCollapsed) ? (
                    <div className={sidebarCollapsed ? 'space-y-1 pt-1' : 'border-s-2 border-blue-500/20 ms-4 ps-2 space-y-1 pt-1 mt-1'}>
                      {[
                        { key: 'warehouses.index' as NavKey, href: '/inventory/warehouses', label: dict.app.nav.layoutKeys.warehouses },
                        { key: 'stock-transfers.index' as NavKey, href: '/inventory/transfers', label: dict.app.nav.layoutKeys.stockTransfers },
                        { key: 'stock-counts.index' as NavKey, href: '/inventory/stock-counts', label: dict.app.nav.layoutKeys.stockCounts },
                        { key: 'stock-adjustments.index' as NavKey, href: '/inventory/adjustments', label: dict.app.nav.layoutKeys.stockAdjustments },
                        { key: 'stock-balances.index' as NavKey, href: '/inventory/stock-balances', label: dict.app.nav.layoutKeys.stockBalances },
                       ].filter((subItem) => navAllowed(subItem.key)).map((subItem) => (
                        <Link
                          key={subItem.key}
                          href={subItem.href}
                          onClick={() => setMobileMenuOpen(false)}
                          title={sidebarCollapsed ? subItem.label : undefined}
                          className={`group relative flex items-center gap-2.5 rounded-xl py-2 text-xs font-medium no-underline transition-all ${
                            sidebarCollapsed ? 'size-10 justify-center mx-auto px-0' : 'px-3'
                          } ${active === subItem.key ? 'bg-gradient-to-br from-blue-600 to-indigo-600 text-white font-bold shadow-md shadow-blue-500/30' : 'text-[var(--text-secondary)] hover:bg-[var(--background)] hover:text-[var(--text-primary)]'}`}
                        >
                          {!sidebarCollapsed ? <span className="truncate">{subItem.label}</span> : null}
                        </Link>
                      ))}
                    </div>
                  ) : null}
                </div>

                {/* 7. Fixed Assets Dropdown Group */}
                <div className={`space-y-1 ${showFixedAssetsGroup ? '' : 'hidden'}`}>
                  <div
                    className={`group relative flex items-center justify-between rounded-xl py-2.5 text-xs font-semibold transition-all ${
                      sidebarCollapsed ? 'size-10 justify-center mx-auto px-0' : 'px-3'
                    } ${
                      isFixedAssetsActive
                        ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400 font-bold border border-blue-500/20'
                        : 'text-[var(--text-secondary)] hover:bg-[var(--background)] hover:text-[var(--text-primary)]'
                    }`}
                  >
                    <Link
                      href="/fixed-assets"
                      onClick={() => setMobileMenuOpen(false)}
                      title={sidebarCollapsed ? accDict.fixedAssets : undefined}
                      className="flex flex-1 items-center gap-3 no-underline text-inherit"
                    >
                      <svg className={`size-4 shrink-0 transition-transform group-hover:scale-110 ${isFixedAssetsActive ? 'text-blue-600 dark:text-blue-400' : 'text-[var(--text-muted)] group-hover:text-[var(--primary)]'}`} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M3 21h18M5 21V7l8-4 6 3v15M9 9h1m-1 4h1m4-4h1m-1 4h1M9 17h1m4 0h1" />
                      </svg>
                      {!sidebarCollapsed ? <span>{accDict.fixedAssets}</span> : null}
                    </Link>
                    {!sidebarCollapsed ? (
                      <button type="button" onClick={() => setFixedAssetsExpanded(!fixedAssetsExpanded)} className={`p-1 transition-colors cursor-pointer ${isFixedAssetsActive ? 'text-blue-600 dark:text-blue-400 hover:text-blue-700' : 'text-[var(--text-muted)] hover:text-[var(--text-primary)]'}`}>
                        <svg className={`size-3.5 transition-transform duration-200 ${fixedAssetsExpanded ? 'rotate-180' : ''}`} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                          <path strokeLinecap="round" strokeLinejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                      </button>
                    ) : null}
                  </div>

                  {(fixedAssetsExpanded || sidebarCollapsed) ? (
                    <div className={sidebarCollapsed ? 'space-y-1 pt-1' : 'border-s-2 border-blue-500/20 ms-4 ps-2 space-y-1 pt-1 mt-1'}>
                      {[
                        { key: 'fixed-assets.index' as NavKey, href: '/fixed-assets', label: accDict.fixedAssets },
                        { key: 'fixed-asset-categories.index' as NavKey, href: '/fixed-asset-categories', label: accDict.fixedAssetCategories },
                        { key: 'fixed-asset-locations.index' as NavKey, href: '/fixed-asset-locations', label: accDict.fixedAssetLocations },
                        { key: 'fixed-assets.depreciation-runs.index' as NavKey, href: '/fixed-assets-depreciation-runs', label: accDict.depreciationRuns },
                        { key: 'fixed-assets-disposals.index' as NavKey, href: '/fixed-assets-disposals', label: accDict.disposals },
                       ].filter((subItem) => navAllowed(subItem.key)).map((subItem) => (
                        <Link
                          key={subItem.key}
                          href={subItem.href}
                          onClick={() => setMobileMenuOpen(false)}
                          title={sidebarCollapsed ? subItem.label : undefined}
                          className={`group relative flex items-center gap-2.5 rounded-xl py-2 text-xs font-medium no-underline transition-all ${
                            sidebarCollapsed ? 'size-10 justify-center mx-auto px-0' : 'px-3'
                          } ${active === subItem.key ? 'bg-gradient-to-br from-blue-600 to-indigo-600 text-white font-bold shadow-md shadow-blue-500/30' : 'text-[var(--text-secondary)] hover:bg-[var(--background)] hover:text-[var(--text-primary)]'}`}
                        >
                          {!sidebarCollapsed ? <span className="truncate">{subItem.label}</span> : null}
                        </Link>
                      ))}
                    </div>
                  ) : null}
                </div>

                {/* Projects & Cost Centers Dropdown Group */}
                <div className={`space-y-1 ${showProjectsCostCentersGroup ? '' : 'hidden'}`}>
                  <div
                    className={`group relative flex items-center justify-between rounded-xl py-2.5 text-xs font-semibold transition-all ${
                      sidebarCollapsed ? 'size-10 justify-center mx-auto px-0' : 'px-3'
                    } ${
                      isProjectsActive
                        ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400 font-bold border border-blue-500/20'
                        : 'text-[var(--text-secondary)] hover:bg-[var(--background)] hover:text-[var(--text-primary)]'
                    }`}
                  >
                    <Link
                      href="/projects"
                      onClick={() => setMobileMenuOpen(false)}
                      title={sidebarCollapsed ? dict.app.nav.layoutKeys.projectsCostCenters : undefined}
                      className="flex flex-1 items-center gap-3 no-underline text-inherit"
                    >
                      <svg className={`size-4 shrink-0 transition-transform group-hover:scale-110 ${isProjectsActive ? 'text-blue-600 dark:text-blue-400' : 'text-[var(--text-muted)] group-hover:text-[var(--primary)]'}`} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                      </svg>
                      {!sidebarCollapsed ? <span>{dict.app.nav.layoutKeys.projectsCostCenters}</span> : null}
                    </Link>
                    {!sidebarCollapsed ? (
                      <button
                        type="button"
                        onClick={() => setProjectsCostCentersExpanded(!projectsCostCentersExpanded)}
                        title={dict.app.nav.layoutKeys.projectsCostCenters}
                        aria-label={dict.app.nav.layoutKeys.projectsCostCenters}
                        className={`p-1 transition-colors cursor-pointer ${isProjectsActive ? 'text-blue-600 dark:text-blue-400 hover:text-blue-700' : 'text-[var(--text-muted)] hover:text-[var(--text-primary)]'}`}
                      >
                        <svg className={`size-3.5 transition-transform duration-200 ${projectsCostCentersExpanded ? 'rotate-180' : ''}`} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                          <path strokeLinecap="round" strokeLinejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                      </button>
                    ) : null}
                  </div>

                  {(projectsCostCentersExpanded || sidebarCollapsed) ? (
                    <div className={sidebarCollapsed ? 'space-y-1 pt-1' : 'border-s-2 border-blue-500/20 ms-4 ps-2 space-y-1 pt-1 mt-1'}>
                      {[
                        { key: 'projects.index' as NavKey, href: '/projects', label: dict.app.nav.layoutKeys.projects },
                        { key: 'cost-centers.index' as NavKey, href: '/cost-centers', label: dict.app.nav.layoutKeys.costCenters },
                        { key: 'budgeting.budgets' as NavKey, href: '/budgeting/budgets', label: dict.app.nav.layoutKeys.budgets },
                        { key: 'budgeting.variance' as NavKey, href: '/budgeting/variance', label: dict.app.nav.layoutKeys.budgetVariance },
                      ].filter((subItem) => navAllowed(subItem.key)).map((subItem) => (
                        <Link
                          key={subItem.key}
                          href={subItem.href}
                          onClick={() => setMobileMenuOpen(false)}
                          title={sidebarCollapsed ? subItem.label : undefined}
                          className={`group relative flex items-center gap-2.5 rounded-xl py-2 text-xs font-medium no-underline transition-all ${
                            sidebarCollapsed ? 'size-10 justify-center mx-auto px-0' : 'px-3'
                          } ${active === subItem.key ? 'bg-gradient-to-br from-blue-600 to-indigo-600 text-white font-bold shadow-md shadow-blue-500/30' : 'text-[var(--text-secondary)] hover:bg-[var(--background)] hover:text-[var(--text-primary)]'}`}
                        >
                          {!sidebarCollapsed ? <span className="truncate">{subItem.label}</span> : null}
                        </Link>
                      ))}
                    </div>
                  ) : null}
                </div>

                {/* 6. Reports & Subledgers Dropdown Group */}
                <div className={`space-y-1 ${showReportsGroup ? '' : 'hidden'}`}>
                  <div
                    className={`group relative flex items-center justify-between rounded-xl py-2.5 text-xs font-semibold transition-all ${
                      sidebarCollapsed ? 'size-10 justify-center mx-auto px-0' : 'px-3'
                    } ${
                      isReportsActive
                        ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400 font-bold border border-blue-500/20'
                        : 'text-[var(--text-secondary)] hover:bg-[var(--background)] hover:text-[var(--text-primary)]'
                    }`}
                  >
                    <Link
                      href="/reports"
                      onClick={() => setMobileMenuOpen(false)}
                      title={sidebarCollapsed ? dict.app.nav.layoutKeys.reportsSubledgers_2 : undefined}
                      className="flex flex-1 items-center gap-3 no-underline text-inherit"
                    >
                      <svg className={`size-4 shrink-0 transition-transform group-hover:scale-110 ${isReportsActive ? 'text-blue-600 dark:text-blue-400' : 'text-[var(--text-muted)] group-hover:text-[var(--primary)]'}`} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                      </svg>
                      {!sidebarCollapsed ? <span>{dict.app.nav.layoutKeys.reportsSubledgers}</span> : null}
                    </Link>
                    {!sidebarCollapsed ? (
                      <button type="button" onClick={() => setReportsExpanded(!reportsExpanded)} className={`p-1 transition-colors cursor-pointer ${isReportsActive ? 'text-blue-600 dark:text-blue-400 hover:text-blue-700' : 'text-[var(--text-muted)] hover:text-[var(--text-primary)]'}`}>
                        <svg className={`size-3.5 transition-transform duration-200 ${reportsExpanded ? 'rotate-180' : ''}`} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                          <path strokeLinecap="round" strokeLinejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                      </button>
                    ) : null}
                  </div>

                  {(reportsExpanded || sidebarCollapsed) ? (
                    <div className={sidebarCollapsed ? 'space-y-1 pt-1' : 'border-s-2 border-blue-500/20 ms-4 ps-2 space-y-1 pt-1 mt-1'}>
                      {[
                        { key: 'reports.index' as NavKey, href: '/reports', label: dict.app.nav.layoutKeys.reportsHub },
                        { key: 'reports.customer-statement' as NavKey, href: '/reports/customer-statement', label: dict.app.nav.layoutKeys.customerStatement },
                        { key: 'reports.supplier-statement' as NavKey, href: '/reports/supplier-statement', label: dict.app.nav.layoutKeys.supplierStatement },
                        { key: 'reports.ar-aging' as NavKey, href: '/reports/ar-aging', label: dict.app.nav.layoutKeys.arAging },
                        { key: 'reports.ap-aging' as NavKey, href: '/reports/ap-aging', label: dict.app.nav.layoutKeys.apAging },
                        { key: 'reports.cash-book' as NavKey, href: '/reports/cash-book', label: dict.app.nav.layoutKeys.cashBook },
                        { key: 'reports.bank-book' as NavKey, href: '/reports/bank-book', label: dict.app.nav.layoutKeys.bankBook },
                        { key: 'reports.cheque-register' as NavKey, href: '/reports/cheque-register', label: dict.app.nav.layoutKeys.chequeRegister },
                        { key: 'reports.bank-reconciliations' as NavKey, href: '/reports/bank-reconciliations', label: dict.app.nav.layoutKeys.bankReconReport },
                        { key: 'reports.ar-gl-reconciliation' as NavKey, href: '/reports/ar-gl-reconciliation', label: dict.app.nav.layoutKeys.arToGlRecon },
                        { key: 'reports.ap-gl-reconciliation' as NavKey, href: '/reports/ap-gl-reconciliation', label: dict.app.nav.layoutKeys.apToGlRecon },
                        { key: 'reports.branch-operations' as NavKey, href: '/reports/branch-operations', label: dict.app.nav.layoutKeys.branchOperations },
                        { key: 'reports.branch-profitability' as NavKey, href: '/reports/branch-profitability', label: dict.app.nav.layoutKeys.branchProfitability },
                        { key: 'reports.rentals' as NavKey, href: '/reports/rentals', label: dict.app.nav.layoutKeys.rentalOperationsReport },
                        { key: 'reports.balance_sheet' as NavKey, href: '/reports/balance-sheet', label: accDict.balanceSheet },
                        { key: 'reports.income_statement' as NavKey, href: '/reports/income-statement', label: accDict.incomeStatement },
                        { key: 'reports.cash_flow' as NavKey, href: '/reports/cash-flow', label: accDict.cashFlowStatement },
                       ].filter((subItem) => navAllowed(subItem.key)).map((subItem) => (
                        <Link
                          key={subItem.key}
                          href={subItem.href}
                          onClick={() => setMobileMenuOpen(false)}
                          title={sidebarCollapsed ? subItem.label : undefined}
                          className={`group relative flex items-center gap-2.5 rounded-xl py-2 text-xs font-medium no-underline transition-all ${
                            sidebarCollapsed ? 'size-10 justify-center mx-auto px-0' : 'px-3'
                          } ${active === subItem.key ? 'bg-gradient-to-br from-blue-600 to-indigo-600 text-white font-bold shadow-md shadow-blue-500/30' : 'text-[var(--text-secondary)] hover:bg-[var(--background)] hover:text-[var(--text-primary)]'}`}
                        >
                          {!sidebarCollapsed ? <span className="truncate">{subItem.label}</span> : null}
                        </Link>
                      ))}
                    </div>
                  ) : null}
                </div>

                {/* 2. Administration & Settings Dropdown Group */}
                {showAdministrationGroup ? (
                <div className="space-y-1">
                  <div
                    className={`group relative flex items-center justify-between rounded-xl py-2.5 text-xs font-semibold transition-all ${
                      sidebarCollapsed ? 'size-10 justify-center mx-auto px-0' : 'px-3'
                    } ${
                      isAdminActive
                        ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400 font-bold border border-blue-500/20'
                        : 'text-[var(--text-secondary)] hover:bg-[var(--background)] hover:text-[var(--text-primary)]'
                    }`}
                  >
                    <Link
                      href="/settings"
                      onClick={() => setMobileMenuOpen(false)}
                      title={sidebarCollapsed ? dict.app.nav.groups.administration : undefined}
                      className="flex flex-1 items-center gap-3 no-underline text-inherit"
                    >
                      <svg
                        className={`size-4 shrink-0 transition-transform group-hover:scale-110 ${
                          isAdminActive ? 'text-blue-600 dark:text-blue-400' : 'text-[var(--text-muted)] group-hover:text-[var(--primary)]'
                        }`}
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        strokeWidth={2}
                      >
                        <path strokeLinecap="round" strokeLinejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      </svg>
                      {!sidebarCollapsed ? <span>{dict.app.nav.groups.administration}</span> : null}
                    </Link>
                    {!sidebarCollapsed ? (
                      <button
                        type="button"
                        onClick={() => setAdminExpanded(!adminExpanded)}
                        className={`p-1 transition-colors cursor-pointer ${isAdminActive ? 'text-blue-600 dark:text-blue-400 hover:text-blue-700' : 'text-[var(--text-muted)] hover:text-[var(--text-primary)]'}`}
                      >
                        <svg
                          className={`size-3.5 transition-transform duration-200 ${adminExpanded ? 'rotate-180' : ''}`}
                          fill="none"
                          viewBox="0 0 24 24"
                          stroke="currentColor"
                          strokeWidth={2}
                        >
                          <path strokeLinecap="round" strokeLinejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                      </button>
                    ) : null}
                  </div>

                  {(adminExpanded || sidebarCollapsed) ? (
                    <div className={sidebarCollapsed ? 'space-y-1 pt-1' : 'border-s-2 border-blue-500/20 ms-4 ps-2 space-y-1 pt-1 mt-1'}>
                      {/* Settings Main Overview Link */}
                      <Link
                        href="/settings"
                        onClick={() => setMobileMenuOpen(false)}
                        title={sidebarCollapsed ? dict.app.nav.settings : undefined}
                        className={`group relative flex items-center gap-2.5 rounded-xl py-2 text-xs font-medium no-underline transition-all ${
                          sidebarCollapsed ? 'size-10 justify-center mx-auto px-0' : 'px-3'
                        } ${
                          active === 'settings'
                            ? 'bg-gradient-to-br from-blue-600 to-indigo-600 text-white shadow-md shadow-blue-500/30 font-bold'
                            : 'text-[var(--text-secondary)] hover:bg-[var(--background)] hover:text-[var(--text-primary)]'
                        }`}
                      >
                        <svg
                          className={`size-3.5 shrink-0 transition-transform group-hover:scale-110 ${
                            active === 'settings' ? 'text-white' : 'text-[var(--text-muted)] group-hover:text-[var(--primary)]'
                          }`}
                          fill="none"
                          viewBox="0 0 24 24"
                          stroke="currentColor"
                          strokeWidth={2}
                        >
                          <path strokeLinecap="round" strokeLinejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                        </svg>
                        {!sidebarCollapsed ? <span className="truncate">{dict.app.nav.settings}</span> : null}
                      </Link>

                      {/* Sub-items (Companies, Branches, Numbering, Users) */}
                      {[
                        { key: 'settings.company' as NavKey, href: '/settings/company', label: dict.app.settings.sections.company.title, icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5m3 0h1m-1-4h1m-1-4h1m-1-4h1m-5 8h1m-1-4h1m-1-4h1 M14 7h1m-1 4h1m-1 4h1' },
                        { key: 'settings.branches' as NavKey, href: '/settings/branches', label: dict.app.settings.sections.branches.title, icon: 'M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z' },
                        { key: 'settings.numbering' as NavKey, href: '/settings/numbering', label: dict.app.settings.sections.numbering.title, icon: 'M7 20l4-16m2 16l4-16M6 9h14M4 15h14' },
                        { key: 'settings.branch_approval_rules' as NavKey, href: '/settings/branch-approval-rules', label: dict.app.settings.sections.branchApprovalRules.title, icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z' },
                        { key: 'settings.users' as NavKey, href: '/settings/users', label: dict.app.settings.sections.users.title, icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z' },
                         { key: 'audit.view' as NavKey, href: '/audit-log', label: dict.app.nav.auditLog, icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
                       ].filter((subItem) => navAllowed(subItem.key)).map((subItem) => {
                        const isSubActive = active === subItem.key;

                        return (
                          <Link
                            key={subItem.key}
                            href={subItem.href}
                            onClick={() => setMobileMenuOpen(false)}
                            title={sidebarCollapsed ? subItem.label : undefined}
                            className={`group relative flex items-center gap-2.5 rounded-xl py-2 text-xs font-medium no-underline transition-all ${
                              sidebarCollapsed ? 'size-10 justify-center mx-auto px-0' : 'px-3'
                            } ${
                              isSubActive
                                ? 'bg-gradient-to-br from-blue-600 to-indigo-600 text-white shadow-md shadow-blue-500/30 font-bold'
                                : 'text-[var(--text-secondary)] hover:bg-[var(--background)] hover:text-[var(--text-primary)]'
                            }`}
                          >
                            <svg
                              className={`size-3.5 shrink-0 transition-transform group-hover:scale-110 ${
                                isSubActive ? 'text-white' : 'text-[var(--text-muted)] group-hover:text-[var(--primary)]'
                              }`}
                              fill="none"
                              viewBox="0 0 24 24"
                              stroke="currentColor"
                              strokeWidth={2}
                            >
                              <path strokeLinecap="round" strokeLinejoin="round" d={subItem.icon} />
                            </svg>
                            {!sidebarCollapsed ? <span className="truncate">{subItem.label}</span> : null}
                          </Link>
                        );
                      })}
                    </div>
                  ) : null}
                </div>
                ) : null}
              </div>
            </div>
          </div>

          {/* Sidebar Footer System Health & Expand Toggle */}
          <div className="border-t border-[var(--border)] p-3 space-y-2">
            {sidebarCollapsed ? (
              <button
                type="button"
                onClick={toggleSidebarCollapse}
                title={dict.app.header.expandSidebar}
                className="flex size-10 items-center justify-center rounded-xl border border-[var(--border)] bg-[var(--background)] text-[var(--text-secondary)] hover:border-[var(--primary)] hover:text-[var(--primary)] transition-all mx-auto shadow-xs"
              >
                <svg className={`size-4 ${isRtl ? '' : 'rotate-180'}`} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
                </svg>
              </button>
            ) : null}

            <div
              title={sidebarCollapsed ? (isOnline ? dict.app.header.systemOnline : dict.app.header.systemOffline) : undefined}
              className={`flex items-center gap-3 rounded-xl border border-[var(--border)] bg-[var(--background)] p-3 ${
                sidebarCollapsed ? 'size-10 justify-center mx-auto p-0' : ''
              }`}
            >
              <span className={`flex size-2.5 shrink-0 rounded-full ${isOnline ? 'bg-emerald-500 animate-pulse' : 'bg-red-500'}`} />
              {!sidebarCollapsed ? (
                <div className="flex flex-col text-xs truncate">
                  <span className="font-bold text-[var(--text-primary)]">
                    {isOnline ? dict.app.header.systemOnline : dict.app.header.systemOffline}
                  </span>
                  <span className="text-[10px] text-[var(--text-muted)] truncate">Argon2id • Balanced Kernel</span>
                </div>
              ) : null}
            </div>
          </div>
        </aside>

        {/* Main Content Viewport */}
        <div className="flex min-w-0 flex-1 flex-col">
          {/* Top Bar Header */}
          <header data-tour="topbar" className="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-[var(--border)] bg-[var(--surface)]/75 px-4 sm:px-6 backdrop-blur-xl shadow-sm shadow-[var(--border)]">
            {/* Mobile Menu Button + Workspace Context */}
            <div className="flex items-center gap-3">
              <button
                type="button"
                onClick={() => setMobileMenuOpen(true)}
                className="rounded-xl border border-[var(--border)] p-2 text-[var(--text-secondary)] hover:bg-[var(--background)] hover:text-[var(--text-primary)] lg:hidden"
              >
                <svg className="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
              </button>

              <div className="hidden items-center gap-2 sm:flex">
                <div className="flex size-7 items-center justify-center rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400">
                  <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5m3 0h1m-1-4h1m-1-4h1m-1-4h1m-5 8h1m-1-4h1m-1-4h1" />
                  </svg>
                </div>
                <span className="text-xs font-bold text-[var(--text-primary)]">
                  {dict.app.header.workspace}
                </span>
                <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[10px] font-bold border transition-colors ${
                  isOnline
                    ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20'
                    : 'bg-red-500/10 text-red-600 dark:text-red-400 border-red-500/20'
                }`}>
                  <span className={`size-1.5 rounded-full ${isOnline ? 'bg-emerald-500 animate-pulse' : 'bg-red-500'}`} />
                  <span>{isOnline ? dict.app.header.systemOnline : dict.app.header.systemOffline}</span>
                </span>
              </div>
            </div>

            {/* Top Right Controls */}
            <div className="flex items-center gap-2.5">
              <TourGuide
                copy={dict.app.tour}
                locale={locale}
                pageKey={page.component}
                sectionKey={active}
              />

              {/* Notification Bell Dropdown */}
              <div className="relative">
                <button
                  type="button"
                  onClick={() => setNotifDropdownOpen(!notifDropdownOpen)}
                  title={dict.app.nav.notifications}
                  className={`relative flex size-9 items-center justify-center rounded-xl border bg-[var(--surface)] text-[var(--text-secondary)] shadow-xs transition-all hover:border-[var(--primary)] hover:text-[var(--text-primary)] ${
                    notifDropdownOpen ? 'border-[var(--primary)] text-[var(--primary)] ring-2 ring-blue-500/20' : 'border-[var(--border)]'
                  }`}
                >
                  <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                  </svg>
                  {unreadNotifications > 0 ? (
                    <span className="absolute -top-1 -end-1 flex size-4 items-center justify-center rounded-full bg-red-500 text-[9px] font-extrabold text-white shadow-xs">
                      {unreadNotifications}
                    </span>
                  ) : null}
                </button>

                {/* Dropdown Menu Popover */}
                {notifDropdownOpen ? (
                  <>
                    <div
                      onClick={() => setNotifDropdownOpen(false)}
                      className="fixed inset-0 z-40"
                    />
                    <div className="absolute end-0 top-full mt-2.5 z-50 w-80 rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-3.5 shadow-2xl shadow-slate-900/30 backdrop-blur-xl animate-in fade-in duration-150">
                      <div className="flex items-center justify-between border-b border-[var(--border)] pb-2.5 px-1">
                        <div className="flex items-center gap-2">
                          <span className="text-xs font-bold text-[var(--text-primary)]">
                            {dict.app.nav.notifications}
                          </span>
                          {unreadNotifications > 0 ? (
                            <span className="rounded-full bg-blue-500/10 px-2 py-0.5 text-[10px] font-bold text-blue-600 dark:text-blue-400">
                              {unreadNotifications} {dict.app.notifications.unread}
                            </span>
                          ) : null}
                        </div>
                        <Link
                          href="/notifications"
                          onClick={() => setNotifDropdownOpen(false)}
                          className="text-[11px] font-semibold text-[var(--primary)] hover:underline no-underline"
                        >
                          {dict.app.notifications.viewAll}
                        </Link>
                      </div>

                      {/* Notifications List */}
                      <div className="my-2 max-h-64 overflow-y-auto space-y-1.5 pe-0.5">
                        {props.notifications?.recent && props.notifications.recent.length > 0 ? (
                          props.notifications.recent.map((item) => (
                            <div
                              key={item.id}
                              className={`flex items-start justify-between rounded-xl p-2.5 text-xs transition-colors ${
                                !item.read
                                  ? 'bg-blue-500/5 border border-blue-500/15 dark:bg-blue-500/10'
                                  : 'bg-[var(--background)] hover:bg-[var(--surface)]'
                              }`}
                            >
                              <div className="space-y-1 min-w-0 flex-1 me-2">
                                <div className="flex items-center gap-1.5">
                                  {!item.read ? (
                                    <span className="size-2 rounded-full bg-blue-500 shrink-0" />
                                  ) : null}
                                  <span className="font-semibold text-[var(--text-primary)] truncate">
                                    {item.type}
                                  </span>
                                </div>
                                <p className="m-0 text-[11px] text-[var(--text-secondary)] truncate">
                                  {item.targetRef || dict.app.notifications.system}
                                </p>
                                <p className="m-0 text-[9px] text-[var(--text-muted)]">
                                  {item.at}
                                </p>
                              </div>
                            </div>
                          ))
                        ) : (
                          <div className="py-6 text-center text-xs text-[var(--text-muted)]">
                            {dict.app.notifications.emptyRecent}
                          </div>
                        )}
                      </div>

                      <div className="border-t border-[var(--border)] pt-2 text-center">
                        <Link
                          href="/notifications"
                          onClick={() => setNotifDropdownOpen(false)}
                          className="block rounded-xl bg-[var(--background)] py-2 text-center text-xs font-semibold text-[var(--text-primary)] hover:bg-[var(--primary)] hover:text-white transition-colors no-underline"
                        >
                          {dict.app.notifications.viewAll}
                        </Link>
                      </div>
                    </div>
                  </>
                ) : null}
              </div>

              {/* Language Switcher Pill */}
              <button
                type="button"
                onClick={() => changeLocale(locale === 'ar' ? 'en' : 'ar')}
                title={dict.app.header.switchLanguage}
                className="flex size-9 items-center justify-center rounded-xl border border-[var(--border)] bg-[var(--surface)] text-xs font-extrabold text-[var(--text-primary)] shadow-xs transition-all hover:border-[var(--primary)]"
              >
                {locale === 'ar' ? dict.common.language.en : dict.common.language.ar}
              </button>

              {/* Theme Toggle Icon */}
              <button
                type="button"
                onClick={() => handleThemeChange(currentTheme === 'dark' ? 'light' : 'dark')}
                title={currentTheme === 'dark' ? dict.common.theme.light : dict.common.theme.dark}
                className="flex size-9 items-center justify-center rounded-xl border border-[var(--border)] bg-[var(--surface)] text-[var(--text-secondary)] shadow-xs transition-colors hover:border-[var(--primary)] hover:text-[var(--text-primary)]"
              >
                {currentTheme === 'dark' ? (
                  <svg className="size-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                  </svg>
                ) : (
                  <svg className="size-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                  </svg>
                )}
              </button>

              {/* User Avatar Menu Dropdown */}
              <div className="relative border-s border-[var(--border)] ps-2">
                <button
                  type="button"
                  onClick={() => setUserMenuOpen(!userMenuOpen)}
                  className={`flex items-center gap-2 rounded-xl p-1 transition-all ${
                    userMenuOpen ? 'ring-2 ring-blue-500/20 bg-[var(--background)]' : 'hover:bg-[var(--background)]'
                  }`}
                >
                  <div className="flex size-8 items-center justify-center rounded-xl bg-gradient-to-br from-blue-600 to-violet-600 text-xs font-bold text-white shadow-md shadow-blue-500/30">
                    {userInitials}
                  </div>
                  <svg className={`size-3 text-[var(--text-muted)] transition-transform duration-200 ${userMenuOpen ? 'rotate-180' : ''}`} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M19 9l-7 7-7-7" />
                  </svg>
                </button>

                {userMenuOpen ? (
                  <>
                    <div onClick={() => setUserMenuOpen(false)} className="fixed inset-0 z-40" />
                    <div className="absolute end-0 top-full mt-2.5 z-50 w-56 rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-3 shadow-2xl shadow-slate-900/30 backdrop-blur-xl animate-in fade-in duration-150">
                      <div className="border-b border-[var(--border)] pb-2 px-1">
                        <p className="m-0 text-xs font-bold text-[var(--text-primary)] truncate">{props.auth.user?.name || dict.app.header.unknownUser}</p>
                        <p className="m-0 mt-0.5 text-[10px] text-[var(--text-muted)] truncate">{props.auth.user?.email || dict.app.header.unknownEmail}</p>
                      </div>

                      <form onSubmit={logout} className="mt-2">
                        <button
                          type="submit"
                          disabled={processing}
                          className="flex w-full items-center justify-between rounded-xl px-2.5 py-2 text-xs font-semibold text-[var(--danger)] hover:bg-red-50 dark:hover:bg-red-950/40 transition-colors disabled:opacity-50"
                        >
                          <span>{dict.app.actions.logout}</span>
                          <svg className="size-4 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                          </svg>
                        </button>
                      </form>
                    </div>
                  </>
                ) : null}
              </div>
            </div>
          </header>

          {/* Page Content Container */}
          <main
            ref={mainContentRef}
            data-tour="page-content"
            tabIndex={-1}
            className="flex-1 p-4 focus:outline-none sm:p-6 lg:p-8"
          >
            <div className="mx-auto max-w">
              {children}
              <UniversalPagination
                locale={locale}
                mode={pagination}
                pageProps={props as unknown as Record<string, unknown>}
              />
            </div>
          </main>
        </div>
      </div>
    </div>
  );
}
