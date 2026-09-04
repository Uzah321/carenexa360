import { useEffect, useState } from "react";
import { useParams } from "react-router-dom";
import {
  Alert,
  Button,
  Card,
  CardBody,
  CardHeader,
  Checkbox,
  FormField,
  PageHeader,
  StatusBadge,
  Textarea,
} from "../../../design-system";
import { useCheckIn, useCheckOut, useUpdateVisit, useVisit } from "../api";
import { useCurrentDutyPeriod } from "../../tracking/api";
import { useAuth } from "../../../lib/auth-context";
import { ROSTERING_ROLES } from "../../../lib/types";
import { getCurrentPosition } from "../../../lib/geolocation";
import type { VisitStatus } from "../../../lib/types";

const STATUS_TONE: Record<VisitStatus, "success" | "warning" | "neutral" | "danger" | "info"> = {
  scheduled: "info",
  in_progress: "warning",
  completed: "success",
  missed: "danger",
  cancelled: "neutral",
};

export function VisitDetailPage() {
  const { visitId } = useParams<{ visitId: string }>();
  const id = Number(visitId);
  const { data: visit, isLoading } = useVisit(id);
  const checkIn = useCheckIn(id);
  const checkOut = useCheckOut(id);
  const updateVisit = useUpdateVisit(id);

  // Starting a visit requires being checked in for work — the API enforces this,
  // and disabling the button here means a carer finds out before they tap it
  // rather than after. Scheduling roles are exempt because starting a visit for
  // someone else is an administrative action, not them going out on the round.
  const { user, hasAnyRole } = useAuth();
  const { data: dutyPeriod } = useCurrentDutyPeriod();
  const canStartVisit = Boolean(dutyPeriod) || hasAnyRole(ROSTERING_ROLES) || !user;

  const [error, setError] = useState<string | null>(null);
  const [overrideReason, setOverrideReason] = useState("");
  const [needsOverride, setNeedsOverride] = useState(false);
  const [noteDraft, setNoteDraft] = useState("");

  // Mirrors the server value but updates immediately on click — checkboxes
  // bound straight to the query result would flicker back to unchecked for
  // the round-trip duration, since React re-renders a controlled input with
  // its old prop value until the mutation resolves and the query refetches.
  const [completedTasks, setCompletedTasks] = useState<string[]>([]);
  const [medicationDone, setMedicationDone] = useState(false);

  useEffect(() => {
    setNoteDraft(visit?.notes ?? "");
  }, [visit?.notes]);

  useEffect(() => {
    setCompletedTasks(visit?.completed_care_tasks ?? []);
    setMedicationDone(visit?.medication_tasks_completed ?? false);
  }, [visit?.completed_care_tasks, visit?.medication_tasks_completed]);

  const tasksEditable = visit?.status === "in_progress";

  function toggleTask(task: string) {
    const next = completedTasks.includes(task)
      ? completedTasks.filter((t) => t !== task)
      : [...completedTasks, task];
    setCompletedTasks(next);
    updateVisit.mutate({ completed_care_tasks: next });
  }

  function toggleMedication() {
    const next = !medicationDone;
    setMedicationDone(next);
    updateVisit.mutate({ medication_tasks_completed: next });
  }

  function saveNote() {
    updateVisit.mutate({ notes: noteDraft });
  }

  async function handleCheckIn() {
    setError(null);
    try {
      const position = await getCurrentPosition();
      await checkIn.mutateAsync({
        latitude: position.coords.latitude,
        longitude: position.coords.longitude,
        accuracy: position.coords.accuracy,
        override_reason: needsOverride ? overrideReason : undefined,
      });
      setNeedsOverride(false);
      setOverrideReason("");
    } catch (err) {
      if (isAxiosGeofenceError(err)) {
        setNeedsOverride(true);
        setError(getAxiosMessage(err));
      } else {
        setError(err instanceof Error ? err.message : "Could not determine your location.");
      }
    }
  }

  async function handleCheckOut() {
    setError(null);
    try {
      const position = await getCurrentPosition();
      await checkOut.mutateAsync({
        latitude: position.coords.latitude,
        longitude: position.coords.longitude,
        accuracy: position.coords.accuracy,
        override_reason: needsOverride ? overrideReason : undefined,
      });
      setNeedsOverride(false);
      setOverrideReason("");
    } catch (err) {
      if (isAxiosGeofenceError(err)) {
        setNeedsOverride(true);
        setError(getAxiosMessage(err));
      } else {
        setError(err instanceof Error ? err.message : "Could not determine your location.");
      }
    }
  }

  if (isLoading || !visit) {
    return (
      <Card>
        <CardBody>Loading…</CardBody>
      </Card>
    );
  }

  return (
    <div>
      <PageHeader
        title={`Visit — ${visit.service_user_name}`}
        breadcrumbs={[{ label: "Visits", to: "/visits" }, { label: `#${visit.id}` }]}
        actions={<StatusBadge label={visit.status.replaceAll("_", " ")} tone={STATUS_TONE[visit.status]} />}
      />

      <Card>
        <CardHeader>Visit Details</CardHeader>
        <CardBody>
          <dl className="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
            <div>
              <dt className="text-inksoft">Date</dt>
              <dd className="font-medium text-ink">{visit.visit_date}</dd>
            </div>
            <div>
              <dt className="text-inksoft">Time</dt>
              <dd className="font-medium text-ink">{visit.start_time}–{visit.end_time}</dd>
            </div>
            <div>
              <dt className="text-inksoft">Carer</dt>
              <dd className="font-medium text-ink">{visit.carer_name ?? "Unassigned"}</dd>
            </div>
          </dl>

          {(visit.care_tasks.length > 0 || visit.medication_tasks) && (
            <div className="mt-5 border-t border-line pt-4">
              <h3 className="mb-2 text-sm font-semibold text-ink">Care Tasks</h3>
              {!tasksEditable && (
                <p className="mb-2 text-xs text-inksoft">
                  {visit.status === "scheduled"
                    ? "Tasks can be ticked off once you've checked in."
                    : "This visit is closed — tasks can no longer be changed."}
                </p>
              )}
              <div className="space-y-2">
                {visit.care_tasks.map((task) => (
                  <Checkbox
                    key={task}
                    id={`task-${task}`}
                    label={task}
                    checked={completedTasks.includes(task)}
                    disabled={!tasksEditable}
                    onChange={() => toggleTask(task)}
                  />
                ))}
                {visit.medication_tasks && (
                  <Checkbox
                    id="task-medication"
                    label="Administer medication"
                    checked={medicationDone}
                    disabled={!tasksEditable}
                    onChange={toggleMedication}
                  />
                )}
              </div>
            </div>
          )}

          <div className="mt-5 border-t border-line pt-4">
            <FormField label="Care note" htmlFor="visit-note">
              <Textarea
                id="visit-note"
                rows={3}
                value={noteDraft}
                onChange={(e) => setNoteDraft(e.target.value)}
              />
            </FormField>
            <Button variant="secondary" onClick={saveNote} isLoading={updateVisit.isPending} disabled={noteDraft === (visit.notes ?? "")}>
              Save note
            </Button>
          </div>

          {error && (
            <div className="mt-4">
              <Alert tone="danger">{error}</Alert>
            </div>
          )}

          {needsOverride && (
            <div className="mt-4">
              <FormField label="Override reason (required to proceed outside the geofence)" htmlFor="override-reason">
                <Textarea
                  id="override-reason"
                  value={overrideReason}
                  onChange={(e) => setOverrideReason(e.target.value)}
                />
              </FormField>
            </div>
          )}

          {visit.status === "scheduled" && !canStartVisit && (
            <div className="mt-4">
              <Alert tone="info">
                You need to check in for work on My Day before starting a visit.
              </Alert>
            </div>
          )}

          <div className="mt-4 flex gap-2">
            {visit.status === "scheduled" && (
              <Button onClick={handleCheckIn} isLoading={checkIn.isPending} disabled={!canStartVisit}>
                Check In
              </Button>
            )}
            {visit.status === "in_progress" && (
              <Button onClick={handleCheckOut} isLoading={checkOut.isPending}>
                Check Out
              </Button>
            )}
          </div>

          {visit.check_in_at && (
            <p className="mt-3 text-sm text-inksoft">
              Checked in at {new Date(visit.check_in_at).toLocaleString()}
            </p>
          )}
          {visit.check_out_at && (
            <p className="text-sm text-inksoft">
              Checked out at {new Date(visit.check_out_at).toLocaleString()}
            </p>
          )}
        </CardBody>
      </Card>
    </div>
  );
}

function isAxiosGeofenceError(err: unknown): boolean {
  return (
    typeof err === "object" &&
    err !== null &&
    "response" in err &&
    (err as { response?: { status?: number } }).response?.status === 422
  );
}

function getAxiosMessage(err: unknown): string {
  const response = (err as { response?: { data?: { errors?: Record<string, string[]> } } }).response;
  const errors = response?.data?.errors;
  return errors ? Object.values(errors).flat().join(" ") : "Unable to check in.";
}
