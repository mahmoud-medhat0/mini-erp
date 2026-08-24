/**
 * Global accounting utility helpers for multilingual label resolution.
 */

export function getLocalizedName(
  nameObj?: Record<string, string> | string | null,
  locale: string = 'en'
): string {
  if (!nameObj) return '';
  if (typeof nameObj === 'string') return nameObj;
  return locale === 'ar' ? nameObj.ar || nameObj.en : nameObj.en || nameObj.ar;
}

export function getAccountTypeLabel(category: string, locale: string = 'en'): string {
  const cats: Record<string, { en: string; ar: string }> = {
    asset: { en: 'Asset', ar: 'أصول' },
    liability: { en: 'Liability', ar: 'التزامات' },
    equity: { en: 'Equity', ar: 'حقوق ملكية' },
    revenue: { en: 'Revenue', ar: 'إيرادات' },
    expense: { en: 'Expense', ar: 'مصروفات' },
    contra_asset: { en: 'Contra Asset', ar: 'أصول مقابلة' },
    contra_liability: { en: 'Contra Liability', ar: 'خصم التزامات' },
    contra_revenue: { en: 'Contra Revenue', ar: 'مردودات مبيعات' },
  };
  const key = (category || '').toLowerCase();
  if (!cats[key]) return (category || '').toUpperCase();
  return locale === 'ar' ? cats[key].ar : cats[key].en;
}

export const getCategoryLabel = getAccountTypeLabel;

export function getAccountNatureLabel(nature: string, locale: string = 'en'): string {
  const natures: Record<string, { en: string; ar: string }> = {
    debit: { en: 'Debit', ar: 'مدين' },
    credit: { en: 'Credit', ar: 'دائن' },
  };
  const key = (nature || '').toLowerCase();
  if (!natures[key]) return (nature || '').toUpperCase();
  return locale === 'ar' ? natures[key].ar : natures[key].en;
}

export function formatDate(dateStr?: string | null): string {
  if (!dateStr) return '';
  const str = String(dateStr).trim();
  if (str.includes('T')) {
    return str.split('T')[0];
  }
  if (str.includes(' ')) {
    return str.split(' ')[0];
  }
  return str;
}

export function formatPeriodLabel(
  period: {
    month: number;
    start_date?: string | null;
    end_date?: string | null;
    fiscal_year?: { year: number } | null;
  },
  locale: string = 'en'
): string {
  const yearPrefix = period.fiscal_year?.year ? `${period.fiscal_year.year} - ` : '';
  const monthText = locale === 'ar' ? `شهر ${period.month}` : `Month ${period.month}`;

  if (period.start_date && period.end_date) {
    const start = formatDate(period.start_date);
    const end = formatDate(period.end_date);
    const toText = locale === 'ar' ? 'إلى' : 'to';
    return `${yearPrefix}${monthText} (\u200E${start}\u200E ${toText} \u200E${end}\u200E)`;
  }

  if (period.start_date) {
    const start = formatDate(period.start_date);
    return `${yearPrefix}${monthText} (\u200E${start}\u200E)`;
  }

  return `${yearPrefix}${monthText}`;
}

export function formatMoney(amountMinor: number | string | null | undefined, currency: string = 'EGP'): string {
  return formatMinorUnits(amountMinor, currency);
}

export function formatMinorUnits(
  amountMinor: number | string | null | undefined,
  currency: string = 'EGP',
  decimalPlaces: number = 2
): string {
  const normalized = normalizeMinorUnits(amountMinor);
  const isNegative = normalized.startsWith('-');
  let digits = isNegative ? normalized.slice(1) : normalized;

  digits = digits.replace(/^0+(?=\d)/, '') || '0';

  if (decimalPlaces > 0) {
    while (digits.length <= decimalPlaces) {
      digits = `0${digits}`;
    }
  }

  const major = decimalPlaces > 0 ? digits.slice(0, -decimalPlaces) || '0' : digits;
  const fraction = decimalPlaces > 0 ? digits.slice(-decimalPlaces).padStart(decimalPlaces, '0') : '';
  const groupedMajor = major.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  const sign = isNegative && digits.replace(/0/g, '') !== '' ? '-' : '';
  const numeric = decimalPlaces > 0 ? `${sign}${groupedMajor}.${fraction}` : `${sign}${groupedMajor}`;

  return `${numeric} ${currency}`;
}

export function formatAccountingAmount(
  amountMinor: number | string | null | undefined,
  currency: string = 'EGP',
  options: { zeroAsDash?: boolean; parenthesesForNegative?: boolean; showCurrency?: boolean } = {}
): string {
  const { zeroAsDash = true, parenthesesForNegative = false, showCurrency = true } = options;
  const normalized = normalizeMinorUnits(amountMinor);
  const isZero = normalized.replace('-', '').replace(/^0+/, '') === '';

  if (zeroAsDash && isZero) return '—';

  const formatted = formatMinorUnits(normalized, showCurrency ? currency : '');
  const trimmed = formatted.trim();

  if (parenthesesForNegative && normalized.startsWith('-')) {
    return `(${trimmed.replace(/^-/, '')})`;
  }

  return trimmed;
}

function normalizeMinorUnits(amountMinor: number | string | null | undefined): string {
  if (amountMinor === null || amountMinor === undefined || amountMinor === '') return '0';

  if (typeof amountMinor === 'number') {
    if (!Number.isFinite(amountMinor)) return '0';
    return String(Math.trunc(amountMinor));
  }

  const raw = String(amountMinor).trim();
  const isNegative = raw.startsWith('-');
  const digits = raw.replace(/^-/, '').replace(/[^\d]/g, '');

  return `${isNegative ? '-' : ''}${digits || '0'}`;
}
