import { Link } from '@inertiajs/react';
import type { HTMLAttributes, ReactNode } from 'react';

import { formatAccountingAmount } from '../lib/accountingHelpers';
import type { PaginationLink } from '../Types';

export function PageHeader({
  title,
  description,
  actions,
}: {
  title: string;
  description?: string;
  actions?: ReactNode;
}) {
  return (
    <div data-tour="page-header" className="mb-5 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-start">
      <div className="min-w-0 sm:flex-1">
        <h1 className="m-0 text-2xl font-bold text-[var(--text-primary)]">{title}</h1>
        {description ? (
          <p className="mt-1 max-w-3xl text-sm leading-6 text-[var(--text-secondary)]">{description}</p>
        ) : null}
      </div>
      {actions ? (
        <div data-tour="page-actions" className="flex flex-wrap items-center gap-2 print:hidden">
          {actions}
        </div>
      ) : null}
    </div>
  );
}

export function Card({
  children,
  className = '',
  ...props
}: {
  children: ReactNode;
  className?: string;
} & Omit<HTMLAttributes<HTMLElement>, 'className'>) {
  return (
    <section {...props} className={`rounded-md border border-[var(--border)] bg-[var(--surface)] shadow-sm ${className}`}>
      {children}
    </section>
  );
}

export function MetricCard({
  label,
  value,
  tone = 'muted',
  hint,
}: {
  label: string;
  value: ReactNode;
  tone?: 'blue' | 'emerald' | 'purple' | 'amber' | 'danger' | 'muted';
  hint?: ReactNode;
}) {
  const tones = {
    blue: 'border-s-blue-500',
    emerald: 'border-s-emerald-500',
    purple: 'border-s-purple-500',
    amber: 'border-s-amber-500',
    danger: 'border-s-red-500',
    muted: 'border-s-[var(--border)]',
  };

  return (
    <Card className={`border-s-4 p-4 ${tones[tone]}`}>
      <span className="block text-xs font-bold uppercase text-[var(--text-secondary)]">{label}</span>
      <span className="accounting-amount mt-2 block text-xl font-extrabold text-[var(--text-primary)]">{value}</span>
      {hint ? <span className="mt-1 block text-xs text-[var(--text-muted)]">{hint}</span> : null}
    </Card>
  );
}

export function AccountingAmount({
  amountMinor,
  currency = 'EGP',
  tone,
  className = '',
  showCurrency = true,
}: {
  amountMinor: number | string | null | undefined;
  currency?: string;
  tone?: 'debit' | 'credit' | 'net' | 'muted' | 'danger' | 'success';
  className?: string;
  showCurrency?: boolean;
}) {
  const tones = {
    debit: 'text-blue-600 dark:text-blue-400',
    credit: 'text-purple-600 dark:text-purple-400',
    net: 'text-[var(--text-primary)]',
    muted: 'text-[var(--text-secondary)]',
    danger: 'text-red-600 dark:text-red-400',
    success: 'text-emerald-600 dark:text-emerald-400',
  };

  return (
    <span className={`accounting-amount font-mono font-bold ${tones[tone || 'net']} ${className}`}>
      {formatAccountingAmount(amountMinor, currency, { showCurrency })}
    </span>
  );
}

export function Button({
  children,
  onClick,
  className = '',
  variant = 'primary',
  type = 'button',
  disabled = false,
  title,
  'aria-label': ariaLabel,
}: {
  children: ReactNode;
  onClick?: () => void;
  className?: string;
  variant?: 'primary' | 'secondary' | 'danger';
  type?: 'button' | 'submit' | 'reset';
  disabled?: boolean;
  title?: string;
  'aria-label'?: string;
}) {
  const base = 'inline-flex items-center justify-center rounded-xl px-4 py-2 text-xs font-bold transition-all cursor-pointer border shadow-2xs';
  const variants = {
    primary: 'bg-[var(--primary)] text-white hover:opacity-90 border-transparent',
    secondary: 'border-[var(--border)] bg-[var(--surface)] text-[var(--text-primary)] hover:bg-[var(--background)]',
    danger: 'bg-red-600 text-white hover:bg-red-700 border-transparent',
  };

  return (
    <button
      type={type}
      onClick={onClick}
      disabled={disabled}
      title={title}
      aria-label={ariaLabel}
      className={`${base} ${variants[variant]} ${disabled ? 'opacity-50 cursor-not-allowed' : ''} ${className}`}
    >
      {children}
    </button>
  );
}

export function EmptyState({
  title,
  description,
  action,
}: {
  title: string;
  description?: string;
  action?: ReactNode;
}) {
  return (
    <div className="rounded-md border border-dashed border-[var(--border)] bg-[var(--surface)] px-5 py-10 text-center">
      <h2 className="m-0 text-base font-semibold text-[var(--text-primary)]">{title}</h2>
      {description ? <p className="mx-auto mt-2 max-w-xl text-sm leading-6 text-[var(--text-secondary)]">{description}</p> : null}
      {action ? <div className="mt-4 flex justify-center">{action}</div> : null}
    </div>
  );
}

export function SettingsLink({ href, title, description }: { href: string; title: string; description: string }) {
  return (
    <Link href={href} className="block rounded-md border border-[var(--border)] bg-[var(--surface)] p-4 no-underline shadow-sm transition-colors hover:border-[var(--primary)]">
      <h2 className="m-0 text-base font-semibold text-[var(--text-primary)]">{title}</h2>
      <p className="mt-2 text-sm leading-6 text-[var(--text-secondary)]">{description}</p>
    </Link>
  );
}

