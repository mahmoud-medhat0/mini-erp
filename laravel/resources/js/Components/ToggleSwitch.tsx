export type ToggleSwitchProps = {
  checked: boolean;
  onChange: (checked: boolean) => void;
  label?: string;
  description?: string;
  disabled?: boolean;
  id?: string;
  className?: string;
};

export default function ToggleSwitch({
  checked,
  onChange,
  label,
  description,
  disabled = false,
  id,
  className = '',
}: ToggleSwitchProps) {
  return (
    <div className={`flex items-center justify-between gap-3 ${className}`}>
      {label || description ? (
        <div className="flex flex-col select-none cursor-pointer" onClick={() => !disabled && onChange(!checked)}>
          {label ? (
            <span className="text-xs font-bold text-[var(--text-primary)]">
              {label}
            </span>
          ) : null}
          {description ? (
            <span className="text-[10px] text-[var(--text-muted)]">
              {description}
            </span>
          ) : null}
        </div>
      ) : null}

      <button
        id={id}
        type="button"
        role="switch"
        aria-checked={checked}
        disabled={disabled}
        onClick={() => !disabled && onChange(!checked)}
        className={`relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-hidden focus:ring-2 focus:ring-blue-500/20 ${
          checked ? 'bg-[var(--primary)]' : 'bg-slate-300 dark:bg-slate-700'
        } ${disabled ? 'cursor-not-allowed opacity-50' : ''}`}
      >
        <span
          className={`pointer-events-none inline-block size-5 rounded-full bg-white shadow-md transition duration-200 ease-in-out ltr:transform rtl:transform-none ${
            checked
              ? 'translate-x-5 rtl:-translate-x-5'
              : 'translate-x-0 rtl:translate-x-0'
          }`}
        />
      </button>
    </div>
  );
}
