import { useState, type FormEvent } from "react";
import { Link } from "react-router-dom";
import {
  Alert,
  Button,
  Checkbox,
  ConfirmDialog,
  DataTable,
  FilterBar,
  FormField,
  Input,
  Modal,
  Pagination,
  RowActionsMenu,
  Select,
  StatusBadge,
  TagInput,
  type Column,
} from "../../../design-system";
import { apiErrorMessage } from "../../../lib/api-error";
import { useCreateVisit, useUpdateVisitStatus, useVisits, type CreateVisitInput } from "../api";
import { EditVisitModal } from "../components/EditVisitModal";
import { useServiceUsers } from "../../service-users/api";
import { useStaff } from "../../staff/api";
import { deliversVisits } from "../../../lib/types";
import type { Visit, VisitStatus } from "../../../lib/types";
import { todayIso } from "../../../lib/dates";

const STATUS_TONE: Record<VisitStatus, "success" | "warning" | "neutral" | "danger" | "info"> = {
  scheduled: "info",
  in_progress: "warning",
  completed: "success",
  missed: "danger",
  cancelled: "neutral",
};

const WEEKDAY_LABELS = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];

const EMPTY_FORM: CreateVisitInput = {
  service_user_id: 0,
  visit_date: "",
  start_time: "",
  end_time: "",
  care_tasks: [],
  required_skills: [],
};

const CANCELLABLE_STATUSES: VisitStatus[] = ["scheduled", "in_progress"];

