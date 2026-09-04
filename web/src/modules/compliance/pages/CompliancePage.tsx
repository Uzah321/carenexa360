import { useState, type FormEvent } from "react";
import {
  Alert,
  Button,
  Card,
  CardBody,
  ConfirmDialog,
  DataTable,
  Drawer,
  EmptyState,
  FileUpload,
  FormField,
  Input,
  Modal,
  RowActionsMenu,
  Select,
  StatusBadge,
  Textarea,
  type Column,
  type RowAction,
} from "../../../design-system";
import { useAuth } from "../../../lib/auth-context";
import { apiErrorMessage } from "../../../lib/api-error";
import { downloadDocument } from "../../documents/api";
import { useStaff } from "../../staff/api";
import {
  useComplianceDocuments,
  useComplianceRequirements,
  useCreateComplianceRequirement,
  useDeleteComplianceRequirement,
  useUpdateComplianceRequirement,
  useUploadComplianceDocument,
  type CreateComplianceRequirementInput,
} from "../api";
import {
  COMPLIANCE_REQUIREMENT_STATUSES,
  COMPLIANCE_ROLES,
  type ComplianceRequirement,
  type ComplianceRequirementStatus,
  type TrainingRecordStatus,
} from "../../../lib/types";

const EXPIRY_TONE: Record<TrainingRecordStatus, "success" | "warning" | "danger" | "neutral"> = {
  valid: "success",
  expiring_soon: "warning",
  expired: "danger",
  no_expiry: "neutral",
};

const EMPTY_FORM: CreateComplianceRequirementInput = { name: "" };

function ComplianceStatusSelect({ requirement }: { requirement: ComplianceRequirement }) {
  const updateRequirement = useUpdateComplianceRequirement(requirement.id);

  return (
    <Select
      value={requirement.status}
      disabled={updateRequirement.isPending}
      onChange={(e) => updateRequirement.mutate({ status: e.target.value as ComplianceRequirementStatus })}
      className="w-auto text-xs"
    >
      {COMPLIANCE_REQUIREMENT_STATUSES.map((status) => (
        <option key={status} value={status}>
          {status.replaceAll("_", " ")}
        </option>
      ))}
    </Select>
  );
}

function ComplianceRequirementFormFields({
  form,
  setForm,
  idPrefix,
}: {
  form: CreateComplianceRequirementInput;
  setForm: (form: CreateComplianceRequirementInput) => void;
  idPrefix: string;
}) {
  const { data: staff } = useStaff(1);

  return (
    <>
      <FormField label="Name" htmlFor={`${idPrefix}-name`}>
        <Input
          id={`${idPrefix}-name`}
          required
          value={form.name}
          onChange={(e) => setForm({ ...form, name: e.target.value })}
        />
      </FormField>
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <FormField label="Category" htmlFor={`${idPrefix}-category`}>
          <Input
            id={`${idPrefix}-category`}
            value={form.category ?? ""}
            onChange={(e) => setForm({ ...form, category: e.target.value })}
          />
        </FormField>
        <FormField label="Jurisdiction" htmlFor={`${idPrefix}-jurisdiction`}>
          <Input
            id={`${idPrefix}-jurisdiction`}
            placeholder="e.g. England"
            value={form.jurisdiction ?? ""}
            onChange={(e) => setForm({ ...form, jurisdiction: e.target.value })}
          />
        </FormField>
      </div>
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <FormField label="Issued date" htmlFor={`${idPrefix}-issued`}>
          <Input
            id={`${idPrefix}-issued`}
            type="date"
            value={form.issued_date ?? ""}
            onChange={(e) => setForm({ ...form, issued_date: e.target.value })}
          />
        </FormField>
        <FormField label="Renewal date" htmlFor={`${idPrefix}-renewal`}>
          <Input
            id={`${idPrefix}-renewal`}
            type="date"
            value={form.renewal_date ?? ""}
            onChange={(e) => setForm({ ...form, renewal_date: e.target.value })}
          />
        </FormField>
      </div>
      <FormField label="Reference number" htmlFor={`${idPrefix}-reference`}>
        <Input
          id={`${idPrefix}-reference`}
          value={form.reference_number ?? ""}
          onChange={(e) => setForm({ ...form, reference_number: e.target.value })}
        />
      </FormField>
      <FormField label="Responsible person" htmlFor={`${idPrefix}-responsible`}>
        <Select
          id={`${idPrefix}-responsible`}
          value={form.responsible_user_id ?? ""}
          onChange={(e) =>
            setForm({ ...form, responsible_user_id: e.target.value ? Number(e.target.value) : null })
          }
        >
          <option value="">Unassigned</option>
          {(staff?.data ?? []).map((s) => (
            <option key={s.id} value={s.user_id}>
              {s.name}
            </option>
          ))}
        </Select>
      </FormField>
      <FormField label="Status" htmlFor={`${idPrefix}-status`}>
        <Select
          id={`${idPrefix}-status`}
          value={form.status ?? "pending"}
          onChange={(e) => setForm({ ...form, status: e.target.value as ComplianceRequirementStatus })}
        >
          {COMPLIANCE_REQUIREMENT_STATUSES.map((status) => (
            <option key={status} value={status}>
              {status.replaceAll("_", " ")}
            </option>
          ))}
        </Select>
      </FormField>
      <FormField label="Notes" htmlFor={`${idPrefix}-notes`}>
        <Textarea
          id={`${idPrefix}-notes`}
          value={form.notes ?? ""}
          onChange={(e) => setForm({ ...form, notes: e.target.value })}
        />
      </FormField>
    </>
  );
}