export function StatusBadge({
  tone,
  children,
  className = '',
}: {
  tone: 'ok' | 'muted' | 'danger' | 'warning' | 'info';
  children: ReactNode;
  className?: string;
}) {
  const tones = {
    ok: {
      badge: 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
      dot: 'bg-emerald-500 animate-pulse',
    },
    muted: {
      badge: 'border-[var(--border)] bg-[var(--background)] text-[var(--text-secondary)]',
      dot: 'bg-slate-400 dark:bg-slate-500',
    },
    danger: {
      badge: 'border-red-500/20 bg-red-500/10 text-red-600 dark:text-red-400',
      dot: 'bg-red-500',
    },
    warning: {
      badge: 'border-amber-500/20 bg-amber-500/10 text-amber-600 dark:text-amber-400',
      dot: 'bg-amber-500 animate-pulse',
    },
    info: {
      badge: 'border-blue-500/20 bg-blue-500/10 text-blue-600 dark:text-blue-400',
      dot: 'bg-blue-500',
    },
  };

  const currentTone = tones[tone] || tones.muted;

  return (
    <span
      className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-bold shadow-2xs transition-colors ${currentTone.badge} ${className}`}
    >
      <span className={`size-1.5 rounded-full ${currentTone.dot}`} />
      <span>{children}</span>
    </span>
  );
}

export function Modal({
  isOpen,
  onClose,
  title,
  children,
}: {
  isOpen: boolean;
  onClose: () => void;
  title: string;
  children: ReactNode;
}) {
  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
      <div className="w-full max-w-lg rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-6 shadow-xl space-y-4">
        <div className="flex items-center justify-between border-b border-[var(--border)] pb-3">
          <h3 className="text-base font-bold text-[var(--text-primary)]">{title}</h3>
          <button
            type="button"
            onClick={onClose}
            className="text-[var(--text-secondary)] hover:text-[var(--text-primary)] text-lg font-bold"
          >
            &times;
          </button>
        </div>
        {children}
      </div>
    </div>
  );
}

export const tableClasses = {
  wrap: 'accounting-table-wrap overflow-x-auto rounded-lg border border-[var(--border)] bg-[var(--surface)] shadow-sm',
  table: 'accounting-table min-w-full border-collapse text-sm',
  th: 'sticky top-0 z-10 border-b border-[var(--border)] bg-[var(--background)] px-4 py-3 text-start text-xs font-bold uppercase text-[var(--text-muted)]',
  td: 'border-b border-[var(--border)] px-4 py-3 align-middle text-[var(--text-primary)]',
};

export function decodePaginationLabel(label: string): string {
  if (!label) return '';
  return label
    .replace(/&laquo;/g, '«')
    .replace(/&raquo;/g, '»')
    .replace(/&amp;/g, '&')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&#039;/g, "'")
    .replace(/&quot;/g, '"');
}

/**
 * Carries the page's active filters onto a pagination link.
 *
 * Reads the current query string only — it never assigns to `window.location`,
 * so navigation stays with Inertia and the SPA keeps its state.
 */
function paginationUrlWithCurrentQuery(url: string): string {
  if (typeof window === 'undefined') return url;

  const currentParams = new URLSearchParams(window.location.search);
  const target = new URL(url, window.location.origin);

  currentParams.forEach((value, key) => {
    if (!target.searchParams.has(key)) {
      target.searchParams.append(key, value);
    }
  });

  return `${target.pathname}${target.search}${target.hash}`;
}

export function PaginationControls({
  links,
  total,
  totalLabel,
  className = '',
}: {
  links?: PaginationLink[] | null;
  total?: number;
  totalLabel?: string;
  className?: string;
}) {
  if (!links || links.length <= 3) {
    return null;
  }

  return (
    <div data-pagination-controls className={`flex flex-col gap-3 border-t border-[var(--border)] bg-[var(--surface)] p-4 sm:flex-row sm:items-center sm:justify-between mt-4 rounded-lg ${className}`}>
      {total !== undefined && totalLabel ? (
        <span className="text-xs text-[var(--text-muted)] font-mono">
          {totalLabel} {total}
        </span>
      ) : (
        <span />
      )}
      <div className="flex flex-wrap items-center justify-center gap-1 sm:justify-end">
        {links.map((link, idx) => {
          const safeLabel = decodePaginationLabel(link.label);

          return link.url ? (
            <Link
              key={idx}
              href={paginationUrlWithCurrentQuery(link.url)}
              preserveScroll
              preserveState
              className={`px-3 py-1 text-xs font-bold rounded-lg border transition-all ${
                link.active
                  ? 'bg-[var(--primary)] text-white border-transparent'
                  : 'border-[var(--border)] bg-[var(--surface)] text-[var(--text-primary)] hover:border-[var(--primary)]'
              }`}
            >
              {safeLabel}
            </Link>
          ) : (
            <span
              key={idx}
              className="px-3 py-1 text-xs font-bold rounded-lg border border-[var(--border)] bg-[var(--background)] text-[var(--text-muted)] opacity-50"
            >
              {safeLabel}
            </span>
          );
        })}
      </div>
    </div>
  );
}

export { default as SearchableSelect } from './SearchableSelect';
export { default as ToggleSwitch } from './ToggleSwitch';
export { default as SensitiveActionModal } from './SensitiveActionModal';
