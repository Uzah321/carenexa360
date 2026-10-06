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
import { useAuth } from "../../../lib/auth-context";
import {
  MEDICATION_NOT_GIVEN_REASONS,
  type Medication,
  type MedicationAdministrationStatus,
  type MedicationNotGivenReason,
} from "../../../lib/types";
import { ThumbsUp } from "lucide-react";
import {
  NOT_GIVEN_REASON_LABELS,
  administrationLabel,
  administrationTone,
  recordableStatuses,
} from "../../medications/administration";
import { MedicationRound } from "../../medications/components/MedicationRound";
import { ScheduleTimesInput } from "../../medications/components/ScheduleTimesInput";

const toNumberOrNull = (value: string) => (value.trim() === "" ? null : Number(value));

/** Stock on hand, reorder level and units per dose — shared by the add and edit forms. */
function StockFields({
  idPrefix,
  stockOnHand,
  reorderLevel,
  unitsPerDose,
  onChange,
}: {
  idPrefix: string;
  stockOnHand: number | null | undefined;
  reorderLevel: number | null | undefined;
  unitsPerDose: number | undefined;
  onChange: (patch: { stock_on_hand?: number | null; reorder_level?: number | null; units_per_dose?: number }) => void;
}) {
  return (
    <>
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <FormField label="Stock on hand" htmlFor={`${idPrefix}-stock`}>
          <Input
            id={`${idPrefix}-stock`}
            type="number"
            min={0}
            step="any"
            placeholder="Not tracked"
            value={stockOnHand ?? ""}
            onChange={(e) => onChange({ stock_on_hand: toNumberOrNull(e.target.value) })}
          />
        </FormField>
        <FormField label="Reorder at" htmlFor={`${idPrefix}-reorder`}>
          <Input
            id={`${idPrefix}-reorder`}
            type="number"
            min={0}
            step="any"
            value={reorderLevel ?? ""}
            onChange={(e) => onChange({ reorder_level: toNumberOrNull(e.target.value) })}
          />
        </FormField>
        <FormField label="Units per dose" htmlFor={`${idPrefix}-units`}>
          <Input
            id={`${idPrefix}-units`}
            type="number"
            min={0.01}
            step="any"
            value={unitsPerDose ?? 1}
            onChange={(e) => onChange({ units_per_dose: Number(e.target.value) || 1 })}
          />
        </FormField>
      </div>
      <p className="-mt-2 mb-4 text-xs text-inksoft">
        Leave stock empty to not track it. Each dose given takes the units per dose off the count.
      </p>
    </>
  );
}

const OUTCOME_LABELS: Partial<Record<MedicationAdministrationStatus, string>> = {
  administered: "Given",
  prn: "Given (PRN)",
  not_given: "Not given",
  missed: "Missed",
};

const EMPTY_FORM: CreateMedicationInput = {
  name: "",
  dose: "",
  route: "",
  frequency: "",
  schedule: [],
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
    stock_on_hand: medication.stock_on_hand,
    reorder_level: medication.reorder_level,
    units_per_dose: medication.units_per_dose,
  };
}

