import { useMemo, useState, type FormEvent } from "react";
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
import { News2Badge, News2Summary, ObservationValueFields } from "../../observations/components/News2Fields";
import { WOUND_STAGE_LABELS } from "../../observations/wound";
import { DEFAULT_UNITS, draftToValue, valueToDraft, type ObservationDraft } from "../../observations/observationDraft";
import { assessNews2 } from "../../observations/news2";
import { rangeScores } from "../../observations/rangeScores";
import { OBSERVATION_TYPES, type Observation, type ObservationType } from "../../../lib/types";

function labelFor(type: ObservationType): string {
  return type === "news2" ? "NEWS2 full set" : type.replaceAll("_", " ");
}

function readingText(observation: Observation): string {
  const v = observation.value;
  if (observation.type === "blood_pressure") return `${v.systolic}/${v.diastolic} mmHg`;
  if (observation.type === "news2") {
    return `RR ${v.respiration_rate} · SpO₂ ${v.spo2}%${v.on_oxygen ? " (O₂)" : ""} · BP ${v.systolic} · P ${v.pulse} · T ${v.temperature}°C`;
  }
  if (observation.type === "wound") {
    const size = v.length_cm && v.width_cm ? ` · ${v.length_cm}×${v.width_cm}${v.depth_cm ? `×${v.depth_cm}` : ""} cm` : "";
    const stage = v.stage ? ` · ${WOUND_STAGE_LABELS[String(v.stage)] ?? v.stage}` : "";
    return `${v.site}${size}${stage}`;
  }
  const oxygen = observation.type === "oxygen_saturation" && v.on_oxygen === true ? " on O₂" : "";
  return `${v.value}${observation.unit ? ` ${observation.unit}` : ""}${oxygen}`;
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
  const [draft, setDraft] = useState<ObservationDraft>({});
  const [notes, setNotes] = useState("");
  const [error, setError] = useState<string | null>(null);

  const [viewingObservation, setViewingObservation] = useState<Observation | null>(null);
  const [editingObservation, setEditingObservation] = useState<Observation | null>(null);
  const [editDraft, setEditDraft] = useState<ObservationDraft>({});
  const [editNotes, setEditNotes] = useState("");
  const [editError, setEditError] = useState<string | null>(null);
  const [archiveTarget, setArchiveTarget] = useState<Observation | null>(null);
  const [archiveError, setArchiveError] = useState<string | null>(null);

  const activeAlerts = (alerts ?? []).filter((a) => !a.acknowledged_at);
  const readingsForType = (observations ?? []).filter((o) => o.type === selectedType);

  // SpO2 Scale 2 is a clinical decision made once for the person, not per
  // reading — carry forward whichever scale their latest SpO2 reading used.
  const lastSpo2Scale = useMemo(() => {
    const latest = (observations ?? []).find((o) => (o.type === "oxygen_saturation" || o.type === "news2") && o.value.spo2_scale);
    return latest ? String(latest.value.spo2_scale) : "1";
  }, [observations]);

  const livePreview = useMemo(() => assessNews2(formType, draftToValue(formType, draft)), [formType, draft]);
  const liveRangeScores = useMemo(() => rangeScores(formType, draftToValue(formType, draft)), [formType, draft]);
  const editRangeScores = useMemo(
    () => (editingObservation ? rangeScores(editingObservation.type, draftToValue(editingObservation.type, editDraft)) : []),
    [editingObservation, editDraft],
  );
  const editPreview = useMemo(
    () => (editingObservation ? assessNews2(editingObservation.type, draftToValue(editingObservation.type, editDraft)) : null),
    [editingObservation, editDraft],
  );

  function freshDraft(type: ObservationType): ObservationDraft {
    if (type === "news2") return { spo2_scale: lastSpo2Scale, on_oxygen: false, consciousness: "alert" };
    if (type === "oxygen_saturation") return { spo2_scale: lastSpo2Scale, on_oxygen: false };
    return {};
  }

  function openCreate() {
    setFormType("blood_pressure");
    setDraft(freshDraft("blood_pressure"));
    setNotes("");
    setError(null);
    setIsCreateOpen(true);
  }

  async function handleCreate(event: FormEvent) {
    event.preventDefault();
    setError(null);
    try {
      await createObservation.mutateAsync({
        type: formType,
        value: draftToValue(formType, draft),
        unit: String(draft.unit || DEFAULT_UNITS[formType] || "") || undefined,
        notes: notes || undefined,
      });
      setIsCreateOpen(false);
    } catch (err) {
      setError(apiErrorMessage(err, "Could not save this observation. Please try again."));
    }
  }

  function openEdit(observation: Observation) {
    setEditingObservation(observation);
    setEditDraft({ ...valueToDraft(observation.value), unit: observation.unit ?? "" });
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
        value: draftToValue(editingObservation.type, editDraft),
        unit: String(editDraft.unit ?? "") || null,
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
    {
      key: "news2",
      header: "Status",
      render: (row) => <News2Badge assessment={row.news2} rangeScores={row.range_scores} isSet={row.type === "news2"} />,
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
          <Button variant="secondary" onClick={openCreate}>
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
              onChange={(e) => {
                const type = e.target.value as ObservationType;
                setFormType(type);
                setDraft(freshDraft(type));
              }}
            >
              {OBSERVATION_TYPES.map((type) => (
                <option key={type} value={type}>
                  {labelFor(type)}
                </option>
              ))}
            </Select>
          </FormField>

          <ObservationValueFields
            type={formType}
            idPrefix="observation"
            draft={draft}
            onChange={(patch) => setDraft((prev) => ({ ...prev, ...patch }))}
          />

          <News2Summary assessment={livePreview} rangeScores={liveRangeScores} isSet={formType === "news2"} />

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
          {editingObservation && (
            <ObservationValueFields
              type={editingObservation.type}
              idPrefix="edit-observation"
              draft={editDraft}
              onChange={(patch) => setEditDraft((prev) => ({ ...prev, ...patch }))}
            />
          )}
          <News2Summary assessment={editPreview} rangeScores={editRangeScores} isSet={editingObservation?.type === "news2"} />
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
          <>
            <dl className="mb-4">
              <div className="flex justify-between border-b border-line py-2 text-sm">
                <dt className="text-inksoft">Recorded at</dt>
                <dd className="font-medium text-ink">{new Date(viewingObservation.recorded_at).toLocaleString()}</dd>
              </div>
              <div className="flex justify-between gap-4 border-b border-line py-2 text-sm">
                <dt className="text-inksoft">Reading</dt>
                <dd className="text-right font-medium text-ink">{readingText(viewingObservation)}</dd>
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
            <News2Summary
              assessment={viewingObservation.news2 ?? null}
              rangeScores={viewingObservation.range_scores}
              isSet={viewingObservation.type === "news2"}
            />
          </>
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
