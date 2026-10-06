export interface ReportDef {
  label: string;
  /** Present only when the report is backed by real data and can be generated. */
  key?: string;
}

export interface ReportCategory {
  key: string;
  label: string;
  reports: ReportDef[];
}

// The full report taxonomy. Every report has a `key` wired to a backend
// handler (ReportGeneratorController / OperationalReports). A report
// without one would show as "coming soon" — keep it that way rather than
// faking data if one is ever added before its data exists.
export const REPORT_CATALOG: ReportCategory[] = [
  {
    key: "client",
    label: "Client / Service User Reports",
    reports: [
      { label: "Client profile summary", key: "client_profile_summary" },
      { label: "Care history", key: "care_history" },
      { label: "Care-plan summary", key: "care_plan_summary" },
      { label: "Review history", key: "review_history" },
      { label: "Visit history", key: "visit_history" },
      { label: "Missed visits", key: "missed_visits" },
      { label: "Daily notes", key: "daily_notes" },
      { label: "Family / contact activity", key: "family_contact_activity" },
      { label: "Admission / discharge history", key: "admission_discharge_history" },
    ],
  },
  {
    key: "care_delivery",
    label: "Care Delivery Reports",
    reports: [
      { label: "Scheduled vs completed visits", key: "visit_status_breakdown" },
      { label: "Care tasks completed / not completed", key: "care_tasks" },
      { label: "Visit duration", key: "care_hours_delivered" },
      { label: "Late visits", key: "late_visits" },
      { label: "Cancelled visits", key: "cancelled_visits" },
      { label: "Missed visits", key: "missed_visits" },
      { label: "Care hours delivered", key: "care_hours_delivered" },
      { label: "Care package utilization", key: "care_package_utilization" },
    ],
  },
  {
    key: "medication",
    label: "Medication Reports",
    reports: [
      { label: "eMAR administration report", key: "emar_administration" },
      { label: "Missed medication", key: "missed_medication" },
      { label: "Refused medication", key: "refused_medication" },
      { label: "Medication not given", key: "medication_not_given" },
      { label: "Reasons medication not given", key: "not_given_reasons" },
      { label: "Unrecorded doses (MAR gaps)", key: "unrecorded_doses" },
      { label: "Late medication", key: "late_medication" },
      { label: "PRN medication usage", key: "prn_medication_usage" },
      { label: "Medication errors", key: "medication_errors" },
      { label: "Medication stock checks", key: "stock_checks" },
      { label: "Medication stock / reorder report", key: "medication_stock" },
      { label: "Medication audit trail", key: "emar_administration" },
    ],
  },
  {
    key: "clinical",
    label: "Clinical Reports",
    reports: [
      { label: "Blood pressure trends", key: "bp_trends" },
      { label: "Glucose trends", key: "glucose_trends" },
      { label: "Oxygen saturation", key: "spo2_trends" },
      { label: "Weight / BMI", key: "weight_bmi_trends" },
      { label: "Temperature", key: "temperature_trends" },
      { label: "Pain scores", key: "pain_score_trends" },
      { label: "Nutrition / hydration", key: "hydration_trends" },
      { label: "Wound progress", key: "wound_progress" },
      { label: "Abnormal observation alerts", key: "abnormal_observation_alerts" },
      { label: "NEWS2 scores", key: "news2_scores" },
      { label: "NEWS2 escalations", key: "news2_escalations" },
    ],
  },
  {
    key: "incident_safeguarding",
    label: "Incident & Safeguarding Reports",
    reports: [
      { label: "Falls", key: "falls" },
      { label: "Medication errors", key: "medication_errors" },
      { label: "Injuries", key: "injuries" },
      { label: "Safeguarding concerns", key: "safeguarding_concerns" },
      { label: "Risk register", key: "risk_register" },
      { label: "Severity trends", key: "incident_severity_breakdown" },
      { label: "Open vs closed incidents", key: "incident_status_breakdown" },
      { label: "Incident frequency per client / facility", key: "incident_frequency" },
      { label: "Corrective actions", key: "corrective_actions" },
    ],
  },
  {
    key: "staff_workforce",
    label: "Staff & Workforce Reports",
    reports: [
      { label: "Staff attendance", key: "staff_attendance" },
      { label: "Clock-in / out", key: "clock_in_out" },
      { label: "Worked hours", key: "worked_hours" },
      { label: "Overtime", key: "overtime" },
      { label: "Sickness", key: "sickness" },
      { label: "Leave", key: "leave_report" },
      { label: "Shift coverage", key: "shift_coverage" },
      { label: "Unfilled shifts", key: "unfilled_shifts" },
      { label: "Late arrivals", key: "late_visits" },
      { label: "Staff utilization", key: "staff_utilization" },
      { label: "Mileage / travel time", key: "mileage_travel_time" },
    ],
  },
  {
    key: "training_compliance",
    label: "Training & Compliance Reports",
    reports: [
      { label: "Expired certifications", key: "expired_certifications" },
      { label: "Certificates due to expire", key: "certificates_expiring_soon" },
      { label: "Mandatory training completion", key: "mandatory_training_completion" },
      { label: "Compliance percentage by branch", key: "compliance_by_branch" },
      { label: "Staff document expiry", key: "staff_document_expiry" },
      { label: "Background-check status", key: "background_checks" },
    ],
  },
  {
    key: "rostering",
    label: "Rostering Reports",
    reports: [
      { label: "Weekly / monthly roster", key: "shift_coverage" },
      { label: "Assigned vs available staff", key: "assigned_vs_available" },
      { label: "Double-bookings", key: "double_bookings" },
      { label: "Overtime risk", key: "overtime_risk" },
      { label: "Staffing gaps", key: "staffing_gaps" },
      { label: "Client-to-carer allocation", key: "client_carer_allocation" },
    ],
  },
  {
    key: "finance",
    label: "Finance Reports",
    reports: [
      { label: "Invoices", key: "invoices" },
      { label: "Payments", key: "payments" },
      { label: "Outstanding balances", key: "outstanding_balances" },
      { label: "Revenue by client / branch / service", key: "revenue_breakdown" },
      { label: "Care hours billed", key: "care_hours_billed" },
      { label: "Funding utilization", key: "funding_utilization" },
      { label: "Payroll cost", key: "payroll_cost" },
      { label: "Mileage reimbursement", key: "mileage_reimbursement" },
      { label: "Profit / margin by service", key: "profit_margin" },
    ],
  },
  {
    key: "quality_audit",
    label: "Quality & Audit Reports",
    reports: [
      { label: "Care-plan reviews overdue", key: "care_plan_reviews_overdue" },
      { label: "Documentation completeness", key: "documentation_completeness" },
      { label: "Consent to care", key: "care_consent" },
      { label: "Risk register", key: "risk_register" },
      { label: "Medication audits", key: "emar_administration" },
      { label: "Spot checks", key: "spot_checks" },
      { label: "Complaints", key: "complaints" },
      { label: "Service-quality indicators", key: "service_quality_indicators" },
      { label: "Unresolved actions", key: "unresolved_actions" },
    ],
  },
  {
    key: "gps_verification",
    label: "GPS / Visit Verification Reports",
    reports: [
      { label: "Trips activity report", key: "trips_activity" },
      { label: "Verified check-ins", key: "verified_checkins" },
      { label: "Check-in distance from client", key: "checkin_distance" },
      { label: "Manual overrides", key: "manual_overrides" },
      { label: "Suspicious check-ins", key: "suspicious_checkins" },
      { label: "Travel distance", key: "travel_distance" },
      { label: "Mileage", key: "mileage" },
    ],
  },
  {
    key: "management",
    label: "Management Reports",
    reports: [
      { label: "Active clients", key: "active_clients" },
      { label: "New admissions", key: "new_admissions" },
      { label: "Discharged clients", key: "discharged_clients" },
      { label: "Total care hours", key: "care_hours_delivered" },
      { label: "Missed visits", key: "missed_visits" },
      { label: "Incidents", key: "all_incidents" },
      { label: "Staff shortages", key: "staff_shortages" },
      { label: "Revenue", key: "revenue_breakdown" },
      { label: "Compliance score", key: "compliance_by_branch" },
      { label: "Branch performance", key: "branch_performance" },
    ],
  },
];
