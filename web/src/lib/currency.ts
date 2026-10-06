import { getTenantPreferences } from "./preferences";

// Symbol only for currencies actually in use across seeded/demo tenants —
// anything else falls back to its ISO code as a prefix rather than guessing
// a symbol that might be wrong.
const CURRENCY_SYMBOLS: Record<string, string> = {
  GBP: "£",
  USD: "$",
  EUR: "€",
  ZAR: "R",
  ZMW: "K",
};

/**
 * `£1,234.56` for a known currency, `XYZ 1,234.56` for anything else.
 * Payroll figures (hourly rates, payslips) don't carry their own currency —
 * unlike invoices, which are tied to a funder that might use a different
 * one — so those callers omit currencyCode and get the organisation's own,
 * from System Settings → Company Details.
 */
export function formatCurrency(
  amount: number | string | null | undefined,
  currencyCode: string | null = getTenantPreferences().currency,
  options: { decimals?: number } = {},
): string {
  if (amount === null || amount === undefined || amount === "") {
    return "—";
  }

  const value = typeof amount === "string" ? Number(amount) : amount;
  if (Number.isNaN(value)) {
    return "—";
  }

  const decimals = options.decimals ?? 2;
  const formatted = value.toLocaleString(getTenantPreferences().locale, { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
  const code = currencyCode?.toUpperCase();
  const symbol = code ? CURRENCY_SYMBOLS[code] : undefined;

  return symbol ? `${symbol}${formatted}` : code ? `${code} ${formatted}` : formatted;
}
