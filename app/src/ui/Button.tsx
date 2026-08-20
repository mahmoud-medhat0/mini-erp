import type { ButtonHTMLAttributes, ReactNode } from 'react';

type Variant = 'primary' | 'secondary' | 'ghost' | 'danger';

const base: React.CSSProperties = {
  display: 'inline-flex',
  alignItems: 'center',
  gap: 6,
  padding: '8px 14px',
  borderRadius: 'var(--radius-sm)',
  fontSize: 'var(--text-base)',
  fontWeight: 600,
  cursor: 'pointer',
  border: '1px solid transparent',
  fontFamily: 'inherit',
  lineHeight: 1.2,
};

const variants: Record<Variant, React.CSSProperties> = {
  primary: { background: 'var(--primary)', color: 'var(--on-primary)' },
  secondary: { background: 'var(--surface)', color: 'var(--text-primary)', borderColor: 'var(--border-strong)' },
  ghost: { background: 'transparent', color: 'var(--text-secondary)' },
  danger: { background: 'var(--danger)', color: '#fff' },
};

export interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: Variant;
  loading?: boolean;
  children?: ReactNode;
}

/** Token-styled button. States: default/hover(via CSS)/disabled/loading. RTL-safe. */
export function Button({ variant = 'primary', loading, disabled, children, style, ...rest }: ButtonProps) {
  const isDisabled = disabled || loading;
  return (
    <button
      {...rest}
      disabled={isDisabled}
      aria-busy={loading || undefined}
      style={{ ...base, ...variants[variant], opacity: isDisabled ? 0.55 : 1, cursor: isDisabled ? 'not-allowed' : 'pointer', ...style }}
    >
      {loading ? '…' : children}
    </button>
  );
}
