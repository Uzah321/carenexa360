import type { CarePlan, CarePlanRiskAssessment, CarePlanSection, ServiceUser } from "../../lib/types";
import { todayIso } from "../../lib/dates";
import {
  LIKELIHOOD_LABELS,
  MEDICATION_SUPPORT_LABELS,
  PERSON_AT_RISK_LABELS,
  RISK_RATING_LABELS,
  SEVERITY_LABELS,
  riskRating,
  riskScore,
} from "./risk";

// Builds the care plan as one self-contained HTML document. The same markup
// feeds both exports: printed via the browser (where "Save as PDF" is the
// PDF route), and downloaded as a .doc, which Word opens natively as HTML —
// so neither needs a PDF or DOCX library.

function esc(value: unknown): string {
  return String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;");
}

function multiline(value: string | null | undefined): string {
  return value ? esc(value).replaceAll("\n", "<br>") : "—";
}

function areaLabel(area: string | null): string {
  if (!area) return "General";
  const text = area.replaceAll("_", " ");
  return text.charAt(0).toUpperCase() + text.slice(1);
}

function scoreCell(likelihood: number | null, severity: number | null): string {
  const score = riskScore(likelihood, severity);
  const rating = riskRating(score);
  if (!rating) return "Not scored";
  return `<span class="rating ${rating}">${esc(RISK_RATING_LABELS[rating])} (${score})</span><br><small>L${likelihood} ${esc(LIKELIHOOD_LABELS[likelihood!])} × S${severity} ${esc(SEVERITY_LABELS[severity!])}</small>`;
}

function row(label: string, value: string): string {
  return `<tr><th>${esc(label)}</th><td>${value}</td></tr>`;
}

function sectionHtml(section: CarePlanSection): string {
  const interventions = section.intervention
    .split("\n")
    .map((l) => l.trim())
    .filter(Boolean)
    .map((l) => `<li>${esc(l)}</li>`)
    .join("");

  return `
    <div class="block">
      <h3>${esc(areaLabel(section.area))}${section.risk ? ` <span class="rating ${esc(section.risk)}">${esc(section.risk.toUpperCase())} RISK</span>` : ""}</h3>
      <table class="kv">
        ${row("Identified need", multiline(section.identified_need))}
        ${row("Desired outcome", multiline(section.goal))}
        ${row("Interventions", interventions ? `<ul>${interventions}</ul>` : "—")}
        ${section.equipment ? row("Equipment", multiline(section.equipment)) : ""}
        ${row("Frequency", esc(section.frequency ?? "—"))}
        ${row("Responsible", esc(section.responsible_staff_name ?? "—"))}
        ${row("Effective from", esc(section.start_date ?? "—"))}
        ${row("Next review", esc(section.review_date ?? "—"))}
      </table>
    </div>`;
}

function riskAssessmentHtml(ra: CarePlanRiskAssessment, index: number): string {
  const med = ra.medication_details;
  const yesNo = (v: boolean | null | undefined) => (v === null || v === undefined ? "—" : v ? "Yes" : "No");

  const medicationRows = med
    ? [
        row("Medication", esc(med.medication_name ?? "—")),
        row("Dose / route / frequency", esc(med.dose_route_frequency ?? "—")),
        row("Level of support", esc(med.support_level ? MEDICATION_SUPPORT_LABELS[med.support_level] : "—")),
        row("Controlled drug", yesNo(med.controlled_drug)),
        row("PRN (as required)", yesNo(med.prn)),
        med.prn ? row("PRN protocol", multiline(med.prn_protocol)) : "",
        row("Capacity & consent", multiline(med.capacity_and_consent)),
        row("Known allergies", multiline(med.known_allergies)),
        row("Side effects to monitor", multiline(med.side_effects_to_monitor)),
        row("Storage", multiline(med.storage)),
        row("Ordering & collection", multiline(med.ordering_and_collection)),
        row("Disposal", multiline(med.disposal)),
        row("If a dose is missed, refused or an error occurs", multiline(med.error_response)),
      ].join("")
    : "";

  return `
    <div class="block">
      <h3>${index + 1}. ${esc(ra.hazard)}</h3>
      <table class="kv">
        ${med ? "" : row("Care plan area", esc(areaLabel(ra.area)))}
        ${medicationRows}
        ${row("Who might be harmed", esc(ra.persons_at_risk.map((p) => PERSON_AT_RISK_LABELS[p]).join(", ") || "—"))}
        ${row("How they might be harmed", multiline(ra.harm_description))}
        ${row("Initial risk", scoreCell(ra.likelihood, ra.severity))}
        ${row("Existing control measures", multiline(ra.existing_controls))}
        ${row("Further action required", multiline(ra.further_actions))}
        ${row("Residual risk", scoreCell(ra.residual_likelihood, ra.residual_severity))}
        ${row("Action by", esc(ra.action_owner_name ?? "—"))}
        ${row("Action due", esc(ra.action_due_date ?? "—"))}
        ${row("Review date", esc(ra.review_date ?? "—"))}
      </table>
    </div>`;
}

