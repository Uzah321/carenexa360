import { useState, type FormEvent } from "react";
import {
  Alert,
  Button,
  Card,
  CardBody,
  CardHeader,
  DataTable,
  EmptyState,
  FormField,
  Input,
  Modal,
  Select,
  StatusBadge,
  Textarea,
  type Column,
} from "../../../design-system";
import { useAuth } from "../../../lib/auth-context";
import { apiErrorMessage } from "../../../lib/api-error";
import { useCreateLeaveRequest, useLeaveRequests, useUpdateLeaveRequest, type CreateLeaveRequestInput } from "../api";
import { HR_ROLES, LEAVE_TYPES, type LeaveRequest, type LeaveStatus, type LeaveType } from "../../../lib/types";

const STATUS_TONE: Record<LeaveStatus, "warning" | "success" | "danger" | "neutral"> = {
  pending: "warning",
  approved: "success",
  rejected: "danger",
  cancelled: "neutral",
};

const EMPTY_FORM: CreateLeaveRequestInput = { type: "annual", start_date: "", end_date: "" };

function AllRequestsSection() {
  const [status, setStatus] = useState<LeaveStatus | "">("pending");
  const { data, isLoading } = useLeaveRequests({ status: status || undefined });
  const updateLeaveRequest = useUpdateLeaveRequest();

  const columns: Column<LeaveRequest>[] = [
    { key: "user", header: "Staff", render: (row) => row.user_name ?? "—" },
    { key: "type", header: "Type", render: (row) => row.type },
    { key: "dates", header: "Dates", render: (row) => `${row.start_date} – ${row.end_date}` },
    { key: "reason", header: "Reason", render: (row) => row.reason ?? "—" },
    {
      key: "status",
      header: "Status",
      render: (row) => <StatusBadge label={row.status} tone={STATUS_TONE[row.status]} />,
    },
    {
      key: "actions",
      header: "",
      render: (row) =>
        row.status === "pending" ? (
          <div className="flex gap-2">
            <button
              type="button"
              className="text-sm font-medium text-teal hover:text-teal/90"
              onClick={() => updateLeaveRequest.mutate({ id: row.id, status: "approved" })}
            >
              Approve
            </button>
            <button
              type="button"
              className="text-sm font-medium text-red-600 hover:text-red-500"
              onClick={() => updateLeaveRequest.mutate({ id: row.id, status: "rejected" })}
            >
              Reject
            </button>
          </div>
        ) : null,
    },
  ];

  return (
    <Card>
      <CardHeader>
        <div className="flex items-center gap-3">
          <span>All Requests</span>
          <Select value={status} onChange={(e) => setStatus(e.target.value as LeaveStatus | "")} className="w-auto">
            <option value="">All</option>
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
            <option value="cancelled">Cancelled</option>
          </Select>
        </div>
      </CardHeader>
      <CardBody>
        {!isLoading && (data?.data ?? []).length === 0 ? (
          <EmptyState message="No leave requests found." />
        ) : (
          <DataTable columns={columns} rows={data?.data ?? []} rowKey={(row) => row.id} isLoading={isLoading} />
        )}
      </CardBody>
    </Card>
  );
}

