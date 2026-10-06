import type {
  Medication,
  MedicationAdministration,
  MedicationAdministrationStatus,
  MedicationNotGivenReason,
} from "../../lib/types";
import { tenantMinutesNow } from "../../lib/preferences";

export const NOT_GIVEN_REASON_LABELS: Record<MedicationNotGivenReason, string> = {
  refused: "Refused",
  unwell: "Unwell",
  hospitalised: "Hospitalised",
  social_leave: "Social leave",
  medication_not_available: "Medication not available",
  client_cancelled: "Client cancelled",
  self_administered: "Self administered",
  administered_by_family: "Administered by family",
  prn_not_required: "PRN not required",
  given_by_other_carer: "Given by other carer",
};

const STATUS_LABELS: Record<MedicationAdministrationStatus, string> = {
  administered: "Given",
  prn: "Given (PRN)",
  not_given: "Not given",
  missed: "Missed",
  // Statuses from before "not given" had reasons — older records still use them.
  refused: "Refused",
  not_available: "Not available",
  hospitalized: "Hospitalised",
  self_administered: "Self administered",
};

/** "Not given — Social leave", "Given", "Refused"… */
export function administrationLabel(administration: Pick<MedicationAdministration, "status" | "not_given_reason">): string {
  const label = STATUS_LABELS[administration.status] ?? administration.status.replaceAll("_", " ");
  return administration.not_given_reason
    ? `${label} — ${NOT_GIVEN_REASON_LABELS[administration.not_given_reason]}`
    : label;
}

export function administrationTone(status: MedicationAdministrationStatus): "success" | "warning" | "danger" {
  if (status === "administered" || status === "prn") return "success";
  if (status === "missed") return "danger";
  return "warning";
}

/** The outcomes a carer picks between when recording a dose. */
export function recordableStatuses(medication: Medication): MedicationAdministrationStatus[] {
  return medication.is_prn ? ["prn", "not_given"] : ["administered", "not_given", "missed"];
}

export interface RoundDose {
  medication: Medication;
  /** Today's record for this slot, if one has been made. */
  record: MedicationAdministration | null;
}

export interface RoundSlot {
  time: string;
  doses: RoundDose[];
}

/**
 * Today's medication round: every active, scheduled (non-PRN) medication
 * grouped under each time it's due, paired with the record made for that
 * slot today.
 */
export function buildRound(medications: Medication[]): RoundSlot[] {
  const slots = new Map<string, RoundDose[]>();

  for (const medication of medications) {
    if (medication.status !== "active" || medication.archived_at || medication.is_prn) continue;
    for (const time of medication.schedule ?? []) {
      const record = (medication.today_administrations ?? []).find((a) => a.scheduled_time === time) ?? null;
      slots.set(time, [...(slots.get(time) ?? []), { medication, record }]);
    }
  }

  return [...slots.entries()]
    .sort(([a], [b]) => a.localeCompare(b))
    .map(([time, doses]) => ({ time, doses }));
}

/** Whether a "HH:mm" slot is already past today. */
export function isPastDue(time: string, now?: Date): boolean {
  const [hours, minutes] = time.split(":").map(Number);
  return (now ? now.getHours() * 60 + now.getMinutes() : tenantMinutesNow()) > hours * 60 + minutes;
}
