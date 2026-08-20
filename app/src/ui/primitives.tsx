import type { ReactNode } from 'react';

/** Card surface. */
export function Card({ children, style }: { children: ReactNode; style?: React.CSSProperties }) {
  return (
    <div
      style={{
        background: 'var(--surface)',
        border: '1px solid var(--border)',
        borderRadius: 'var(--radius-lg)',
        padding: 'var(--space-6)',
        boxShadow: 'var(--shadow-sm)',
        ...style,
      }}
    >
      {children}
    </div>
  );
}

/** Page header with title + optional actions. */
export function PageHeader({ title, actions }: { title: string; actions?: ReactNode }) {
  return (
    <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--space-3)', marginBlockEnd: 'var(--space-6)' }}>
      <h1 style={{ fontSize: 'var(--text-xl)', fontWeight: 700, margin: 0 }}>{title}</h1>
      <div style={{ flex: 1 }} />
      {actions}
    </div>
  );
}

/** Empty state — explains what to do next; never fake data. */
export function EmptyState({ title, description, action }: { title: string; description?: string; action?: ReactNode }) {
  return (
    <div
      style={{
        background: 'var(--surface)',
        border: '1px dashed var(--border-strong)',
        borderRadius: 'var(--radius-lg)',
        padding: 'var(--space-12)',
        textAlign: 'center',
        color: 'var(--text-secondary)',
      }}
    >
      <h3 style={{ margin: 0, fontSize: 'var(--text-lg)', color: 'var(--text-primary)' }}>{title}</h3>
      {description && <p style={{ color: 'var(--text-muted)', fontSize: 'var(--text-sm)' }}>{description}</p>}
      {action && <div style={{ marginBlockStart: 'var(--space-4)' }}>{action}</div>}
    </div>
  );
}

/** Permission-denied state (server-enforced; UI just communicates it). */
export function PermissionDenied({ message }: { message: string }) {
  return (
    <div style={{ padding: 'var(--space-8)', textAlign: 'center', color: 'var(--text-muted)' }}>
      <p style={{ fontSize: 'var(--text-md)' }}>🔒 {message}</p>
    </div>
  );
}
