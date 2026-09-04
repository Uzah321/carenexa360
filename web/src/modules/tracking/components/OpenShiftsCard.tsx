import { useState, type FormEvent } from "react";
import {
  Alert,
  Button,
  Card,
  CardBody,
  CardHeader,
  FormField,
  Modal,
  StatusBadge,
  Textarea,
} from "../../../design-system";
import { useForceCloseDutyPeriod, useOpenDutyPeriods, type DutyPeriod } from "../api";

/**
 * A shift running longer than this almost certainly means the carer forgot to
 * check out rather than that they are still working — a normal home-care shift
 * is well under it, so it makes a safe threshold for flagging.
 */
const STALE_AFTER_HOURS = 14;

function hoursOnDuty(startedAt: string): number {
  return (Date.now() - new Date(startedAt).getTime()) / 3_600_000;
}

function formatDuration(startedAt: string): string {
  const hours = hoursOnDuty(startedAt);
  if (hours < 1) return `${Math.max(0, Math.round(hours * 60))} min`;
  const whole = Math.floor(hours);
  const minutes = Math.round((hours - whole) * 60);
  return minutes ? `${whole}h ${minutes}m` : `${whole}h`;
}

function formatStart(startedAt: string): string {
  return new Date(startedAt).toLocaleString(undefined, {
    weekday: "short",
    hour: "2-digit",
    minute: "2-digit",
  });
}

export function OpenShiftsCard() {
  const { data: shifts, isLoading } = useOpenDutyPeriods();
  const forceClose = useForceCloseDutyPeriod();
  const [closing, setClosing] = useState<DutyPeriod | null>(null);
  const [reason, setReason] = useState("");
  const [error, setError] = useState<string | null>(null);

  async function handleClose(event: FormEvent) {
    event.preventDefault();
    if (!closing) return;
    setError(null);
    try {
      await forceClose.mutateAsync({ id: closing.id, reason });
      setClosing(null);
      setReason("");
    } catch (err) {
      const response = (err as { response?: { data?: { errors?: Record<string, string[]>; message?: string } } })
        .response;
      setError(
        Object.values(response?.data?.errors ?? {}).flat().join(" ") ||
          response?.data?.message ||
          "Could not close this shift. Please try again.",
      );
    }
  }

  if (isLoading || !shifts || shifts.length === 0) return null;

  const stale = shifts.filter((shift) => hoursOnDuty(shift.started_at) >= STALE_AFTER_HOURS);

  return (
    <>
      <Card className="mt-4">
        <CardHeader>
          Open shifts
          <span className="ml-2 rounded-full bg-paper px-2 py-0.5 text-xs font-normal text-inksoft">
            {shifts.length} on duty
            {stale.length > 0 ? ` · ${stale.length} need attention` : ""}
          </span>
        </CardHeader>
        <CardBody>
          <ul className="divide-y divide-line">
            {shifts.map((shift) => {
              const isStale = hoursOnDuty(shift.started_at) >= STALE_AFTER_HOURS;
              return (
                <li key={shift.id} className="flex flex-wrap items-center gap-3 py-2.5 first:pt-0 last:pb-0">
                  <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-2">
                      <span className="font-medium text-ink">{shift.carer_name ?? `User #${shift.user_id}`}</span>
                      {isStale && <StatusBadge label="No check-out" tone="warning" />}
                    </div>
                    <p className="mt-0.5 text-xs text-inksoft">
                      On duty since {formatStart(shift.started_at)} · {formatDuration(shift.started_at)}
                    </p>
                  </div>
                  <Button
                    variant="secondary"
                    onClick={() => {
                      setClosing(shift);
                      setReason("");
                      setError(null);
                    }}
                  >
                    Close shift
                  </Button>
                </li>
              );
            })}
          </ul>
          <p className="mt-3 text-xs text-inksoft">
            Closing a shift on a carer's behalf records your reason against their shift record.
          </p>
        </CardBody>
      </Card>

      <Modal
        isOpen={Boolean(closing)}
        onClose={() => setClosing(null)}
        title={closing ? `Close shift — ${closing.carer_name ?? "carer"}` : "Close shift"}
        footer={
          <>
            <Button variant="secondary" onClick={() => setClosing(null)}>
              Cancel
            </Button>
            <Button form="close-shift-form" type="submit" isLoading={forceClose.isPending}>
              Close shift
            </Button>
          </>
        }
      >
        <form id="close-shift-form" onSubmit={handleClose}>
          {error && (
            <div className="mb-4">
              <Alert tone="danger">{error}</Alert>
            </div>
          )}
          <p className="mb-4 text-sm text-inksoft">
            {closing && (
              <>
                {closing.carer_name ?? "This carer"} has been on duty since{" "}
                <span className="font-medium text-ink">{formatStart(closing.started_at)}</span> and
                has not checked out. Closing it ends the shift now.
              </>
            )}
          </p>
          <FormField label="Reason" htmlFor="close-shift-reason">
            <Textarea
              id="close-shift-reason"
              required
              rows={3}
              value={reason}
              onChange={(e) => setReason(e.target.value)}
              placeholder="e.g. Carer finished at 17:00 but forgot to check out — confirmed by phone."
            />
          </FormField>
        </form>
      </Modal>
    </>
  );
}
