import type { MedicalHistoryEntry, ServiceUser, ServiceUserContact } from "../../lib/types";
import { todayIso } from "../../lib/dates";
import { downloadWordDocument, escapeHtml as esc, fileSlug, htmlDocument, printHtmlDocument } from "../../lib/htmlDocument";
import { formatDate } from "../../lib/preferences";
import {
  CATEGORY_CONFIG,
  CATEGORY_ORDER,
  RESUSCITATION_LABELS,
  SEVERITY_LABELS,
  SMOKING_LABELS,
  entryColumns,
  sortEntries,
} from "./categories";

// The client's medical history as one printable document — the record to
// hand a GP, paramedic or hospital. See lib/htmlDocument for how it's
// printed and downloaded.

const EXTRA_STYLES = `
  .alert { color: #b91c1c; font-weight: bold; }
`;

function multiline(value: string | null | undefined): string {
  return value && value.trim() ? esc(value).replaceAll("\n", "<br>") : "—";
}

function row(label: string, value: string): string {
  return `<tr><th>${esc(label)}</th><td>${value}</td></tr>`;
}

function allergySummary(serviceUser: ServiceUser, entries: MedicalHistoryEntry[]): string {
  const current = entries.filter((e) => e.category === "allergy" && e.status !== "resolved");
  if (current.length > 0) {
    return current
      .map((e) => `<span class="alert">${esc(e.name)}</span>${e.severity ? ` — ${esc(SEVERITY_LABELS[e.severity])}` : ""}`)
      .join("<br>");
  }
  return serviceUser.no_known_allergies ? "No known allergies (confirmed)" : '<span class="alert">Not recorded — check before giving any medication</span>';
}

function categoryHtml(category: (typeof CATEGORY_ORDER)[number], entries: MedicalHistoryEntry[]): string {
  const config = CATEGORY_CONFIG[category];
  const rows = sortEntries(entries.filter((e) => e.category === category));
  if (rows.length === 0) return `<h2>${esc(config.title)}</h2><p>${esc(config.emptyMessage)}</p>`;

  const columns = entryColumns(config);
  const head = columns.map((c) => `<th>${esc(c.label)}</th>`).join("");
  const body = rows
    .map((entry) => `<tr>${columns.map((c) => `<td>${c.key === "notes" || c.key === "reaction" ? multiline(c.text(entry)) : esc(c.text(entry))}</td>`).join("")}</tr>`)
    .join("");

  return `<h2>${esc(config.title)}</h2><table class="grid"><thead><tr>${head}</tr></thead><tbody>${body}</tbody></table>`;
}

export function buildMedicalHistoryHtml(
  serviceUser: ServiceUser,
  entries: MedicalHistoryEntry[],
  contacts: ServiceUserContact[],
): string {
  const name = `${serviceUser.first_name} ${serviceUser.last_name}`;
  const gps = contacts.filter((c) => c.type === "gp");
  const reviewed = serviceUser.medical_history_reviewed_at
    ? `Last reviewed ${esc(formatDate(serviceUser.medical_history_reviewed_at))}${serviceUser.medical_history_reviewed_by_name ? ` by ${esc(serviceUser.medical_history_reviewed_by_name)}` : ""}`
    : "Not yet reviewed";

  const keyRows = [
    row("Date of birth", esc(formatDate(serviceUser.date_of_birth))),
    row("NHS number", esc(serviceUser.nhs_number ?? "—")),
    row("Allergies", allergySummary(serviceUser, entries)),
    row(
      "Resuscitation",
      (serviceUser.resuscitation_status
        ? `<span class="${serviceUser.resuscitation_status === "dnacpr" ? "alert" : ""}">${esc(RESUSCITATION_LABELS[serviceUser.resuscitation_status])}</span>`
        : "Not recorded") + (serviceUser.resuscitation_notes ? `<br><small>${multiline(serviceUser.resuscitation_notes)}</small>` : ""),
    ),
    row("Registered GP", gps.length ? gps.map((gp) => esc([gp.name, gp.phone].filter(Boolean).join(" — "))).join("<br>") : "—"),
    row("Smoking", esc(serviceUser.smoking_status ? SMOKING_LABELS[serviceUser.smoking_status] : "—")),
    row("Alcohol", esc(serviceUser.alcohol_intake ?? "—")),
  ].join("");

  const body = `
  <h1>Medical history — ${esc(name)}</h1>
  <p class="meta">${reviewed} · Printed ${esc(todayIso())}</p>
  <h2>Key information</h2>
  <table class="kv">${keyRows}</table>
  ${serviceUser.medical_history_summary ? `<h3>Summary</h3><p>${multiline(serviceUser.medical_history_summary)}</p>` : ""}
  ${CATEGORY_ORDER.map((category) => categoryHtml(category, entries)).join("\n")}`;

  return htmlDocument(`Medical history — ${name}`, body, EXTRA_STYLES);
}

function fileBaseName(serviceUser: ServiceUser): string {
  return fileSlug(`medical-history-${serviceUser.first_name}-${serviceUser.last_name}`);
}

export function printMedicalHistory(serviceUser: ServiceUser, entries: MedicalHistoryEntry[], contacts: ServiceUserContact[]): void {
  printHtmlDocument(buildMedicalHistoryHtml(serviceUser, entries, contacts), fileBaseName(serviceUser));
}

export function downloadMedicalHistoryWord(serviceUser: ServiceUser, entries: MedicalHistoryEntry[], contacts: ServiceUserContact[]): void {
  downloadWordDocument(buildMedicalHistoryHtml(serviceUser, entries, contacts), fileBaseName(serviceUser));
}
