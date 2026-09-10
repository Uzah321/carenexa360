import { useState, type FormEvent } from "react";
import {
  Alert,
  Button,
  Card,
  CardBody,
  CardHeader,
  Checkbox,
  ConfirmDialog,
  DataTable,
  Drawer,
  EmptyState,
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
import { apiErrorMessage } from "../../../lib/api-error";
import {
  useArchiveMedication,
  useCreateMedication,
  useMedicationAdministrations,
  useMedications,
  useRecordAdministration,
  useUpdateMedication,
  type CreateMedicationInput,
  type UpdateMedicationInput,
} from "../../medications/api";
import { useStaff } from "../../staff/api";
import {
  MEDICATION_ADMINISTRATION_STATUSES,
  type Medication,
  type MedicationAdministrationStatus,
} from "../../../lib/types";

const EMPTY_FORM: CreateMedicationInput = {
  name: "",
  dose: "",
  route: "",
  frequency: "",
  start_date: "",
};

function toUpdateInput(medication: Medication): UpdateMedicationInput {
  return {
    dose: medication.dose,
    frequency: medication.frequency,
    schedule: medication.schedule,
    end_date: medication.end_date ?? "",
    instructions: medication.instructions ?? "",
    status: medication.status,
  };
}

export function MedicationsTab({ serviceUserId }: { serviceUserId: number }) {
  const { data: medications, isLoading } = useMedications(serviceUserId);
  const createMedication = useCreateMedication(serviceUserId);
  const updateMedication = useUpdateMedication(serviceUserId);
  const archiveMedication = useArchiveMedication(serviceUserId);
  const { data: staff } = useStaff(1, 500);

  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [form, setForm] = useState<CreateMedicationInput>(EMPTY_FORM);
  const [createError, setCreateError] = useState<string | null>(null);

  const [activeMedication, setActiveMedication] = useState<Medication | null>(null);
  const [recordStatus, setRecordStatus] = useState<MedicationAdministrationStatus>("administered");
  const [witnessId, setWitnessId] = useState<number | "">("");
  const [administrationNotes, setAdministrationNotes] = useState("");
  const [recordError, setRecordError] = useState<string | null>(null);

  const [viewingMedication, setViewingMedication] = useState<Medication | null>(null);
  const [editingMedication, setEditingMedication] = useState<Medication | null>(null);
  const [editDraft, setEditDraft] = useState<UpdateMedicationInput>({});
  const [editError, setEditError] = useState<string | null>(null);
  const [archiveTarget, setArchiveTarget] = useState<Medication | null>(null);
  const [archiveError, setArchiveError] = useState<string | null>(null);

  const { data: administrations, isLoading: isLoadingAdministrations } = useMedicationAdministrations(
    activeMedication?.id ?? null,
  );
  const recordAdministration = useRecordAdministration(activeMedication?.id ?? null);

  async function handleCreate(event: FormEvent) {
    event.preventDefault();
    setCreateError(null);
    try {
      await createMedication.mutateAsync(form);
      setIsCreateOpen(false);
      setForm(EMPTY_FORM);
    } catch (err) {
      setCreateError(apiErrorMessage(err, "Could not save this medication. Please try again."));
    }
  }

  async function handleRecordAdministration(event: FormEvent) {
    event.preventDefault();
    setRecordError(null);
    try {
      await recordAdministration.mutateAsync({
        status: recordStatus,
        witness_id: witnessId === "" ? undefined : witnessId,
        notes: administrationNotes || undefined,
      });
      setRecordStatus("administered");
      setWitnessId("");
      setAdministrationNotes("");
    } catch (err) {
      setRecordError(apiErrorMessage(err, "Could not record this administration. Please try again."));
    }
  }

  function openEdit(medication: Medication) {
    setEditingMedication(medication);
    setEditDraft(toUpdateInput(medication));
    setEditError(null);
  }

  async function handleSaveEdit(event: FormEvent) {
    event.preventDefault();
    if (!editingMedication) return;
    setEditError(null);
    try {
      await updateMedication.mutateAsync({
        id: editingMedication.id,
        ...editDraft,
        end_date: editDraft.end_date || null,
        instructions: editDraft.instructions || null,
      });
      setEditingMedication(null);
    } catch (err) {
      setEditError(apiErrorMessage(err, "Could not save this medication. Please try again."));
    }
  }

  async function handleConfirmArchive() {
    if (!archiveTarget) return;
    setArchiveError(null);
    try {
      await archiveMedication.mutateAsync({ id: archiveTarget.id, archived: !archiveTarget.archived_at });
      setArchiveTarget(null);
    } catch (err) {
      setArchiveError(apiErrorMessage(err, "Could not update this medication. Please try again."));
    }
  }

  const columns: Column<Medication>[] = [
    { key: "name", header: "Medication", render: (row) => row.name },
    { key: "dose", header: "Dose", render: (row) => row.dose },
    { key: "route", header: "Route", render: (row) => row.route },
    { key: "frequency", header: "Frequency", render: (row) => row.frequency },
    {
      key: "controlled",
      header: "",
      render: (row) => (row.is_controlled_drug ? <StatusBadge label="Controlled Drug" tone="warning" /> : null),
    },
    {
      key: "status",
      header: "Status",
      render: (row) => (
        <div className="flex items-center gap-1.5">
          <StatusBadge label={row.status} tone={row.status === "active" ? "success" : "neutral"} />
          {row.archived_at && <StatusBadge label="Archived" tone="neutral" />}
        </div>
      ),
    },
    {
      key: "history",
      header: "",
      render: (row) => (
        <button
          type="button"
          className="text-sm font-medium text-teal hover:text-teal/90"
          onClick={() => setActiveMedication(row)}
        >
          Record / History
        </button>
      ),
    },
    {
      key: "actions",
      header: "",
      className: "text-right",
      render: (row) => {
        const actions: RowAction[] = [
          { label: "View", onClick: () => setViewingMedication(row) },
          { label: "Edit", onClick: () => openEdit(row) },
          {
            label: row.archived_at ? "Restore" : "Archive",
            onClick: () => setArchiveTarget(row),
            tone: row.archived_at ? "default" : "danger",
          },
        ];
        return <RowActionsMenu actions={actions} label={`${row.name} actions`} />;
      },
    },
  ];

  return (
    <Card>
      <CardHeader>
        <div className="flex items-center justify-between">
          <span>Medications</span>
          <Button variant="secondary" onClick={() => setIsCreateOpen(true)}>
            Add Medication
          </Button>
        </div>
      </CardHeader>
      <CardBody>
        {!isLoading && (medications ?? []).length === 0 ? (
          <EmptyState message="No medications recorded yet." />
        ) : (
          <DataTable columns={columns} rows={medications ?? []} rowKey={(row) => row.id} isLoading={isLoading} />
        )}
      </CardBody>

      <Modal
        isOpen={isCreateOpen}
        onClose={() => {
          setIsCreateOpen(false);
          setCreateError(null);
        }}
        title="Add Medication"
        footer={
          <>
            <Button variant="secondary" onClick={() => setIsCreateOpen(false)}>
              Cancel
            </Button>
            <Button form="new-medication-form" type="submit" isLoading={createMedication.isPending}>
              Save
            </Button>
          </>
        }
      >
        <form id="new-medication-form" onSubmit={handleCreate}>
          {createError && (
            <div className="mb-4">
              <Alert tone="danger">{createError}</Alert>
            </div>
          )}
          <FormField label="Name" htmlFor="med-name">
            <Input
              id="med-name"
              required
              value={form.name}
              onChange={(e) => setForm({ ...form, name: e.target.value })}
            />
          </FormField>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <FormField label="Dose" htmlFor="med-dose">
              <Input
                id="med-dose"
                required
                value={form.dose}
                onChange={(e) => setForm({ ...form, dose: e.target.value })}
              />
            </FormField>
            <FormField label="Route" htmlFor="med-route">
              <Input
                id="med-route"
                required
                value={form.route}
                onChange={(e) => setForm({ ...form, route: e.target.value })}
              />
            </FormField>
          </div>
          <FormField label="Frequency" htmlFor="med-frequency">
            <Input
              id="med-frequency"
              required
              value={form.frequency}
              onChange={(e) => setForm({ ...form, frequency: e.target.value })}
            />
          </FormField>
          <FormField label="Start date" htmlFor="med-start">
            <Input
              id="med-start"
              type="date"
              required
              value={form.start_date}
              onChange={(e) => setForm({ ...form, start_date: e.target.value })}
            />
          </FormField>
          <FormField label="Instructions" htmlFor="med-instructions">
            <Textarea
              id="med-instructions"
              value={form.instructions ?? ""}
              onChange={(e) => setForm({ ...form, instructions: e.target.value })}
            />
          </FormField>
          <div className="mt-2 space-y-2">
            <Checkbox
              id="med-prn"
              label="PRN (as needed)"
              checked={Boolean(form.is_prn)}
              onChange={(e) => setForm({ ...form, is_prn: e.target.checked })}
            />
            <Checkbox
              id="med-controlled"
              label="Controlled drug (requires a witness on administration)"
              checked={Boolean(form.is_controlled_drug)}
              onChange={(e) => setForm({ ...form, is_controlled_drug: e.target.checked })}
            />
          </div>
        </form>
      </Modal>

      <Modal
        isOpen={Boolean(editingMedication)}
        onClose={() => {
          setEditingMedication(null);
          setEditError(null);
        }}
        title={`Edit — ${editingMedication?.name ?? "Medication"}`}
        footer={
          <>
            <Button variant="secondary" onClick={() => setEditingMedication(null)}>
              Cancel
            </Button>
            <Button form="edit-medication-form" type="submit" isLoading={updateMedication.isPending}>
              Save
            </Button>
          </>
        }
      >
        <form id="edit-medication-form" onSubmit={handleSaveEdit}>
          {editError && (
            <div className="mb-4">
              <Alert tone="danger">{editError}</Alert>
            </div>
          )}
          <p className="mb-4 text-xs text-inksoft">
            Name, route and start date are fixed once recorded — the fields below can be updated as care needs change.
          </p>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <FormField label="Dose" htmlFor="edit-med-dose">
              <Input
                id="edit-med-dose"
                required
                value={editDraft.dose ?? ""}
                onChange={(e) => setEditDraft({ ...editDraft, dose: e.target.value })}
              />
            </FormField>
            <FormField label="Frequency" htmlFor="edit-med-frequency">
              <Input
                id="edit-med-frequency"
                required
                value={editDraft.frequency ?? ""}
                onChange={(e) => setEditDraft({ ...editDraft, frequency: e.target.value })}
              />
            </FormField>
          </div>
          <FormField label="End date" htmlFor="edit-med-end-date">
            <Input
              id="edit-med-end-date"
              type="date"
              value={editDraft.end_date ?? ""}
              onChange={(e) => setEditDraft({ ...editDraft, end_date: e.target.value })}
            />
          </FormField>
          <FormField label="Instructions" htmlFor="edit-med-instructions">
            <Textarea
              id="edit-med-instructions"
              value={editDraft.instructions ?? ""}
              onChange={(e) => setEditDraft({ ...editDraft, instructions: e.target.value })}
            />
          </FormField>
          <FormField label="Status" htmlFor="edit-med-status">
            <Select
              id="edit-med-status"
              value={editDraft.status ?? "active"}
              onChange={(e) => setEditDraft({ ...editDraft, status: e.target.value as Medication["status"] })}
            >
              <option value="active">Active</option>
              <option value="discontinued">Discontinued</option>
            </Select>
          </FormField>
        </form>
      </Modal>

      <Modal
        isOpen={Boolean(viewingMedication)}
        onClose={() => setViewingMedication(null)}
        title={viewingMedication?.name ?? "Medication"}
        footer={
          <Button variant="secondary" onClick={() => setViewingMedication(null)}>
            Close
          </Button>
        }
      >
        {viewingMedication && (
          <dl>
            <div className="flex justify-between border-b border-line py-2 text-sm">
              <dt className="text-inksoft">Strength</dt>
              <dd className="font-medium text-ink">{viewingMedication.strength ?? "—"}</dd>
            </div>
            <div className="flex justify-between border-b border-line py-2 text-sm">
              <dt className="text-inksoft">Form</dt>
              <dd className="font-medium text-ink">{viewingMedication.form ?? "—"}</dd>
            </div>
            <div className="flex justify-between border-b border-line py-2 text-sm">
              <dt className="text-inksoft">Dose</dt>
              <dd className="font-medium text-ink">{viewingMedication.dose}</dd>
            </div>
            <div className="flex justify-between border-b border-line py-2 text-sm">
              <dt className="text-inksoft">Route</dt>
              <dd className="font-medium text-ink">{viewingMedication.route}</dd>
            </div>
            <div className="flex justify-between border-b border-line py-2 text-sm">
              <dt className="text-inksoft">Frequency</dt>
              <dd className="font-medium text-ink">{viewingMedication.frequency}</dd>
            </div>
            <div className="flex justify-between border-b border-line py-2 text-sm">
              <dt className="text-inksoft">Start date</dt>
              <dd className="font-medium text-ink">{viewingMedication.start_date}</dd>
            </div>
            <div className="flex justify-between border-b border-line py-2 text-sm">
              <dt className="text-inksoft">End date</dt>
              <dd className="font-medium text-ink">{viewingMedication.end_date ?? "—"}</dd>
            </div>
            <div className="flex justify-between border-b border-line py-2 text-sm">
              <dt className="text-inksoft">Prescriber</dt>
              <dd className="font-medium text-ink">{viewingMedication.prescriber ?? "—"}</dd>
            </div>
            <div className="flex justify-between border-b border-line py-2 text-sm">
              <dt className="text-inksoft">Pharmacy</dt>
              <dd className="font-medium text-ink">{viewingMedication.pharmacy ?? "—"}</dd>
            </div>
            <div className="border-b border-line py-2 text-sm">
              <dt className="mb-1 text-inksoft">Instructions</dt>
              <dd className="font-medium text-ink">{viewingMedication.instructions ?? "—"}</dd>
            </div>
            <div className="flex justify-between border-b border-line py-2 text-sm">
              <dt className="text-inksoft">PRN</dt>
              <dd className="font-medium text-ink">{viewingMedication.is_prn ? "Yes" : "No"}</dd>
            </div>
            <div className="flex justify-between py-2 text-sm">
              <dt className="text-inksoft">Controlled drug</dt>
              <dd className="font-medium text-ink">{viewingMedication.is_controlled_drug ? "Yes" : "No"}</dd>
            </div>
          </dl>
        )}
      </Modal>

      <ConfirmDialog
        isOpen={Boolean(archiveTarget)}
        title={archiveTarget?.archived_at ? "Restore medication" : "Archive medication"}
        message={
          archiveTarget?.archived_at
            ? `Restore "${archiveTarget?.name}" to the active medications list?`
            : `Archive "${archiveTarget?.name}"? It's kept with its full history but hidden from the active list.`
        }
        confirmLabel={archiveTarget?.archived_at ? "Restore" : "Archive"}
        tone={archiveTarget?.archived_at ? "default" : "danger"}
        isLoading={archiveMedication.isPending}
        error={archiveError}
        onConfirm={handleConfirmArchive}
        onCancel={() => {
          setArchiveTarget(null);
          setArchiveError(null);
        }}
      />

      <Drawer
        isOpen={Boolean(activeMedication)}
        onClose={() => {
          setActiveMedication(null);
          setRecordError(null);
        }}
        title={activeMedication ? `${activeMedication.name} — ${activeMedication.dose}` : ""}
      >
        {activeMedication && (
          <div>
            <form id="record-administration-form" onSubmit={handleRecordAdministration} className="mb-6">
              {recordError && (
                <div className="mb-4">
                  <Alert tone="danger">{recordError}</Alert>
                </div>
              )}
              <FormField label="Status" htmlFor="admin-status">
                <Select
                  id="admin-status"
                  value={recordStatus}
                  onChange={(e) => setRecordStatus(e.target.value as MedicationAdministrationStatus)}
                >
                  {MEDICATION_ADMINISTRATION_STATUSES.map((status) => (
                    <option key={status} value={status}>
                      {status.replaceAll("_", " ")}
                    </option>
                  ))}
                </Select>
              </FormField>
              {activeMedication.is_controlled_drug && (
                <FormField label="Witness" htmlFor="admin-witness">
                  <Select
                    id="admin-witness"
                    required
                    value={witnessId}
                    onChange={(e) => setWitnessId(e.target.value ? Number(e.target.value) : "")}
                  >
                    <option value="" disabled>
                      Select a witness
                    </option>
                    {(staff?.data ?? []).map((s) => (
                      <option key={s.id} value={s.user_id}>
                        {s.name}
                      </option>
                    ))}
                  </Select>
                </FormField>
              )}
              <FormField label="Notes" htmlFor="admin-notes">
                <Textarea
                  id="admin-notes"
                  value={administrationNotes}
                  onChange={(e) => setAdministrationNotes(e.target.value)}
                />
              </FormField>
              <Button type="submit" isLoading={recordAdministration.isPending}>
                Record Administration
              </Button>
            </form>

            <h3 className="mb-2 text-sm font-semibold text-ink">History</h3>
            {!isLoadingAdministrations && (administrations ?? []).length === 0 ? (
              <EmptyState message="No administrations recorded yet." />
            ) : (
              <ul className="space-y-2">
                {(administrations ?? []).map((administration) => (
                  <li
                    key={administration.id}
                    className="rounded-lg border border-line p-3 text-sm"
                  >
                    <div className="flex items-center justify-between">
                      <StatusBadge
                        label={administration.status.replaceAll("_", " ")}
                        tone={administration.status === "administered" ? "success" : "neutral"}
                      />
                      <span className="text-xs text-inksoft">
                        {administration.administered_at
                          ? new Date(administration.administered_at).toLocaleString()
                          : "—"}
                      </span>
                    </div>
                    <div className="mt-1 text-inksoft">
                      By {administration.administered_by_name ?? "—"}
                      {administration.witness_name && ` · Witnessed by ${administration.witness_name}`}
                    </div>
                    {administration.notes && <div className="mt-1 text-inksoft">{administration.notes}</div>}
                  </li>
                ))}
              </ul>
            )}
          </div>
        )}
      </Drawer>
    </Card>
  );
}