export function VisitsPage() {
  // Defaults to today, matching Schedule/My Day — an unfiltered list sorted
  // oldest-first buried new visits 20+ rows down with no pagination to reach
  // them, which is exactly what made "create a visit" look like it silently
  // did nothing.
  const [date, setDate] = useState(todayIso());
  const [page, setPage] = useState(1);
  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [form, setForm] = useState<CreateVisitInput>(EMPTY_FORM);
  const [repeats, setRepeats] = useState(false);
  const [weekdays, setWeekdays] = useState<number[]>([]);
  const [until, setUntil] = useState("");
  const [warnings, setWarnings] = useState<string[]>([]);
  const [error, setError] = useState<string | null>(null);

  const { data, isLoading } = useVisits({ date: date || undefined, page });
  const { data: serviceUsers } = useServiceUsers(1);
  const { data: staff } = useStaff(1);
  const createVisit = useCreateVisit();

  const [editingVisit, setEditingVisit] = useState<Visit | null>(null);
  const updateStatus = useUpdateVisitStatus();

  const [cancellingVisit, setCancellingVisit] = useState<Visit | null>(null);
  const [cancelError, setCancelError] = useState<string | null>(null);

  async function handleCancelVisit() {
    if (!cancellingVisit) return;
    setCancelError(null);
    try {
      await updateStatus.mutateAsync({ id: cancellingVisit.id, status: "cancelled" });
      setCancellingVisit(null);
    } catch (err) {
      setCancelError(apiErrorMessage(err, "Could not cancel this visit. Please try again."));
    }
  }

  async function handleCreate(event: FormEvent) {
    event.preventDefault();
    setError(null);
    const payload: CreateVisitInput = {
      ...form,
      recurrence: repeats && weekdays.length > 0 ? { weekdays, until } : undefined,
    };
    let result;
    try {
      result = await createVisit.mutateAsync(payload);
    } catch (err) {
      const response = (err as { response?: { data?: { errors?: Record<string, string[]> } } }).response;
      const errors = response?.data?.errors;
      setError(errors ? Object.values(errors).flat().join(" ") : "Something went wrong.");
      return;
    }
    setWarnings(result.warnings);
    if (result.warnings.length === 0) {
      setIsCreateOpen(false);
      setForm(EMPTY_FORM);
      setRepeats(false);
      setWeekdays([]);
      setUntil("");
      // Land back on page 1 so the visit just created — almost always the
      // most recent thing sorted into the list — is actually on screen.
      setPage(1);
    }
  }

  function openCreate() {
    setForm({ ...EMPTY_FORM, visit_date: date || todayIso() });
    setIsCreateOpen(true);
  }

  const columns: Column<Visit>[] = [
    {
      key: "date",
      header: "Date",
      render: (row) => (
        <Link to={`/visits/${row.id}`} className="font-medium text-teal hover:text-teal/90">
          {row.visit_date}
        </Link>
      ),
    },
    { key: "time", header: "Time", render: (row) => `${row.start_time}–${row.end_time}` },
    { key: "service_user", header: "Service User", render: (row) => row.service_user_name ?? "—" },
    { key: "carer", header: "Carer", render: (row) => row.carer_name ?? "Unassigned" },
    {
      key: "status",
      header: "Status",
      render: (row) => <StatusBadge label={row.status.replaceAll("_", " ")} tone={STATUS_TONE[row.status]} />,
    },
    {
      key: "actions",
      header: "",
      className: "w-12 text-right",
      render: (row) => (
        <RowActionsMenu
          actions={[
            { label: "Edit", onClick: () => setEditingVisit(row) },
            {
              label: "Cancel visit",
              tone: "danger",
              onClick: () => setCancellingVisit(row),
              hidden: !CANCELLABLE_STATUSES.includes(row.status),
            },
          ]}
        />
      ),
    },
  ];

  return (
    <div>
      <div className="mb-6 rounded-2xl border border-line bg-white px-5 py-4">
        <div className="flex items-start justify-between">
          <div>
            <h1 className="text-2xl font-extrabold tracking-tight text-ink">Visits</h1>
            <p className="mt-1 text-sm text-inksoft">Scheduled care visits.</p>
          </div>
          <div className="flex items-center gap-2">
            <Link to="/visits/route" className="text-sm font-medium text-teal hover:text-teal/90">
              View Route Planner
            </Link>
            <Button onClick={openCreate}>New Visit</Button>
          </div>
        </div>
      </div>

      <FilterBar>
        <FormField label="Date" htmlFor="filter-date">
          <Input
            id="filter-date"
            type="date"
            value={date}
            onChange={(e) => {
              setDate(e.target.value);
              setPage(1);
            }}
          />
        </FormField>
      </FilterBar>

      <DataTable columns={columns} rows={data?.data ?? []} rowKey={(row) => row.id} isLoading={isLoading} />

      {data && <Pagination currentPage={data.meta.current_page} lastPage={data.meta.last_page} onPageChange={setPage} />}

      <Modal
        isOpen={isCreateOpen}
        onClose={() => setIsCreateOpen(false)}
        title="New Visit"
        footer={
          <>
            <Button variant="secondary" onClick={() => setIsCreateOpen(false)}>
              Cancel
            </Button>
            <Button form="create-visit-form" type="submit" isLoading={createVisit.isPending}>
              Create
            </Button>
          </>
        }
      >
        <form id="create-visit-form" onSubmit={handleCreate}>
          {warnings.length > 0 && (
            <div className="mb-4">
              <Alert tone="warning" title="Created with warnings">
                {warnings.join(" ")}
              </Alert>
            </div>
          )}
          {error && (
            <div className="mb-4">
              <Alert tone="danger">{error}</Alert>
            </div>
          )}
          <FormField label="Service user" htmlFor="visit-service-user">
            <Select
              id="visit-service-user"
              required
              value={form.service_user_id || ""}
              onChange={(e) => setForm({ ...form, service_user_id: Number(e.target.value) })}
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
          <FormField label="Carer" htmlFor="visit-carer">
            <Select
              id="visit-carer"
              value={form.carer_id ?? ""}
              onChange={(e) => setForm({ ...form, carer_id: e.target.value ? Number(e.target.value) : null })}
            >
              <option value="">Unassigned</option>
              {(staff?.data ?? []).filter((s) => deliversVisits(s.roles)).map((s) => (
                <option key={s.id} value={s.user_id}>
                  {s.name}
                </option>
              ))}
            </Select>
          </FormField>
          <FormField label="Visit date" htmlFor="visit-date">
            <Input
              id="visit-date"
              type="date"
              required
              value={form.visit_date}
              onChange={(e) => setForm({ ...form, visit_date: e.target.value })}
            />
          </FormField>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <FormField label="Start time" htmlFor="visit-start">
              <Input
                id="visit-start"
                type="time"
                required
                value={form.start_time}
                onChange={(e) => setForm({ ...form, start_time: e.target.value })}
              />
            </FormField>
            <FormField label="End time" htmlFor="visit-end">
              <Input
                id="visit-end"
                type="time"
                required
                value={form.end_time}
                onChange={(e) => setForm({ ...form, end_time: e.target.value })}
              />
            </FormField>
          </div>
          <FormField label="Care tasks" htmlFor="visit-tasks">
            <TagInput
              id="visit-tasks"
              value={form.care_tasks ?? []}
              onChange={(care_tasks) => setForm({ ...form, care_tasks })}
              placeholder="e.g. Morning wash"
            />
          </FormField>
          <FormField label="Required skills" htmlFor="visit-skills">
            <TagInput
              id="visit-skills"
              value={form.required_skills ?? []}
              onChange={(required_skills) => setForm({ ...form, required_skills })}
              placeholder="e.g. manual_handling"
            />
          </FormField>

          <div className="mb-2 mt-4">
            <Checkbox
              id="visit-repeats"
              label="Repeats on selected weekdays"
              checked={repeats}
              onChange={(e) => setRepeats(e.target.checked)}
            />
          </div>
          {repeats && (
            <div className="mb-4 rounded-xl border border-line p-3">
              <div className="mb-2 flex flex-wrap gap-2">
                {WEEKDAY_LABELS.map((label, index) => (
                  <button
                    key={label}
                    type="button"
                    onClick={() =>
                      setWeekdays((prev) =>
                        prev.includes(index) ? prev.filter((d) => d !== index) : [...prev, index],
                      )
                    }
                    className={`rounded-full border px-2.5 py-1 text-xs font-medium transition-colors duration-150 ${
                      weekdays.includes(index)
                        ? "border-teal bg-tealtint text-teal"
                        : "border-line text-inksoft"
                    }`}
                  >
                    {label}
                  </button>
                ))}
              </div>
              <FormField label="Repeat until" htmlFor="visit-until">
                <Input id="visit-until" type="date" value={until} onChange={(e) => setUntil(e.target.value)} />
              </FormField>
            </div>
          )}
        </form>
      </Modal>

      <EditVisitModal visit={editingVisit} onClose={() => setEditingVisit(null)} />

      <ConfirmDialog
        isOpen={Boolean(cancellingVisit)}
        title="Cancel visit"
        message={
          cancellingVisit
            ? `Cancel the ${cancellingVisit.visit_date} ${cancellingVisit.start_time} visit for ${cancellingVisit.service_user_name ?? "this service user"}?`
            : ""
        }
        confirmLabel="Cancel visit"
        tone="danger"
        isLoading={updateStatus.isPending}
        error={cancelError}
        onConfirm={handleCancelVisit}
        onCancel={() => {
          setCancellingVisit(null);
          setCancelError(null);
        }}
      />
    </div>
  );
}