export function MedicationsTab({ serviceUserId }: { serviceUserId: number }) {
  const { data: medications, isLoading } = useMedications(serviceUserId);
  const createMedication = useCreateMedication(serviceUserId);
  const updateMedication = useUpdateMedication(serviceUserId);
  const archiveMedication = useArchiveMedication(serviceUserId);
  const { data: staff } = useStaff(1, 500);
  const { user } = useAuth();

  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [form, setForm] = useState<CreateMedicationInput>(EMPTY_FORM);
  const [createError, setCreateError] = useState<string | null>(null);

  const [activeMedication, setActiveMedication] = useState<Medication | null>(null);
  const [recordStatus, setRecordStatus] = useState<MedicationAdministrationStatus>("administered");
  const [scheduledTime, setScheduledTime] = useState("");
  const [notGivenReason, setNotGivenReason] = useState<MedicationNotGivenReason | "">("");
  const [stockChecked, setStockChecked] = useState(false);
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
  const recordAdministration = useRecordAdministration(activeMedication?.id ?? null, serviceUserId);

  const isGiven = recordStatus === "administered" || recordStatus === "prn";

  function resetRecordForm(medication: Medication | null, time = "") {
    setRecordStatus(medication?.is_prn ? "prn" : "administered");
    setScheduledTime(time);
    setNotGivenReason("");
    setStockChecked(false);
    setWitnessId("");
    setAdministrationNotes("");
    setRecordError(null);
  }

  function openRecord(medication: Medication, time = "") {
    setActiveMedication(medication);
    resetRecordForm(medication, time);
  }

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
        scheduled_time: scheduledTime || null,
        not_given_reason: recordStatus === "not_given" && notGivenReason ? notGivenReason : null,
        stock_checked: stockChecked,
        witness_id: isGiven && witnessId !== "" ? witnessId : undefined,
        notes: administrationNotes || undefined,
      });
      resetRecordForm(activeMedication);
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
      key: "stock",
      header: "Stock",
      render: (row) =>
        row.stock_on_hand === null ? (
          <span className="text-inksoft">—</span>
        ) : (
          <div className="flex items-center gap-1.5">
            <span>
              {row.stock_on_hand}
              {row.days_of_stock_left !== null && <span className="text-xs text-inksoft"> · {row.days_of_stock_left}d</span>}
            </span>
            {row.needs_reorder && <StatusBadge label={row.stock_on_hand <= 0 ? "Out" : "Reorder"} tone="danger" />}
          </div>
        ),
    },
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
          onClick={() => openRecord(row)}
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
        {(medications ?? []).length > 0 && (
          <section className="mb-6 border-b border-line pb-6">
            <h3 className="mb-3 text-sm font-semibold text-ink">Today's round</h3>
            <MedicationRound medications={medications ?? []} onRecord={openRecord} />
          </section>
        )}
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
          {!form.is_prn && (
            <FormField label="Dose times" htmlFor="med-schedule">
              <ScheduleTimesInput
                id="med-schedule"
                value={form.schedule ?? []}
                onChange={(schedule) => setForm({ ...form, schedule })}
              />
            </FormField>
          )}
          <FormField label="Start date" htmlFor="med-start">
            <Input
              id="med-start"
              type="date"
              required
              value={form.start_date}
              onChange={(e) => setForm({ ...form, start_date: e.target.value })}
            />
          </FormField>
          <StockFields
            idPrefix="med"
            stockOnHand={form.stock_on_hand}
            reorderLevel={form.reorder_level}
            unitsPerDose={form.units_per_dose}
            onChange={(patch) => setForm({ ...form, ...patch })}
          />
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
          {!editingMedication?.is_prn && (
            <FormField label="Dose times" htmlFor="edit-med-schedule">
              <ScheduleTimesInput
                id="edit-med-schedule"
                value={editDraft.schedule ?? []}
                onChange={(schedule) => setEditDraft({ ...editDraft, schedule })}
              />
            </FormField>
          )}
          <FormField label="End date" htmlFor="edit-med-end-date">
            <Input
              id="edit-med-end-date"
              type="date"
              value={editDraft.end_date ?? ""}
              onChange={(e) => setEditDraft({ ...editDraft, end_date: e.target.value })}
            />
          </FormField>
          <StockFields
            idPrefix="edit-med"
            stockOnHand={editDraft.stock_on_hand}
            reorderLevel={editDraft.reorder_level}
            unitsPerDose={editDraft.units_per_dose}
            onChange={(patch) => setEditDraft({ ...editDraft, ...patch })}
          />
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
              <dt className="text-inksoft">Dose times</dt>
              <dd className="font-medium text-ink">
                {viewingMedication.schedule.length > 0 ? viewingMedication.schedule.join(", ") : "—"}
              </dd>
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
            <div className="flex justify-between border-b border-line py-2 text-sm">
              <dt className="text-inksoft">Stock on hand</dt>
              <dd className="font-medium text-ink">
                {viewingMedication.stock_on_hand === null
                  ? "Not tracked"
                  : `${viewingMedication.stock_on_hand}${viewingMedication.days_of_stock_left !== null ? ` (${viewingMedication.days_of_stock_left} days)` : ""}${viewingMedication.needs_reorder ? " — reorder now" : ""}`}
              </dd>
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
              {activeMedication.schedule.length > 0 && !activeMedication.is_prn && (
                <FormField label="Dose due at" htmlFor="admin-scheduled-time">
                  <Select
                    id="admin-scheduled-time"
                    value={scheduledTime}
                    onChange={(e) => setScheduledTime(e.target.value)}
                  >
                    <option value="">Not a scheduled dose</option>
                    {activeMedication.schedule.map((time) => (
                      <option key={time} value={time}>
                        {time}
                      </option>
                    ))}
                  </Select>
                </FormField>
              )}
              <FormField label="Outcome" htmlFor="admin-status">
                <Select
                  id="admin-status"
                  value={recordStatus}
                  onChange={(e) => setRecordStatus(e.target.value as MedicationAdministrationStatus)}
                >
                  {recordableStatuses(activeMedication).map((status) => (
                    <option key={status} value={status}>
                      {OUTCOME_LABELS[status]}
                    </option>
                  ))}
                </Select>
              </FormField>
              {recordStatus === "not_given" && (
                <>
                  <FormField label="Not given by" htmlFor="admin-not-given-by">
                    <Input id="admin-not-given-by" value={user?.name ?? ""} readOnly disabled />
                  </FormField>
                  <FormField label="Reason not given" htmlFor="admin-not-given-reason">
                    <Select
                      id="admin-not-given-reason"
                      required
                      value={notGivenReason}
                      onChange={(e) => setNotGivenReason(e.target.value as MedicationNotGivenReason)}
                    >
                      <option value="" disabled>
                        Select reason for not given
                      </option>
                      {MEDICATION_NOT_GIVEN_REASONS.map((reason) => (
                        <option key={reason} value={reason}>
                          {NOT_GIVEN_REASON_LABELS[reason]}
                        </option>
                      ))}
                    </Select>
                  </FormField>
                </>
              )}
              <div className="mb-4 flex items-center gap-2">
                <ThumbsUp className={`h-4 w-4 ${stockChecked ? "text-lime" : "text-inksoft"}`} aria-hidden />
                <Checkbox
                  id="admin-stock-checked"
                  label="Stock checked and correct"
                  checked={stockChecked}
                  onChange={(e) => setStockChecked(e.target.checked)}
                />
              </div>
              {activeMedication.is_controlled_drug && isGiven && (
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
                Record
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
                        label={administrationLabel(administration)}
                        tone={administrationTone(administration.status)}
                      />
                      <span className="text-xs text-inksoft">
                        {administration.administered_at
                          ? new Date(administration.administered_at).toLocaleString()
                          : "—"}
                      </span>
                    </div>
                    <div className="mt-1 text-inksoft">
                      {administration.scheduled_time && `Due ${administration.scheduled_time} · `}
                      By {administration.administered_by_name ?? "—"}
                      {administration.witness_name && ` · Witnessed by ${administration.witness_name}`}
                      {administration.stock_checked && " · Stock checked"}
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
