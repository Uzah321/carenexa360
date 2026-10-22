import type { CarePlan, CarePlanRiskAssessment, CarePlanSection, HomeCarePlan, ServiceUser } from "../../lib/types";
import { todayIso } from "../../lib/dates";
import { downloadWordDocument, escapeHtml as esc, fileSlug, htmlDocument, printHtmlDocument } from "../../lib/htmlDocument";
import { richTextToHtml } from "../../lib/richText";
import { END_OF_LIFE_AREAS, NEED_AREAS, SUMMARIES, consentLabel, homeCarePlanHasContent, type NeedAreaConfig } from "./homeCarePlan";
import {
  LIKELIHOOD_LABELS,
  MEDICATION_SUPPORT_LABELS,
  PERSON_AT_RISK_LABELS,
  RISK_RATING_LABELS,
  RISK_TYPE_LABELS,
  SEVERITY_LABELS,
  riskRating,
  riskScore,
} from "./risk";

// Builds the care plan as one self-contained HTML document — see
// lib/htmlDocument for how it's printed and downloaded.

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

/** Saved rich text, re-sanitised — or "—" when empty. */
function rich(value: string | null | undefined): string {
  return richTextToHtml(value) || "—";
}

function homeCarePlanHtml(plan: HomeCarePlan | null): string {
  if (!plan || !homeCarePlanHasContent(plan)) return "<p>No home care plan recorded on this version.</p>";

  const needRows = (areas: NeedAreaConfig[]) =>
    areas
      .map((config) => {
        const need = plan.needs?.[config.key];
        if (!need) return "";
        const consent = config.consentLabel ? `<br><small>${esc(consentLabel(need.consented))}</small>` : "";
        return row(config.title, rich(need.details) + consent);
      })
      .join("");

  const summaryRows = SUMMARIES.filter((s) => plan.summaries?.[s.key])
    .map((s) => row(s.label, esc(plan.summaries?.[s.key])))
    .join("");

  return `
    <table class="kv">
      ${row("About me", rich(plan.about_me))}
      ${row("Desired goals and outcomes", [plan.desired_outcomes ? `<b>${esc(plan.desired_outcomes)}</b>` : "", richTextToHtml(plan.goals_and_outcomes)].filter(Boolean).join("<br>") || "—")}
      ${row("Cognitive impairment", [plan.cognitive_impairment_summary ? `<b>${esc(plan.cognitive_impairment_summary)}</b>` : "", richTextToHtml(plan.cognitive_impairment)].filter(Boolean).join("<br>") || "—")}
      ${needRows(NEED_AREAS)}
    </table>
    ${summaryRows ? `<h3>Summaries</h3><table class="kv">${summaryRows}</table>` : ""}
    ${END_OF_LIFE_AREAS.some((a) => plan.needs?.[a.key]) ? `<h3>Advance care and final days</h3><table class="kv">${needRows(END_OF_LIFE_AREAS)}</table>` : ""}`;
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
        ${med ? "" : row("Risk type", esc(ra.risk_type ? RISK_TYPE_LABELS[ra.risk_type] : "—"))}
        ${med ? "" : row("Care plan area", esc(areaLabel(ra.area)))}
        ${medicationRows}
        ${ra.details ? row("Risk details", rich(ra.details)) : ""}
        ${ra.triggers ? row("Risk triggers", multiline(ra.triggers)) : ""}
        ${row("Who might be harmed", esc(ra.persons_at_risk.map((p) => PERSON_AT_RISK_LABELS[p]).join(", ") || "—"))}
        ${row("How they might be harmed", multiline(ra.harm_description))}
        ${row("Initial risk", scoreCell(ra.likelihood, ra.severity))}
        ${row("Existing control measures", multiline(ra.existing_controls))}
        ${row("Further action required", multiline(ra.further_actions))}
        ${row("Residual risk", scoreCell(ra.residual_likelihood, ra.residual_severity))}
        ${ra.target_likelihood || ra.target_severity ? row("Target risk", scoreCell(ra.target_likelihood, ra.target_severity)) : ""}
        ${row("Safety / contingency plan required", ra.contingency_plan_required ? "Yes" : "No")}
        ${ra.contingency_plan_required ? row("Safety / contingency plan", rich(ra.contingency_plan)) : ""}
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

/**
 * What an export covers — the tab the user is on when they press Print or
 * Download. "full" is the whole care plan (the Overview tab); every other
 * scope prints just that tab's content under the same header.
 */
export type CarePlanExportScope =
  | { kind: "full" }
  | { kind: "home-care-plan" }
  | { kind: "area"; area: string }
  | { kind: "risks" }
  | { kind: "medication-risk" }
  | { kind: "goals" };

const SCOPE_TITLES: Record<CarePlanExportScope["kind"], string> = {
  full: "Care Plan",
  "home-care-plan": "Home Care Plan",
  area: "Care Plan",
  risks: "Risk Assessment",
  "medication-risk": "Medication Risk Assessment",
  goals: "Care Plan Goals",
};

function scopeTitle(scope: CarePlanExportScope): string {
  return scope.kind === "area" ? `${areaLabel(scope.area)} Care Plan` : SCOPE_TITLES[scope.kind];
}

const RISK_SCORING_NOTE =
  "<p>Risks are scored likelihood (1–5) × severity (1–5): 1–4 Low, 5–9 Medium, 10–16 High, 20–25 Very high.</p>";

function generalRisksHtml(assessments: CarePlanRiskAssessment[]): string {
  return assessments.length
    ? `${RISK_SCORING_NOTE}${riskSummaryTable(assessments)}${assessments.map(riskAssessmentHtml).join("")}`
    : "<p>No general risk assessments recorded.</p>";
}

function medicationRisksHtml(assessments: CarePlanRiskAssessment[]): string {
  return assessments.length
    ? `${RISK_SCORING_NOTE}${riskSummaryTable(assessments)}${assessments.map(riskAssessmentHtml).join("")}`
    : "<p>No medication risk assessments recorded.</p>";
}

/** Mirrors the "Care plan area risk levels" table on the Risk Assessment tab. */
function areaRiskLevelsHtml(sections: CarePlanSection[]): string {
  const order: Record<string, number> = { high: 0, medium: 1, low: 2 };
  const withRisk = sections.filter((s) => s.risk).sort((a, b) => (order[a.risk ?? ""] ?? 3) - (order[b.risk ?? ""] ?? 3));
  if (withRisk.length === 0) return "";
  const rows = withRisk
    .map(
      (s) => `<tr>
        <td><b>${esc(areaLabel(s.area))}</b><br><small>${esc(s.identified_need)}</small></td>
        <td><span class="rating ${esc(s.risk)}">${esc(s.risk?.toUpperCase())}</span></td>
        <td>${multiline(s.equipment ? `${s.intervention} (${s.equipment})` : s.intervention)}</td>
      </tr>`,
    )
    .join("");
  return `<h3>Care plan area risk levels</h3><table class="grid"><thead><tr><th>Risk</th><th>Level</th><th>Control</th></tr></thead><tbody>${rows}</tbody></table>`;
}

function goalsHtml(sections: CarePlanSection[]): string {
  if (sections.length === 0) return "<p>No goals recorded.</p>";
  const rows = sections.map((s) => `<tr><td>${esc(areaLabel(s.area))}</td><td>${multiline(s.goal)}</td></tr>`).join("");
  return `<table class="grid"><thead><tr><th>Area</th><th>Goal / desired outcome</th></tr></thead><tbody>${rows}</tbody></table>`;
}

const SIGNATURES_HTML = `
  <h2>Agreement</h2>
  <table class="grid signatures">
    <thead><tr><th>Role</th><th>Name</th><th>Signature</th><th>Date</th></tr></thead>
    <tbody>
      <tr><td>Service user / representative</td><td></td><td></td><td></td></tr>
      <tr><td>Assessor / care coordinator</td><td></td><td></td><td></td></tr>
      <tr><td>Registered manager</td><td></td><td></td><td></td></tr>
    </tbody>
  </table>`;

export function buildCarePlanHtml(
  plan: CarePlan,
  serviceUser: ServiceUser | undefined,
  scope: CarePlanExportScope = { kind: "full" },
): string {
  const name = serviceUser ? `${serviceUser.first_name} ${serviceUser.last_name}` : `Service user #${plan.service_user_id}`;
  const assessments = plan.risk_assessments ?? [];
  const general = assessments.filter((ra) => ra.type === "general");
  const medication = assessments.filter((ra) => ra.type === "medication");
  const title = scopeTitle(scope);

  const profileRows = serviceUser
    ? [
        row("Name", esc(name + (serviceUser.preferred_name ? ` (prefers "${serviceUser.preferred_name}")` : ""))),
        row("Date of birth", esc(serviceUser.date_of_birth ?? "—")),
        row("NHS number", esc(serviceUser.nhs_number ?? "—")),
        row("Address", multiline(serviceUser.address)),
        row("Allergies", esc(serviceUser.allergies?.join(", ") || (serviceUser.no_known_allergies ? "No known allergies" : "Not recorded"))),
        row("Diagnoses", esc(serviceUser.diagnoses?.join(", ") || "—")),
        row("Communication needs", multiline(serviceUser.communication_needs)),
        row("Capacity & consent", multiline(serviceUser.capacity_consent_notes)),
      ].join("")
    : row("Name", esc(name));

  const aboutHtml = `<h2>About the person</h2><table class="kv">${profileRows}</table>`;

  let body: string;
  switch (scope.kind) {
    case "home-care-plan":
      body = `${aboutHtml}<h2>Home care plan</h2>${homeCarePlanHtml(plan.home_care_plan)}${SIGNATURES_HTML}`;
      break;
    case "area": {
      const sections = plan.sections.filter((s) => s.area === scope.area);
      body = `${aboutHtml}<h2>${esc(areaLabel(scope.area))}</h2>${sections.map(sectionHtml).join("") || "<p>No sections recorded.</p>"}${SIGNATURES_HTML}`;
      break;
    }
    case "risks":
      body = `${aboutHtml}<h2>Risk assessment and management</h2>${generalRisksHtml(general)}${areaRiskLevelsHtml(plan.sections)}${SIGNATURES_HTML}`;
      break;
    case "medication-risk":
      body = `${aboutHtml}<h2>Medication risk assessment</h2>${medicationRisksHtml(medication)}${SIGNATURES_HTML}`;
      break;
    case "goals":
      body = `<h2>Goals</h2>${goalsHtml(plan.sections)}`;
      break;
    default:
      body = `
  ${aboutHtml}
  ${plan.notes ? `<h3>Plan notes</h3><p>${multiline(plan.notes)}</p>` : ""}

  <h2>Home care plan</h2>
  ${homeCarePlanHtml(plan.home_care_plan)}

  <h2>Care and support needs</h2>
  ${plan.sections.map(sectionHtml).join("") || "<p>No sections recorded.</p>"}

  <h2>Risk assessment and management</h2>
  ${generalRisksHtml(general)}

  <h2>Medication risk assessment</h2>
  ${medication.length ? `${riskSummaryTable(medication)}${medication.map(riskAssessmentHtml).join("")}` : "<p>No medication risk assessments recorded.</p>"}
  ${SIGNATURES_HTML}`;
  }

  return htmlDocument(
    `${title} — ${name} — v${plan.version}`,
    `
  <h1>${esc(title)} — ${esc(name)}</h1>
  <p class="meta">
    Care plan version ${plan.version} · ${plan.status === "active" ? "Current" : "Archived"} · Effective from ${esc(plan.effective_from)}
    ${plan.created_by_name ? ` · Prepared by ${esc(plan.created_by_name)}` : ""} · Printed ${esc(todayIso())}
  </p>
  ${body}`,
  );
}

function fileBaseName(plan: CarePlan, serviceUser: ServiceUser | undefined, scope: CarePlanExportScope): string {
  const who = serviceUser ? `${serviceUser.first_name}-${serviceUser.last_name}` : `service-user-${plan.service_user_id}`;
  return fileSlug(`${scopeTitle(scope)}-${who}-v${plan.version}`);
}

export function printCarePlan(plan: CarePlan, serviceUser: ServiceUser | undefined, scope: CarePlanExportScope = { kind: "full" }): void {
  printHtmlDocument(buildCarePlanHtml(plan, serviceUser, scope), fileBaseName(plan, serviceUser, scope));
}

export function downloadCarePlanWord(
  plan: CarePlan,
  serviceUser: ServiceUser | undefined,
  scope: CarePlanExportScope = { kind: "full" },
): void {
  downloadWordDocument(buildCarePlanHtml(plan, serviceUser, scope), fileBaseName(plan, serviceUser, scope));
}