function riskSummaryTable(assessments: CarePlanRiskAssessment[]): string {
  const rows = assessments
    .map(
      (ra, i) => `<tr>
        <td>${i + 1}</td>
        <td>${esc(ra.hazard)}</td>
        <td>${esc(ra.persons_at_risk.map((p) => PERSON_AT_RISK_LABELS[p]).join(", ") || "—")}</td>
        <td>${scoreCell(ra.likelihood, ra.severity)}</td>
        <td>${scoreCell(ra.residual_likelihood, ra.residual_severity)}</td>
        <td>${esc(ra.review_date ?? "—")}</td>
      </tr>`,
    )
    .join("");
  return `<table class="grid"><thead><tr><th>#</th><th>Hazard</th><th>Who might be harmed</th><th>Initial</th><th>Residual</th><th>Review</th></tr></thead><tbody>${rows}</tbody></table>`;
}

const STYLES = `
  @page { size: A4; margin: 16mm 14mm; }
  body { font-family: Arial, Helvetica, sans-serif; font-size: 10.5pt; color: #1a1a1a; line-height: 1.4; }
  h1 { font-size: 18pt; margin: 0 0 4pt; }
  h2 { font-size: 13pt; margin: 18pt 0 6pt; padding-bottom: 3pt; border-bottom: 2px solid #0f766e; color: #0f766e; }
  h3 { font-size: 11pt; margin: 12pt 0 4pt; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 6pt; }
  th, td { border: 1px solid #c8c8c8; padding: 4pt 6pt; vertical-align: top; text-align: left; }
  table.kv th { width: 32%; background: #f3f4f6; font-weight: bold; }
  table.grid thead th { background: #f3f4f6; }
  ul { margin: 0; padding-left: 14pt; }
  small { color: #555; }
  .meta { color: #555; margin-bottom: 8pt; }
  .block { page-break-inside: avoid; break-inside: avoid; }
  .rating { font-weight: bold; }
  .rating.low { color: #3f7d20; }
  .rating.medium { color: #b45309; }
  .rating.high, .rating.very_high { color: #b91c1c; }
  .signatures td { height: 34pt; }
  * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
`;

