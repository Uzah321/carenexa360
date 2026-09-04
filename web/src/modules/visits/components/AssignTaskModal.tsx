import { useEffect, useState, type FormEvent } from "react";
import { Alert, Button, FormField, Input, Modal, Select, TagInput } from "../../../design-system";
import { useServiceUsers } from "../../service-users/api";
import { useCreateVisit, type CreateVisitInput } from "../api";
import { todayIso } from "../../../lib/dates";
import { COMMON_CARE_TASKS } from "../../../lib/types";

function errorMessage(err: unknown): string {
  const response = (err as { response?: { data?: { errors?: Record<string, string[]>; message?: string } } })
    .response;
  const errors = response?.data?.errors;
  if (errors) return Object.values(errors).flat().join(" ");
  return response?.data?.message ?? "Something went wrong. Please try again.";
}

function pad(n: number): string {
  return String(n).padStart(2, "0");
}

/**
 * The carer and date are already decided by the row you clicked "Assign" on, so
 * the only things left to choose are the service user and the tasks. Seeding a
 * sensible slot — the next full hour for today, the start of the working day for
 * a future date — means the common case is two clicks rather than typing times
 * by hand. Late-evening starts clamp so the slot never runs past midnight.
 */
function addOneHour(time: string): string {
  const [h, m] = time.split(":").map(Number);
  return `${pad(Math.min(23, h + 1))}:${pad(m)}`;
}

function defaultTaskFields(date: string) {
  const isToday = date === todayIso();
  let startHour = isToday ? new Date().getHours() + 1 : 9;
  if (startHour > 22) startHour = 22;

  return {
    service_user_id: 0,
    start_time: `${pad(startHour)}:00`,
    end_time: `${pad(startHour + 1)}:00`,
    care_tasks: [] as string[],
  };
}

export function AssignTaskModal({
  carer,
  date,
  serviceUserId,
  startTime,
  onClose,
}: {
  carer: { user_id: number; name: string } | null;
  date: string;
  /** Pre-selected when the modal was opened by dropping a carer on a client row. */
  serviceUserId?: number | null;
  /** Pre-filled from where on the timeline the carer was dropped. */
  startTime?: string | null;
  onClose: () => void;
}) {
  const [fields, setFields] = useState(() => defaultTaskFields(date));
  const [error, setError] = useState<string | null>(null);
  const { data: serviceUsers } = useServiceUsers(1);
  const createVisit = useCreateVisit();

  useEffect(() => {
    if (!carer) return;
    const base = defaultTaskFields(date);
    // A drop already decided the client and the hour — carry both in rather
    // than making the user re-enter what they just expressed by dropping.
    setFields({
      ...base,
      ...(serviceUserId ? { service_user_id: serviceUserId } : {}),
      ...(startTime ? { start_time: startTime, end_time: addOneHour(startTime) } : {}),
    });
    setError(null);
  }, [carer, date, serviceUserId, startTime]);

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    if (!carer) return;
    setError(null);
    const payload: CreateVisitInput = {
      ...fields,
      carer_id: carer.user_id,
      visit_date: date,
    };
    try {
      await createVisit.mutateAsync(payload);
    } catch (err) {
      setError(errorMessage(err));
      return;
    }
    onClose();
  }

  return (
    <Modal
      isOpen={Boolean(carer)}
      onClose={onClose}
      title={carer ? `Assign a Task — ${carer.name}` : "Assign a Task"}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button form="assign-task-form" type="submit" isLoading={createVisit.isPending}>
            Assign
          </Button>
        </>
      }
    >
      <form id="assign-task-form" onSubmit={handleSubmit}>
        {error && (
          <div className="mb-4">
            <Alert tone="danger">{error}</Alert>
          </div>
        )}
        <p className="mb-4 text-sm text-inksoft">
          New visit for <span className="font-medium text-ink">{carer?.name}</span> on{" "}
          <span className="font-medium text-ink">{date}</span>.
        </p>
        <FormField label="Service user" htmlFor="assign-service-user">
          <Select
            id="assign-service-user"
            required
            value={fields.service_user_id || ""}
            onChange={(e) => setFields({ ...fields, service_user_id: Number(e.target.value) })}
          >
            <option value="" disabled>
              Select a service user
            </option>
            {(serviceUsers?.data ?? []).map((su) => (
              <option key={su.id} value={su.id}>
                {su.first_name} {su.last_name}
              </option>
            ))}
          </Select>
        </FormField>
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <FormField label="Start time" htmlFor="assign-start">
            <Input
              id="assign-start"
              type="time"
              required
              value={fields.start_time}
              onChange={(e) => setFields({ ...fields, start_time: e.target.value })}
            />
          </FormField>
          <FormField label="End time" htmlFor="assign-end">
            <Input
              id="assign-end"
              type="time"
              required
              value={fields.end_time}
              onChange={(e) => setFields({ ...fields, end_time: e.target.value })}
            />
          </FormField>
        </div>
        <FormField label="Care tasks" htmlFor="assign-tasks">
          <TagInput
            id="assign-tasks"
            value={fields.care_tasks}
            onChange={(care_tasks) => setFields({ ...fields, care_tasks })}
            placeholder="e.g. Morning wash"
            suggestions={COMMON_CARE_TASKS}
          />
        </FormField>
      </form>
    </Modal>
  );
}
