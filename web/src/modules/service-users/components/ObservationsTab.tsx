import { useState, type FormEvent } from "react";
import {
  Alert,
  Button,
  Card,
  CardBody,
  CardHeader,
  ConfirmDialog,
  DataTable,
  EmptyState,
  FormField,
  Input,
  Modal,
  RowActionsMenu,
  Select,
  Textarea,
  type Column,
  type RowAction,
} from "../../../design-system";
import { apiErrorMessage } from "../../../lib/api-error";
import {
  useAcknowledgeAlert,
  useArchiveObservation,
  useClinicalAlerts,
  useCreateObservation,
  useObservations,
  useUpdateObservation,
} from "../../observations/api";
import { ObservationTrendChart } from "../../observations/components/ObservationTrendChart";
import { OBSERVATION_TYPES, type Observation, type ObservationType } from "../../../lib/types";

function labelFor(type: ObservationType): string {
  return type.replaceAll("_", " ");
}

function readingText(observation: Observation): string {
  return observation.type === "blood_pressure"
    ? `${observation.value.systolic}/${observation.value.diastolic} mmHg`
    : `${observation.value.value}${observation.unit ? ` ${observation.unit}` : ""}`;
}

export function ObservationsTab({ serviceUserId }: { serviceUserId: number }) {
  const [selectedType, setSelectedType] = useState<ObservationType>("blood_pressure");
  const { data: observations, isLoading } = useObservations(serviceUserId);
  const { data: alerts } = useClinicalAlerts(serviceUserId);
  const acknowledgeAlert = useAcknowledgeAlert(serviceUserId);
  const createObservation = useCreateObservation(serviceUserId);
  const updateObservation = useUpdateObservation(serviceUserId);
  const archiveObservation = useArchiveObservation(serviceUserId);

  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [formType, setFormType] = useState<ObservationType>("blood_pressure");
  const [systolic, setSystolic] = useState("");
  const [diastolic, setDiastolic] = useState("");
  const [value, setValue] = useState("");
  const [unit, setUnit] = useState("");
  const [notes, setNotes] = useState("");
  const [error, setError] = useState<string | null>(null);

  const [viewingObservation, setViewingObservation] = useState<Observation | null>(null);
  const [editingObservation, setEditingObservation] = useState<Observation | null>(null);
  const [editSystolic, setEditSystolic] = useState("");
  const [editDiastolic, setEditDiastolic] = useState("");
  const [editValue, setEditValue] = useState("");
  const [editUnit, setEditUnit] = useState("");
  const [editNotes, setEditNotes] = useState("");
  const [editError, setEditError] = useState<string | null>(null);
  const [archiveTarget, setArchiveTarget] = useState<Observation | null>(null);
  const [archiveError, setArchiveError] = useState<string | null>(null);

  const activeAlerts = (alerts ?? []).filter((a) => !a.acknowledged_at);
  const readingsForType = (observations ?? []).filter((o) => o.type === selectedType);

  function resetForm() {
    setFormType("blood_pressure");
    setSystolic("");
    setDiastolic("");
    setValue("");
    setUnit("");
    setNotes("");
  }

  async function handleCreate(event: FormEvent) {
    event.preventDefault();
    setError(null);
    try {
      await createObservation.mutateAsync({
        type: formType,
        value:
          formType === "blood_pressure"
            ? { systolic: Number(systolic), diastolic: Number(diastolic) }
            : { value: Number(value) },
        unit: unit || undefined,
        notes: notes || undefined,
      });
      setIsCreateOpen(false);
      resetForm();
    } catch (err) {
      setError(apiErrorMessage(err, "Could not save this observation. Please try again."));
    }
  }

  function openEdit(observation: Observation) {
    setEditingObservation(observation);
    if (observation.type === "blood_pressure") {
      setEditSystolic(String(observation.value.systolic ?? ""));
      setEditDiastolic(String(observation.value.diastolic ?? ""));
    } else {
      setEditValue(String(observation.value.value ?? ""));
    }
    setEditUnit(observation.unit ?? "");
    setEditNotes(observation.notes ?? "");
    setEditError(null);
  }

  async function handleSaveEdit(event: FormEvent) {
    event.preventDefault();
    if (!editingObservation) return;
    setEditError(null);
    try {
      await updateObservation.mutateAsync({
        id: editingObservation.id,
        value:
          editingObservation.type === "blood_pressure"
            ? { systolic: Number(editSystolic), diastolic: Number(editDiastolic) }
            : { value: Number(editValue) },
        unit: editUnit || null,
        notes: editNotes || null,
      });
      setEditingObservation(null);
    } catch (err) {
      setEditError(apiErrorMessage(err, "Could not save this observation. Please try again."));
    }
  }

  async function handleConfirmArchive() {
    if (!archiveTarget) return;
    setArchiveError(null);
    try {
      await archiveObservation.mutateAsync({ id: archiveTarget.id, archived: !archiveTarget.archived_at });
      setArchiveTarget(null);
    } catch (err) {
      setArchiveError(apiErrorMessage(err, "Could not update this observation. Please try again."));
    }
  }

  const columns: Column<Observation>[] = [
    {
      key: "recorded_at",
      header: "Recorded",
      render: (row) => new Date(row.recorded_at).toLocaleString(),
    },
    { key: "type", header: "Type", render: (row) => labelFor(row.type) },
    {
      key: "value",
      header: "Reading",
      render: (row) => readingText(row),
    },
    { key: "recorded_by", header: "Recorded By", render: (row) => row.recorded_by_name ?? "—" },
    {
      key: "alert",
      header: "",
      render: (row) => (
        <div className="flex items-center gap-1.5">
          {(row.alerts ?? []).length > 0 && <span className="text-xs font-medium text-red-600">Breach flagged</span>}
          {row.archived_at && <span className="text-xs font-medium text-inksoft">Archived</span>}
        </div>
      ),
    },
    {
      key: "actions",
      header: "",
      className: "text-right",
      render: (row) => {
        const actions: RowAction[] = [
          { label: "View", onClick: () => setViewingObservation(row) },
          { label: "Edit", onClick: () => openEdit(row) },
          {
            label: row.archived_at ? "Restore" : "Archive",
            onClick: () => setArchiveTarget(row),
            tone: row.archived_at ? "default" : "danger",
          },
        ];
        return <RowActionsMenu actions={actions} label={`${labelFor(row.type)} actions`} />;
      },
    },
  ];

  return (
    <Card>
      <CardHeader>
        <div className="flex items-center justify-between">
          <span>Observations</span>
          <Button variant="secondary" onClick={() => setIsCreateOpen(true)}>
            Record Observation
          </Button>
        </div>
      </CardHeader>
      <CardBody>
        {activeAlerts.length > 0 && (
          <div className="mb-4 space-y-2">
            {activeAlerts.map((alert) => (
              <Alert key={alert.id} tone={alert.severity === "critical" ? "danger" : "warning"}>
                <div className="flex items-center justify-between gap-3">
                  <span>{alert.message}</span>
                  <button
                    type="button"
                    className="shrink-0 text-xs font-medium underline"
                    onClick={() => acknowledgeAlert.mutate(alert.id)}
                  >
                    Acknowledge
                  </button>
                </div>
              </Alert>
            ))}
          </div>
        )}

        <div className="mb-4 flex items-center gap-3">
          <FormField label="Trend for" htmlFor="observation-trend-type">
            <Select
              id="observation-trend-type"
              value={selectedType}
              onChange={(e) => setSelectedType(e.target.value as ObservationType)}
            >
              {OBSERVATION_TYPES.map((type) => (
                <option key={type} value={type}>
                  {labelFor(type)}
                </option>
              ))}
            </Select>
          </FormField>
        </div>
        <div className="mb-6 rounded-xl border border-line p-4">
          <ObservationTrendChart
            observations={readingsForType}
            type={selectedType}
            unit={readingsForType[0]?.unit ?? null}
          />
        </div>

        {!isLoading && (observations ?? []).length === 0 ? (
          <EmptyState message="No observations recorded yet." />
        ) : (
          <DataTable columns={columns} rows={observations ?? []} rowKey={(row) => row.id} isLoading={isLoading} />
        )}
      </CardBody>

      <Modal
        isOpen={isCreateOpen}
        onClose={() => {
          setIsCreateOpen(false);
          setError(null);
        }}
        title="Record Observation"
        footer={
          <>
            <Button variant="secondary" onClick={() => setIsCreateOpen(false)}>
              Cancel
            </Button>
            <Button form="new-observation-form" type="submit" isLoading={createObservation.isPending}>
              Save
            </Button>
          </>
        }
      >
        <form id="new-observation-form" onSubmit={handleCreate}>
          {error && (
            <div className="mb-4">
              <Alert tone="danger">{error}</Alert>
            </div>
          )}
          <FormField label="Type" htmlFor="observation-type">
            <Select
              id="observation-type"
              value={formType}
              onChange={(e) => setFormType(e.target.value as ObservationType)}
            >
              {OBSERVATION_TYPES.map((type) => (
                <option key={type} value={type}>
                  {labelFor(type)}
                </option>
              ))}
            </Select>
          </FormField>

          {formType === "blood_pressure" ? (
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <FormField label="Systolic" htmlFor="observation-systolic">
                <Input
                  id="observation-systolic"
                  type="number"
                  required
                  value={systolic}
                  onChange={(e) => setSystolic(e.target.value)}
                />
              </FormField>
              <FormField label="Diastolic" htmlFor="observation-diastolic">
                <Input
                  id="observation-diastolic"
                  type="number"
                  required
                  value={diastolic}
                  onChange={(e) => setDiastolic(e.target.value)}
                />
              </FormField>
            </div>
          ) : (
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <FormField label="Value" htmlFor="observation-value">
                <Input
                  id="observation-value"
                  type="number"
                  required
                  value={value}
                  onChange={(e) => setValue(e.target.value)}
                />
              </FormField>
              <FormField label="Unit" htmlFor="observation-unit">
                <Input id="observation-unit" value={unit} onChange={(e) => setUnit(e.target.value)} />
              </FormField>
            </div>
          )}

          <FormField label="Notes" htmlFor="observation-notes">
            <Textarea id="observation-notes" value={notes} onChange={(e) => setNotes(e.target.value)} />
          </FormField>
        </form>
      </Modal>

      <Modal
        isOpen={Boolean(editingObservation)}
        onClose={() => {
          setEditingObservation(null);
          setEditError(null);
        }}
        title={editingObservation ? `Edit — ${labelFor(editingObservation.type)}` : "Edit Observation"}
        footer={
          <>
            <Button variant="secondary" onClick={() => setEditingObservation(null)}>
              Cancel
            </Button>
            <Button form="edit-observation-form" type="submit" isLoading={updateObservation.isPending}>
              Save
            </Button>
          </>
        }
      >
        <form id="edit-observation-form" onSubmit={handleSaveEdit}>
          {editError && (
            <div className="mb-4">
              <Alert tone="danger">{editError}</Alert>
            </div>
          )}
          {editingObservation?.type === "blood_pressure" ? (
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <FormField label="Systolic" htmlFor="edit-observation-systolic">
                <Input
                  id="edit-observation-systolic"
                  type="number"
                  required
                  value={editSystolic}
                  onChange={(e) => setEditSystolic(e.target.value)}
                />
              </FormField>
              <FormField label="Diastolic" htmlFor="edit-observation-diastolic">
                <Input
                  id="edit-observation-diastolic"
                  type="number"
                  required
                  value={editDiastolic}
                  onChange={(e) => setEditDiastolic(e.target.value)}
                />
              </FormField>
            </div>
          ) : (
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <FormField label="Value" htmlFor="edit-observation-value">
                <Input
                  id="edit-observation-value"
                  type="number"
                  required
                  value={editValue}
                  onChange={(e) => setEditValue(e.target.value)}
                />
              </FormField>
              <FormField label="Unit" htmlFor="edit-observation-unit">
                <Input id="edit-observation-unit" value={editUnit} onChange={(e) => setEditUnit(e.target.value)} />
              </FormField>
            </div>
          )}
          <FormField label="Notes" htmlFor="edit-observation-notes">
            <Textarea id="edit-observation-notes" value={editNotes} onChange={(e) => setEditNotes(e.target.value)} />
          </FormField>
        </form>
      </Modal>

      <Modal
        isOpen={Boolean(viewingObservation)}
        onClose={() => setViewingObservation(null)}
        title={viewingObservation ? labelFor(viewingObservation.type) : "Observation"}
        footer={
          <Button variant="secondary" onClick={() => setViewingObservation(null)}>
            Close
          </Button>
        }
      >
        {viewingObservation && (
          <dl>
            <div className="flex justify-between border-b border-line py-2 text-sm">
              <dt className="text-inksoft">Recorded at</dt>
              <dd className="font-medium text-ink">{new Date(viewingObservation.recorded_at).toLocaleString()}</dd>
            </div>
            <div className="flex justify-between border-b border-line py-2 text-sm">
              <dt className="text-inksoft">Reading</dt>
              <dd className="font-medium text-ink">{readingText(viewingObservation)}</dd>
            </div>
            <div className="flex justify-between border-b border-line py-2 text-sm">
              <dt className="text-inksoft">Recorded by</dt>
              <dd className="font-medium text-ink">{viewingObservation.recorded_by_name ?? "—"}</dd>
            </div>
            <div className="border-b border-line py-2 text-sm last:border-0">
              <dt className="mb-1 text-inksoft">Notes</dt>
              <dd className="font-medium text-ink">{viewingObservation.notes ?? "—"}</dd>
            </div>
          </dl>
        )}
      </Modal>

      <ConfirmDialog
        isOpen={Boolean(archiveTarget)}
        title={archiveTarget?.archived_at ? "Restore observation" : "Archive observation"}
        message={
          archiveTarget?.archived_at
            ? "Restore this observation to the active list?"
            : "Archive this observation? It's kept for the record but hidden from the active list and trend chart."
        }
        confirmLabel={archiveTarget?.archived_at ? "Restore" : "Archive"}
        tone={archiveTarget?.archived_at ? "default" : "danger"}
        isLoading={archiveObservation.isPending}
        error={archiveError}
        onConfirm={handleConfirmArchive}
        onCancel={() => {
          setArchiveTarget(null);
          setArchiveError(null);
        }}
      />
    </Card>
  );
}
