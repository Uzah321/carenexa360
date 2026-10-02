import type { CarePlanRiskAssessmentInput, CarePlanRiskAssessmentPayload } from "./api";
import type {
  CarePlanRiskAssessment,
  MedicationSupportLevel,
  PersonAtRisk,
  RiskAssessmentType,
} from "../../lib/types";

// 5×5 likelihood × severity matrix — the scale most UK care providers use
// for CQC-facing risk assessments.
export const LIKELIHOOD_LABELS: Record<number, string> = {
  1: "Rare",
  2: "Unlikely",
  3: "Possible",
  4: "Likely",
  5: "Almost certain",
};

export const SEVERITY_LABELS: Record<number, string> = {
  1: "Negligible",
  2: "Minor",
  3: "Moderate",
  4: "Major",
  5: "Catastrophic",
};

export const PERSON_AT_RISK_LABELS: Record<PersonAtRisk, string> = {
  service_user: "Service user",
  care_staff: "Care staff",
  family_members: "Family members",
  other_household_members: "Other household members",
  visitors: "Visitors",
  members_of_public: "Members of the public",
};

export const MEDICATION_SUPPORT_LABELS: Record<MedicationSupportLevel, string> = {
  self_administers: "Self-administers (no support)",
  prompt: "Prompt / remind only",
  assist: "Assist (e.g. open packaging)",
  administer: "Administer (carer gives medication)",
};

// Offered as suggestions on the hazard field — staff can still type their own.
export const COMMON_MEDICATION_HAZARDS = [
  "Missed or omitted dose",
  "Wrong dose or double dose given",
  "Medication given at the wrong time",
  "Wrong medication given",
  "Refusal of medication",
  "Adverse reaction or side effects",
  "Allergic reaction",
  "Unsafe storage / accessible to others",
  "Accidental ingestion by others in the household",
  "Misuse or diversion of controlled drugs",
  "Difficulty swallowing tablets",
  "Running out of stock",
  "Confusion over self-administration",
];

export type RiskRating = "low" | "medium" | "high" | "very_high";

export function riskScore(likelihood: number | null | undefined, severity: number | null | undefined): number | null {
  return likelihood && severity ? likelihood * severity : null;
}

export function riskRating(score: number | null): RiskRating | null {
  if (!score) return null;
  if (score <= 4) return "low";
  if (score <= 9) return "medium";
  if (score <= 16) return "high";
  return "very_high";
}

export const RISK_RATING_LABELS: Record<RiskRating, string> = {
  low: "Low",
  medium: "Medium",
  high: "High",
  very_high: "Very high",
};

export const RISK_RATING_TONE: Record<RiskRating, "success" | "warning" | "danger"> = {
  low: "success",
  medium: "warning",
  high: "danger",
  very_high: "danger",
};

export function emptyRiskAssessmentInput(type: RiskAssessmentType): CarePlanRiskAssessmentInput {
  return {
    type,
    area: type === "medication" ? "medication" : "",
    hazard: "",
    persons_at_risk: ["service_user"],
    harm_description: "",
    likelihood: null,
    severity: null,
    existing_controls: "",
    further_actions: "",
    residual_likelihood: null,
    residual_severity: null,
    action_owner_id: null,
    action_due_date: "",
    review_date: "",
    medication_details: type === "medication" ? { controlled_drug: false, prn: false } : null,
  };
}

export function toRiskAssessmentInput(ra: CarePlanRiskAssessment): CarePlanRiskAssessmentInput {
  return {
    type: ra.type,
    area: ra.area ?? "",
    hazard: ra.hazard,
    persons_at_risk: ra.persons_at_risk ?? [],
    harm_description: ra.harm_description ?? "",
    likelihood: ra.likelihood,
    severity: ra.severity,
    existing_controls: ra.existing_controls ?? "",
    further_actions: ra.further_actions ?? "",
    residual_likelihood: ra.residual_likelihood,
    residual_severity: ra.residual_severity,
    action_owner_id: ra.action_owner_id,
    action_due_date: ra.action_due_date ?? "",
    review_date: ra.review_date ?? "",
    medication_details: ra.medication_details,
  };
}

// The API treats "" as a value, not as "unset" — send nulls for blank optionals.
export function normalizeRiskAssessmentInput(ra: CarePlanRiskAssessmentInput): CarePlanRiskAssessmentPayload {
  return {
    ...ra,
    area: ra.area || null,
    harm_description: ra.harm_description || null,
    existing_controls: ra.existing_controls || null,
    further_actions: ra.further_actions || null,
    action_due_date: ra.action_due_date || null,
    review_date: ra.review_date || null,
    medication_details: ra.type === "medication" ? ra.medication_details : null,
  };
}
