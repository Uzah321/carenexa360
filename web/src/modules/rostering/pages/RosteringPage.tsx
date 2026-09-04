import { useState, type FormEvent } from "react";
import {
  Alert,
  Button,
  DataTable,
  FilterBar,
  FormField,
  Input,
  Modal,
  PageHeader,
  Select,
  StatusBadge,
  type Column,
} from "../../../design-system";
import { useCreateShift, useShifts, type CreateShiftInput } from "../api";
import { useStaff } from "../../staff/api";
import { SHIFT_TYPES, type Shift, type ShiftStatus } from "../../../lib/types";

const STATUS_TONE: Record<ShiftStatus, "success" | "info" | "neutral"> = {
  scheduled: "info",
  confirmed: "success",
  completed: "success",
  cancelled: "neutral",
};

const EMPTY_FORM: CreateShiftInput = {
  user_id: 0,
  shift_date: "",
  start_time: "",
  end_time: "",
  shift_type: "day",
};

function extractErrorMessage(err: unknown): string {
  const response = (err as { response?: { data?: { errors?: Record<string, string[]>; message?: string } } })
    .response;
  const errors = response?.data?.errors;
  if (errors) return Object.values(errors).flat().join(" ");
  return response?.data?.message ?? "Something went wrong.";
}

export function RosteringPage() {
  const [date, setDate] = useState("");
  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [form, setForm] = useState<CreateShiftInput>(EMPTY_FORM);
  const [error, setError] = useState<string | null>(null);

  const { data, isLoading } = useShifts({ date: date || undefined });
  const { data: staff } = useStaff(1);
  const createShift = useCreateShift();

  async function handleCreate(event: FormEvent) {
    event.preventDefault();
    setError(null);
    try {
      await createShift.mutateAsync(form);
      setIsCreateOpen(false);
      setForm(EMPTY_FORM);
    } catch (err) {
      setError(extractErrorMessage(err));
    }
  }

  const columns: Column<Shift>[] = [
    { key: "date", header: "Date", render: (row) => row.shift_date },
    { key: "time", header: "Time", render: (row) => `${row.start_time}–${row.end_time}` },
    { key: "staff", header: "Staff", render: (row) => row.user_name ?? "—" },
    { key: "type", header: "Type", render: (row) => row.shift_type },
    {
      key: "status",
      header: "Status",
      render: (row) => <StatusBadge label={row.status} tone={STATUS_TONE[row.status]} />,
    },
  ];

  return (
    <div>
      <PageHeader
        title="Rostering"
        description="Staff shifts across your organization."
        actions={<Button onClick={() => setIsCreateOpen(true)}>New Shift</Button>}
      />

      <FilterBar>
        <FormField label="Date" htmlFor="shift-filter-date">
          <Input id="shift-filter-date" type="date" value={date} onChange={(e) => setDate(e.target.value)} />
        </FormField>
      </FilterBar>

      <DataTable columns={columns} rows={data?.data ?? []} rowKey={(row) => row.id} isLoading={isLoading} />

      <Modal
        isOpen={isCreateOpen}
        onClose={() => setIsCreateOpen(false)}
        title="New Shift"
        footer={
          <>
            <Button variant="secondary" onClick={() => setIsCreateOpen(false)}>
              Cancel
            </Button>
            <Button form="create-shift-form" type="submit" isLoading={createShift.isPending}>
              Create
            </Button>
          </>
        }
      >
        <form id="create-shift-form" onSubmit={handleCreate}>
          {error && (
            <div className="mb-4">
              <Alert tone="danger">{error}</Alert>
            </div>
          )}
          <FormField label="Staff member" htmlFor="shift-user">
            <Select
              id="shift-user"
              required
              value={form.user_id || ""}
              onChange={(e) => setForm({ ...form, user_id: Number(e.target.value) })}
            >
              <option value="" disabled>
                Select a staff member
              </option>
              {(staff?.data ?? []).map((s) => (
                <option key={s.id} value={s.user_id}>
                  {s.name}
                </option>
              ))}
            </Select>
          </FormField>
          <FormField label="Shift date" htmlFor="shift-date">
            <Input
              id="shift-date"
              type="date"
              required
              value={form.shift_date}
              onChange={(e) => setForm({ ...form, shift_date: e.target.value })}
            />
          </FormField>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <FormField label="Start time" htmlFor="shift-start">
              <Input
                id="shift-start"
                type="time"
                required
                value={form.start_time}
                onChange={(e) => setForm({ ...form, start_time: e.target.value })}
              />
            </FormField>
            <FormField label="End time" htmlFor="shift-end">
              <Input
                id="shift-end"
                type="time"
                required
                value={form.end_time}
                onChange={(e) => setForm({ ...form, end_time: e.target.value })}
              />
            </FormField>
          </div>
          <FormField label="Shift type" htmlFor="shift-type">
            <Select
              id="shift-type"
              value={form.shift_type}
              onChange={(e) => setForm({ ...form, shift_type: e.target.value as CreateShiftInput["shift_type"] })}
            >
              {SHIFT_TYPES.map((type) => (
                <option key={type} value={type}>
                  {type}
                </option>
              ))}
            </Select>
          </FormField>
        </form>
      </Modal>
    </div>
  );
}
