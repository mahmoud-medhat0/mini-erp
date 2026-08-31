import type { InputHTMLAttributes } from 'react';

export interface InputProps extends InputHTMLAttributes<HTMLInputElement> {
  label?: string;
  error?: string;
  hint?: string;
}

/** Token-styled labelled input with error state. RTL-safe via logical alignment. */
export function Input({ label, error, hint, id, style, ...rest }: InputProps) {
  const inputId = id ?? rest.name;
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
      {label && (
        <label htmlFor={inputId} style={{ fontSize: 'var(--text-sm)', fontWeight: 600, color: 'var(--text-secondary)' }}>
          {label}
        </label>
      )}
      <input
        id={inputId}
        aria-invalid={!!error || undefined}
        style={{
          padding: '8px var(--space-3)',
          border: `1px solid ${error ? 'var(--danger)' : 'var(--border)'}`,
          borderRadius: 'var(--radius-sm)',
          background: error ? 'var(--danger-subtle)' : 'var(--background)',
          color: 'var(--text-primary)',
          fontFamily: 'inherit',
          fontSize: 'var(--text-base)',
          textAlign: 'start',
          ...style,
        }}
        {...rest}
      />
      {error ? (
        <span role="alert" style={{ fontSize: 'var(--text-xs)', color: 'var(--danger)' }}>
          {error}
        </span>
      ) : hint ? (
        <span style={{ fontSize: 'var(--text-xs)', color: 'var(--text-muted)' }}>{hint}</span>
      ) : null}
    </div>
  );
}
