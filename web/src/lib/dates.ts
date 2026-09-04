/**
 * Calendar-date helpers that work in the viewer's own timezone.
 *
 * The whole app used to derive dates from `new Date().toISOString().slice(0, 10)`,
 * which is the **UTC** calendar date. Anywhere ahead of UTC — Harare is UTC+2 —
 * that is still yesterday for the first hours of the morning, so at 01:00 a carer
 * opened My Day, saw today's date in the heading, and got the *previous* day's
 * visits underneath it. Their real visits for the day were missing entirely.
 *
 * The same conversion broke date arithmetic: building a Date from local midnight
 * and reading it back through `toISOString()` shifts it backwards across the day
 * boundary, so the schedule's next/previous-day arrows moved by the wrong amount
 * and the reports "from" date defaulted into the previous month.
 *
 * `toLocaleDateString("en-CA")` is the fix: en-CA formats as YYYY-MM-DD and
 * locale formatting reads the local clock, so the string always matches the date
 * on the wall. Route every "what day is it" through here rather than reaching for
 * `toISOString()` again.
 */

/** Format a Date as YYYY-MM-DD in the local timezone. */
export function toIsoDate(date: Date): string {
  return date.toLocaleDateString("en-CA");
}

/** Today's calendar date, locally — what the user would call "today". */
export function todayIso(): string {
  return toIsoDate(new Date());
}

/** Shift a YYYY-MM-DD string by whole days, staying on local calendar dates. */
export function addDays(iso: string, days: number): string {
  const date = new Date(`${iso}T00:00:00`);
  date.setDate(date.getDate() + days);
  return toIsoDate(date);
}

/** The first day of the current month, locally. */
export function startOfMonthIso(): string {
  const now = new Date();
  return toIsoDate(new Date(now.getFullYear(), now.getMonth(), 1));
}
