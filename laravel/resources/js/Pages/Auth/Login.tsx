import { Head, useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import TourGuide from '../../Components/TourGuide';
import { changeLocale, getDictionary } from '../../lib/i18n';
import type { SharedPageProps } from '../../Types/page';

type LoginForm = {
  email: string;
  password: string;
  remember: boolean;
};

export default function Login() {
  const { props } = usePage<SharedPageProps>();
  const [showPassword, setShowPassword] = useState(false);
  const [currentTheme, setCurrentTheme] = useState<string>(props.theme || 'system');
  const currentLocale = (props.locale === 'ar' ? 'ar' : 'en') as 'en' | 'ar';
  
  const dict = getDictionary(currentLocale);
  const t = dict.auth.login;
  const common = dict.common;
  const passwordToggleLabel = showPassword ? t.hidePassword : t.showPassword;
  const devQuickFillEnabled = import.meta.env.DEV;

  const { data, setData, post, processing, errors, reset } = useForm<LoginForm>({
    email: '',
    password: '',
    remember: false,
  });
  const loginSubmitLabel = processing ? t.submitting : t.submitButton;

  function handleThemeChange(newTheme: 'light' | 'dark' | 'system') {
    setCurrentTheme(newTheme);
    if (newTheme === 'system') {
      const isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
      document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');
    } else {
      document.documentElement.setAttribute('data-theme', newTheme);
    }
  }

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    post('/login', {
      preserveScroll: true,
      onFinish: () => reset('password'),
    });
  }

  function fillCredentials(email: string = 'admin@mini-erp.local', password: string = 'Password123!') {
    setData('email', email);
    setData('password', password);
  }

  return (
    <>
      <Head title={t.title} />
      <div dir={currentLocale === 'ar' ? 'rtl' : 'ltr'} className="flex min-h-screen w-full bg-[var(--background)] text-[var(--text-primary)] transition-colors duration-200">
        {/* Left Hero Section (Visible on LG screens) */}
        <div className="relative hidden w-1/2 flex-col justify-between overflow-hidden bg-gradient-to-br from-slate-900 via-blue-950 to-slate-900 p-12 text-white lg:flex">
          {/* Ambient Background Grids */}
          <div className="absolute inset-0 bg-[radial-gradient(#38bdf8_1px,transparent_1px)] [background-size:24px_24px] opacity-10" />
          <div className="absolute -top-32 -left-32 h-96 w-96 rounded-full bg-blue-500/20 blur-3xl" />
          <div className="absolute -bottom-32 -right-32 h-96 w-96 rounded-full bg-indigo-500/20 blur-3xl" />

          {/* Top Brand Header */}
          <div data-tour="login-brand" className="relative z-10 flex items-center gap-3">
            <div className="flex size-11 items-center justify-center rounded-xl bg-blue-600/90 text-white shadow-lg shadow-blue-500/30 backdrop-blur-md ring-1 ring-white/20">
              <svg className="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
              </svg>
            </div>
            <div>
              <span className="text-lg font-bold tracking-tight text-white">Mini ERP</span>
              <span className="ml-2 rounded-full bg-blue-500/20 px-2 py-0.5 text-xs font-semibold text-blue-300 border border-blue-400/30">
                Enterprise v1.0
              </span>
            </div>
          </div>

          {/* Center Showcase Content */}
          <div data-tour="login-features" className="relative z-10 max-w-lg space-y-6">
            <div className="inline-flex items-center gap-2 rounded-full bg-white/10 px-3.5 py-1.5 text-xs font-medium text-blue-200 backdrop-blur-md ring-1 ring-white/15">
              <span className="flex size-2 rounded-full bg-emerald-400 animate-pulse" />
              {t.heroTag}
            </div>

            <h1 className="text-4xl font-extrabold tracking-tight text-white leading-tight">
              {t.heroHeadingPrefix}
              <span className="bg-gradient-to-r from-blue-400 to-indigo-300 bg-clip-text text-transparent">
                {t.heroHeadingHighlight}
              </span>
            </h1>

            <p className="text-base text-slate-300 leading-relaxed">
              {t.heroDesc}
            </p>

            {/* Feature Highlights */}
            <div className="space-y-3.5 pt-2">
              {t.features.map((item, idx) => (
                <div key={idx} className="flex items-start gap-3 rounded-lg bg-white/5 p-3 backdrop-blur-sm ring-1 ring-white/10">
                  <div className="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-blue-500/20 text-blue-400">
                    <svg className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={3}>
                      <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                  </div>
                  <div>
                    <p className="text-xs font-semibold text-white">{item.title}</p>
                    <p className="text-xs text-slate-400">{item.desc}</p>
                  </div>
                </div>
              ))}
            </div>
          </div>

          {/* Footer Security Badge */}
          <div className="relative z-10 flex items-center justify-between border-t border-white/10 pt-6 text-xs text-slate-400">
            <span className="flex items-center gap-1.5">
              <svg className="size-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
              </svg>
              Argon2id Encrypted Sessions
            </span>
            <span>© 2026 Mini ERP Inc.</span>
          </div>
        </div>

        {/* Right Form Section */}
        <div className="relative flex w-full flex-col justify-between p-6 sm:p-12 lg:w-1/2">
          {/* Top Bar: Brand (Mobile) + Locale Switcher + Theme Switcher */}
          <div className="flex items-center justify-between gap-3">
            <div className="flex items-center gap-2 lg:hidden">
              <div className="flex size-9 items-center justify-center rounded-lg bg-[var(--primary)] text-white shadow-md">
                <svg className="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
              </div>
              <span className="font-bold tracking-tight text-[var(--text-primary)]">Mini ERP</span>
            </div>

            <div data-tour="login-preferences" className="ml-auto flex items-center gap-2">
              <TourGuide
                copy={dict.app.tour}
                locale={currentLocale}
                pageKey="Auth/Login"
                sectionKey="login"
                variant="login"
              />

              {/* Locale Switcher */}
              <div className="flex items-center rounded-lg border border-[var(--border)] bg-[var(--surface)] p-1 shadow-sm">
                <button
                  type="button"
                  onClick={() => changeLocale('en')}
                  title={t.switchToEnglish}
                  aria-label={t.switchToEnglish}
                  className={`rounded-md px-2.5 py-1 text-xs font-semibold transition-all ${
                    currentLocale === 'en'
                      ? 'bg-[var(--primary)] text-white shadow-xs'
                      : 'text-[var(--text-secondary)] hover:text-[var(--text-primary)]'
                  }`}
                >
                  {common.language.en}
                </button>
                <button
                  type="button"
                  onClick={() => changeLocale('ar')}
                  title={t.switchToArabic}
                  aria-label={t.switchToArabic}
                  className={`rounded-md px-2.5 py-1 text-xs font-semibold transition-all ${
                    currentLocale === 'ar'
                      ? 'bg-[var(--primary)] text-white shadow-xs'
                      : 'text-[var(--text-secondary)] hover:text-[var(--text-primary)]'
                  }`}
                >
                  {common.language.ar}
                </button>
              </div>

              {/* Theme Switcher */}
              <div className="flex items-center rounded-lg border border-[var(--border)] bg-[var(--surface)] p-1 shadow-sm">
                {[
                  { key: 'light', icon: 'M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z', label: common.theme.light },
                  { key: 'dark', icon: 'M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z', label: common.theme.dark },
                  { key: 'system', icon: 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z', label: common.theme.system },
                ].map((mode) => (
                  <button
                    key={mode.key}
                    type="button"
                    onClick={() => handleThemeChange(mode.key as 'light' | 'dark' | 'system')}
                    title={t.switchTheme.replace(':theme', mode.label)}
                    aria-label={t.switchTheme.replace(':theme', mode.label)}
                    className={`flex items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-medium transition-all ${
                      currentTheme === mode.key
                        ? 'bg-[var(--primary)] text-white shadow-xs'
                        : 'text-[var(--text-secondary)] hover:text-[var(--text-primary)]'
                    }`}
                  >
                    <svg className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                      <path strokeLinecap="round" strokeLinejoin="round" d={mode.icon} />
                    </svg>
                    <span className="hidden sm:inline">{mode.label}</span>
                  </button>
                ))}
              </div>
            </div>
          </div>

          {/* Form Container */}
          <div data-tour="login-form" className="mx-auto my-auto w-full max-w-md py-8">
            <div className="mb-8 text-start">
              <h2 className="text-2xl font-bold tracking-tight text-[var(--text-primary)] sm:text-3xl">
                {t.heading}
              </h2>
              <p className="mt-2 text-sm text-[var(--text-secondary)]">
                {t.subtitle}
              </p>
            </div>

            {/* Error Banner */}
            {errors.email || errors.password ? (
              <div className="mb-6 flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-300">
                <svg className="size-5 shrink-0 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <div className="space-y-0.5">
                  <p className="font-semibold">{t.authFailed}</p>
                  <p className="text-xs text-red-700 dark:text-red-400">
                    {errors.email || errors.password}
                  </p>
                </div>
              </div>
            ) : null}

            <form onSubmit={submit} className="space-y-5">
              {/* Email Input */}
              <div className="space-y-1.5">
                <label className="text-xs font-semibold uppercase tracking-wider text-[var(--text-secondary)]">
                  {t.emailLabel}
                </label>
                <div className="relative">
                  <div className="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3.5 text-[var(--text-muted)]">
                    <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                      <path strokeLinecap="round" strokeLinejoin="round" d="M16 12a4 4 0 10-8 0 4 4 0 018 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" />
                    </svg>
                  </div>
                  <input
                    type="email"
                    name="email"
                    value={data.email}
                    onChange={(event) => setData('email', event.target.value)}
                    placeholder="name@company.com"
                    autoComplete="username"
                    autoFocus
                    required
                    className="block w-full rounded-xl border border-[var(--border)] bg-[var(--surface)] py-2.5 ps-10 pe-3.5 text-sm text-[var(--text-primary)] placeholder-[var(--text-muted)] shadow-xs transition-all outline-none focus:border-[var(--primary)] focus:ring-2 focus:ring-[var(--primary-glow)]"
                  />
                </div>
              </div>

              {/* Password Input */}
              <div className="space-y-1.5">
                <div className="flex items-center justify-between">
                  <label className="text-xs font-semibold uppercase tracking-wider text-[var(--text-secondary)]">
                    {t.passwordLabel}
                  </label>
                  <a href="#" onClick={(e) => e.preventDefault()} className="text-xs font-semibold text-[var(--primary)] hover:underline">
                    {t.forgotPassword}
                  </a>
                </div>
                <div className="relative">
                  <div className="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3.5 text-[var(--text-muted)]">
                    <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                      <path strokeLinecap="round" strokeLinejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                  </div>
                  <input
                    type={showPassword ? 'text' : 'password'}
                    name="password"
                    value={data.password}
                    onChange={(event) => setData('password', event.target.value)}
                    placeholder="••••••••••••"
                    autoComplete="current-password"
                    required
                    className="block w-full rounded-xl border border-[var(--border)] bg-[var(--surface)] py-2.5 ps-10 pe-10 text-sm text-[var(--text-primary)] placeholder-[var(--text-muted)] shadow-xs transition-all outline-none focus:border-[var(--primary)] focus:ring-2 focus:ring-[var(--primary-glow)]"
                  />
                  <button
                    type="button"
                    onClick={() => setShowPassword(!showPassword)}
                    title={passwordToggleLabel}
                    aria-label={passwordToggleLabel}
                    className="absolute inset-y-0 end-0 flex items-center pe-3.5 text-[var(--text-muted)] hover:text-[var(--text-primary)] transition-colors"
                  >
                    {showPassword ? (
                      <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                      </svg>
                    ) : (
                      <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path strokeLinecap="round" strokeLinejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                    )}
                  </button>
                </div>
              </div>

              {/* Remember Me */}
              <div className="flex items-center justify-between">
                <label className="flex cursor-pointer items-center gap-2.5 text-xs text-[var(--text-secondary)] select-none">
                  <input
                    type="checkbox"
                    checked={data.remember}
                    onChange={(event) => setData('remember', event.target.checked)}
                    className="size-4 rounded-md border border-[var(--border)] text-[var(--primary)] focus:ring-0 focus:ring-offset-0"
                  />
                  <span>{t.rememberMe}</span>
                </label>
              </div>

              {/* Submit Button */}
              <button
                type="submit"
                disabled={processing}
                title={loginSubmitLabel}
                aria-label={loginSubmitLabel}
                className="group relative flex w-full items-center justify-center gap-2 rounded-xl bg-[var(--primary)] py-3 px-4 text-sm font-semibold text-white shadow-md shadow-blue-500/20 transition-all hover:bg-[var(--primary-hover)] active:scale-[0.99] disabled:cursor-not-allowed disabled:opacity-60"
              >
                {processing ? (
                  <>
                    <svg className="size-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                      <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                      <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                    </svg>
                    <span>{t.submitting}</span>
                  </>
                ) : (
                  <>
                    <span>{t.submitButton}</span>
                    <svg className="size-4 transition-transform group-hover:translate-x-0.5 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                      <path strokeLinecap="round" strokeLinejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                  </>
                )}
              </button>
            </form>

            {/* Dev Quick Fill Helper */}
            {devQuickFillEnabled ? (
              <div className="mt-8 rounded-xl border border-dashed border-[var(--border)] p-3.5 bg-[var(--surface)]/50 text-xs">
                <p className="font-semibold text-[var(--text-secondary)]">{t.devQuickFill}</p>
                <div className="mt-2 flex flex-wrap gap-2">
                  <button
                    type="button"
                    onClick={() => fillCredentials('admin@mini-erp.local', 'Password123!')}
                    title={t.fillDevAdminCredentials}
                    aria-label={t.fillDevAdminCredentials}
                    className="rounded-md border border-[var(--border)] bg-[var(--background)] px-2.5 py-1 text-xs font-semibold text-[var(--primary)] hover:border-[var(--primary)] hover:bg-[var(--surface)] transition-colors"
                  >
                    admin@mini-erp.local
                  </button>
                </div>
              </div>
            ) : null}
          </div>

          {/* Bottom Footer */}
          <div className="text-center text-xs text-[var(--text-muted)] lg:text-left rtl:lg:text-right">
            {t.footerText}
          </div>
        </div>
      </div>
    </>
  );
}
