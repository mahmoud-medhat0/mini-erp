import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

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
    <div className="mb-5 flex flex-wrap items-start gap-3">
      <div className="min-w-0 flex-1">
        <h1 className="m-0 text-2xl font-bold text-[var(--text-primary)]">{title}</h1>
        {description ? (
          <p className="mt-1 max-w-3xl text-sm leading-6 text-[var(--text-secondary)]">{description}</p>
        ) : null}
      </div>
      {actions ? <div className="flex items-center gap-2">{actions}</div> : null}
    </div>
  );
}

export function Card({ children, className = '' }: { children: ReactNode; className?: string }) {
  return (
    <section className={`rounded-md border border-[var(--border)] bg-[var(--surface)] shadow-sm ${className}`}>
      {children}
    </section>
  );
}

export function EmptyState({ title, description }: { title: string; description?: string }) {
  return (
    <div className="rounded-md border border-dashed border-[var(--border)] bg-[var(--surface)] px-5 py-10 text-center">
      <h2 className="m-0 text-base font-semibold text-[var(--text-primary)]">{title}</h2>
      {description ? <p className="mx-auto mt-2 max-w-xl text-sm leading-6 text-[var(--text-secondary)]">{description}</p> : null}
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

export const tableClasses = {
  wrap: 'overflow-x-auto rounded-2xl border border-[var(--border)] bg-[var(--surface)] shadow-md',
  table: 'min-w-full border-collapse text-sm',
  th: 'border-b border-[var(--border)] px-5 py-3.5 text-start text-xs font-bold uppercase tracking-wider text-[var(--text-muted)] bg-[var(--background)]/60',
  td: 'border-b border-[var(--border)] px-5 py-4 align-middle text-[var(--text-primary)]',
};

export { default as SearchableSelect } from './SearchableSelect';
export { default as ToggleSwitch } from './ToggleSwitch';
