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

// The full requested taxonomy. Reports with a `key` are wired to a real
// backend handler (ReportGeneratorController) and can be generated today;
// reports without one are shown as "coming soon" — the underlying data
// isn't modeled yet (see project memory for the per-report reasoning),
// so they're listed honestly rather than faked.
export const REPORT_CATALOG: ReportCategory[] = [
  {
    key: "client",
    label: "Client / Service User Reports",
    reports: [
      { label: "Client profile summary" },
      { label: "Care history" },
      { label: "Care-plan summary" },
      { label: "Review history" },
      { label: "Visit history", key: "visit_history" },
      { label: "Missed visits", key: "missed_visits" },
      { label: "Daily notes" },
      { label: "Family / contact activity" },
      { label: "Admission / discharge history" },
    ],
  },
  {
    key: "care_delivery",
    label: "Care Delivery Reports",
    reports: [
      { label: "Scheduled vs completed visits", key: "visit_status_breakdown" },
      { label: "Care tasks completed / not completed" },
      { label: "Visit duration", key: "care_hours_delivered" },
      { label: "Late visits", key: "late_visits" },
      { label: "Cancelled visits", key: "cancelled_visits" },
      { label: "Missed visits", key: "missed_visits" },
      { label: "Care hours delivered", key: "care_hours_delivered" },
      { label: "Care package utilization" },
    ],
  },
  {
    key: "medication",
    label: "Medication Reports",
    reports: [
      { label: "eMAR administration report", key: "emar_administration" },
      { label: "Missed medication", key: "missed_medication" },
      { label: "Refused medication", key: "refused_medication" },
      { label: "Late medication" },
      { label: "PRN medication usage", key: "prn_medication_usage" },
      { label: "Medication errors", key: "medication_errors" },
      { label: "Medication stock / reorder report" },
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
      { label: "Wound progress" },
      { label: "Abnormal observation alerts", key: "abnormal_observation_alerts" },
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
      { label: "Staff attendance" },
      { label: "Clock-in / out" },
      { label: "Worked hours", key: "worked_hours" },
      { label: "Overtime" },
      { label: "Sickness" },
      { label: "Leave", key: "leave_report" },
      { label: "Shift coverage", key: "shift_coverage" },
      { label: "Unfilled shifts" },
      { label: "Late arrivals", key: "late_visits" },
      { label: "Staff utilization" },
      { label: "Mileage / travel time" },
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
      { label: "Background-check status" },
    ],
  },
  {
    key: "rostering",
    label: "Rostering Reports",
    reports: [
      { label: "Weekly / monthly roster", key: "shift_coverage" },
      { label: "Assigned vs available staff" },
      { label: "Double-bookings", key: "double_bookings" },
      { label: "Overtime risk" },
      { label: "Staffing gaps" },
      { label: "Client-to-carer allocation" },
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
      { label: "Funding utilization" },
      { label: "Payroll cost", key: "payroll_cost" },
      { label: "Mileage reimbursement" },
      { label: "Profit / margin by service" },
    ],
  },
  {
    key: "quality_audit",
    label: "Quality & Audit Reports",
    reports: [
      { label: "Care-plan reviews overdue", key: "care_plan_reviews_overdue" },
      { label: "Documentation completeness" },
      { label: "Medication audits", key: "emar_administration" },
      { label: "Spot checks" },
      { label: "Complaints" },
      { label: "Service-quality indicators" },
      { label: "Unresolved actions", key: "unresolved_actions" },
    ],
  },
  {
    key: "gps_verification",
    label: "GPS / Visit Verification Reports",
    reports: [
      { label: "Verified check-ins", key: "verified_checkins" },
      { label: "Check-in distance from client", key: "checkin_distance" },
      { label: "Manual overrides", key: "manual_overrides" },
      { label: "Suspicious check-ins", key: "suspicious_checkins" },
      { label: "Travel distance" },
      { label: "Mileage" },
    ],
  },
  {
    key: "management",
    label: "Management Reports",
    reports: [
      { label: "Active clients", key: "active_clients" },
      { label: "New admissions" },
      { label: "Discharged clients", key: "discharged_clients" },
      { label: "Total care hours", key: "care_hours_delivered" },
      { label: "Missed visits", key: "missed_visits" },
      { label: "Incidents", key: "all_incidents" },
      { label: "Staff shortages" },
      { label: "Revenue", key: "revenue_breakdown" },
      { label: "Compliance score", key: "compliance_by_branch" },
      { label: "Branch performance", key: "branch_performance" },
    ],
  },
];
