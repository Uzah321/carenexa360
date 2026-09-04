import { useState, type FormEvent } from "react";
import {
  Alert,
  Button,
  ConfirmDialog,
  DataTable,
  Drawer,
  EmptyState,
  FileUpload,
  FormField,
  Input,
  Modal,
  PageHeader,
  Pagination,
  RowActionsMenu,
  Select,
  StatusBadge,
  TagInput,
  type Column,
} from "../../../design-system";
import { apiErrorMessage } from "../../../lib/api-error";
import { downloadDocument, useStaffDocuments, useUploadStaffDocument } from "../../documents/api";
import {
  useCreateStaff,
  useStaff,
  useUpdateStaff,
  useUpdateStaffStatus,
  type CreateStaffInput,
  type UpdateStaffInput,
} from "../api";
import { TENANT_ROLES, type StaffMember } from "../../../lib/types";

function StaffDetailDrawer({ staff }: { staff: StaffMember }) {
  const updateStaff = useUpdateStaff(staff.id);
  const [hourlyRate, setHourlyRate] = useState(staff.hourly_rate ?? "");
  const { data: documents, isLoading } = useStaffDocuments(staff.id);
  const uploadDocument = useUploadStaffDocument(staff.id);
  const [pendingFile, setPendingFile] = useState<File | null>(null);
  const [category, setCategory] = useState("");
  const [rateError, setRateError] = useState<string | null>(null);
  const [uploadError, setUploadError] = useState<string | null>(null);

  async function handleSaveRate(event: FormEvent) {
    event.preventDefault();
    setRateError(null);
    try {
      await updateStaff.mutateAsync({ hourly_rate: hourlyRate === "" ? null : Number(hourlyRate) });
    } catch (err) {
      setRateError(apiErrorMessage(err, "Could not save the hourly rate. Please try again."));
    }
  }

  async function handleUpload() {
    if (!pendingFile) return;
    setUploadError(null);
    try {
      await uploadDocument.mutateAsync({ file: pendingFile, category: category || undefined });
      setPendingFile(null);
      setCategory("");
    } catch (err) {
      setUploadError(apiErrorMessage(err, "Could not upload this file. Please try again."));
    }
  }

  return (
    <div>
      <p className="mb-4 text-sm text-inksoft">{staff.name}</p>

      <form onSubmit={handleSaveRate} className="mb-6">
        {rateError && (
          <div className="mb-4">
            <Alert tone="danger">{rateError}</Alert>
          </div>
        )}
        <FormField label="Hourly rate (for payroll)" htmlFor="staff-hourly-rate">
          <Input
            id="staff-hourly-rate"
            type="number"
            step="0.01"
            min={0}
            value={hourlyRate}
            onChange={(e) => setHourlyRate(e.target.value)}
          />
        </FormField>
        <Button type="submit" isLoading={updateStaff.isPending}>
          Save Rate
        </Button>
      </form>

      <h3 className="mb-2 text-sm font-semibold text-ink">HR Documents</h3>
      {uploadError && (
        <div className="mb-4">
          <Alert tone="danger">{uploadError}</Alert>
        </div>
      )}
      <div className="mb-4 flex flex-wrap items-end gap-3 rounded-xl border border-line p-3">
        <div className="flex-1">
          <FileUpload onSelect={setPendingFile} />
        </div>
        <FormField label="Category" htmlFor="staff-doc-category">
          <Input id="staff-doc-category" value={category} onChange={(e) => setCategory(e.target.value)} />
        </FormField>
        <Button onClick={handleUpload} disabled={!pendingFile} isLoading={uploadDocument.isPending}>
          Upload
        </Button>
      </div>

      {!isLoading && (documents ?? []).length === 0 ? (
        <EmptyState message="No documents uploaded yet." />
      ) : (
        <ul className="space-y-2">
          {(documents ?? []).map((doc) => (
            <li key={doc.id} className="flex items-center justify-between rounded-lg border border-line p-3 text-sm">
              <span>
                {doc.original_filename}
                {doc.category && <span className="ml-2 text-xs text-inksoft">({doc.category})</span>}
              </span>
              <button
                type="button"
                className="text-sm font-medium text-teal hover:text-teal/90"
                onClick={() => downloadDocument(doc.id, doc.original_filename)}
              >
                Download
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

const EMPTY_FORM: CreateStaffInput = {
  name: "",
  email: "",
  password: "",
  role: "Carer / Support Worker",
  job_title: "",
  skills: [],
};

const STATUS_TONE: Record<StaffMember["employment_status"], "success" | "warning" | "neutral"> = {
  active: "success",
  on_leave: "warning",
  inactive: "neutral",
};

const EMPTY_EDIT_FORM: UpdateStaffInput = {
  job_title: "",
  employment_start_date: "",
  skills: [],
  employment_status: "active",
  hourly_rate: null,
};

function staffToEditForm(staff: StaffMember): UpdateStaffInput {
  return {
    job_title: staff.job_title ?? "",
    employment_start_date: staff.employment_start_date ?? "",
    skills: staff.skills,
    employment_status: staff.employment_status,
    hourly_rate: staff.hourly_rate ? Number(staff.hourly_rate) : null,
  };
}

export function StaffPage() {
  const [page, setPage] = useState(1);
  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const { data, isLoading } = useStaff(page);
  const createStaff = useCreateStaff();
  const [form, setForm] = useState<CreateStaffInput>(EMPTY_FORM);
  const [createError, setCreateError] = useState<string | null>(null);
  const [activeStaff, setActiveStaff] = useState<StaffMember | null>(null);

  const [editingStaff, setEditingStaff] = useState<StaffMember | null>(null);
  const [editForm, setEditForm] = useState<UpdateStaffInput>(EMPTY_EDIT_FORM);
  const [editError, setEditError] = useState<string | null>(null);
  const updateStaff = useUpdateStaff(editingStaff?.id ?? 0);
  const updateStatus = useUpdateStaffStatus();

  const [deactivatingStaff, setDeactivatingStaff] = useState<StaffMember | null>(null);
  const [deactivateError, setDeactivateError] = useState<string | null>(null);

  async function handleCreate(event: FormEvent) {
    event.preventDefault();
    setCreateError(null);
    try {
      await createStaff.mutateAsync(form);
      setIsCreateOpen(false);
      setForm(EMPTY_FORM);
    } catch (err) {
      setCreateError(apiErrorMessage(err, "Could not add this staff member. Please try again."));
    }
  }

  function openEdit(staff: StaffMember) {
    setEditingStaff(staff);
    setEditForm(staffToEditForm(staff));
    setEditError(null);
  }

  async function handleEdit(event: FormEvent) {
    event.preventDefault();
    setEditError(null);
    try {
      await updateStaff.mutateAsync(editForm);
      setEditingStaff(null);
    } catch (err) {
      setEditError(apiErrorMessage(err, "Could not save these changes. Please try again."));
    }
  }

  async function handleDeactivate() {
    if (!deactivatingStaff) return;
    setDeactivateError(null);
    try {
      await updateStatus.mutateAsync({ id: deactivatingStaff.id, employment_status: "inactive" });
      setDeactivatingStaff(null);
    } catch (err) {
      setDeactivateError(apiErrorMessage(err, "Could not deactivate this staff member. Please try again."));
    }
  }

  const columns: Column<StaffMember>[] = [
    { key: "name", header: "Name", render: (row) => row.name },
    { key: "email", header: "Email", render: (row) => row.email },
    { key: "role", header: "Role", render: (row) => row.roles.join(", ") || "—" },
    { key: "job_title", header: "Job Title", render: (row) => row.job_title ?? "—" },
    {
      key: "status",
      header: "Status",
      render: (row) => (
        <StatusBadge label={row.employment_status.replaceAll("_", " ")} tone={STATUS_TONE[row.employment_status]} />
      ),
    },
    {
      key: "actions",
      header: "",
      className: "w-12 text-right",
      render: (row) => (
        <RowActionsMenu
          actions={[
            { label: "Edit", onClick: () => openEdit(row) },
            { label: "Rate & documents", onClick: () => setActiveStaff(row) },
            {
              label: "Reactivate",
              onClick: () => updateStatus.mutate({ id: row.id, employment_status: "active" }),
              hidden: row.employment_status !== "inactive",
            },
            {
              label: "Deactivate",
              tone: "danger",
              onClick: () => setDeactivatingStaff(row),
              hidden: row.employment_status === "inactive",
            },
          ]}
        />
      ),
    },
  ];

  return (
    <div>
      <PageHeader
        title="Staff"
        description="Carers and other staff members in your organization."
        actions={<Button onClick={() => setIsCreateOpen(true)}>Add Staff Member</Button>}
      />

      <DataTable columns={columns} rows={data?.data ?? []} rowKey={(row) => row.id} isLoading={isLoading} />

      {data && (
        <Pagination
          currentPage={data.meta.current_page}
          lastPage={data.meta.last_page}
          onPageChange={setPage}
        />
      )}

      <Modal
        isOpen={isCreateOpen}
        onClose={() => {
          setIsCreateOpen(false);
          setCreateError(null);
        }}
        title="Add Staff Member"
        footer={
          <>
            <Button variant="secondary" onClick={() => setIsCreateOpen(false)}>
              Cancel
            </Button>
            <Button form="create-staff-form" type="submit" isLoading={createStaff.isPending}>
              Add
            </Button>
          </>
        }
      >
        <form id="create-staff-form" onSubmit={handleCreate}>
          {createError && (
            <div className="mb-4">
              <Alert tone="danger">{createError}</Alert>
            </div>
          )}
          <FormField label="Name" htmlFor="staff-name">
            <Input
              id="staff-name"
              required
              value={form.name}
              onChange={(e) => setForm({ ...form, name: e.target.value })}
            />
          </FormField>
          <FormField label="Email" htmlFor="staff-email">
            <Input
              id="staff-email"
              type="email"
              required
              value={form.email}
              onChange={(e) => setForm({ ...form, email: e.target.value })}
            />
          </FormField>
          <FormField label="Password" htmlFor="staff-password">
            <Input
              id="staff-password"
              type="password"
              required
              minLength={8}
              value={form.password}
              onChange={(e) => setForm({ ...form, password: e.target.value })}
            />
          </FormField>
          <FormField label="Role" htmlFor="staff-role">
            <Select
              id="staff-role"
              required
              value={form.role}
              onChange={(e) => setForm({ ...form, role: e.target.value })}
            >
              {TENANT_ROLES.map((role) => (
                <option key={role} value={role}>
                  {role}
                </option>
              ))}
            </Select>
          </FormField>
          <FormField label="Job title" htmlFor="staff-job-title">
            <Input
              id="staff-job-title"
              value={form.job_title ?? ""}
              onChange={(e) => setForm({ ...form, job_title: e.target.value })}
            />
          </FormField>
          <FormField label="Employment start date" htmlFor="staff-start-date">
            <Input
              id="staff-start-date"
              type="date"
              value={form.employment_start_date ?? ""}
              onChange={(e) => setForm({ ...form, employment_start_date: e.target.value })}
            />
          </FormField>
          <FormField label="Skills" htmlFor="staff-skills">
            <TagInput
              id="staff-skills"
              value={form.skills ?? []}
              onChange={(skills) => setForm({ ...form, skills })}
              placeholder="Type a skill and press Enter"
            />
          </FormField>
        </form>
      </Modal>

      <Drawer isOpen={Boolean(activeStaff)} onClose={() => setActiveStaff(null)} title="Staff Member">
        {activeStaff && <StaffDetailDrawer staff={activeStaff} />}
      </Drawer>

      <Modal
        isOpen={Boolean(editingStaff)}
        onClose={() => {
          setEditingStaff(null);
          setEditError(null);
        }}
        title="Edit Staff Member"
        footer={
          <>
            <Button variant="secondary" onClick={() => setEditingStaff(null)}>
              Cancel
            </Button>
            <Button form="edit-staff-form" type="submit" isLoading={updateStaff.isPending}>
              Save
            </Button>
          </>
        }
      >
        <form id="edit-staff-form" onSubmit={handleEdit}>
          {editError && (
            <div className="mb-4">
              <Alert tone="danger">{editError}</Alert>
            </div>
          )}
          <FormField label="Job title" htmlFor="edit-staff-job-title">
            <Input
              id="edit-staff-job-title"
              value={editForm.job_title ?? ""}
              onChange={(e) => setEditForm({ ...editForm, job_title: e.target.value })}
            />
          </FormField>
          <FormField label="Employment start date" htmlFor="edit-staff-start-date">
            <Input
              id="edit-staff-start-date"
              type="date"
              value={editForm.employment_start_date ?? ""}
              onChange={(e) => setEditForm({ ...editForm, employment_start_date: e.target.value })}
            />
          </FormField>
          <FormField label="Skills" htmlFor="edit-staff-skills">
            <TagInput
              id="edit-staff-skills"
              value={editForm.skills ?? []}
              onChange={(skills) => setEditForm({ ...editForm, skills })}
              placeholder="Type a skill and press Enter"
            />
          </FormField>
          <FormField label="Employment status" htmlFor="edit-staff-status">
            <Select
              id="edit-staff-status"
              value={editForm.employment_status}
              onChange={(e) =>
                setEditForm({ ...editForm, employment_status: e.target.value as StaffMember["employment_status"] })
              }
            >
              <option value="active">Active</option>
              <option value="on_leave">On leave</option>
              <option value="inactive">Inactive</option>
            </Select>
          </FormField>
          <FormField label="Hourly rate" htmlFor="edit-staff-rate">
            <Input
              id="edit-staff-rate"
              type="number"
              step="0.01"
              min={0}
              value={editForm.hourly_rate ?? ""}
              onChange={(e) => setEditForm({ ...editForm, hourly_rate: e.target.value === "" ? null : Number(e.target.value) })}
            />
          </FormField>
        </form>
      </Modal>

      <ConfirmDialog
        isOpen={Boolean(deactivatingStaff)}
        title="Deactivate staff member"
        message={`Deactivate ${deactivatingStaff?.name}? They'll no longer appear as an option for scheduling until reactivated.`}
        confirmLabel="Deactivate"
        tone="danger"
        isLoading={updateStatus.isPending}
        error={deactivateError}
        onConfirm={handleDeactivate}
        onCancel={() => {
          setDeactivatingStaff(null);
          setDeactivateError(null);
        }}
      />
    </div>
  );
}
