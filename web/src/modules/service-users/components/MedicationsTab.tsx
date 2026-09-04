import { useState, type FormEvent } from "react";
import {
  Alert,
  Button,
  Card,
  CardBody,
  CardHeader,
  Checkbox,
  DataTable,
  Drawer,
  EmptyState,
  FormField,
  Input,
  Modal,
  Select,
  StatusBadge,
  Textarea,
  type Column,
} from "../../../design-system";
import { apiErrorMessage } from "../../../lib/api-error";
import {
  useCreateMedication,
  useMedicationAdministrations,
  useMedications,
  useRecordAdministration,
  type CreateMedicationInput,
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

export function MedicationsTab({ serviceUserId }: { serviceUserId: number }) {
  const { data: medications, isLoading } = useMedications(serviceUserId);
  const createMedication = useCreateMedication(serviceUserId);
  const { data: staff } = useStaff(1);

  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [form, setForm] = useState<CreateMedicationInput>(EMPTY_FORM);
  const [createError, setCreateError] = useState<string | null>(null);

  const [activeMedication, setActiveMedication] = useState<Medication | null>(null);
  const [recordStatus, setRecordStatus] = useState<MedicationAdministrationStatus>("administered");
  const [witnessId, setWitnessId] = useState<number | "">("");
  const [administrationNotes, setAdministrationNotes] = useState("");
  const [recordError, setRecordError] = useState<string | null>(null);

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
        <StatusBadge label={row.status} tone={row.status === "active" ? "success" : "neutral"} />
      ),
    },
    {
      key: "actions",
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
