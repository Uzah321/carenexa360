import type { EffectiveTenantSettings } from "../modules/settings/defaults";

/** What's sent when saving — any subset. The API returns the full set with defaults filled in. */
export type TenantSettings = Partial<Omit<EffectiveTenantSettings, "care_pathway" | "reference_data">> & {
  care_pathway?: Partial<EffectiveTenantSettings["care_pathway"]>;
  reference_data?: Partial<EffectiveTenantSettings["reference_data"]>;
};

export interface Tenant {
  id: number;
  name: string;
  slug: string;
  country: string;
  timezone: string;
  currency: string;
  locale: string;
  plan: string;
  status: "active" | "suspended" | "trial";
  /** Saved values over defaults — the API always sends the full set. */
  settings: EffectiveTenantSettings;
  created_at: string;
}

export interface Branch {
  id: number;
  tenant_id: number;
  name: string;
  country: string;
  region: string | null;
  address: string | null;
  status: "active" | "inactive";
  created_at: string;
}

export interface Department {
  id: number;
  branch_id: number;
  tenant_id: number;
  name: string;
  created_at: string;
}

export interface User {
  id: number;
  tenant_id: number | null;
  name: string;
  email: string;
  status: "active" | "inactive";
  mfa_enabled: boolean;
  roles: string[];
  permissions: string[];
  /** The organisation's preferences — null for platform admins. */
  tenant: {
    id: number;
    name: string;
    country: string;
    timezone: string;
    currency: string;
    locale: string;
    settings: EffectiveTenantSettings;
  } | null;
}

export interface AuditLogEntry {
  id: number;
  tenant_id: number | null;
  user_id: number | null;
  user_name: string | null;
  action: "created" | "updated" | "deleted";
  auditable_type: string;
  auditable_id: number;
  /** "Client", "Medication"… */
  record_label: string;
  /** Which one, by name — "Metformin 500mg — Ruth Chikafu". */
  record_name: string;
  /** Where to find it in the app; null once deleted. */
  record_link: string | null;
  record_exists: boolean;
  changed_fields: string[];
  old_values: Record<string, unknown> | null;
  new_values: Record<string, unknown> | null;
  ip_address: string | null;
  user_agent: string | null;
  /** "Chrome on Windows". */
  device: string | null;
  created_at: string;
}

export interface AuditLogChange {
  field: string;
  label: string;
  before: unknown;
  after: unknown;
  /** A name for an id field (care_manager_id 5 → "Tendai Moyo"). */
  before_display: string | null;
  after_display: string | null;
}

export interface AuditLogDetail extends AuditLogEntry {
  user_email: string | null;
  user_roles: string[];
  changes: AuditLogChange[];
}

