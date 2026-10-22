import { Alert, StatusBadge } from "../../../design-system";
import type { VisitClientAlerts } from "../../../lib/types";
import { RESUSCITATION_LABELS, SEVERITY_LABELS } from "../../medical-history/categories";

// A client's allergies and resuscitation decision, from their Medical
// History, wherever a carer is about to see them.

/** Compact — for a visit card on My Day. Nothing when there's nothing to flag. */
export function ClientAlertBadges({ alerts }: { alerts: VisitClientAlerts | null | undefined }) {
  if (!alerts) return null;

  const allergyNames = alerts.allergies.map((a) => a.name).join(", ");
  const allergyUnknown = alerts.allergies.length === 0 && !alerts.no_known_allergies;
  if (!allergyNames && !allergyUnknown && alerts.resuscitation_status !== "dnacpr") return null;

  return (
    <div className="mt-2 flex flex-wrap gap-1.5">
      {alerts.resuscitation_status === "dnacpr" && <StatusBadge label="DNACPR" tone="danger" />}
      {allergyNames && <StatusBadge label={`Allergies: ${allergyNames}`} tone="danger" />}
      {allergyUnknown && <StatusBadge label="Allergies not recorded" tone="warning" />}
    </div>
  );
}

/** In full — at the top of the visit page. */
export function ClientAlertsPanel({ alerts }: { alerts: VisitClientAlerts | null | undefined }) {
  if (!alerts) return null;

  const isDnacpr = alerts.resuscitation_status === "dnacpr";
  const isForCpr = alerts.resuscitation_status === "for_resuscitation";
  const hasAllergies = alerts.allergies.length > 0;
  const isNoKnownAllergies = !hasAllergies && alerts.no_known_allergies;

  return (
    <div className="mb-4 space-y-3">
      {isDnacpr && (
        <Alert tone="danger" title="DNACPR in place">
          {alerts.resuscitation_notes ? (
            <p className="whitespace-pre-line">{alerts.resuscitation_notes}</p>
          ) : (
            <p>Do not attempt CPR. Check with the office where the signed form is kept.</p>
          )}
        </Alert>
      )}

      {hasAllergies && (
        <Alert tone="danger" title="Allergies">
          <ul className="space-y-1">
            {alerts.allergies.map((allergy) => (
              <li key={allergy.name}>
                <span className="font-semibold">{allergy.name}</span>
                {allergy.severity && <> — {SEVERITY_LABELS[allergy.severity]}</>}
                {allergy.reaction && <span className="block text-xs">{allergy.reaction}</span>}
              </li>
            ))}
          </ul>
        </Alert>
      )}

      {!hasAllergies && !alerts.no_known_allergies && (
        <Alert tone="warning" title="Allergies not recorded">
          Check with the office before giving any medication or food you're unsure about.
        </Alert>
      )}

      {(isForCpr || isNoKnownAllergies) && (
        <div className="flex flex-wrap gap-1.5">
          {isForCpr && <StatusBadge label={RESUSCITATION_LABELS.for_resuscitation} tone="info" />}
          {isNoKnownAllergies && <StatusBadge label="No known allergies" tone="success" />}
        </div>
      )}
    </div>
  );
}
