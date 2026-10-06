import { DEFAULT_SETTINGS, type EffectiveTenantSettings } from "../modules/settings/defaults";

/**
 * The signed-in user's organisation preferences — currency, locale,
 * timezone and settings from System Settings — held here so plain helper
 * functions (formatCurrency, formatDate, todayIso…) can follow them without
 * every caller threading them through. AuthProvider sets it whenever the
 * user loads; before that (and for platform admins with no organisation)
 * it falls back to the browser's own timezone and en-GB / GBP.
 */
export interface TenantPreferences {
  name: string;
  country: string;
  timezone: string;
  currency: string;
  locale: string;
  settings: EffectiveTenantSettings;
}

const browserTimezone = (() => {
  try {
    return Intl.DateTimeFormat().resolvedOptions().timeZone || "UTC";
  } catch {
    return "UTC";
  }
})();

const FALLBACK: TenantPreferences = {
  name: "",
  country: "",
  timezone: browserTimezone,
  currency: "GBP",
  locale: "en-GB",
  settings: DEFAULT_SETTINGS,
};

let current: TenantPreferences = FALLBACK;

// A bare language ("en") formats dates the US way (10/06/2026). Care
// providers here expect day-first, so a bare locale is read as its
// British-English form unless the organisation is in the US; an explicit
// regional locale (en-ZA, en-IE…) is used exactly as set.
function resolveLocale(locale: string | undefined, country: string | undefined): string {
  if (!locale) return FALLBACK.locale;
  if (locale.includes("-")) {
    try {
      return Intl.DateTimeFormat.supportedLocalesOf([locale]).length ? locale : FALLBACK.locale;
    } catch {
      return FALLBACK.locale;
    }
  }
  const isUs = ["united states", "usa", "us"].includes((country ?? "").trim().toLowerCase());
  return locale === "en" ? (isUs ? "en-US" : "en-GB") : locale;
}

function validTimezone(timezone: string | undefined): string {
  if (!timezone) return browserTimezone;
  try {
    new Intl.DateTimeFormat("en-GB", { timeZone: timezone });
    return timezone;
  } catch {
    return browserTimezone;
  }
}

export function setTenantPreferences(
  tenant: { name: string; country: string; timezone: string; currency: string; locale: string; settings: EffectiveTenantSettings } | null | undefined,
): void {
  current = tenant
    ? {
        name: tenant.name,
        country: tenant.country,
        timezone: validTimezone(tenant.timezone),
        currency: (tenant.currency || "GBP").toUpperCase(),
        locale: resolveLocale(tenant.locale, tenant.country),
        settings: tenant.settings ?? DEFAULT_SETTINGS,
      }
    : FALLBACK;
}

export function getTenantPreferences(): TenantPreferences {
  return current;
}

export function tenantSettings(): EffectiveTenantSettings {
  return current.settings;
}

// ---- Dates and times, in the organisation's timezone and locale ------------

const DATE_ONLY = /^\d{4}-\d{2}-\d{2}$/;

function toDate(value: string | Date): Date {
  return value instanceof Date ? value : new Date(value);
}

/** A calendar date ("6 Oct 2026"). Date-only strings are shown as-is, never shifted by timezone. */
export function formatDate(value: string | Date | null | undefined): string {
  if (!value) return "—";
  if (typeof value === "string" && DATE_ONLY.test(value)) {
    const [y, m, d] = value.split("-").map(Number);
    return new Intl.DateTimeFormat(current.locale, { dateStyle: "medium", timeZone: "UTC" }).format(new Date(Date.UTC(y, m - 1, d)));
  }
  const date = toDate(value);
  if (Number.isNaN(date.getTime())) return "—";
  return new Intl.DateTimeFormat(current.locale, { dateStyle: "medium", timeZone: current.timezone }).format(date);
}

/** A moment in time ("6 Oct 2026, 09:30") in the organisation's timezone. */
export function formatDateTime(value: string | Date | null | undefined): string {
  if (!value) return "—";
  const date = toDate(value);
  if (Number.isNaN(date.getTime())) return "—";
  return new Intl.DateTimeFormat(current.locale, { dateStyle: "medium", timeStyle: "short", timeZone: current.timezone }).format(date);
}

/** Just the time ("09:30") in the organisation's timezone. */
export function formatTime(value: string | Date | null | undefined): string {
  if (!value) return "—";
  const date = toDate(value);
  if (Number.isNaN(date.getTime())) return "—";
  return new Intl.DateTimeFormat(current.locale, { timeStyle: "short", timeZone: current.timezone }).format(date);
}

/** The date it is now for the organisation, as YYYY-MM-DD. */
export function tenantToday(): string {
  return new Intl.DateTimeFormat("en-CA", { timeZone: current.timezone, year: "numeric", month: "2-digit", day: "2-digit" }).format(new Date());
}

/** Minutes past midnight right now, on the organisation's clock. */
export function tenantMinutesNow(): number {
  const parts = new Intl.DateTimeFormat("en-GB", { timeZone: current.timezone, hour: "2-digit", minute: "2-digit", hourCycle: "h23" }).formatToParts(new Date());
  const get = (type: string) => Number(parts.find((p) => p.type === type)?.value ?? 0);
  return get("hour") * 60 + get("minute");
}
