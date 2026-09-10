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
  Modal,
  RowActionsMenu,
  Select,
  StatusBadge,
  Textarea,
  type Column,
  type RowAction,
} from "../../../design-system";
import { apiErrorMessage } from "../../../lib/api-error";
import { useArchiveIncident, useCreateIncident, useIncidents, useUpdateIncident } from "../../incidents/api";
import {
  INCIDENT_SEVERITIES,
  INCIDENT_STATUSES,
  INCIDENT_TYPES,
  type Incident,
  type IncidentSeverity,
  type IncidentStatus,
  type IncidentType,
} from "../../../lib/types";

const SEVERITY_TONE: Record<IncidentSeverity, "neutral" | "warning" | "danger"> = {
  low: "neutral",
  medium: "warning",
  high: "danger",
  critical: "danger",
};

function EditIncidentModal({ incident, onClose }: { incident: Incident; onClose: () => void }) {
  const updateIncident = useUpdateIncident(incident.id);
  const [type, setType] = useState<IncidentType>(incident.type);
  const [severity, setSeverity] = useState<IncidentSeverity>(incident.severity);
  const [description, setDescription] = useState(incident.description);
  const [immediateAction, setImmediateAction] = useState(incident.immediate_action ?? "");
  const [status, setStatus] = useState<IncidentStatus>(incident.status);
  const [investigationNotes, setInvestigationNotes] = useState(incident.investigation_notes ?? "");
  const [correctiveActions, setCorrectiveActions] = useState(incident.corrective_actions ?? "");
  const [error, setError] = useState<string | null>(null);

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setError(null);
    try {
      await updateIncident.mutateAsync({
        type,
        severity,
        description,
        immediate_action: immediateAction || undefined,
        status,
        investigation_notes: investigationNotes || undefined,
        corrective_actions: correctiveActions || undefined,
      });
      onClose();
    } catch (err) {
      setError(apiErrorMessage(err, "Could not save this incident. Please try again."));
    }
  }

  return (
    <Modal
      isOpen
      onClose={onClose}
      title="Edit Incident"
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button form="edit-incident-form" type="submit" isLoading={updateIncident.isPending}>
            Save
          </Button>
        </>
      }
    >
      <form id="edit-incident-form" onSubmit={handleSubmit}>
        {error && (
          <div className="mb-4">
            <Alert tone="danger">{error}</Alert>
          </div>
        )}
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <FormField label="Type" htmlFor="edit-incident-type">
            <Select id="edit-incident-type" value={type} onChange={(e) => setType(e.target.value as IncidentType)}>
              {INCIDENT_TYPES.map((t) => (
                <option key={t} value={t}>
                  {t.replaceAll("_", " ")}
                </option>
              ))}
            </Select>
          </FormField>
          <FormField label="Severity" htmlFor="edit-incident-severity">
            <Select
              id="edit-incident-severity"
              value={severity}
              onChange={(e) => setSeverity(e.target.value as IncidentSeverity)}
            >
              {INCIDENT_SEVERITIES.map((s) => (
                <option key={s} value={s}>
                  {s}
                </option>
              ))}
            </Select>
          </FormField>
        </div>
        <FormField label="Status" htmlFor="edit-incident-status">
          <Select id="edit-incident-status" value={status} onChange={(e) => setStatus(e.target.value as IncidentStatus)}>
            {INCIDENT_STATUSES.map((s) => (
              <option key={s} value={s}>
                {s.replaceAll("_", " ")}
              </option>
            ))}
          </Select>
        </FormField>
        <FormField label="Description" htmlFor="edit-incident-description">
          <Textarea
            id="edit-incident-description"
            required
            value={description}
            onChange={(e) => setDescription(e.target.value)}
          />
        </FormField>
        <FormField label="Immediate action taken" htmlFor="edit-incident-immediate-action">
          <Textarea
            id="edit-incident-immediate-action"
            value={immediateAction}
            onChange={(e) => setImmediateAction(e.target.value)}
          />
        </FormField>
        <FormField label="Investigation notes" htmlFor="edit-incident-investigation-notes">
          <Textarea
            id="edit-incident-investigation-notes"
            value={investigationNotes}
            onChange={(e) => setInvestigationNotes(e.target.value)}
          />
        </FormField>
        <FormField label="Corrective actions" htmlFor="edit-incident-corrective-actions">
          <Textarea
            id="edit-incident-corrective-actions"
            value={correctiveActions}
            onChange={(e) => setCorrectiveActions(e.target.value)}
          />
        </FormField>
      </form>
    </Modal>
  );
}

