import { useState, type FormEvent } from "react";
import { Link } from "react-router-dom";
import {
  Alert,
  Button,
  DataTable,
  FilterBar,
  FormField,
  Input,
  Modal,
  Pagination,
  RowActionsMenu,
  Select,
  StatusBadge,
  Textarea,
  type Column,
} from "../../../design-system";
import { apiErrorMessage } from "../../../lib/api-error";
import { todayIso } from "../../../lib/dates";
import {
  COMPLAINT_CATEGORIES,
  COMPLAINT_CHANNELS,
  COMPLAINT_OUTCOMES,
  COMPLAINT_SEVERITIES,
  COMPLAINT_STATUSES,
  type Complaint,
  type ComplaintStatus,
} from "../../../lib/types";
import { useServiceUsers } from "../../service-users/api";
import { useStaff } from "../../staff/api";
import { label, useComplaints, useSaveComplaint, type ComplaintInput } from "../api";

const SEVERITY_TONE = { low: "neutral", medium: "warning", high: "danger" } as const;
const STATUS_TONE = { received: "info", investigating: "warning", resolved: "success", closed: "neutral", withdrawn: "neutral" } as const;

const EMPTY: ComplaintInput = {
  received_date: todayIso(),
  complainant_name: "",
  complainant_relationship: "",
  channel: "phone",
  category: "quality_of_care",
  severity: "medium",
  description: "",
  status: "received",
};

function toForm(c: Complaint): ComplaintInput {
  return {
    service_user_id: c.service_user_id,
    received_date: c.received_date,
    complainant_name: c.complainant_name,
    complainant_relationship: c.complainant_relationship ?? "",
    channel: c.channel,
    category: c.category,
    severity: c.severity,
    description: c.description,
    status: c.status,
    assigned_to: c.assigned_to,
    acknowledged_date: c.acknowledged_date ?? "",
    response_due_date: c.response_due_date ?? "",
    outcome: c.outcome,
    findings: c.findings ?? "",
    actions_taken: c.actions_taken ?? "",
    resolved_date: c.resolved_date ?? "",
  };
}

/** Blank optional fields go to the API as null, not "". */
function toPayload(form: ComplaintInput): ComplaintInput {
  return Object.fromEntries(Object.entries(form).map(([k, v]) => [k, v === "" ? null : v])) as ComplaintInput;
}

function Options({ values }: { values: readonly string[] }) {
  return (
    <>
      {values.map((v) => (
        <option key={v} value={v}>
          {label(v)}
        </option>
      ))}
    </>
  );
}

