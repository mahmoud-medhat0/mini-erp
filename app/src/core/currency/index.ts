/**
 * Currency registry. `exponent` = number of minor units per major unit.
 * Multi-currency is Day-One; base currency is configurable (seed EGP).
 * Nothing here hardcodes EGP into calculations — it is only the seeded default.
 */
export interface CurrencyDef {
  code: string;
  name_en: string;
  name_ar: string;
  symbol: string;
  exponent: number; // e.g. 2 => 1 EGP = 100 piastres
}

export const CURRENCIES: Readonly<Record<string, CurrencyDef>> = Object.freeze({
  EGP: { code: 'EGP', name_en: 'Egyptian Pound', name_ar: 'جنيه مصري', symbol: 'E£', exponent: 2 },
  USD: { code: 'USD', name_en: 'US Dollar', name_ar: 'دولار أمريكي', symbol: '$', exponent: 2 },
  EUR: { code: 'EUR', name_en: 'Euro', name_ar: 'يورو', symbol: '€', exponent: 2 },
  SAR: { code: 'SAR', name_en: 'Saudi Riyal', name_ar: 'ريال سعودي', symbol: 'ر.س', exponent: 2 },
  AED: { code: 'AED', name_en: 'UAE Dirham', name_ar: 'درهم إماراتي', symbol: 'د.إ', exponent: 2 },
  KWD: { code: 'KWD', name_en: 'Kuwaiti Dinar', name_ar: 'دينار كويتي', symbol: 'د.ك', exponent: 3 },
});

export const DEFAULT_BASE_CURRENCY = 'EGP';

export function currencyExponent(code: string): number {
  const c = CURRENCIES[code];
  if (!c) throw new Error(`Unknown currency: ${code}`);
  return c.exponent;
}
