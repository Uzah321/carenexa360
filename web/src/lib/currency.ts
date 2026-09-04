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
 * one — so those callers omit currencyCode and get the org's own, GBP.
 */
export function formatCurrency(amount: number | string | null | undefined, currencyCode: string | null = "GBP"): string {
  if (amount === null || amount === undefined || amount === "") {
    return "—";
  }

  const value = typeof amount === "string" ? Number(amount) : amount;
  if (Number.isNaN(value)) {
    return "—";
  }

  const formatted = value.toLocaleString("en-GB", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const code = currencyCode?.toUpperCase();
  const symbol = code ? CURRENCY_SYMBOLS[code] : undefined;

  return symbol ? `${symbol}${formatted}` : code ? `${code} ${formatted}` : formatted;
}