export function IncidentsTab({ serviceUserId }: { serviceUserId: number }) {
  const { data, isLoading } = useIncidents({ service_user_id: serviceUserId });
  const createIncident = useCreateIncident();
  const archiveIncident = useArchiveIncident();

  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [type, setType] = useState<IncidentType>("fall");
  const [severity, setSeverity] = useState<IncidentSeverity>("low");
  const [description, setDescription] = useState("");
  const [immediateAction, setImmediateAction] = useState("");
  const [error, setError] = useState<string | null>(null);

  const [viewingIncident, setViewingIncident] = useState<Incident | null>(null);
  const [editingIncident, setEditingIncident] = useState<Incident | null>(null);
  const [archiveTarget, setArchiveTarget] = useState<Incident | null>(null);
  const [archiveError, setArchiveError] = useState<string | null>(null);

  async function handleCreate(event: FormEvent) {
    event.preventDefault();
    setError(null);
    try {
      await createIncident.mutateAsync({
        service_user_id: serviceUserId,
        type,
        severity,
        description,
        immediate_action: immediateAction || undefined,
      });
      setIsCreateOpen(false);
      setType("fall");
      setSeverity("low");
      setDescription("");
      setImmediateAction("");
    } catch (err) {
      setError(apiErrorMessage(err, "Could not report the incident. Please try again."));
    }
  }

  async function handleConfirmArchive() {
    if (!archiveTarget) return;
    setArchiveError(null);
    try {
      await archiveIncident.mutateAsync({ id: archiveTarget.id, archived: !archiveTarget.archived_at });
      setArchiveTarget(null);
    } catch (err) {
      setArchiveError(apiErrorMessage(err, "Could not update this incident. Please try again."));
    }
  }

  const columns: Column<Incident>[] = [
    {
      key: "created_at",
      header: "Reported",
      render: (row) => new Date(row.created_at).toLocaleString(),
    },
    { key: "type", header: "Type", render: (row) => row.type.replaceAll("_", " ") },
    {
      key: "severity",
      header: "Severity",
      render: (row) => <StatusBadge label={row.severity} tone={SEVERITY_TONE[row.severity]} />,
    },
    { key: "description", header: "Description", render: (row) => row.description },
    {
      key: "status",
      header: "Status",
      render: (row) => (
        <div className="flex items-center gap-1.5">
          <StatusBadge label={row.status.replaceAll("_", " ")} tone="info" />
          {row.archived_at && <StatusBadge label="Archived" tone="neutral" />}
        </div>
      ),
    },
    {
      key: "actions",
      header: "",
      className: "text-right",
      render: (row) => {
        const actions: RowAction[] = [
          { label: "View", onClick: () => setViewingIncident(row) },
          { label: "Edit", onClick: () => setEditingIncident(row) },
          {
            label: row.archived_at ? "Restore" : "Archive",
            onClick: () => setArchiveTarget(row),
            tone: row.archived_at ? "default" : "danger",
          },
        ];
        return <RowActionsMenu actions={actions} label="Incident actions" />;
      },
    },
  ];

  return (
    <Card>
      <CardHeader>
        <div className="flex items-center justify-between">
          <span>Incidents</span>
          <Button variant="secondary" onClick={() => setIsCreateOpen(true)}>
            Report Incident
          </Button>
        </div>
      </CardHeader>
      <CardBody>
        {!isLoading && (data?.data ?? []).length === 0 ? (
          <EmptyState message="No incidents reported for this service user." />
        ) : (
          <DataTable columns={columns} rows={data?.data ?? []} rowKey={(row) => row.id} isLoading={isLoading} />
        )}
      </CardBody>

      <Modal
        isOpen={isCreateOpen}
        onClose={() => {
          setIsCreateOpen(false);
          setError(null);
        }}
        title="Report Incident"
        footer={
          <>
            <Button variant="secondary" onClick={() => setIsCreateOpen(false)}>
              Cancel
            </Button>
            <Button form="new-incident-form" type="submit" isLoading={createIncident.isPending}>
              Save
            </Button>
          </>
        }
      >
        <form id="new-incident-form" onSubmit={handleCreate}>
          {error && (
            <div className="mb-4">
              <Alert tone="danger">{error}</Alert>
            </div>
          )}
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <FormField label="Type" htmlFor="incident-type">
              <Select id="incident-type" value={type} onChange={(e) => setType(e.target.value as IncidentType)}>
                {INCIDENT_TYPES.map((t) => (
                  <option key={t} value={t}>
                    {t.replaceAll("_", " ")}
                  </option>
                ))}
              </Select>
            </FormField>
            <FormField label="Severity" htmlFor="incident-severity">
              <Select
                id="incident-severity"
                value={severity}
                onChange={(e) => setSeverity(e.target.value as IncidentSeverity)}
              >
                {INCIDENT_SEVERITIES.map((s) => (
                  <option key={s} value={s}>
                    {s}
                  </option>
                ))}
              </Select>
            </FormField>
          </div>
          <FormField label="Description" htmlFor="incident-description">
            <Textarea
              id="incident-description"
              required
              value={description}
              onChange={(e) => setDescription(e.target.value)}
            />
          </FormField>
          <FormField label="Immediate action taken" htmlFor="incident-immediate-action">
            <Textarea
              id="incident-immediate-action"
              value={immediateAction}
              onChange={(e) => setImmediateAction(e.target.value)}
            />
          </FormField>
        </form>
      </Modal>

      <Modal
        isOpen={Boolean(viewingIncident)}
        onClose={() => setViewingIncident(null)}
        title="Incident Details"
        footer={
          <Button variant="secondary" onClick={() => setViewingIncident(null)}>
            Close
          </Button>
        }
      >
        {viewingIncident && (
          <dl>
            <div className="flex justify-between border-b border-line py-2 text-sm">
              <dt className="text-inksoft">Type</dt>
              <dd className="font-medium text-ink">{viewingIncident.type.replaceAll("_", " ")}</dd>
            </div>
            <div className="flex justify-between border-b border-line py-2 text-sm">
              <dt className="text-inksoft">Severity</dt>
              <dd className="font-medium text-ink">{viewingIncident.severity}</dd>
            </div>
            <div className="flex justify-between border-b border-line py-2 text-sm">
              <dt className="text-inksoft">Status</dt>
              <dd className="font-medium text-ink">{viewingIncident.status.replaceAll("_", " ")}</dd>
            </div>
            <div className="border-b border-line py-2 text-sm">
              <dt className="mb-1 text-inksoft">Description</dt>
              <dd className="font-medium text-ink">{viewingIncident.description}</dd>
            </div>
            <div className="border-b border-line py-2 text-sm">
              <dt className="mb-1 text-inksoft">Immediate action</dt>
              <dd className="font-medium text-ink">{viewingIncident.immediate_action ?? "—"}</dd>
            </div>
            <div className="border-b border-line py-2 text-sm">
              <dt className="mb-1 text-inksoft">Investigation notes</dt>
              <dd className="font-medium text-ink">{viewingIncident.investigation_notes ?? "—"}</dd>
            </div>
            <div className="border-b border-line py-2 text-sm">
              <dt className="mb-1 text-inksoft">Corrective actions</dt>
              <dd className="font-medium text-ink">{viewingIncident.corrective_actions ?? "—"}</dd>
            </div>
            <div className="flex justify-between border-b border-line py-2 text-sm">
              <dt className="text-inksoft">Reported by</dt>
              <dd className="font-medium text-ink">{viewingIncident.reported_by_name ?? "—"}</dd>
            </div>
            <div className="flex justify-between border-b border-line py-2 text-sm">
              <dt className="text-inksoft">Assigned to</dt>
              <dd className="font-medium text-ink">{viewingIncident.assigned_to_name ?? "—"}</dd>
            </div>
            <div className="flex justify-between py-2 text-sm">
              <dt className="text-inksoft">Closed at</dt>
              <dd className="font-medium text-ink">
                {viewingIncident.closed_at ? new Date(viewingIncident.closed_at).toLocaleString() : "—"}
              </dd>
            </div>
          </dl>
        )}
      </Modal>

      {editingIncident && <EditIncidentModal incident={editingIncident} onClose={() => setEditingIncident(null)} />}

      <ConfirmDialog
        isOpen={Boolean(archiveTarget)}
        title={archiveTarget?.archived_at ? "Restore incident" : "Archive incident"}
        message={
          archiveTarget?.archived_at
            ? "Restore this incident to the active list?"
            : "Archive this incident? It's kept for compliance review but hidden from the active list."
        }
        confirmLabel={archiveTarget?.archived_at ? "Restore" : "Archive"}
        tone={archiveTarget?.archived_at ? "default" : "danger"}
        isLoading={archiveIncident.isPending}
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