function EditRequirementModal({
  requirement,
  onClose,
}: {
  requirement: ComplianceRequirement;
  onClose: () => void;
}) {
  const updateRequirement = useUpdateComplianceRequirement(requirement.id);
  const [form, setForm] = useState<CreateComplianceRequirementInput>({
    name: requirement.name,
    category: requirement.category ?? "",
    jurisdiction: requirement.jurisdiction ?? "",
    status: requirement.status,
    issued_date: requirement.issued_date ?? "",
    renewal_date: requirement.renewal_date ?? "",
    reference_number: requirement.reference_number ?? "",
    responsible_user_id: requirement.responsible_user_id ?? null,
    notes: requirement.notes ?? "",
  });
  const [error, setError] = useState<string | null>(null);

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setError(null);
    try {
      await updateRequirement.mutateAsync(form);
      onClose();
    } catch (err) {
      setError(apiErrorMessage(err, "Could not save this requirement. Please try again."));
    }
  }

  return (
    <Modal
      isOpen
      onClose={onClose}
      title="Edit Compliance Requirement"
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button form="edit-compliance-requirement-form" type="submit" isLoading={updateRequirement.isPending}>
            Save
          </Button>
        </>
      }
    >
      <form id="edit-compliance-requirement-form" onSubmit={handleSubmit}>
        {error && (
          <div className="mb-4">
            <Alert tone="danger">{error}</Alert>
          </div>
        )}
        <ComplianceRequirementFormFields form={form} setForm={setForm} idPrefix="edit-compliance" />
      </form>
    </Modal>
  );
}