export function buildCarePlanHtml(plan: CarePlan, serviceUser: ServiceUser | undefined): string {
  const name = serviceUser ? `${serviceUser.first_name} ${serviceUser.last_name}` : `Service user #${plan.service_user_id}`;
  const assessments = plan.risk_assessments ?? [];
  const general = assessments.filter((ra) => ra.type === "general");
  const medication = assessments.filter((ra) => ra.type === "medication");

  const profileRows = serviceUser
    ? [
        row("Name", esc(name + (serviceUser.preferred_name ? ` (prefers "${serviceUser.preferred_name}")` : ""))),
        row("Date of birth", esc(serviceUser.date_of_birth ?? "—")),
        row("Address", multiline(serviceUser.address)),
        row("Allergies", esc(serviceUser.allergies?.join(", ") || "None recorded")),
        row("Diagnoses", esc(serviceUser.diagnoses?.join(", ") || "—")),
        row("Communication needs", multiline(serviceUser.communication_needs)),
        row("Capacity & consent", multiline(serviceUser.capacity_consent_notes)),
      ].join("")
    : row("Name", esc(name));

  return `<!DOCTYPE html>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta charset="utf-8">
<title>Care Plan — ${esc(name)} — v${plan.version}</title>
<!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View><w:Zoom>100</w:Zoom></w:WordDocument></xml><![endif]-->
<style>${STYLES}</style>
</head>
<body>
  <h1>Care Plan — ${esc(name)}</h1>
  <p class="meta">
    Version ${plan.version} · ${plan.status === "active" ? "Current" : "Archived"} · Effective from ${esc(plan.effective_from)}
    ${plan.created_by_name ? ` · Prepared by ${esc(plan.created_by_name)}` : ""} · Printed ${esc(todayIso())}
  </p>

  <h2>About the person</h2>
  <table class="kv">${profileRows}</table>
  ${plan.notes ? `<h3>Plan notes</h3><p>${multiline(plan.notes)}</p>` : ""}

  <h2>Care and support needs</h2>
  ${plan.sections.map(sectionHtml).join("") || "<p>No sections recorded.</p>"}

  <h2>Risk assessment and management</h2>
  ${
    general.length
      ? `<p>Risks are scored likelihood (1–5) × severity (1–5): 1–4 Low, 5–9 Medium, 10–16 High, 20–25 Very high.</p>
         ${riskSummaryTable(general)}${general.map(riskAssessmentHtml).join("")}`
      : "<p>No general risk assessments recorded.</p>"
  }

  <h2>Medication risk assessment</h2>
  ${medication.length ? `${riskSummaryTable(medication)}${medication.map(riskAssessmentHtml).join("")}` : "<p>No medication risk assessments recorded.</p>"}

  <h2>Agreement</h2>
  <table class="grid signatures">
    <thead><tr><th>Role</th><th>Name</th><th>Signature</th><th>Date</th></tr></thead>
    <tbody>
      <tr><td>Service user / representative</td><td></td><td></td><td></td></tr>
      <tr><td>Assessor / care coordinator</td><td></td><td></td><td></td></tr>
      <tr><td>Registered manager</td><td></td><td></td><td></td></tr>
    </tbody>
  </table>
</body>
</html>`;
}

function fileBaseName(plan: CarePlan, serviceUser: ServiceUser | undefined): string {
  const who = serviceUser ? `${serviceUser.first_name}-${serviceUser.last_name}` : `service-user-${plan.service_user_id}`;
  return `care-plan-${who}-v${plan.version}`.toLowerCase().replace(/[^a-z0-9-]+/g, "-");
}

// Prints via a hidden iframe so the app's own layout/CSS doesn't leak into
// the document. The browser's print dialog offers "Save as PDF".
export function printCarePlan(plan: CarePlan, serviceUser: ServiceUser | undefined): void {
  const iframe = document.createElement("iframe");
  iframe.setAttribute("aria-hidden", "true");
  iframe.style.cssText = "position:fixed;right:0;bottom:0;width:0;height:0;border:0;";
  document.body.appendChild(iframe);

  const doc = iframe.contentDocument;
  const win = iframe.contentWindow;
  if (!doc || !win) {
    iframe.remove();
    return;
  }

  doc.open();
  doc.write(buildCarePlanHtml(plan, serviceUser));
  doc.close();
  // The print dialog's default filename comes from the document title.
  doc.title = fileBaseName(plan, serviceUser);

  const cleanup = () => setTimeout(() => iframe.remove(), 500);
  win.addEventListener("afterprint", cleanup, { once: true });
  setTimeout(() => {
    win.focus();
    win.print();
  }, 50);
}

export function downloadCarePlanWord(plan: CarePlan, serviceUser: ServiceUser | undefined): void {
  // A leading BOM makes Word pick up UTF-8 rather than guessing the encoding.
  const blob = new Blob(["﻿", buildCarePlanHtml(plan, serviceUser)], { type: "application/msword" });
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = `${fileBaseName(plan, serviceUser)}.doc`;
  document.body.appendChild(link);
  link.click();
  link.remove();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}
