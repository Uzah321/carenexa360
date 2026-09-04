export interface TenantSettings {
  geofence_radius_meters?: number;
  training_expiry_warning_days?: number;
}

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
  settings: TenantSettings;
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
  roles: string[];
  permissions: string[];
}

export interface AuditLogEntry {
  id: number;
  tenant_id: number | null;
  user_id: number | null;
  user_name: string | null;
  action: string;
  auditable_type: string;
  auditable_id: number;
  old_values: Record<string, unknown> | null;
  new_values: Record<string, unknown> | null;
  ip_address: string | null;
  user_agent: string | null;
  created_at: string;
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
  sections: CarePlanSection[];
  created_at: string;
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
] as const;
export type MedicationAdministrationStatus = (typeof MEDICATION_ADMINISTRATION_STATUSES)[number];

export interface MedicationAdministration {
  id: number;
  medication_id: number;
  visit_id: number | null;
  status: MedicationAdministrationStatus;
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
  status: "active" | "discontinued";
  created_by: number | null;
  administrations?: MedicationAdministration[];
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
] as const;
export type ObservationType = (typeof OBSERVATION_TYPES)[number];

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
  value: Record<string, number | string>;
  unit: string | null;
  recorded_by: number | null;
  recorded_by_name?: string | null;
  recorded_at: string;
  notes: string | null;
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
