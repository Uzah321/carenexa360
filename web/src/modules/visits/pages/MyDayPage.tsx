import { useState } from "react";
import { Link } from "react-router-dom";
import { Alert, Button, EmptyState, StatusBadge } from "../../../design-system";
import { useAuth } from "../../../lib/auth-context";
import { getCurrentPosition } from "../../../lib/geolocation";
import type { VisitStatus } from "../../../lib/types";
import { useCheckInDuty, useCheckOutDuty, useCurrentDutyPeriod } from "../../tracking/api";
import { useLocationSharing } from "../../tracking/useLocationSharing";
import { useVisits } from "../api";
import { todayIso } from "../../../lib/dates";

const STATUS_TONE: Record<VisitStatus, "success" | "warning" | "neutral" | "danger" | "info"> = {
  scheduled: "info",
  in_progress: "warning",
  completed: "success",
  missed: "danger",
  cancelled: "neutral",
};

function formatTime(iso: string) {
  return new Date(iso).toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
}

export function MyDayPage() {
  const { user } = useAuth();
  const today = todayIso();
  const { data, isLoading } = useVisits({ carer_id: user?.id, date: today });
  const { data: dutyPeriod, isLoading: isDutyLoading } = useCurrentDutyPeriod();
  const checkInDuty = useCheckInDuty();
  const checkOutDuty = useCheckOutDuty();
  const [dutyError, setDutyError] = useState<string | null>(null);

  const visits = data?.data ?? [];
  const activeVisit = visits.find((v) => v.status === "in_progress");
  const isOnDuty = dutyPeriod?.is_active ?? false;
  const sharing = useLocationSharing(isOnDuty, activeVisit?.id ?? null);

  async function handleCheckIn() {
    setDutyError(null);
    try {
      const position = await getCurrentPosition();
      await checkInDuty.mutateAsync({
        latitude: position.coords.latitude,
        longitude: position.coords.longitude,
        accuracy: position.coords.accuracy,
      });
    } catch (err) {
      setDutyError(err instanceof Error ? err.message : "Could not check in for work.");
    }
  }

  async function handleCheckOut() {
    if (!dutyPeriod) return;
    setDutyError(null);
    try {
      // Prefer the position already flowing from the active location watch
      // over a fresh one-shot request — requesting a new fix while a watch
      // is running can hang on some browsers, and the watch's fix is at
      // most ~25s old anyway.
      const coords = sharing.lastKnownPosition ?? (await getCurrentPosition()).coords;
      await checkOutDuty.mutateAsync({
        id: dutyPeriod.id,
        latitude: coords.latitude,
        longitude: coords.longitude,
        accuracy: coords.accuracy,
      });
    } catch (err) {
      setDutyError(err instanceof Error ? err.message : "Could not check out.");
    }
  }

  // Only worth nudging about check-out once there is nothing left to do — a
  // reminder while visits are still outstanding would just be noise.
  const allVisitsSettled =
    visits.length > 0 &&
    visits.every((visit) => ["completed", "cancelled", "missed"].includes(visit.status));

  return (
    <div className="mx-auto max-w-md">
      <div className="mb-4">
        <h1 className="text-2xl font-extrabold tracking-tight text-ink">My Day</h1>
        <p className="mt-1 text-sm text-inksoft">
          {new Date().toLocaleDateString(undefined, { weekday: "long", month: "long", day: "numeric" })}
        </p>
      </div>

      <div
        className={`mb-6 rounded-2xl border p-4 ${
          isOnDuty ? "border-lime/30 bg-limetint/40" : "border-line bg-white"
        }`}
      >
        {!isDutyLoading && isOnDuty && dutyPeriod && (
          <>
            <div className="flex items-center gap-2 text-sm font-semibold text-lime">
              <span className="h-2 w-2 rounded-full bg-lime" />
              On duty since {formatTime(dutyPeriod.started_at)}
            </div>
            <p className="mt-1 text-xs text-inksoft">
              Your location is being shared so your visits can be tracked in real time.
            </p>
            {allVisitsSettled && (
              <p className="mt-2 rounded-xl bg-white/70 px-3 py-2 text-xs font-medium text-ink">
                All your visits for today are done — remember to check out before you finish.
              </p>
            )}
            <Button
              variant="secondary"
              className="mt-3 w-full"
              onClick={handleCheckOut}
              isLoading={checkOutDuty.isPending}
            >
              Check Out
            </Button>
          </>
        )}
        {!isDutyLoading && !isOnDuty && (
          <>
            <p className="text-sm font-medium text-ink">Not checked in yet</p>
            <p className="mt-1 text-xs text-inksoft">
              Check in when you arrive at work. You need to be checked in before you can
              start a visit, and it shares your location for the day.
            </p>
            <Button className="mt-3 w-full" onClick={handleCheckIn} isLoading={checkInDuty.isPending}>
              Check In for Work
            </Button>
          </>
        )}
        {(dutyError || sharing.error) && (
          <div className="mt-3">
            <Alert tone="danger">{dutyError ?? sharing.error}</Alert>
          </div>
        )}
      </div>

      {!isDutyLoading && !isOnDuty && visits.length > 0 && (
        <div className="mb-3">
          <Alert tone="info">
            Check in for work above to start these visits.
          </Alert>
        </div>
      )}

      {!isLoading && visits.length === 0 ? (
        <EmptyState message="No visits assigned to you today." />
      ) : (
        <div className="space-y-3">
          {visits.map((visit) => (
            <Link
              key={visit.id}
              to={`/visits/${visit.id}`}
              className="block rounded-2xl border border-line bg-white p-4 transition-colors duration-150 hover:border-teal"
            >
              <div className="flex items-center justify-between">
                <span className="text-lg font-semibold text-ink">
                  {visit.start_time}–{visit.end_time}
                </span>
                <StatusBadge label={visit.status.replaceAll("_", " ")} tone={STATUS_TONE[visit.status]} />
              </div>
              <div className="mt-1 text-sm text-inksoft">{visit.service_user_name ?? "—"}</div>
              {visit.care_tasks.length > 0 && (
                <div className="mt-2 flex flex-wrap gap-1.5">
                  {visit.care_tasks.map((task) => (
                    <span key={task} className="bg-paper px-2 py-0.5 text-xs text-inksoft">
                      {task}
                    </span>
                  ))}
                </div>
              )}
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}