export interface Paginated<T> {
  data: T[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export interface ServiceUser {
  id: number;
  tenant_id: number;
  branch_id: number | null;
  care_manager_id: number | null;
  first_name: string;
  last_name: string;
  preferred_name: string | null;
  date_of_birth: string | null;
  nhs_number: string | null;
  gender: string | null;
  language: string | null;
  phone: string | null;
  email: string | null;
  address: string | null;
  latitude: number | null;
  longitude: number | null;
  funding_source: string | null;
  status: "active" | "inactive" | "discharged";
  allergies: string[];
  diagnoses: string[];
  medical_conditions: string[];
  disabilities: string[];
  mobility_notes: string | null;
  communication_needs: string | null;
  dietary_needs: string | null;
  cultural_preferences: string | null;
  religious_requirements: string | null;
  behavioural_considerations: string | null;
  preferred_routines: string | null;
  capacity_consent_notes: string | null;
  referring_hospital: string | null;
  hospital_record_number: string | null;
  discharge_date: string | null;
  discharge_summary: string | null;
  carers?: { id: number; name: string }[];
  created_at: string;
}

export const SERVICE_USER_CONTACT_TYPES = [
  "emergency_contact",
  "next_of_kin",
  "gp",
  "pharmacy",
  "legal_representative",
  "family",
] as const;

export type ServiceUserContactType = (typeof SERVICE_USER_CONTACT_TYPES)[number];

export interface ServiceUserContact {
  id: number;
  service_user_id: number;
  user_id: number | null;
  has_portal_access: boolean;
  type: ServiceUserContactType;
  name: string;
  relationship: string | null;
  phone: string | null;
  email: string | null;
  address: string | null;
  notes: string | null;
}

export const CARE_PLAN_AREAS = [
  "personal_care",
  "mobility",
  "nutrition",
  "hydration",
  "medication",
  "communication",
  "mental_wellbeing",
  "behaviour",
  "social_activities",
  "sleep",
  "continence",
  "skin_integrity",
  "pain_management",
  "respiratory_care",
  "diabetes_management",
  "falls_prevention",
  "end_of_life_care",
  "safeguarding",
  "daily_living",
  "rehabilitation",
] as const;

export type CarePlanArea = (typeof CARE_PLAN_AREAS)[number];

export const CARE_PLAN_RISK_LEVELS = ["low", "medium", "high"] as const;
export type CarePlanRiskLevel = (typeof CARE_PLAN_RISK_LEVELS)[number];

export interface CarePlanSection {
  id: number;
  area: CarePlanArea;
  identified_need: string;
  risk: CarePlanRiskLevel | null;
  goal: string;
  intervention: string;
  equipment: string | null;
  frequency: string | null;
  responsible_staff_id: number | null;
  responsible_staff_name?: string | null;
  start_date: string | null;
  review_date: string | null;
  status: "ongoing" | "met" | "discontinued";
  notes: string | null;
}

export interface CarePlan {
  id: number;
  service_user_id: number;
  version: number;
  status: "active" | "archived";
  effective_from: string;
  created_by: number | null;
  created_by_name?: string | null;
  notes: string | null;
  home_care_plan: HomeCarePlan | null;
  sections: CarePlanSection[];
  risk_assessments?: CarePlanRiskAssessment[];
  created_at: string;
}

// The narrative "Home Care Plan" on each version. Mirrors
// api/app/Modules/CarePlanning/Support/HomeCarePlan.php.
export const HOME_CARE_PLAN_NEED_AREAS = [
  "personal_care",
  "continence",
  "mobility",
  "meals",
  "medication",
  "support",
  "other_support",
  "advance_support",
  "final_days",
] as const;
export type HomeCarePlanNeedArea = (typeof HOME_CARE_PLAN_NEED_AREAS)[number];

export const HOME_CARE_PLAN_SUMMARIES = [
  "personal_needs",
  "meal_requirements",
  "dietary_needs",
  "household_support",
  "continence",
  "medication",
  "mobility",
  "mobility_aids",
  "other_support_needs",
] as const;
export type HomeCarePlanSummaryKey = (typeof HOME_CARE_PLAN_SUMMARIES)[number];

export interface HomeCarePlanNeed {
  /** Rich text (sanitised HTML). */
  details: string | null;
  /** null = consent not recorded. */
  consented: boolean | null;
}

export interface HomeCarePlan {
  about_me?: string | null;
  desired_outcomes?: string | null;
  goals_and_outcomes?: string | null;
  cognitive_impairment_summary?: string | null;
  cognitive_impairment?: string | null;
  needs?: Partial<Record<HomeCarePlanNeedArea, HomeCarePlanNeed>>;
  summaries?: Partial<Record<HomeCarePlanSummaryKey, string>>;
}

export const RISK_TYPES = [
  "falls",
  "moving_and_handling",
  "pressure_ulcers",
  "choking",
  "nutrition_and_hydration",
  "medication",
  "infection_control",
  "environment",
  "fire",
  "self_neglect",
  "behaviour_that_challenges",
  "wandering",
  "self_harm",
  "safeguarding_and_abuse",
  "financial",
  "lone_working",
  "equipment",
  "other",
] as const;
export type RiskType = (typeof RISK_TYPES)[number];

export const RISK_ASSESSMENT_TYPES = ["general", "medication"] as const;
export type RiskAssessmentType = (typeof RISK_ASSESSMENT_TYPES)[number];

export const PERSONS_AT_RISK = [
  "service_user",
  "care_staff",
  "family_members",
  "other_household_members",
  "visitors",
  "members_of_public",
] as const;
export type PersonAtRisk = (typeof PERSONS_AT_RISK)[number];

export const MEDICATION_SUPPORT_LEVELS = ["self_administers", "prompt", "assist", "administer"] as const;
export type MedicationSupportLevel = (typeof MEDICATION_SUPPORT_LEVELS)[number];

export interface MedicationRiskDetails {
  medication_name?: string | null;
  dose_route_frequency?: string | null;
  support_level?: MedicationSupportLevel | null;
  capacity_and_consent?: string | null;
  storage?: string | null;
  controlled_drug?: boolean | null;
  prn?: boolean | null;
  prn_protocol?: string | null;
  side_effects_to_monitor?: string | null;
  known_allergies?: string | null;
  ordering_and_collection?: string | null;
  disposal?: string | null;
  error_response?: string | null;
}

export interface CarePlanRiskAssessment {
  id: number;
  type: RiskAssessmentType;
  area: CarePlanArea | null;
  risk_type: RiskType | null;
  hazard: string;
  /** Rich text (sanitised HTML). */
  details: string | null;
  triggers: string | null;
  persons_at_risk: PersonAtRisk[];
  harm_description: string | null;
  likelihood: number | null;
  severity: number | null;
  risk_score: number | null;
  existing_controls: string | null;
  further_actions: string | null;
  residual_likelihood: number | null;
  residual_severity: number | null;
  residual_risk_score: number | null;
  /** The score the plan is aiming for. */
  target_likelihood: number | null;
  target_severity: number | null;
  target_risk_score: number | null;
  contingency_plan_required: boolean;
  /** Rich text (sanitised HTML). */
  contingency_plan: string | null;
  action_owner_id: number | null;
  action_owner_name?: string | null;
  action_due_date: string | null;
  review_date: string | null;
  medication_details: MedicationRiskDetails | null;
}

export const ASSESSMENT_FIELD_TYPES = [
  "text",
  "textarea",
  "number",
  "date",
  "select",
  "checkbox",
  "score",
] as const;

export type AssessmentFieldType = (typeof ASSESSMENT_FIELD_TYPES)[number];

export interface AssessmentField {
  key: string;
  label: string;
  type: AssessmentFieldType;
  options?: string[];
  required?: boolean;
}

export interface AssessmentTemplate {
  id: number;
  name: string;
  category: string | null;
  description: string | null;
  fields: AssessmentField[];
  is_active: boolean;
  created_at: string;
}

export interface AssessmentResponse {
  id: number;
  service_user_id: number;
  assessment_template_id: number;
  template_name?: string | null;
  answers: Record<string, unknown>;
  completed_by: number | null;
  completed_by_name?: string | null;
  completed_at: string | null;
  status: "draft" | "completed";
  archived_at: string | null;
}

export interface CareDocument {
  id: number;
  category: string | null;
  original_filename: string;
  mime_type: string | null;
  size: number;
  version: number;
  uploaded_by: number | null;
  uploaded_by_name?: string | null;
  expiry_date: string | null;
  visible_to_family: boolean;
  created_at: string;
}

export interface CareNote {
  id: number;
  caption: string | null;
  mime_type: string | null;
  size: number;
  duration_seconds: number | null;
  visit_id: number | null;
  author_id: number;
  author_name?: string | null;
  created_at: string;
}

// Mirrors App\Modules\CareNotes\Http\Controllers\CareNoteController::CAN_MODERATE
// on the backend — who can delete a care note someone else recorded.
export const CARE_NOTE_MODERATOR_ROLES = [
  "Organization Owner",
  "Organization Admin",
  "Branch Manager",
  "Care Manager",
] as const;

// Mirrors App\Modules\Identity\Support\DefaultRoles::TENANT_ROLES on the
// backend — every tenant is auto-seeded with exactly these roles (see
// TenantObserver), and custom roles aren't supported yet, so it's safe to
// hardcode the list here rather than adding a "list this tenant's roles"
// endpoint just for a select dropdown.
export const TENANT_ROLES = [
  "Organization Owner",
  "Organization Admin",
  "Branch Manager",
  "Care Manager",
  "Care Coordinator",
  "Nurse",
  "Senior Carer",
  "Carer / Support Worker",
  "Doctor",
  "Therapist",
  "Pharmacist",
  "Finance Officer",
  "HR Officer",
  "Compliance Officer",
  "Receptionist",
  "Family Member",
  "Service User / Patient",
  "Auditor",
] as const;

// Family Member / Service User portal accounts are excluded — assigning
// either to an actual staff member would break their access (StaffOnly
// middleware blocks Family Member from every internal screen) rather than
// just change what they can do, so they don't belong in this picker even
// though the backend's TENANT_ROLES validation itself doesn't forbid it.
export const STAFF_ASSIGNABLE_ROLES = TENANT_ROLES.filter(
  (role) => role !== "Family Member" && role !== "Service User / Patient",
);

export interface UserRoleAssignment {
  id: number;
  name: string;
  email: string;
  status: "active" | "inactive";
  job_title: string | null;
  role: string | null;
}

export interface StaffMember {
  id: number;
  user_id: number;
  name: string;
  email: string;
  roles: string[];
  branch_id: number | null;
  job_title: string | null;
  skills: string[];
  employment_status: "active" | "on_leave" | "inactive";
  /** HR-sensitive — only present in the API response for STAFF_ROLES. */
  employee_number?: string | null;
  employment_start_date?: string | null;
  hourly_rate?: string | null;
  created_at: string;
}

export const VISIT_STATUSES = ["scheduled", "in_progress", "completed", "missed", "cancelled"] as const;
export type VisitStatus = (typeof VISIT_STATUSES)[number];

export const VISIT_PRIORITIES = ["low", "medium", "high"] as const;
export type VisitPriority = (typeof VISIT_PRIORITIES)[number];

export interface Visit {
  id: number;
  service_user_id: number;
  service_user_name?: string | null;
  carer_id: number | null;
  carer_name?: string | null;
  visit_date: string;
  start_time: string;
  end_time: string;
  care_tasks: string[];
  completed_care_tasks: string[];
  medication_tasks: boolean;
  medication_tasks_completed: boolean;
  required_skills: string[];
  priority: VisitPriority;
  status: VisitStatus;
  notes: string | null;
  check_in_at: string | null;
  check_in_lat: number | null;
  check_in_lng: number | null;
  check_out_at: string | null;
  check_out_lat: number | null;
  check_out_lng: number | null;
  override_reason: string | null;
  created_at: string;
}

export interface RouteStop {
  visit_id: number;
  label: string;
  start_time: string;
  latitude: number;
  longitude: number;
}

export const SHIFT_TYPES = ["day", "night", "split"] as const;
export type ShiftType = (typeof SHIFT_TYPES)[number];

export const SHIFT_STATUSES = ["scheduled", "confirmed", "completed", "cancelled"] as const;
export type ShiftStatus = (typeof SHIFT_STATUSES)[number];

export interface Shift {
  id: number;
  user_id: number;
  user_name?: string | null;
  branch_id: number | null;
  shift_date: string;
  start_time: string;
  end_time: string;
  shift_type: ShiftType;
  status: ShiftStatus;
  notes: string | null;
  created_at: string;
}

export const MEDICATION_ADMINISTRATION_STATUSES = [
  "administered",
  "refused",
  "missed",
  "not_available",
  "hospitalized",
  "self_administered",
  "prn",
  "not_given",
] as const;
export type MedicationAdministrationStatus = (typeof MEDICATION_ADMINISTRATION_STATUSES)[number];

/** Why a dose wasn't given — required when status is "not_given". Mirrors MedicationAdministration::NOT_GIVEN_REASONS. */
export const MEDICATION_NOT_GIVEN_REASONS = [
  "refused",
  "unwell",
  "hospitalised",
  "social_leave",
  "medication_not_available",
  "client_cancelled",
  "self_administered",
  "administered_by_family",
  "prn_not_required",
  "given_by_other_carer",
] as const;
export type MedicationNotGivenReason = (typeof MEDICATION_NOT_GIVEN_REASONS)[number];

export interface MedicationAdministration {
  id: number;
  medication_id: number;
  visit_id: number | null;
  status: MedicationAdministrationStatus;
  /** The schedule slot ("19:30") this record answers for, if any. */
  scheduled_time: string | null;
  not_given_reason: MedicationNotGivenReason | null;
  stock_checked: boolean | null;
  administered_at: string | null;
  administered_by: number | null;
  administered_by_name?: string | null;
  witness_id: number | null;
  witness_name?: string | null;
  notes: string | null;
  created_at: string;
}

export interface Medication {
  id: number;
  service_user_id: number;
  name: string;
  strength: string | null;
  form: string | null;
  dose: string;
  route: string;
  frequency: string;
  schedule: string[];
  start_date: string;
  end_date: string | null;
  prescriber: string | null;
  pharmacy: string | null;
  instructions: string | null;
  is_prn: boolean;
  prn_instructions: string | null;
  is_controlled_drug: boolean;
  /** null = stock isn't tracked for this medication. */
  stock_on_hand: number | null;
  reorder_level: number | null;
  units_per_dose: number;
  days_of_stock_left: number | null;
  needs_reorder: boolean;
  status: "active" | "discontinued";
  archived_at: string | null;
  created_by: number | null;
  administrations?: MedicationAdministration[];
  /** Today's records, for the medication round. Included on the service user's medication list. */
  today_administrations?: MedicationAdministration[];
  created_at: string;
}

export const OBSERVATION_TYPES = [
  "blood_pressure",
  "pulse",
  "temperature",
  "blood_glucose",
  "oxygen_saturation",
  "respiratory_rate",
  "weight",
  "height",
  "bmi",
  "pain_score",
  "fluid_intake",
  "urine_output",
  "bowel_movement",
  "sleep",
  "mood",
  "news2",
  "wound",
] as const;
export type ObservationType = (typeof OBSERVATION_TYPES)[number];

export type News2Direction = "low" | "high" | "normal" | "abnormal";
export type News2Risk = "none" | "low" | "low_medium" | "medium" | "high";

export interface News2Parameter {
  parameter: string;
  label: string;
  reading: string;
  score: number;
  direction: News2Direction;
}

export interface News2Assessment {
  total: number;
  risk: News2Risk;
  single_parameter_3: boolean;
  response: string;
  parameters: News2Parameter[];
}

export interface ClinicalAlert {
  id: number;
  service_user_id: number;
  observation_id: number;
  message: string;
  severity: "warning" | "critical";
  acknowledged_at: string | null;
  acknowledged_by: number | null;
  acknowledged_by_name?: string | null;
  created_at: string;
}

export interface Observation {
  id: number;
  service_user_id: number;
  visit_id: number | null;
  type: ObservationType;
  value: Record<string, number | string | boolean>;
  unit: string | null;
  recorded_by: number | null;
  recorded_by_name?: string | null;
  recorded_at: string;
  notes: string | null;
  archived_at: string | null;
  news2?: News2Assessment | null;
  /** 0–3 scores for measurements outside NEWS2 (diastolic BP, glucose) — not part of the NEWS2 total. */
  range_scores?: News2Parameter[];
  alerts?: ClinicalAlert[];
  created_at: string;
}

export const INCIDENT_TYPES = [
  "fall",
  "medication_error",
  "injury",
  "behavioural",
  "missing_person",
  "property_damage",
  "staff_injury",
  "infection",
  "hospital_admission",
  "other",
] as const;
export type IncidentType = (typeof INCIDENT_TYPES)[number];

export const INCIDENT_SEVERITIES = ["low", "medium", "high", "critical"] as const;
export type IncidentSeverity = (typeof INCIDENT_SEVERITIES)[number];

export const INCIDENT_STATUSES = [
  "reported",
  "investigating",
  "corrective_action",
  "reviewed",
  "closed",
] as const;
export type IncidentStatus = (typeof INCIDENT_STATUSES)[number];

export interface Incident {
  id: number;
  service_user_id: number | null;
  service_user_name?: string | null;
  type: IncidentType;
  severity: IncidentSeverity;
  description: string;
  immediate_action: string | null;
  status: IncidentStatus;
  reported_by: number | null;
  reported_by_name?: string | null;
  assigned_to: number | null;
  assigned_to_name?: string | null;
  investigation_notes: string | null;
  corrective_actions: string | null;
  reviewed_by: number | null;
  reviewed_at: string | null;
  closed_at: string | null;
  archived_at: string | null;
  created_at: string;
}

export const SAFEGUARDING_CASE_STATUSES = ["reported", "investigating", "actions_taken", "closed"] as const;
export type SafeguardingCaseStatus = (typeof SAFEGUARDING_CASE_STATUSES)[number];

// Mirrors App\Modules\Safeguarding\Support\SafeguardingRoles::ALLOWED on the
// backend — used to hide the Safeguarding nav item and page from users who
// hold none of these roles (the backend re-checks this on every request;
// this is a UI convenience, not the actual access boundary).
export const SAFEGUARDING_ROLES = [
  "Organization Owner",
  "Organization Admin",
  "Care Manager",
  "Compliance Officer",
  "Auditor",
] as const;

export interface SafeguardingCase {
  id: number;
  service_user_id: number | null;
  service_user_name?: string | null;
  victim_name: string | null;
  alleged_perpetrator: string | null;
  concern_type: string;
  immediate_risk: boolean;
  external_agencies_notified: string | null;
  investigation_notes: string | null;
  actions_taken: string | null;
  outcome: string | null;
  status: SafeguardingCaseStatus;
  reported_by: number | null;
  reported_by_name?: string | null;
  confidential_notes: string | null;
  created_at: string;
}

export const FUNDER_TYPES = ["local_authority", "nhs", "private", "insurance", "self_funded"] as const;
export type FunderType = (typeof FUNDER_TYPES)[number];

export interface Funder {
  id: number;
  name: string;
  type: FunderType;
  contact_name: string | null;
  phone: string | null;
  email: string | null;
  address: string | null;
  default_hourly_rate: string | null;
  notes: string | null;
  status: "active" | "inactive";
  created_at: string;
}

export const INVOICE_STATUSES = ["draft", "sent", "paid", "overdue", "cancelled"] as const;
export type InvoiceStatus = (typeof INVOICE_STATUSES)[number];

export interface InvoiceLineItem {
  id: number;
  invoice_id: number;
  visit_id: number | null;
  description: string;
  quantity: string;
  unit_rate: string;
  amount: string;
}

export interface Invoice {
  id: number;
  service_user_id: number;
  service_user_name?: string | null;
  funder_id: number | null;
  funder_name?: string | null;
  invoice_number: string | null;
  period_start: string;
  period_end: string;
  issue_date: string;
  due_date: string | null;
  status: InvoiceStatus;
  subtotal: string;
  tax_amount: string;
  total: string;
  currency: string;
  notes: string | null;
  line_items?: InvoiceLineItem[];
  created_at: string;
}

export interface PayPeriod {
  id: number;
  start_date: string;
  end_date: string;
  notes: string | null;
  payslips?: Payslip[];
  created_at: string;
}

/** A staff member PayslipGenerator silently excludes from every pay period
 * until someone sets their hourly rate on the Staff page. */
export interface StaffMissingHourlyRate {
  id: number;
  name: string | null;
}

export const PAYSLIP_STATUSES = ["draft", "finalized", "paid"] as const;
export type PayslipStatus = (typeof PAYSLIP_STATUSES)[number];

export interface Payslip {
  id: number;
  pay_period_id: number;
  pay_period_start?: string | null;
  pay_period_end?: string | null;
  user_id: number;
  user_name?: string | null;
  regular_hours: string;
  gross_pay: string;
  deductions: string;
  net_pay: string;
  status: PayslipStatus;
  generated_at: string | null;
}

// Mirrors App\Modules\Billing\Support\FinanceRoles / Payroll\Support\PayrollRoles
// on the backend — UI convenience for nav gating, not the access boundary itself.
export const FINANCE_ROLES = ["Organization Owner", "Organization Admin", "Finance Officer"] as const;
export const PAYROLL_ROLES = [
  "Organization Owner",
  "Organization Admin",
  "HR Officer",
  "Finance Officer",
] as const;
export const HR_ROLES = ["Organization Owner", "Organization Admin", "HR Officer"] as const;
export const COMPLIANCE_ROLES = [
  "Organization Owner",
  "Organization Admin",
  "Compliance Officer",
  "HR Officer",
] as const;

export const LEAVE_TYPES = ["annual", "sick", "unpaid", "other"] as const;
export type LeaveType = (typeof LEAVE_TYPES)[number];

export const LEAVE_STATUSES = ["pending", "approved", "rejected", "cancelled"] as const;
export type LeaveStatus = (typeof LEAVE_STATUSES)[number];

export interface LeaveRequest {
  id: number;
  user_id: number;
  user_name?: string | null;
  type: LeaveType;
  start_date: string;
  end_date: string;
  status: LeaveStatus;
  reason: string | null;
  approved_by: number | null;
  approved_by_name?: string | null;
  approved_at: string | null;
  notes: string | null;
  created_at: string;
}

export interface TrainingCourse {
  id: number;
  name: string;
  category: string | null;
  description: string | null;
  validity_period_months: number | null;
  is_mandatory: boolean;
  created_at: string;
}

export const TRAINING_RECORD_STATUSES = ["valid", "expiring_soon", "expired", "no_expiry"] as const;
export type TrainingRecordStatus = (typeof TRAINING_RECORD_STATUSES)[number];

export interface TrainingRecord {
  id: number;
  user_id: number;
  user_name?: string | null;
  training_course_id: number;
  training_course_name?: string | null;
  completed_date: string;
  expiry_date: string | null;
  status: TrainingRecordStatus;
  notes: string | null;
  recorded_by: number | null;
  recorded_by_name?: string | null;
  created_at: string;
}

// Mirrors App\Modules\Communication\Support\CommunicationRoles::ALLOWED on
// the backend — who may post an announcement (reading is open to everyone).
export const COMMUNICATION_ROLES = [
  "Organization Owner",
  "Organization Admin",
  "Branch Manager",
  "Care Manager",
] as const;

export interface Announcement {
  id: number;
  branch_id: number | null;
  branch_name?: string | null;
  title: string;
  body: string;
  posted_by: number | null;
  posted_by_name?: string | null;
  pinned: boolean;
  created_at: string;
}

export interface FamilyPortalIncident {
  id: number;
  type: string;
  severity: string;
  description: string;
  status: string;
  created_at: string;
}

export interface FamilyPortalDetail {
  service_user: ServiceUser;
  care_plan: CarePlan | null;
  upcoming_visits: Visit[];
  recent_visits: Visit[];
  documents: CareDocument[];
  incidents: FamilyPortalIncident[];
}

// Mirrors App\Modules\Analytics\Support\AnalyticsRoles::ALLOWED on the
// backend — UI convenience for nav gating, not the access boundary itself.
export const ANALYTICS_ROLES = [
  "Organization Owner",
  "Organization Admin",
  "Branch Manager",
  "Care Manager",
  "Finance Officer",
] as const;

// UI-only nav gating for the Administration category's admin-sensitive
// pages (System Settings, User Roles & Permissions) — no backend route
// exists yet for either, so there's no server-side mirror to reference.
export const ADMINISTRATION_ROLES = ["Organization Owner", "Organization Admin"] as const;

// Mirrors App\Modules\Tracking\Support\TrackingRoles::ALLOWED on the
// backend — UI convenience for nav gating, not the access boundary itself.
export const TRACKING_ROLES = [
  "Organization Owner",
  "Organization Admin",
  "Branch Manager",
  "Care Manager",
] as const;

// Mirrors App\Modules\Reports\Support\ReportRoles::ALLOWED on the backend.
export const REPORT_ROLES = [
  "Organization Owner",
  "Organization Admin",
  "Branch Manager",
  "Care Manager",
  "Compliance Officer",
  "HR Officer",
] as const;

// Mirrors App\Modules\Staff\Support\StaffRoles::ALLOWED on the backend.
export const STAFF_ROLES = [
  "Organization Owner",
  "Organization Admin",
  "Branch Manager",
  "HR Officer",
] as const;

// Roles that actually attend a service user's visit. Used to decide who gets a
// carer row on the schedule and who can be offered a visit to deliver — a
// Finance Officer or Pharmacist holds a staff profile but is never sent out on
// a care visit, so listing them as "carers with no visits" invites a nonsense
// assignment. Note this gates *suggestions* only: a visit already assigned to
// someone outside this list still gets its own row, so nothing is ever hidden.
export const VISIT_DELIVERY_ROLES = [
  "Carer / Support Worker",
  "Senior Carer",
  "Nurse",
  "Care Coordinator",
  "Care Manager",
  "Branch Manager",
] as const;

export function deliversVisits(roles: readonly string[]): boolean {
  return roles.some((role) => (VISIT_DELIVERY_ROLES as readonly string[]).includes(role));
}

// Mirrors App\Modules\Rostering\Support\RosteringRoles::ALLOWED on the backend.
export const ROSTERING_ROLES = [
  "Organization Owner",
  "Organization Admin",
  "Branch Manager",
  "Care Manager",
  "Care Coordinator",
] as const;

// Mirrors App\Modules\Audit\Support\AuditRoles::ALLOWED on the backend.
export const AUDIT_ROLES = [
  "Organization Owner",
  "Organization Admin",
  "Compliance Officer",
  "Auditor",
] as const;

// Platform-level, not a tenant role — only App\Modules\Identity\Support\
// DefaultRoles::PLATFORM_SUPER_ADMIN carries this, so a tenant-scoped user
// (any TENANT_ROLES member) never matches it.
export const PLATFORM_ADMIN_ROLES = ["Platform Super Admin"] as const;

export const COMPLIANCE_REQUIREMENT_STATUSES = ["pending", "compliant", "non_compliant"] as const;
export type ComplianceRequirementStatus = (typeof COMPLIANCE_REQUIREMENT_STATUSES)[number];

export interface ComplianceRequirement {
  id: number;
  name: string;
  category: string | null;
  jurisdiction: string | null;
  status: ComplianceRequirementStatus;
  expiry_status: TrainingRecordStatus;
  issued_date: string | null;
  renewal_date: string | null;
  reference_number: string | null;
  responsible_user_id: number | null;
  responsible_user_name?: string | null;
  notes: string | null;
  created_at: string;
}

export interface TodayStats {
  training_expiring_soon: {
    count: number;
    soonest_expiry_date: string | null;
  };
  missed_visits_this_week: number;
  open_incidents: number;
  mar_accuracy_pct: number | null;
  rota_coverage_pct: number | null;
}

export interface TodayResponse {
  date: string;
  stats: TodayStats;
  visits: Visit[];
}

export interface ClientSnapshotMedication {
  id: number;
  name: string;
  dose: string;
  latest_administration: {
    status: MedicationAdministrationStatus;
    administered_at: string | null;
  } | null;
}

export interface ClientSnapshotCarePlanSection {
  id: number;
  area: CarePlanArea;
  goal: string;
  status: "ongoing" | "met" | "discontinued";
}

export interface ClientSnapshot {
  service_user: ServiceUser;
  care_plan_sections: ClientSnapshotCarePlanSection[];
  medications: ClientSnapshotMedication[];
}

// ---- Quality: complaints and spot checks ------------------------------------

// Mirrors App\Modules\Quality\Support\QualityRoles::ALLOWED (UI gating only —
// the API re-checks every request).
export const QUALITY_ROLES = [
  "Organization Owner",
  "Organization Admin",
  "Branch Manager",
  "Care Manager",
  "Care Coordinator",
  "Compliance Officer",
  "Auditor",
] as const;

export const COMPLAINT_CHANNELS = ["phone", "email", "letter", "in_person", "online", "other"] as const;
export const COMPLAINT_CATEGORIES = [
  "quality_of_care",
  "staff_conduct",
  "timekeeping",
  "missed_visit",
  "communication",
  "medication",
  "dignity_and_respect",
  "billing",
  "other",
] as const;
export const COMPLAINT_SEVERITIES = ["low", "medium", "high"] as const;
export const COMPLAINT_STATUSES = ["received", "investigating", "resolved", "closed", "withdrawn"] as const;
export const COMPLAINT_OUTCOMES = ["upheld", "partially_upheld", "not_upheld"] as const;

export type ComplaintStatus = (typeof COMPLAINT_STATUSES)[number];

export interface Complaint {
  id: number;
  service_user_id: number | null;
  service_user_name?: string | null;
  received_date: string;
  complainant_name: string;
  complainant_relationship: string | null;
  channel: (typeof COMPLAINT_CHANNELS)[number];
  category: (typeof COMPLAINT_CATEGORIES)[number];
  severity: (typeof COMPLAINT_SEVERITIES)[number];
  description: string;
  status: ComplaintStatus;
  assigned_to: number | null;
  assigned_to_name?: string | null;
  acknowledged_date: string | null;
  response_due_date: string | null;
  is_overdue: boolean;
  outcome: (typeof COMPLAINT_OUTCOMES)[number] | null;
  findings: string | null;
  actions_taken: string | null;
  resolved_date: string | null;
  created_at: string;
}

export const SPOT_CHECK_AREAS = [
  "punctuality",
  "id_and_uniform",
  "infection_control",
  "dignity_and_privacy",
  "care_delivery",
  "moving_and_handling",
  "medication",
  "communication",
  "record_keeping",
  "safeguarding_awareness",
] as const;
export type SpotCheckArea = (typeof SPOT_CHECK_AREAS)[number];
export type SpotCheckResult = "pass" | "fail" | "na";
export type SpotCheckOutcome = "pass" | "needs_improvement" | "fail";

export interface SpotCheck {
  id: number;
  staff_user_id: number;
  staff_name?: string | null;
  checked_by: number | null;
  checked_by_name?: string | null;
  service_user_id: number | null;
  service_user_name?: string | null;
  visit_id: number | null;
  check_date: string;
  results: Partial<Record<SpotCheckArea, SpotCheckResult>>;
  outcome: SpotCheckOutcome;
  notes: string | null;
  actions_required: string | null;
  follow_up_date: string | null;
  created_at: string;
}
