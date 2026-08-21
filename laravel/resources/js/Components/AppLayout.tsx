import { Link, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState, type FormEvent, type ReactNode } from 'react';

import { changeLocale, getDictionary } from '../lib/i18n';
import type { SharedPageProps } from '../Types/page';

export type NavKey =
  | 'dashboard'
  | 'settings'
  | 'settings.company'
  | 'settings.branches'
  | 'settings.numbering'
  | 'settings.users'
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
  | 'accounting.account_categories';

type AppLayoutProps = {
  active: NavKey;
  children: ReactNode;
};

export default function AppLayout({ active, children }: AppLayoutProps) {
  const { props } = usePage<SharedPageProps>();
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  const [sidebarCollapsed, setSidebarCollapsed] = useState(false);
  const [notifDropdownOpen, setNotifDropdownOpen] = useState(false);
  const [userMenuOpen, setUserMenuOpen] = useState(false);
  const [adminExpanded, setAdminExpanded] = useState(() => active.startsWith('settings'));
  const [accountingExpanded, setAccountingExpanded] = useState(() => active.startsWith('accounting'));
  const [currentTheme, setCurrentTheme] = useState<string>(props.theme || 'system');
  const [isOnline, setIsOnline] = useState(() => (typeof navigator !== 'undefined' ? navigator.onLine : true));

  const locale = props.locale === 'ar' ? 'ar' : 'en';
  const isRtl = locale === 'ar';
  const dict = getDictionary(locale);
  const accDict = (dict.app as any).accounting || {};
  const { post, processing } = useForm({});

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

  return (
    <div className="min-h-screen bg-[var(--background)] text-[var(--text-primary)] transition-colors duration-200">
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
                  <div className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-blue-600 text-white shadow-md shadow-blue-500/20">
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
          <div className="flex-1 overflow-y-auto px-3 py-4 space-y-5">
            {/* GROUP 1: MAIN */}
            <div className="space-y-1">
              {!sidebarCollapsed ? (
                <p className="px-3 text-[10px] font-bold uppercase tracking-wider text-[var(--text-muted)]">
                  {dict.app.nav.groups.main}
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
                      ? 'bg-[var(--primary)] text-white shadow-md shadow-blue-500/20'
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

                {/* Accounting Core Link & Sub-menu */}
                <div className="space-y-1">
                  <div
                    className={`group relative flex items-center justify-between rounded-xl py-2.5 text-xs font-semibold transition-all ${
                      sidebarCollapsed ? 'size-10 justify-center mx-auto px-0' : 'px-3'
                    } ${
                      active.startsWith('accounting')
                        ? 'bg-[var(--primary)] text-white shadow-md shadow-blue-500/20'
                        : 'text-[var(--text-secondary)] hover:bg-[var(--background)] hover:text-[var(--text-primary)]'
                    }`}
                  >
                    <Link
                      href="/accounting"
                      onClick={() => setMobileMenuOpen(false)}
                      title={sidebarCollapsed ? (accDict.title || 'Accounting Core') : undefined}
                      className="flex flex-1 items-center gap-3 no-underline text-inherit"
                    >
                      <svg
                        className={`size-4 shrink-0 transition-transform group-hover:scale-110 ${
                          active.startsWith('accounting') ? 'text-white' : 'text-[var(--text-muted)] group-hover:text-[var(--primary)]'
                        }`}
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        strokeWidth={2}
                      >
                        <path strokeLinecap="round" strokeLinejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                      </svg>
                      {!sidebarCollapsed ? <span>{accDict.title || 'Accounting Core'}</span> : null}
                    </Link>
                    {!sidebarCollapsed ? (
                      <button
                        type="button"
                        onClick={() => setAccountingExpanded(!accountingExpanded)}
                        className="p-1 hover:text-white transition-colors cursor-pointer"
                      >
                        <svg
                          className={`size-3 transition-transform duration-200 ${accountingExpanded ? 'rotate-180' : ''}`}
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
                    <div className="space-y-1 pt-0.5">
                      {[
                        { key: 'accounting.coa' as NavKey, href: '/accounting/coa', label: accDict.coa || 'Chart of Accounts', icon: 'M4 6h16M4 10h16M4 14h16M4 18h16' },
                        { key: 'accounting.account_categories' as NavKey, href: '/accounting/account-categories', label: accDict.accountCategories || (locale === 'ar' ? 'تصنيفات الحسابات' : 'Account Categories'), icon: 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10' },
                        { key: 'accounting.account_types' as NavKey, href: '/accounting/account-types', label: accDict.accountTypes || 'Account Types', icon: 'M7 7h10M7 12h10M7 17h10' },
                        { key: 'accounting.journal' as NavKey, href: '/accounting/journal', label: accDict.journal || 'General Journal', icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
                        { key: 'accounting.ledger' as NavKey, href: '/accounting/ledger', label: accDict.ledger || 'General Ledger', icon: 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
                        { key: 'accounting.trial_balance' as NavKey, href: '/accounting/trial-balance', label: accDict.trialBalance || 'Trial Balance', icon: 'M3 6l9-4 9 4v14l-9 4-9-4V6z' },
                        { key: 'accounting.periods' as NavKey, href: '/accounting/periods', label: accDict.periods || 'Fiscal Periods', icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' },
                        { key: 'accounting.opening_balances' as NavKey, href: '/accounting/opening-balances', label: accDict.openingBalances || 'Opening Balances', icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
                        { key: 'accounting.fx_rates' as NavKey, href: '/accounting/fx-rates', label: accDict.fxRates || 'Exchange Rates', icon: 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4' },
                        { key: 'accounting.currencies' as NavKey, href: '/accounting/currencies', label: accDict.currencies || 'Currencies', icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
                      ].map((subItem) => {
                        const isSubActive = active === subItem.key;
                        return (
                          <Link
                            key={subItem.key}
                            href={subItem.href}
                            onClick={() => setMobileMenuOpen(false)}
                            title={sidebarCollapsed ? subItem.label : undefined}
                            className={`group relative flex items-center gap-3 rounded-xl py-2 text-xs font-medium no-underline transition-all ${
                              sidebarCollapsed ? 'size-10 justify-center mx-auto px-0' : 'px-3 ps-6'
                            } ${
                              isSubActive
                                ? 'bg-[var(--primary)] text-white shadow-xs font-bold'
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

                {/* Notifications Link */}
                <Link
                  href="/notifications"
                  onClick={() => setMobileMenuOpen(false)}
                  title={sidebarCollapsed ? dict.app.nav.notifications : undefined}
                  className={`group relative flex items-center gap-3 rounded-xl py-2.5 text-xs font-semibold no-underline transition-all ${
                    sidebarCollapsed ? 'size-10 justify-center mx-auto px-0' : 'px-3 justify-between'
                  } ${
                    active === 'notifications'
                      ? 'bg-[var(--primary)] text-white shadow-md shadow-blue-500/20'
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
              </div>
            </div>

            {/* GROUP 2: ADMINISTRATION (Collapsible Sub-Menu) */}
            <div className="space-y-1">
              {!sidebarCollapsed ? (
                <div className="flex items-center justify-between px-3 text-[10px] font-bold uppercase tracking-wider text-[var(--text-muted)]">
                  <span>{dict.app.nav.groups.administration}</span>
                  <button
                    type="button"
                    onClick={() => setAdminExpanded(!adminExpanded)}
                    className="p-0.5 hover:text-[var(--text-primary)] transition-colors"
                  >
                    <svg
                      className={`size-3 transition-transform duration-200 ${adminExpanded ? 'rotate-180' : ''}`}
                      fill="none"
                      viewBox="0 0 24 24"
                      stroke="currentColor"
                      strokeWidth={2}
                    >
                      <path strokeLinecap="round" strokeLinejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                  </button>
                </div>
              ) : (
                <div className="my-1 border-t border-[var(--border)]" />
              )}

              {(adminExpanded || sidebarCollapsed) ? (
                <div className="space-y-1 pt-0.5">
                  {/* Settings Main Overview Link */}
                  <Link
                    href="/settings"
                    onClick={() => setMobileMenuOpen(false)}
                    title={sidebarCollapsed ? dict.app.nav.settings : undefined}
                    className={`group relative flex items-center gap-3 rounded-xl py-2.5 text-xs font-semibold no-underline transition-all ${
                      sidebarCollapsed ? 'size-10 justify-center mx-auto px-0' : 'px-3 justify-between'
                    } ${
                      active === 'settings'
                        ? 'bg-[var(--primary)] text-white shadow-md shadow-blue-500/20'
                        : 'text-[var(--text-secondary)] hover:bg-[var(--background)] hover:text-[var(--text-primary)]'
                    }`}
                  >
                    <div className="flex items-center gap-3">
                      <svg
                        className={`size-4 shrink-0 transition-transform group-hover:scale-110 ${
                          active === 'settings' ? 'text-white' : 'text-[var(--text-muted)] group-hover:text-[var(--primary)]'
                        }`}
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        strokeWidth={2}
                      >
                        <path strokeLinecap="round" strokeLinejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      </svg>
                      {!sidebarCollapsed ? <span>{dict.app.nav.settings}</span> : null}
                    </div>
                  </Link>

                  {/* Sub-items (Companies, Branches, Numbering, Users) */}
                  {[
                    { key: 'settings.company' as NavKey, href: '/settings/company', label: dict.app.settings.sections.company.title, icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5m3 0h1m-1-4h1m-1-4h1m-1-4h1m-5 8h1m-1-4h1m-1-4h1 M14 7h1m-1 4h1m-1 4h1' },
                    { key: 'settings.branches' as NavKey, href: '/settings/branches', label: dict.app.settings.sections.branches.title, icon: 'M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z' },
                    { key: 'settings.numbering' as NavKey, href: '/settings/numbering', label: dict.app.settings.sections.numbering.title, icon: 'M7 20l4-16m2 16l4-16M6 9h14M4 15h14' },
                    { key: 'settings.users' as NavKey, href: '/settings/users', label: dict.app.settings.sections.users.title, icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z' },
                  ].map((subItem) => {
                    const isSubActive = active === subItem.key;

                    return (
                      <Link
                        key={subItem.key}
                        href={subItem.href}
                        onClick={() => setMobileMenuOpen(false)}
                        title={sidebarCollapsed ? subItem.label : undefined}
                        className={`group relative flex items-center gap-3 rounded-xl py-2 text-xs font-medium no-underline transition-all ${
                          sidebarCollapsed ? 'size-10 justify-center mx-auto px-0' : 'px-3 ps-6'
                        } ${
                          isSubActive
                            ? 'bg-[var(--primary)] text-white shadow-xs font-bold'
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

            {/* GROUP 3: SYSTEM */}
            <div className="space-y-1">
              {!sidebarCollapsed ? (
                <p className="px-3 text-[10px] font-bold uppercase tracking-wider text-[var(--text-muted)]">
                  {dict.app.nav.groups.system}
                </p>
              ) : (
                <div className="my-1 border-t border-[var(--border)]" />
              )}

              <div className="space-y-1">
                <Link
                  href="/foundation"
                  onClick={() => setMobileMenuOpen(false)}
                  title={sidebarCollapsed ? dict.app.nav.diagnostics : undefined}
                  className={`group relative flex items-center gap-3 rounded-xl py-2.5 text-xs font-semibold no-underline transition-all ${
                    sidebarCollapsed ? 'size-10 justify-center mx-auto px-0' : 'px-3 justify-between'
                  } ${
                    active === 'foundation'
                      ? 'bg-[var(--primary)] text-white shadow-md shadow-blue-500/20'
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
          <header className="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-[var(--border)] bg-[var(--surface)]/90 px-4 sm:px-6 backdrop-blur-md">
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
                title="Switch Language"
                className="flex size-9 items-center justify-center rounded-xl border border-[var(--border)] bg-[var(--surface)] text-xs font-extrabold text-[var(--text-primary)] shadow-xs transition-all hover:border-[var(--primary)]"
              >
                {locale === 'ar' ? 'EN' : 'عربي'}
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
                  <div className="flex size-8 items-center justify-center rounded-lg bg-gradient-to-tr from-blue-600 to-indigo-500 text-xs font-bold text-white shadow-xs">
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
                        <p className="m-0 text-xs font-bold text-[var(--text-primary)] truncate">{props.auth.user?.name || 'Admin'}</p>
                        <p className="m-0 mt-0.5 text-[10px] text-[var(--text-muted)] truncate">{props.auth.user?.email || 'admin@mini-erp.local'}</p>
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
          <main className="flex-1 p-4 sm:p-6 lg:p-8">
            <div className="mx-auto max-w-7xl">{children}</div>
          </main>
        </div>
      </div>
    </div>
  );
}