export function ComplaintsPage() {
  const [status, setStatus] = useState<ComplaintStatus | "">("");
  const [page, setPage] = useState(1);
  const { data, isLoading } = useComplaints({ status: status || undefined, page });
  const { data: serviceUsers } = useServiceUsers(1);
  const { data: staff } = useStaff(1, 500);
  const save = useSaveComplaint();

  // `editingId` is null while logging a new complaint.
  const [isOpen, setIsOpen] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [form, setForm] = useState<ComplaintInput>(EMPTY);
  const [error, setError] = useState<string | null>(null);
  const set = (patch: ComplaintInput) => setForm((prev) => ({ ...prev, ...patch }));

  function open(complaint: Complaint | null) {
    setEditingId(complaint?.id ?? null);
    setForm(complaint ? toForm(complaint) : { ...EMPTY, received_date: todayIso() });
    setError(null);
    setIsOpen(true);
  }

  async function handleSave(event: FormEvent) {
    event.preventDefault();
    setError(null);
    try {
      await save.mutateAsync({ id: editingId ?? undefined, ...toPayload(form) });
      setIsOpen(false);
    } catch (err) {
      setError(apiErrorMessage(err, "Could not save this complaint. Please try again."));
    }
  }

  const columns: Column<Complaint>[] = [
    { key: "received", header: "Received", render: (row) => row.received_date },
    {
      key: "complainant",
      header: "Complainant",
      render: (row) => (
        <div>
          <div className="font-medium text-ink">{row.complainant_name}</div>
          {row.complainant_relationship && <div className="text-xs text-inksoft">{row.complainant_relationship}</div>}
        </div>
      ),
    },
    {
      key: "client",
      header: "Client",
      render: (row) =>
        row.service_user_id ? (
          <Link to={`/service-users/${row.service_user_id}`} className="font-medium text-teal hover:text-teal/90">
            {row.service_user_name ?? "—"}
          </Link>
        ) : (
          "—"
        ),
    },
    { key: "category", header: "Category", render: (row) => label(row.category) },
    { key: "severity", header: "Severity", render: (row) => <StatusBadge label={label(row.severity)} tone={SEVERITY_TONE[row.severity]} /> },
    {
      key: "status",
      header: "Status",
      render: (row) => (
        <div className="flex flex-wrap items-center gap-1.5">
          <StatusBadge label={label(row.status)} tone={STATUS_TONE[row.status]} />
          {row.is_overdue && <StatusBadge label="Overdue" tone="danger" />}
        </div>
      ),
    },
    { key: "due", header: "Response Due", render: (row) => row.response_due_date ?? "—" },
    { key: "assigned", header: "Investigating", render: (row) => row.assigned_to_name ?? "Unassigned" },
    {
      key: "actions",
      header: "",
      className: "w-12 text-right",
      render: (row) => <RowActionsMenu actions={[{ label: "Open / Update", onClick: () => open(row) }]} label={`Complaint from ${row.complainant_name}`} />,
    },
  ];

  const isClosing = form.status === "resolved" || form.status === "closed";

  return (
    <div>
      <div className="mb-6 rounded-2xl border border-line bg-white px-5 py-4">
        <div className="flex items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-extrabold tracking-tight text-ink">Complaints</h1>
            <p className="mt-1 text-sm text-inksoft">
              Log, investigate and respond to complaints. A full response is due 28 days after receipt unless you set another date.
            </p>
          </div>
          <Button onClick={() => open(null)}>Log Complaint</Button>
        </div>
      </div>

      <FilterBar>
        <FormField label="Status" htmlFor="complaint-filter-status">
          <Select id="complaint-filter-status" value={status} onChange={(e) => setStatus(e.target.value as ComplaintStatus | "")}>
            <option value="">All statuses</option>
            <Options values={COMPLAINT_STATUSES} />
          </Select>
        </FormField>
      </FilterBar>

      <DataTable columns={columns} rows={data?.data ?? []} rowKey={(row) => row.id} isLoading={isLoading} />
      {data && <Pagination currentPage={data.meta.current_page} lastPage={data.meta.last_page} onPageChange={setPage} />}

      <Modal
        isOpen={isOpen}
        onClose={() => setIsOpen(false)}
        title={editingId ? "Complaint" : "Log Complaint"}
        footer={
          <>
            <Button variant="secondary" onClick={() => setIsOpen(false)}>
              Cancel
            </Button>
            <Button form="complaint-form" type="submit" isLoading={save.isPending}>
              Save
            </Button>
          </>
        }
      >
        <form id="complaint-form" onSubmit={handleSave}>
          {error && (
            <div className="mb-4">
              <Alert tone="danger">{error}</Alert>
            </div>
          )}
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <FormField label="Complainant" htmlFor="c-name">
              <Input id="c-name" required value={form.complainant_name ?? ""} onChange={(e) => set({ complainant_name: e.target.value })} />
            </FormField>
            <FormField label="Relationship" htmlFor="c-rel">
              <Input
                id="c-rel"
                placeholder="e.g. Daughter, client, social worker"
                value={form.complainant_relationship ?? ""}
                onChange={(e) => set({ complainant_relationship: e.target.value })}
              />
            </FormField>
            <FormField label="Received" htmlFor="c-received">
              <Input id="c-received" type="date" required value={form.received_date ?? ""} onChange={(e) => set({ received_date: e.target.value })} />
            </FormField>
            <FormField label="Received by" htmlFor="c-channel">
              <Select id="c-channel" value={form.channel} onChange={(e) => set({ channel: e.target.value as Complaint["channel"] })}>
                <Options values={COMPLAINT_CHANNELS} />
              </Select>
            </FormField>
            <FormField label="Client (if about one)" htmlFor="c-client">
              <Select
                id="c-client"
                value={form.service_user_id ?? ""}
                onChange={(e) => set({ service_user_id: e.target.value ? Number(e.target.value) : null })}
              >
                <option value="">Not about a specific client</option>
                {(serviceUsers?.data ?? []).map((su) => (
                  <option key={su.id} value={su.id}>
                    {su.first_name} {su.last_name}
                  </option>
                ))}
              </Select>
            </FormField>
            <FormField label="Category" htmlFor="c-category">
              <Select id="c-category" value={form.category} onChange={(e) => set({ category: e.target.value as Complaint["category"] })}>
                <Options values={COMPLAINT_CATEGORIES} />
              </Select>
            </FormField>
            <FormField label="Severity" htmlFor="c-severity">
              <Select id="c-severity" value={form.severity} onChange={(e) => set({ severity: e.target.value as Complaint["severity"] })}>
                <Options values={COMPLAINT_SEVERITIES} />
              </Select>
            </FormField>
            <FormField label="Investigating" htmlFor="c-assigned">
              <Select
                id="c-assigned"
                value={form.assigned_to ?? ""}
                onChange={(e) => set({ assigned_to: e.target.value ? Number(e.target.value) : null })}
              >
                <option value="">Unassigned</option>
                {(staff?.data ?? []).map((s) => (
                  <option key={s.id} value={s.user_id}>
                    {s.name}
                  </option>
                ))}
              </Select>
            </FormField>
          </div>
          <FormField label="What the complaint is about" htmlFor="c-description">
            <Textarea id="c-description" required value={form.description ?? ""} onChange={(e) => set({ description: e.target.value })} />
          </FormField>

          {editingId && (
            <>
              <h3 className="mb-3 mt-5 text-sm font-semibold text-teal">Investigation and response</h3>
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <FormField label="Status" htmlFor="c-status">
                  <Select id="c-status" value={form.status} onChange={(e) => set({ status: e.target.value as ComplaintStatus })}>
                    <Options values={COMPLAINT_STATUSES} />
                  </Select>
                </FormField>
                <FormField label="Acknowledged" htmlFor="c-ack">
                  <Input id="c-ack" type="date" value={form.acknowledged_date ?? ""} onChange={(e) => set({ acknowledged_date: e.target.value })} />
                </FormField>
                <FormField label="Response due" htmlFor="c-due">
                  <Input id="c-due" type="date" value={form.response_due_date ?? ""} onChange={(e) => set({ response_due_date: e.target.value })} />
                </FormField>
              </div>
              <FormField label="Findings" htmlFor="c-findings">
                <Textarea id="c-findings" value={form.findings ?? ""} onChange={(e) => set({ findings: e.target.value })} />
              </FormField>
              <FormField label="Actions taken" htmlFor="c-actions">
                <Textarea id="c-actions" value={form.actions_taken ?? ""} onChange={(e) => set({ actions_taken: e.target.value })} />
              </FormField>
              {isClosing && (
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                  <FormField label="Outcome" htmlFor="c-outcome">
                    <Select
                      id="c-outcome"
                      value={form.outcome ?? ""}
                      onChange={(e) => set({ outcome: (e.target.value || null) as Complaint["outcome"] })}
                    >
                      <option value="">Not decided</option>
                      <Options values={COMPLAINT_OUTCOMES} />
                    </Select>
                  </FormField>
                  <FormField label="Resolved on" htmlFor="c-resolved">
                    <Input id="c-resolved" type="date" value={form.resolved_date ?? ""} onChange={(e) => set({ resolved_date: e.target.value })} />
                  </FormField>
                </div>
              )}
            </>
          )}
        </form>
      </Modal>
    </div>
  );
}
