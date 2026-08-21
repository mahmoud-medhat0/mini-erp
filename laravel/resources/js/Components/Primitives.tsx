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

export function StatusBadge({ tone, children }: { tone: 'ok' | 'muted' | 'danger'; children: ReactNode }) {
  const classes = {
    ok: 'border-green-200 bg-green-50 text-green-700 dark:border-green-900/60 dark:bg-green-950/40 dark:text-green-300',
    muted: 'border-[var(--border)] bg-[var(--background)] text-[var(--text-secondary)]',
    danger: 'border-red-200 bg-red-50 text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300',
  };

  return (
    <span className={`inline-flex items-center rounded-sm border px-2 py-1 text-xs font-semibold ${classes[tone]}`}>
      {children}
    </span>
  );
}

export const tableClasses = {
  wrap: 'overflow-x-auto rounded-md border border-[var(--border)] bg-[var(--surface)] shadow-sm',
  table: 'min-w-full border-collapse text-sm',
  th: 'border-b border-[var(--border)] px-4 py-3 text-start text-xs font-bold uppercase text-[var(--text-muted)]',
  td: 'border-b border-[var(--border)] px-4 py-3 align-top text-[var(--text-primary)]',
};
