import type { ReactNode } from 'react';

export type StatusTone = 'draft' | 'pending' | 'approved' | 'posted' | 'overdue' | 'paid' | 'cancelled';

const tones: Record<StatusTone, { bg: string; fg: string }> = {
  draft: { bg: 'var(--surface-muted)', fg: 'var(--text-secondary)' },
  pending: { bg: 'var(--warning-subtle)', fg: 'var(--warning)' },
  approved: { bg: 'var(--info-subtle)', fg: 'var(--info)' },
  posted: { bg: 'var(--success-subtle)', fg: 'var(--success)' },
  paid: { bg: 'var(--success-subtle)', fg: 'var(--success)' },
  overdue: { bg: 'var(--danger-subtle)', fg: 'var(--danger)' },
  cancelled: { bg: 'var(--surface-muted)', fg: 'var(--text-muted)' },
};

/** Status pill — colour is paired with a dot + label (never colour alone). */
export function StatusBadge({ tone, children }: { tone: StatusTone; children: ReactNode }) {
  const t = tones[tone];
  return (
    <span
      style={{
        display: 'inline-flex',
        alignItems: 'center',
        gap: 5,
        padding: '3px 9px',
        borderRadius: 'var(--radius-full)',
        fontSize: 'var(--text-xs)',
        fontWeight: 600,
        lineHeight: 1,
        background: t.bg,
        color: t.fg,
      }}
    >
      <span aria-hidden style={{ width: 6, height: 6, borderRadius: '50%', background: t.fg }} />
      {children}
    </span>
  );
}