export function LeavePage() {
  const { hasAnyRole, user } = useAuth();
  const isHrAdmin = hasAnyRole(HR_ROLES);

  // An HR-role viewer's unfiltered index() call returns everyone's requests
  // (that's what powers the "All Requests" admin section below), so "My
  // Requests" must explicitly filter to their own id rather than relying on
  // the backend's default scoping, which only self-limits non-admin viewers.
  const { data: myRequests, isLoading } = useLeaveRequests({ user_id: user?.id });
  const createLeaveRequest = useCreateLeaveRequest();
  const updateLeaveRequest = useUpdateLeaveRequest();

  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [form, setForm] = useState<CreateLeaveRequestInput>(EMPTY_FORM);
  const [error, setError] = useState<string | null>(null);

  async function handleCreate(event: FormEvent) {
    event.preventDefault();
    setError(null);
    try {
      await createLeaveRequest.mutateAsync(form);
      setIsCreateOpen(false);
      setForm(EMPTY_FORM);
    } catch (err) {
      setError(apiErrorMessage(err, "Could not submit the leave request. Please try again."));
    }
  }

  const columns: Column<LeaveRequest>[] = [
    { key: "type", header: "Type", render: (row) => row.type },
    { key: "dates", header: "Dates", render: (row) => `${row.start_date} – ${row.end_date}` },
    { key: "reason", header: "Reason", render: (row) => row.reason ?? "—" },
    {
      key: "status",
      header: "Status",
      render: (row) => <StatusBadge label={row.status} tone={STATUS_TONE[row.status]} />,
    },
    {
      key: "actions",
      header: "",
      render: (row) =>
        row.status === "pending" ? (
          <button
            type="button"
            className="text-sm font-medium text-inksoft hover:text-ink"
            onClick={() => updateLeaveRequest.mutate({ id: row.id, status: "cancelled" })}
          >
            Cancel
          </button>
        ) : null,
    },
  ];

  return (
    <div>
      <div className="mb-6 rounded-2xl border border-line bg-white px-5 py-4">
        <div className="flex items-start justify-between">
          <div>
            <h1 className="text-2xl font-extrabold tracking-tight text-ink">Leave</h1>
            <p className="mt-1 text-sm text-inksoft">Request and track time off.</p>
          </div>
          <Button onClick={() => setIsCreateOpen(true)}>Request Leave</Button>
        </div>
      </div>

      <Card>
        <CardHeader>My Requests</CardHeader>
        <CardBody>
          {!isLoading && (myRequests?.data ?? []).length === 0 ? (
            <EmptyState message="No leave requests yet." />
          ) : (
            <DataTable columns={columns} rows={myRequests?.data ?? []} rowKey={(row) => row.id} isLoading={isLoading} />
          )}
        </CardBody>
      </Card>

      {isHrAdmin && (
        <div className="mt-6">
          <AllRequestsSection />
        </div>
      )}

      <Modal
        isOpen={isCreateOpen}
        onClose={() => {
          setIsCreateOpen(false);
          setError(null);
        }}
        title="Request Leave"
        footer={
          <>
            <Button variant="secondary" onClick={() => setIsCreateOpen(false)}>
              Cancel
            </Button>
            <Button form="new-leave-form" type="submit" isLoading={createLeaveRequest.isPending}>
              Submit
            </Button>
          </>
        }
      >
        <form id="new-leave-form" onSubmit={handleCreate}>
          {error && (
            <div className="mb-4">
              <Alert tone="danger">{error}</Alert>
            </div>
          )}
          <FormField label="Type" htmlFor="leave-type">
            <Select
              id="leave-type"
              value={form.type}
              onChange={(e) => setForm({ ...form, type: e.target.value as LeaveType })}
            >
              {LEAVE_TYPES.map((type) => (
                <option key={type} value={type}>
                  {type}
                </option>
              ))}
            </Select>
          </FormField>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <FormField label="Start date" htmlFor="leave-start">
              <Input
                id="leave-start"
                type="date"
                required
                value={form.start_date}
                onChange={(e) => setForm({ ...form, start_date: e.target.value })}
              />
            </FormField>
            <FormField label="End date" htmlFor="leave-end">
              <Input
                id="leave-end"
                type="date"
                required
                value={form.end_date}
                onChange={(e) => setForm({ ...form, end_date: e.target.value })}
              />
            </FormField>
          </div>
          <FormField label="Reason" htmlFor="leave-reason">
            <Textarea
              id="leave-reason"
              value={form.reason ?? ""}
              onChange={(e) => setForm({ ...form, reason: e.target.value })}
            />
          </FormField>
        </form>
      </Modal>
    </div>
  );
}
