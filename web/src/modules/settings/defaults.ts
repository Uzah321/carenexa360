// Mirrors App\Modules\Organization\Support\TenantSettings::DEFAULTS — the
// API always sends saved values over these, so the web only falls back to
// them before a user (and their organisation) has loaded.

export const REFERENCE_LISTS = [
  "care_tasks",
  "skills",
  "job_titles",
  "staff_document_categories",
  "client_document_categories",
  "medication_routes",
  "medication_forms",
  "equipment",
] as const;
export type ReferenceList = (typeof REFERENCE_LISTS)[number];

export interface CarePathwaySettings {
  assessment_within_days: number;
  care_plan_within_days: number;
  first_review_within_weeks: number;
  review_interval_months: number;
  risk_review_interval_months: number;
}

export interface EffectiveTenantSettings {
  geofence_radius_meters: number;
  late_arrival_minutes: number;
  overtime_weekly_hours: number;
  mileage_rate_per_mile: number;
  training_expiry_warning_days: number;
  medication_window_minutes: number;
  stock_reorder_days: number;
  complaint_response_days: number;
  session_timeout_minutes: number | null;
  care_pathway: CarePathwaySettings;
  reference_data: Record<ReferenceList, string[]>;
}

export const DEFAULT_SETTINGS: EffectiveTenantSettings = {
  geofence_radius_meters: 100,
  late_arrival_minutes: 10,
  overtime_weekly_hours: 40,
  mileage_rate_per_mile: 0.45,
  training_expiry_warning_days: 30,
  medication_window_minutes: 60,
  stock_reorder_days: 7,
  complaint_response_days: 28,
  session_timeout_minutes: null,
  care_pathway: {
    assessment_within_days: 3,
    care_plan_within_days: 7,
    first_review_within_weeks: 6,
    review_interval_months: 6,
    risk_review_interval_months: 3,
  },
  reference_data: {
    care_tasks: [
      "Personal care", "Morning wash", "Bathing/showering", "Dressing", "Continence care", "Meal preparation",
      "Medication prompt", "Medication administration", "Mobility support", "Companionship",
      "Light housekeeping", "Shopping", "Wound care", "Clinical observations",
    ],
    skills: [
      "Personal Care", "Medication Administration", "Manual Handling", "Dementia Care", "Wound Care",
      "Catheter Care", "PEG Feeding", "Diabetes Care", "End of Life Care", "Clinical Observations",
    ],
    job_titles: ["Carer", "Senior Carer", "Nurse", "Care Coordinator", "Care Manager", "Branch Manager"],
    staff_document_categories: ["DBS check", "Right to work", "Contract", "ID", "References", "Training certificate"],
    client_document_categories: ["Hospital Record", "Consent form", "Signed care plan", "Assessment", "DNACPR / ReSPECT", "Correspondence"],
    medication_routes: ["Oral", "Topical", "Inhaled", "Transdermal patch", "Subcutaneous injection", "Eye drops", "Ear drops", "Nasal", "Rectal", "PEG"],
    medication_forms: ["Tablet", "Capsule", "Liquid", "Cream", "Ointment", "Inhaler", "Patch", "Drops", "Injection"],
    equipment: ["Walking frame", "Walking stick", "Wheelchair", "Hoist", "Slide sheet", "Commode", "Shower chair", "Pressure-relieving mattress", "Gait belt"],
  },
};

export const REFERENCE_LIST_LABELS: Record<ReferenceList, { label: string; usedFor: string }> = {
  care_tasks: { label: "Care tasks", usedFor: "Offered when booking and editing visits." },
  skills: { label: "Staff skills", usedFor: "Offered on staff profiles and a visit's required skills." },
  job_titles: { label: "Job titles", usedFor: "Offered on staff profiles." },
  staff_document_categories: { label: "Staff document categories", usedFor: "Offered when uploading staff documents. Include a DBS category for the background-check report." },
  client_document_categories: { label: "Client document categories", usedFor: "Offered when uploading a client's documents." },
  medication_routes: { label: "Medication routes", usedFor: "Offered when adding a medication." },
  medication_forms: { label: "Medication forms", usedFor: "Offered when adding a medication." },
  equipment: { label: "Equipment", usedFor: "Offered on care plan sections." },
};
