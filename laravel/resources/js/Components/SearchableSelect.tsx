import { usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

import { getDictionary } from '../lib/i18n';
import type { SharedPageProps } from '../Types/page';

export type SelectOption<T = string | number> = {
  value: T;
  label: string;
  sublabel?: string;
  badge?: string;
  disabled?: boolean;
};

export type SearchableSelectProps<T = string | number> = {
  options: Array<SelectOption<T> | T>;
  value?: T | null;
  onChange: (value: T | null) => void;
  placeholder?: string;
  searchPlaceholder?: string;
  label?: string;
  isClearable?: boolean;
  isSearchable?: boolean;
  isCreatable?: boolean;
  createOptionLabel?: (query: string) => string;
  disabled?: boolean;
  error?: string;
  className?: string;
  id?: string;
  required?: boolean;
};

export default function SearchableSelect<T extends string | number = string>({
  options,
  value,
  onChange,
  placeholder,
  searchPlaceholder,
  label,
  isClearable = true,
  isSearchable = true,
  isCreatable = false,
  createOptionLabel,
  disabled = false,
  error,
  className = '',
  id,
  required = false,
}: SearchableSelectProps<T>) {
  const page = usePage<SharedPageProps>();
  const locale = page.props?.locale === 'ar' ? 'ar' : 'en';
  const dict = getDictionary(locale);

  const activePlaceholder = placeholder ?? dict.common.select.placeholder;
  const activeSearchPlaceholder = searchPlaceholder ?? dict.common.select.searchPlaceholder;
  const activeNoOptionsLabel = dict.common.select.noOptions;
  const activeClearLabel = dict.common.select.clear;

  const [isOpen, setIsOpen] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const containerRef = useRef<HTMLDivElement>(null);
  const searchInputRef = useRef<HTMLInputElement>(null);

  // Normalize options array into SelectOption format
  const normalizedOptions: SelectOption<T>[] = options.map((opt) => {
    if (typeof opt === 'object' && opt !== null && 'value' in opt) {
      return opt as SelectOption<T>;
    }
    return {
      value: opt as T,
      label: String(opt),
    };
  });

  // Check if search query matches any option exactly
  const trimmedQuery = searchQuery.trim();
  const exactMatch = normalizedOptions.some(
    (opt) =>
      String(opt.value).toLowerCase() === trimmedQuery.toLowerCase() ||
      opt.label.toLowerCase() === trimmedQuery.toLowerCase(),
  );

  const showCreateOption = isCreatable && trimmedQuery !== '' && !exactMatch;

  // Find active option or fallback to custom value
  const selectedOption =
    normalizedOptions.find((opt) => opt.value === value) ||
    (value !== null && value !== undefined && value !== ''
      ? { value: value as T, label: String(value) }
      : undefined);

  // Filter options by search query
  const filteredOptions = normalizedOptions.filter((opt) => {
    if (!trimmedQuery) return true;
    const q = trimmedQuery.toLowerCase();
    const matchLabel = opt.label.toLowerCase().includes(q);
    const matchSublabel = opt.sublabel ? opt.sublabel.toLowerCase().includes(q) : false;
    const matchValue = String(opt.value).toLowerCase().includes(q);
    return matchLabel || matchSublabel || matchValue;
  });

  // Handle click outside to close dropdown
  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    }
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  // Auto-focus search input when opened
  useEffect(() => {
    if (isOpen && isSearchable && searchInputRef.current) {
      searchInputRef.current.focus();
    }
  }, [isOpen, isSearchable]);

  function handleSelect(optionValue: T) {
    onChange(optionValue);
    setIsOpen(false);
    setSearchQuery('');
  }

  function handleClear(e: React.MouseEvent) {
    e.stopPropagation();
    onChange(null);
    setSearchQuery('');
  }

  return (
    <div ref={containerRef} className={`relative w-full ${className}`}>
      {label ? (
        <label htmlFor={id} className="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)]">
          {label} {required ? <span className="text-[var(--danger)]">*</span> : null}
        </label>
      ) : null}

      {/* Main Select Button Trigger */}
      <button
        id={id}
        type="button"
        disabled={disabled}
        onClick={() => setIsOpen(!isOpen)}
        className={`flex min-h-[42px] w-full items-center justify-between gap-2 rounded-xl border bg-[var(--background)] px-3.5 py-2 text-start text-sm transition-all focus:outline-hidden ${
          error
            ? 'border-[var(--danger)] text-[var(--text-primary)]'
            : isOpen
              ? 'border-[var(--primary)] ring-2 ring-blue-500/20 text-[var(--text-primary)]'
              : 'border-[var(--border)] text-[var(--text-primary)] hover:border-[var(--primary)]'
        } ${disabled ? 'cursor-not-allowed opacity-60 bg-[var(--surface)]' : 'cursor-pointer'}`}
      >
        <div className="flex flex-1 items-center gap-2 min-w-0">
          {selectedOption ? (
            <span className="font-semibold text-[var(--text-primary)] truncate" title={selectedOption.label}>
              {selectedOption.label}
            </span>
          ) : (
            <span className="text-[var(--text-muted)]">{activePlaceholder}</span>
          )}
        </div>

        <div className="flex items-center gap-1.5 shrink-0 text-[var(--text-muted)]">
          {isClearable && selectedOption && !disabled ? (
            <span
              role="button"
              tabIndex={0}
              onClick={handleClear}
              title={activeClearLabel}
              className="rounded-full p-0.5 hover:bg-[var(--surface)] hover:text-[var(--text-primary)] transition-colors"
            >
              <svg className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </span>
          ) : null}

          <svg className={`size-4 transition-transform duration-200 ${isOpen ? 'rotate-180 text-[var(--primary)]' : ''}`} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M19 9l-7 7-7-7" />
          </svg>
        </div>
      </button>

      {/* Error Message */}
      {error ? <p className="mt-1 text-xs font-semibold text-[var(--danger)]">{error}</p> : null}

      {/* Popover Dropdown Panel */}
      {isOpen && !disabled ? (
        <div className="absolute start-0 top-full mt-1.5 z-50 min-w-full w-max max-w-xl overflow-hidden rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-2 shadow-2xl shadow-slate-900/30 backdrop-blur-xl animate-in fade-in duration-150">
          {/* Search Input Filter */}
          {isSearchable ? (
            <div className="relative mb-2">
              <input
                ref={searchInputRef}
                type="text"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder={activeSearchPlaceholder}
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] py-2 pe-8 ps-8 text-xs text-[var(--text-primary)] placeholder-[var(--text-muted)] transition-colors focus:border-[var(--primary)] focus:outline-hidden"
              />
              <svg className="absolute start-2.5 top-2.5 size-3.5 text-[var(--text-muted)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
              {searchQuery ? (
                <button
                  type="button"
                  onClick={() => setSearchQuery('')}
                  className="absolute end-2.5 top-2.5 rounded-full p-0.5 text-[var(--text-muted)] hover:text-[var(--text-primary)]"
                >
                  <svg className="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              ) : null}
            </div>
          ) : null}

          {/* Options List */}
          <div className="max-h-64 overflow-y-auto space-y-1 pe-1">
            {showCreateOption ? (
              <button
                type="button"
                onClick={() => {
                  const firstVal = normalizedOptions[0]?.value;
                  const isNum = typeof firstVal === 'number' || typeof value === 'number';
                  const parsedVal = isNum && !isNaN(Number(trimmedQuery)) ? Number(trimmedQuery) : trimmedQuery;
                  handleSelect(parsedVal as T);
                }}
                className="flex w-full items-center justify-between rounded-xl px-3 py-2.5 text-start text-xs font-bold text-[var(--primary)] hover:bg-[var(--primary)]/10 cursor-pointer border border-dashed border-[var(--primary)]/40 mb-1"
              >
                <span>
                  + {createOptionLabel ? createOptionLabel(trimmedQuery) : (locale === 'ar' ? `إضافة "${trimmedQuery}"` : `Add "${trimmedQuery}"`)}
                </span>
              </button>
            ) : null}

            {filteredOptions.length > 0 ? (
              filteredOptions.map((option) => {
                const isSelected = option.value === value;

                return (
                  <button
                    key={String(option.value)}
                    type="button"
                    disabled={option.disabled}
                    onClick={() => handleSelect(option.value)}
                    className={`flex w-full items-center justify-between rounded-xl px-3 py-2.5 text-start text-xs font-semibold transition-all ${
                      isSelected
                        ? 'bg-[var(--primary)] text-white shadow-xs'
                        : 'text-[var(--text-primary)] hover:bg-[var(--background)]'
                    } ${option.disabled ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer'}`}
                  >
                    <div className="flex flex-col me-2 text-start">
                      <span className="whitespace-normal leading-relaxed">{option.label}</span>
                      {option.sublabel ? (
                        <span className={`text-[10px] whitespace-normal leading-tight ${isSelected ? 'text-white/80' : 'text-[var(--text-muted)]'}`}>
                          {option.sublabel}
                        </span>
                      ) : null}
                    </div>

                    {option.badge ? (
                      <span className={`rounded-full px-2 py-0.5 text-[10px] font-bold ${
                        isSelected ? 'bg-white/20 text-white' : 'bg-blue-500/10 text-blue-600 dark:text-blue-400'
                      }`}>
                        {option.badge}
                      </span>
                    ) : null}
                  </button>
                );
              })
            ) : !showCreateOption ? (
              <div className="py-6 text-center text-xs text-[var(--text-muted)]">
                {activeNoOptionsLabel}
              </div>
            ) : null}
          </div>
        </div>
      ) : null}
    </div>
  );
}
