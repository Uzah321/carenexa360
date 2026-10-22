import { formatDate } from "../../lib/preferences";
import type {
  AllergySeverity,
  AllergyType,
  MedicalHistoryCategory,
  MedicalHistoryEntry,
  MedicalHistoryStatus,
  ResuscitationStatus,
  SmokingStatus,
} from "../../lib/types";

/**
 * How each kind of medical history entry is recorded and shown: which
 * optional fields it has and what they're called for that category. A
 * field with no label isn't recorded for the category.
 *
 * Field set mirrors App\Modules\MedicalHistory\Models\MedicalHistoryEntry::FIELDS.
 */
export interface CategoryConfig {
  title: string;
  /** Title case, for "Add Condition", "Edit Condition". */
  noun: string;
  emptyMessage: string;
  nameLabel: string;
  /** Reference Data list offered as suggestions for the name. */
  suggestions?: "medical_conditions" | "allergens" | "vaccines";
  statuses?: readonly MedicalHistoryStatus[];
  /** Allergy type, severity and reaction. */
  isAllergy?: boolean;
  relationshipLabel?: string;
  startedLabel?: string;
  endedLabel?: string;
  /** End date only applies once the entry is resolved (vs always, e.g. a discharge date). */
  endedWhenResolved?: boolean;
  providerLabel?: string;
  notesLabel: string;
}

export const CATEGORY_CONFIG: Record<MedicalHistoryCategory, CategoryConfig> = {
  allergy: {
    title: "Allergies & Adverse Reactions",
    noun: "Allergy",
    emptyMessage: "No allergies recorded.",
    nameLabel: "Allergen",
    suggestions: "allergens",
    statuses: ["active", "resolved"],
    isAllergy: true,
    startedLabel: "Identified",
    notesLabel: "Notes",
  },
  condition: {
    title: "Conditions & Diagnoses",
    noun: "Condition",
    emptyMessage: "No conditions recorded.",
    nameLabel: "Condition",
    suggestions: "medical_conditions",
    statuses: ["active", "in_remission", "resolved"],
    startedLabel: "Diagnosed",
    endedLabel: "Resolved",
    endedWhenResolved: true,
    providerLabel: "Diagnosed by / where",
    notesLabel: "How it affects their care",
  },
  mental_health: {
    title: "Mental Health",
    noun: "Mental Health Condition",
    emptyMessage: "No mental health history recorded.",
    nameLabel: "Condition",
    suggestions: "medical_conditions",
    statuses: ["active", "in_remission", "resolved"],
    startedLabel: "Diagnosed",
    endedLabel: "Resolved",
    endedWhenResolved: true,
    providerLabel: "Diagnosed by / where",
    notesLabel: "How it affects their care",
  },
  disability: {
    title: "Disabilities & Impairments",
    noun: "Disability",
    emptyMessage: "No disabilities or impairments recorded.",
    nameLabel: "Disability or impairment",
    startedLabel: "Since",
    notesLabel: "Support needed",
  },
  procedure: {
    title: "Operations & Procedures",
    noun: "Procedure",
    emptyMessage: "No operations or procedures recorded.",
    nameLabel: "Operation or procedure",
    startedLabel: "Date",
    providerLabel: "Hospital",
    notesLabel: "Outcome",
  },
  admission: {
    title: "Hospital Admissions",
    noun: "Hospital Admission",
    emptyMessage: "No hospital admissions recorded.",
    nameLabel: "Reason for admission",
    startedLabel: "Admitted",
    endedLabel: "Discharged",
    providerLabel: "Hospital / ward",
    notesLabel: "Outcome and follow-up",
  },
  immunisation: {
    title: "Immunisations",
    noun: "Immunisation",
    emptyMessage: "No immunisations recorded.",
    nameLabel: "Vaccine",
    suggestions: "vaccines",
    startedLabel: "Date given",
    providerLabel: "Given by / where",
    notesLabel: "Notes",
  },
  family_history: {
    title: "Family History",
    noun: "Family History",
    emptyMessage: "No family history recorded.",
    nameLabel: "Condition",
    suggestions: "medical_conditions",
    relationshipLabel: "Family member",
    notesLabel: "Notes",
  },
};

