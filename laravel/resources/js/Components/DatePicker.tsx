import { usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

import { getDictionary } from '../lib/i18n';
import type { SharedPageProps } from '../Types/page';

export type DatePickerProps = {
  value?: string | null; // Expected format: YYYY-MM-DD
  onChange: (value: string | null) => void;
  label?: string;
  placeholder?: string;
  minDate?: string; // YYYY-MM-DD
  maxDate?: string; // YYYY-MM-DD
  disabled?: boolean;
  required?: boolean;
  error?: string;
  isClearable?: boolean;
  displayFormat?: 'YYYY-MM-DD' | 'DD/MM/YYYY' | 'MM/DD/YYYY' | 'readable';
  presets?: Array<{ label: string; value: string }>;
  className?: string;
  id?: string;
};

type ViewMode = 'days' | 'months' | 'years';

const MONTH_NAMES_EN = [
  'January',
  'February',
  'March',
  'April',
  'May',
  'June',
  'July',
  'August',
  'September',
  'October',
  'November',
  'December',
];

const MONTH_NAMES_AR = [
  'يناير',
  'فبراير',
  'مارس',
  'أبريل',
  'مايو',
  'يونيو',
  'يوليو',
  'أغسطس',
  'سبتمبر',
  'أكتوبر',
  'نوفمبر',
  'ديسمبر',
];

const DAY_NAMES_EN = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const DAY_NAMES_AR = ['أحد', 'إثنين', 'ثلاثاء', 'أربعاء', 'خميس', 'جمعة', 'سبت'];

export default function DatePicker({
  value,
  onChange,
  label,
  placeholder = 'YYYY-MM-DD',
  minDate,
  maxDate,
  disabled = false,
  required = false,
  error,
  isClearable = true,
  displayFormat = 'YYYY-MM-DD',
  presets,
  className = '',
  id,
}: DatePickerProps) {
  const page = usePage<SharedPageProps>();
  const locale = page.props?.locale === 'ar' ? 'ar' : 'en';
  const dict = getDictionary(locale);

  const [isOpen, setIsOpen] = useState(false);
  const [viewMode, setViewMode] = useState<ViewMode>('days');
  const containerRef = useRef<HTMLDivElement>(null);

  // Parse initial date safely (validates year range 1900-2100)
  const parseDate = (dateStr: string | null | undefined): Date | null => {
    if (!dateStr) return null;
    const parts = dateStr.split('-');
    if (parts.length === 3) {
      const year = parseInt(parts[0], 10);
      const month = parseInt(parts[1], 10) - 1;
      const day = parseInt(parts[2], 10);
      if (!isNaN(year) && !isNaN(month) && !isNaN(day)) {
        if (year >= 1900 && year <= 2100 && month >= 0 && month <= 11 && day >= 1 && day <= 31) {
          return new Date(year, month, day);
        }
      }
    }
    return null;
  };

  const formatDateString = (date: Date): string => {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
  };

  const selectedDate = parseDate(value);
  const [viewDate, setViewDate] = useState<Date>(() => selectedDate || new Date());

  // Year decade page start (for 12-year grid view)
  const [yearDecadeStart, setYearDecadeStart] = useState<number>(() => {
    const y = (selectedDate || new Date()).getFullYear();
    return Math.floor(y / 10) * 10 - 1;
  });

  // Keep viewDate in sync when value changes externally
  useEffect(() => {
    if (selectedDate) {
      setViewDate(selectedDate);
      setYearDecadeStart(Math.floor(selectedDate.getFullYear() / 10) * 10 - 1);
    }
  }, [value]);

  // Reset view mode to 'days' when popover closes
  useEffect(() => {
    if (!isOpen) {
      setViewMode('days');
    }
  }, [isOpen]);

  // Outside click listener
  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    }
    if (isOpen) {
      document.addEventListener('mousedown', handleClickOutside);
    }
    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, [isOpen]);

  const monthNames = locale === 'ar' ? MONTH_NAMES_AR : MONTH_NAMES_EN;
  const dayNames = locale === 'ar' ? DAY_NAMES_AR : DAY_NAMES_EN;

  const currentYear = viewDate.getFullYear();
  const currentMonth = viewDate.getMonth();

  // Calendar grid math for Days view
  const firstDayOfMonth = new Date(currentYear, currentMonth, 1);
  const startingDayOfWeek = firstDayOfMonth.getDay();
  const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
  const daysInPrevMonth = new Date(currentYear, currentMonth, 0).getDate();

  const prevMonthDays: number[] = [];
  for (let i = startingDayOfWeek - 1; i >= 0; i--) {
    prevMonthDays.push(daysInPrevMonth - i);
  }

  const currentMonthDays: number[] = [];
  for (let d = 1; d <= daysInMonth; d++) {
    currentMonthDays.push(d);
  }

  const totalGridCells = prevMonthDays.length + currentMonthDays.length;
  const nextMonthDaysCount = totalGridCells % 7 === 0 ? 0 : 7 - (totalGridCells % 7);
  const nextMonthDays: number[] = [];
  for (let d = 1; d <= nextMonthDaysCount; d++) {
    nextMonthDays.push(d);
  }

  // Navigation handlers
  const handlePrevMonth = () => {
    setViewDate(new Date(currentYear, currentMonth - 1, 1));
  };

  const handleNextMonth = () => {
    setViewDate(new Date(currentYear, currentMonth + 1, 1));
  };

  const handlePrevDecade = () => {
    setYearDecadeStart((prev) => prev - 12);
  };

  const handleNextDecade = () => {
    setYearDecadeStart((prev) => prev + 12);
  };

  const isDateDisabled = (year: number, month: number, day: number): boolean => {
    const targetStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    if (minDate && targetStr < minDate) return true;
    if (maxDate && targetStr > maxDate) return true;
    return false;
  };

  const isMonthDisabled = (year: number, month: number): boolean => {
    if (minDate) {
      const minParts = minDate.split('-');
      const minY = parseInt(minParts[0], 10);
      const minM = parseInt(minParts[1], 10) - 1;
      if (year < minY || (year === minY && month < minM)) return true;
    }
    if (maxDate) {
      const maxParts = maxDate.split('-');
      const maxY = parseInt(maxParts[0], 10);
      const maxM = parseInt(maxParts[1], 10) - 1;
      if (year > maxY || (year === maxY && month > maxM)) return true;
    }
    return false;
  };

  const isYearDisabled = (year: number): boolean => {
    if (minDate) {
      const minY = parseInt(minDate.split('-')[0], 10);
      if (year < minY) return true;
    }
    if (maxDate) {
      const maxY = parseInt(maxDate.split('-')[0], 10);
      if (year > maxY) return true;
    }
    return false;
  };

  const handleSelectDay = (day: number, isPrev = false, isNext = false) => {
    let targetYear = currentYear;
    let targetMonth = currentMonth;

    if (isPrev) {
      targetMonth -= 1;
      if (targetMonth < 0) {
        targetMonth = 11;
        targetYear -= 1;
      }
    } else if (isNext) {
      targetMonth += 1;
      if (targetMonth > 11) {
        targetMonth = 0;
        targetYear += 1;
      }
    }

    if (isDateDisabled(targetYear, targetMonth, day)) return;

    const formatted = `${targetYear}-${String(targetMonth + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    onChange(formatted);
    setIsOpen(false);
  };

  const handleSelectMonth = (monthIdx: number) => {
    if (isMonthDisabled(currentYear, monthIdx)) return;
    setViewDate(new Date(currentYear, monthIdx, 1));
    setViewMode('days');
  };

  const handleSelectYear = (selectedY: number) => {
    if (isYearDisabled(selectedY)) return;
    setViewDate(new Date(selectedY, currentMonth, 1));
    setViewMode('months');
  };

  const handleSelectToday = () => {
    const today = new Date();
    const y = today.getFullYear();
    const m = today.getMonth();
    const d = today.getDate();
    if (isDateDisabled(y, m, d)) return;
    const formatted = formatDateString(today);
    onChange(formatted);
    setViewDate(today);
    setViewMode('days');
    setIsOpen(false);
  };

  const handleClear = () => {
    onChange(null);
    setIsOpen(false);
  };

  const formatDisplay = (dateStr: string | null | undefined): string => {
    if (!dateStr) return '';
    const date = parseDate(dateStr);
    if (!date) return dateStr;

    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');

    if (displayFormat === 'DD/MM/YYYY') return `${d}/${m}/${y}`;
    if (displayFormat === 'MM/DD/YYYY') return `${m}/${d}/${y}`;
    if (displayFormat === 'readable') {
      const monthName = monthNames[date.getMonth()];
      return `${d} ${monthName} ${y}`;
    }
    return dateStr;
  };

  const todayStr = formatDateString(new Date());

  // Generate 12 years for year decade grid
  const decadeYears: number[] = [];
  for (let i = 0; i < 12; i++) {
    decadeYears.push(yearDecadeStart + i);
  }

  return (
    <div className={`relative ${className}`} ref={containerRef}>
      {label ? (
        <label htmlFor={id} className="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-[var(--text-secondary)]">
          {label}
          {required ? <span className="ms-1 text-red-500">*</span> : null}
        </label>
      ) : null}

      {/* Trigger Button Input */}
      <div className="relative">
        <button
          type="button"
          id={id}
          disabled={disabled}
          onClick={() => setIsOpen(!isOpen)}
          className={`flex w-full items-center justify-between gap-2 rounded-md border px-3 py-2 text-sm transition-all text-start bg-[var(--surface)] shadow-2xs focus:outline-hidden focus:ring-2 focus:ring-[var(--primary)] ${
            disabled
              ? 'cursor-not-allowed opacity-60 border-[var(--border)] text-[var(--text-muted)] bg-[var(--background)]'
              : error
              ? 'border-red-500 focus:ring-red-500 text-[var(--text-primary)]'
              : isOpen
              ? 'border-[var(--primary)] ring-2 ring-[var(--primary)]/20 text-[var(--text-primary)]'
              : 'border-[var(--border)] text-[var(--text-primary)] hover:border-[var(--primary)]/50'
          }`}
        >
          <div className="flex items-center gap-2 min-w-0 flex-1">
            <svg
              className="size-4 shrink-0 text-[var(--text-secondary)]"
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
              strokeWidth="1.5"
              stroke="currentColor"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"
              />
            </svg>
            <span className={`truncate ${!value ? 'text-[var(--text-muted)]' : 'font-medium text-[var(--text-primary)]'}`}>
              {value ? formatDisplay(value) : placeholder}
            </span>
          </div>

          <div className="flex items-center gap-1 shrink-0">
            {isClearable && value && !disabled ? (
              <span
                role="button"
                tabIndex={0}
                onClick={(e) => {
                  e.stopPropagation();
                  handleClear();
                }}
                onKeyDown={(e) => {
                  if (e.key === 'Enter' || e.key === ' ') {
                    e.stopPropagation();
                    handleClear();
                  }
                }}
                className="rounded-full p-0.5 text-[var(--text-secondary)] hover:bg-[var(--background)] hover:text-red-500 transition-colors"
                title={locale === 'ar' ? 'مسح' : 'Clear'}
              >
                <svg className="size-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </span>
            ) : null}

            <svg
              className={`size-4 text-[var(--text-secondary)] transition-transform duration-200 ${isOpen ? 'rotate-180' : ''}`}
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
              strokeWidth="1.5"
              stroke="currentColor"
            >
              <path strokeLinecap="round" strokeLinejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
            </svg>
          </div>
        </button>
      </div>

      {error ? <p className="mt-1 text-xs text-red-500">{error}</p> : null}

      {/* Popover Calendar Container */}
      {isOpen && !disabled ? (
        <div className="absolute z-50 mt-1.5 w-72 rounded-xl border border-[var(--border)] bg-[var(--surface)] p-3.5 shadow-2xl transition-all animate-in fade-in slide-in-from-top-2 duration-150">
          {/* Quick Presets Bar */}
          {presets && presets.length > 0 && viewMode === 'days' ? (
            <div className="mb-3 flex flex-wrap gap-1 border-b border-[var(--border)] pb-2.5">
              {presets.map((preset) => (
                <button
                  key={preset.value}
                  type="button"
                  onClick={() => {
                    onChange(preset.value);
                    const pDate = parseDate(preset.value);
                    if (pDate) {
                      setViewDate(pDate);
                      setYearDecadeStart(Math.floor(pDate.getFullYear() / 10) * 10 - 1);
                    }
                    setIsOpen(false);
                  }}
                  className={`rounded-md px-2 py-1 text-xs font-medium transition-colors ${
                    value === preset.value
                      ? 'bg-[var(--primary)] text-white'
                      : 'bg-[var(--background)] text-[var(--text-secondary)] hover:bg-[var(--border)] hover:text-[var(--text-primary)]'
                  }`}
                >
                  {preset.label}
                </button>
              ))}
            </div>
          ) : null}

          {/* Interactive Header Bar */}
          <div className="mb-3 flex items-center justify-between gap-1">
            {/* Prev Button */}
            <button
              type="button"
              onClick={viewMode === 'years' ? handlePrevDecade : handlePrevMonth}
              className="flex size-7 items-center justify-center rounded-lg text-[var(--text-secondary)] transition-colors hover:bg-[var(--background)] hover:text-[var(--text-primary)]"
              title={viewMode === 'years' ? (locale === 'ar' ? 'العقد السابق' : 'Previous Decade') : (locale === 'ar' ? 'الشهر السابق' : 'Previous Month')}
            >
              <svg className="size-4 rtl:rotate-180" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
              </svg>
            </button>

            {/* Interactive View Selector Buttons (Month & Year) */}
            <div className="flex items-center gap-1">
              {/* Month Selector Pill Button */}
              <button
                type="button"
                onClick={() => setViewMode(viewMode === 'months' ? 'days' : 'months')}
                className={`flex items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-bold transition-all ${
                  viewMode === 'months'
                    ? 'bg-[var(--primary)] text-white shadow-2xs'
                    : 'text-[var(--text-primary)] hover:bg-[var(--background)]'
                }`}
              >
                <span>{monthNames[currentMonth]}</span>
                <svg className={`size-3 transition-transform ${viewMode === 'months' ? 'rotate-180' : ''}`} xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2.5" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                </svg>
              </button>

              {/* Year Selector Pill Button */}
              <button
                type="button"
                onClick={() => {
                  if (viewMode === 'years') {
                    setViewMode('days');
                  } else {
                    setYearDecadeStart(Math.floor(currentYear / 10) * 10 - 1);
                    setViewMode('years');
                  }
                }}
                className={`flex items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-bold transition-all ${
                  viewMode === 'years'
                    ? 'bg-[var(--primary)] text-white shadow-2xs'
                    : 'text-[var(--text-primary)] hover:bg-[var(--background)]'
                }`}
              >
                <span className="font-mono">{currentYear}</span>
                <svg className={`size-3 transition-transform ${viewMode === 'years' ? 'rotate-180' : ''}`} xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2.5" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                </svg>
              </button>
            </div>

            {/* Next Button */}
            <button
              type="button"
              onClick={viewMode === 'years' ? handleNextDecade : handleNextMonth}
              className="flex size-7 items-center justify-center rounded-lg text-[var(--text-secondary)] transition-colors hover:bg-[var(--background)] hover:text-[var(--text-primary)]"
              title={viewMode === 'years' ? (locale === 'ar' ? 'العقد التالي' : 'Next Decade') : (locale === 'ar' ? 'الشهر التالي' : 'Next Month')}
            >
              <svg className="size-4 rtl:rotate-180" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
              </svg>
            </button>
          </div>

          {/* VIEW MODE 1: DAYS GRID */}
          {viewMode === 'days' ? (
            <>
              {/* Day of Week Headers */}
              <div className="grid grid-cols-7 mb-1 text-center">
                {dayNames.map((dName) => (
                  <span key={dName} className="text-[10px] font-bold uppercase tracking-wider text-[var(--text-muted)] py-1">
                    {dName}
                  </span>
                ))}
              </div>

              {/* Days Cells */}
              <div className="grid grid-cols-7 gap-0.5 text-center text-xs">
                {/* Trailing days from previous month */}
                {prevMonthDays.map((d) => {
                  let pMonth = currentMonth - 1;
                  let pYear = currentYear;
                  if (pMonth < 0) {
                    pMonth = 11;
                    pYear -= 1;
                  }
                  const disabledDay = isDateDisabled(pYear, pMonth, d);
                  return (
                    <button
                      key={`prev-${d}`}
                      type="button"
                      disabled={disabledDay}
                      onClick={() => handleSelectDay(d, true, false)}
                      className={`size-8 flex items-center justify-center rounded-lg text-[var(--text-muted)] opacity-40 transition-colors ${
                        disabledDay ? 'cursor-not-allowed opacity-20' : 'hover:bg-[var(--background)] hover:opacity-100'
                      }`}
                    >
                      {d}
                    </button>
                  );
                })}

                {/* Current month days */}
                {currentMonthDays.map((d) => {
                  const dayStr = `${currentYear}-${String(currentMonth + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
                  const isSelected = value === dayStr;
                  const isToday = todayStr === dayStr;
                  const disabledDay = isDateDisabled(currentYear, currentMonth, d);

                  return (
                    <button
                      key={`curr-${d}`}
                      type="button"
                      disabled={disabledDay}
                      onClick={() => handleSelectDay(d)}
                      className={`size-8 flex items-center justify-center rounded-lg font-medium transition-all ${
                        isSelected
                          ? 'bg-[var(--primary)] text-white shadow-xs font-bold'
                          : isToday
                          ? 'border border-[var(--primary)] text-[var(--primary)] font-bold bg-[var(--primary)]/10'
                          : disabledDay
                          ? 'cursor-not-allowed text-[var(--text-muted)] opacity-30'
                          : 'text-[var(--text-primary)] hover:bg-[var(--background)]'
                      }`}
                    >
                      {d}
                    </button>
                  );
                })}

                {/* Leading days of next month */}
                {nextMonthDays.map((d) => {
                  let nMonth = currentMonth + 1;
                  let nYear = currentYear;
                  if (nMonth > 11) {
                    nMonth = 0;
                    nYear += 1;
                  }
                  const disabledDay = isDateDisabled(nYear, nMonth, d);
                  return (
                    <button
                      key={`next-${d}`}
                      type="button"
                      disabled={disabledDay}
                      onClick={() => handleSelectDay(d, false, true)}
                      className={`size-8 flex items-center justify-center rounded-lg text-[var(--text-muted)] opacity-40 transition-colors ${
                        disabledDay ? 'cursor-not-allowed opacity-20' : 'hover:bg-[var(--background)] hover:opacity-100'
                      }`}
                    >
                      {d}
                    </button>
                  );
                })}
              </div>
            </>
          ) : null}

          {/* VIEW MODE 2: MONTHS GRID (3x4) */}
          {viewMode === 'months' ? (
            <div className="grid grid-cols-3 gap-2 py-1 text-xs">
              {monthNames.map((mName, idx) => {
                const isSelectedMonth = currentMonth === idx;
                const disabledM = isMonthDisabled(currentYear, idx);

                return (
                  <button
                    key={mName}
                    type="button"
                    disabled={disabledM}
                    onClick={() => handleSelectMonth(idx)}
                    className={`flex h-10 items-center justify-center rounded-lg px-2 text-xs font-bold transition-all ${
                      isSelectedMonth
                        ? 'bg-[var(--primary)] text-white shadow-xs'
                        : disabledM
                        ? 'cursor-not-allowed text-[var(--text-muted)] opacity-30'
                        : 'text-[var(--text-primary)] hover:bg-[var(--background)] hover:border hover:border-[var(--border)]'
                    }`}
                  >
                    {mName}
                  </button>
                );
              })}
            </div>
          ) : null}

          {/* VIEW MODE 3: YEARS GRID (3x4 Decade View) */}
          {viewMode === 'years' ? (
            <div className="grid grid-cols-3 gap-2 py-1 text-xs">
              {decadeYears.map((y) => {
                const isSelectedYear = currentYear === y;
                const disabledY = isYearDisabled(y);

                return (
                  <button
                    key={y}
                    type="button"
                    disabled={disabledY}
                    onClick={() => handleSelectYear(y)}
                    className={`flex h-10 items-center justify-center rounded-lg px-2 font-mono text-xs font-bold transition-all ${
                      isSelectedYear
                        ? 'bg-[var(--primary)] text-white shadow-xs'
                        : disabledY
                        ? 'cursor-not-allowed text-[var(--text-muted)] opacity-30'
                        : 'text-[var(--text-primary)] hover:bg-[var(--background)] hover:border hover:border-[var(--border)]'
                    }`}
                  >
                    {y}
                  </button>
                );
              })}
            </div>
          ) : null}

          {/* Footer Bar Actions */}
          <div className="mt-3 flex items-center justify-between border-t border-[var(--border)] pt-2.5 text-xs">
            <button
              type="button"
              onClick={handleSelectToday}
              className="font-semibold text-[var(--primary)] hover:underline"
            >
              {locale === 'ar' ? 'اليوم' : 'Today'}
            </button>

            {viewMode !== 'days' ? (
              <button
                type="button"
                onClick={() => setViewMode('days')}
                className="font-medium text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:underline"
              >
                {locale === 'ar' ? 'العودة للأيام' : 'Back to Days'}
              </button>
            ) : isClearable && value ? (
              <button
                type="button"
                onClick={handleClear}
                className="font-medium text-[var(--text-secondary)] hover:text-red-500 hover:underline"
              >
                {locale === 'ar' ? 'مسح' : 'Clear'}
              </button>
            ) : null}
          </div>
        </div>
      ) : null}
    </div>
  );
}