function RequirementDocumentsDrawer({ requirement }: { requirement: ComplianceRequirement }) {
  const { data: documents, isLoading } = useComplianceDocuments(requirement.id);
  const uploadDocument = useUploadComplianceDocument(requirement.id);
  const [pendingFile, setPendingFile] = useState<File | null>(null);
  const [category, setCategory] = useState("");
  const [uploadError, setUploadError] = useState<string | null>(null);

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
      <p className="mb-4 text-sm text-inksoft">{requirement.name}</p>
      {uploadError && (
        <div className="mb-4">
          <Alert tone="danger">{uploadError}</Alert>
        </div>
      )}
      <div className="mb-4 flex flex-wrap items-end gap-3 rounded-xl border border-line p-3">
        <div className="flex-1">
          <FileUpload onSelect={setPendingFile} />
        </div>
        <FormField label="Category" htmlFor="compliance-doc-category">
          <Input id="compliance-doc-category" value={category} onChange={(e) => setCategory(e.target.value)} />
        </FormField>
        <Button onClick={handleUpload} disabled={!pendingFile} isLoading={uploadDocument.isPending}>
          Upload
        </Button>
      </div>

      {!isLoading && (documents ?? []).length === 0 ? (
        <EmptyState message="No evidence uploaded yet." />
      ) : (
        <ul className="space-y-2">
          {(documents ?? []).map((doc) => (
            <li key={doc.id} className="flex items-center justify-between rounded-lg border border-line p-3 text-sm">
              <span>{doc.original_filename}</span>
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

export function CompliancePage() {
  const { hasAnyRole } = useAuth();
  const isAuthorized = hasAnyRole(COMPLIANCE_ROLES);

  const { data: requirements, isLoading } = useComplianceRequirements();
  const createRequirement = useCreateComplianceRequirement();
  const deleteRequirement = useDeleteComplianceRequirement();

  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [form, setForm] = useState<CreateComplianceRequirementInput>(EMPTY_FORM);
  const [createError, setCreateError] = useState<string | null>(null);
  const [activeRequirement, setActiveRequirement] = useState<ComplianceRequirement | null>(null);
  const [editTarget, setEditTarget] = useState<ComplianceRequirement | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<ComplianceRequirement | null>(null);
  const [deleteError, setDeleteError] = useState<string | null>(null);

  if (!isAuthorized) {
    return (
      <Card>
        <CardBody>
          <Alert tone="danger">You don't have permission to view the Compliance Register.</Alert>
        </CardBody>
      </Card>
    );
  }

  async function handleCreate(event: FormEvent) {
    event.preventDefault();
    setCreateError(null);
    try {
      await createRequirement.mutateAsync(form);
      setIsCreateOpen(false);
      setForm(EMPTY_FORM);
    } catch (err) {
      setCreateError(apiErrorMessage(err, "Could not save this requirement. Please try again."));
    }
  }

  async function handleConfirmDelete() {
    if (!deleteTarget) return;
    setDeleteError(null);
    try {
      await deleteRequirement.mutateAsync(deleteTarget.id);
      setDeleteTarget(null);
    } catch (err) {
      setDeleteError(apiErrorMessage(err, "Could not delete this requirement. Please try again."));
    }
  }

  const columns: Column<ComplianceRequirement>[] = [
    { key: "name", header: "Requirement", render: (row) => row.name },
    { key: "category", header: "Category", render: (row) => row.category ?? "—" },
    { key: "jurisdiction", header: "Jurisdiction", render: (row) => row.jurisdiction ?? "—" },
    { key: "renewal_date", header: "Renewal", render: (row) => row.renewal_date ?? "—" },
    {
      key: "expiry_status",
      header: "",
      render: (row) =>
        row.expiry_status !== "no_expiry" ? (
          <StatusBadge label={row.expiry_status.replaceAll("_", " ")} tone={EXPIRY_TONE[row.expiry_status]} />
        ) : null,
    },
    {
      key: "status",
      header: "Status",
      render: (row) => <ComplianceStatusSelect requirement={row} />,
    },
    { key: "responsible", header: "Responsible", render: (row) => row.responsible_user_name ?? "—" },
    {
      key: "actions",
      header: "",
      className: "text-right",
      render: (row) => {
        const actions: RowAction[] = [
          { label: "Edit", onClick: () => setEditTarget(row) },
          { label: "Evidence", onClick: () => setActiveRequirement(row) },
          { label: "Delete", onClick: () => setDeleteTarget(row), tone: "danger" },
        ];
        return <RowActionsMenu actions={actions} label={`${row.name} actions`} />;
      },
    },
  ];

  return (
    <div>
      <div className="mb-6 rounded-2xl border border-line bg-white px-5 py-4">
        <div className="flex items-start justify-between">
          <div>
            <h1 className="text-2xl font-extrabold tracking-tight text-ink">Compliance Register</h1>
            <p className="mt-1 text-sm text-inksoft">
              Track your organization's own regulatory registrations, insurance, and filings.
            </p>
          </div>
          <Button onClick={() => setIsCreateOpen(true)}>Add Requirement</Button>
        </div>
      </div>

      {!isLoading && (requirements ?? []).length === 0 ? (
        <EmptyState message="No compliance requirements tracked yet." />
      ) : (
        <DataTable columns={columns} rows={requirements ?? []} rowKey={(row) => row.id} isLoading={isLoading} />
      )}

      <Modal
        isOpen={isCreateOpen}
        onClose={() => {
          setIsCreateOpen(false);
          setCreateError(null);
        }}
        title="Add Compliance Requirement"
        footer={
          <>
            <Button variant="secondary" onClick={() => setIsCreateOpen(false)}>
              Cancel
            </Button>
            <Button form="new-compliance-requirement-form" type="submit" isLoading={createRequirement.isPending}>
              Save
            </Button>
          </>
        }
      >
        <form id="new-compliance-requirement-form" onSubmit={handleCreate}>
          {createError && (
            <div className="mb-4">
              <Alert tone="danger">{createError}</Alert>
            </div>
          )}
          <ComplianceRequirementFormFields form={form} setForm={setForm} idPrefix="compliance" />
        </form>
      </Modal>

      {editTarget && <EditRequirementModal requirement={editTarget} onClose={() => setEditTarget(null)} />}

      <Drawer
        isOpen={Boolean(activeRequirement)}
        onClose={() => setActiveRequirement(null)}
        title="Compliance Evidence"
      >
        {activeRequirement && <RequirementDocumentsDrawer requirement={activeRequirement} />}
      </Drawer>

      <ConfirmDialog
        isOpen={Boolean(deleteTarget)}
        title="Delete requirement"
        message={`Delete "${deleteTarget?.name}"? This removes it from the active register; existing evidence and audit history are preserved.`}
        confirmLabel="Delete"
        tone="danger"
        isLoading={deleteRequirement.isPending}
        error={deleteError}
        onConfirm={handleConfirmDelete}
        onCancel={() => {
          setDeleteTarget(null);
          setDeleteError(null);
        }}
      />
    </div>
  );
}