/** Top to bottom on the Medical History tab and in the printout. */
export const CATEGORY_ORDER: MedicalHistoryCategory[] = [
  "allergy",
  "condition",
  "mental_health",
  "disability",
  "procedure",
  "admission",
  "immunisation",
  "family_history",
];

export const STATUS_LABELS: Record<MedicalHistoryStatus, string> = {
  active: "Active",
  in_remission: "In remission",
  resolved: "Resolved",
};

export const STATUS_TONE: Record<MedicalHistoryStatus, "warning" | "info" | "neutral"> = {
  active: "warning",
  in_remission: "info",
  resolved: "neutral",
};

export const SEVERITY_LABELS: Record<AllergySeverity, string> = {
  mild: "Mild",
  moderate: "Moderate",
  severe: "Severe",
  life_threatening: "Life-threatening (anaphylaxis)",
};

export const SEVERITY_TONE: Record<AllergySeverity, "info" | "warning" | "danger"> = {
  mild: "info",
  moderate: "warning",
  severe: "danger",
  life_threatening: "danger",
};

export const ALLERGY_TYPE_LABELS: Record<AllergyType, string> = {
  medication: "Medication",
  food: "Food",
  environmental: "Environmental",
  other: "Other",
};

export const SMOKING_LABELS: Record<SmokingStatus, string> = {
  never: "Never smoked",
  former: "Ex-smoker",
  current: "Current smoker",
};

export const RESUSCITATION_LABELS: Record<ResuscitationStatus, string> = {
  for_resuscitation: "For resuscitation (CPR)",
  dnacpr: "DNACPR in place",
};

export const FAMILY_RELATIONSHIPS = ["Mother", "Father", "Sister", "Brother", "Grandparent", "Son", "Daughter"] as const;

export type EntryColumnKey =
  | "name"
  | "allergy_type"
  | "severity"
  | "reaction"
  | "status"
  | "relationship"
  | "started_on"
  | "ended_on"
  | "provider"
  | "notes";

export interface EntryColumn {
  key: EntryColumnKey;
  label: string;
  /** Plain-text value, "—" when blank — the tab swaps in badges for status and severity. */
  text: (entry: MedicalHistoryEntry) => string;
}

const orDash = (value: string | null | undefined) => (value && value.trim() ? value : "—");

/** The columns a category's table shows, on screen and in the printout. */
export function entryColumns(config: CategoryConfig): EntryColumn[] {
  const columns: EntryColumn[] = [{ key: "name", label: config.nameLabel, text: (e) => e.name }];

  if (config.isAllergy) {
    columns.push(
      { key: "allergy_type", label: "Type", text: (e) => (e.allergy_type ? ALLERGY_TYPE_LABELS[e.allergy_type] : "—") },
      { key: "severity", label: "Severity", text: (e) => (e.severity ? SEVERITY_LABELS[e.severity] : "Not recorded") },
      { key: "reaction", label: "Reaction", text: (e) => orDash(e.reaction) },
    );
  }
  if (config.statuses) {
    columns.push({ key: "status", label: "Status", text: (e) => (e.status ? STATUS_LABELS[e.status] : "—") });
  }
  if (config.relationshipLabel) {
    columns.push({ key: "relationship", label: config.relationshipLabel, text: (e) => orDash(e.relationship) });
  }
  if (config.startedLabel) {
    columns.push({ key: "started_on", label: config.startedLabel, text: (e) => formatDate(e.started_on) });
  }
  if (config.endedLabel) {
    columns.push({ key: "ended_on", label: config.endedLabel, text: (e) => formatDate(e.ended_on) });
  }
  if (config.providerLabel) {
    columns.push({ key: "provider", label: config.providerLabel, text: (e) => orDash(e.provider) });
  }
  columns.push({ key: "notes", label: config.notesLabel, text: (e) => orDash(e.notes) });

  return columns;
}

/** Current (not resolved) entries first, then most recent. The API already sends newest first. */
export function sortEntries(entries: MedicalHistoryEntry[]): MedicalHistoryEntry[] {
  return [...entries].sort((a, b) => Number(a.status === "resolved") - Number(b.status === "resolved"));
}
