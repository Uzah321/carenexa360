import { Clock, Pill } from "lucide-react";
import { StatusBadge } from "../../../design-system";
import type { Medication } from "../../../lib/types";
import { administrationLabel, administrationTone, buildRound, isPastDue } from "../administration";

interface MedicationRoundProps {
  medications: Medication[];
  onRecord: (medication: Medication, scheduledTime: string) => void;
}

/** Today's doses grouped by the time they're due, eMAR-style. */
export function MedicationRound({ medications, onRecord }: MedicationRoundProps) {
  const slots = buildRound(medications);

  if (slots.length === 0) {
    return (
      <p className="text-sm text-inksoft">
        No scheduled doses today. Add dose times to a medication to see it in the round.
      </p>
    );
  }

  return (
    <ol className="space-y-4">
      {slots.map((slot) => (
        <li key={slot.time} className="flex gap-3">
          <div className="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-skytint text-sky">
            <Pill className="h-4 w-4" aria-hidden />
          </div>
          <div className="min-w-0 flex-1">
            <h4 className="flex items-center gap-1.5 text-sm font-semibold text-ink">
              <Clock className="h-4 w-4" aria-hidden />
              Medication(s) due at {slot.time}
            </h4>
            <ul className="mt-2 divide-y divide-line rounded-lg border border-line">
              {slot.doses.map(({ medication, record }) => (
                <li
                  key={medication.id}
                  className="flex flex-wrap items-center justify-between gap-2 px-3 py-2 text-sm"
                >
                  <div className="min-w-0">
                    <div className="font-medium text-ink">
                      {medication.name}
                      {medication.strength && ` ${medication.strength}`}
                    </div>
                    <div className="text-inksoft">
                      {medication.dose} · {medication.frequency}
                    </div>
                  </div>
                  <div className="flex items-center gap-2">
                    {record ? (
                      <StatusBadge label={administrationLabel(record)} tone={administrationTone(record.status)} />
                    ) : (
                      <StatusBadge
                        label={isPastDue(slot.time) ? "Overdue" : "Due"}
                        tone={isPastDue(slot.time) ? "danger" : "info"}
                      />
                    )}
                    {!record && (
                      <button
                        type="button"
                        className="text-sm font-medium text-teal hover:text-teal/90"
                        onClick={() => onRecord(medication, slot.time)}
                      >
                        Record
                      </button>
                    )}
                  </div>
                </li>
              ))}
            </ul>
          </div>
        </li>
      ))}
    </ol>
  );
}
