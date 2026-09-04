import { useState, type FormEvent } from "react";
import { Link } from "react-router-dom";
import {
  Alert,
  Button,
  DataTable,
  FilterBar,
  FormField,
  Modal,
  Pagination,
  RowActionsMenu,
  Select,
  StatusBadge,
  Textarea,
  type Column,
} from "../../../design-system";
import { apiErrorMessage } from "../../../lib/api-error";
import { useCreateIncident, useIncidents, useUpdateIncident, useUpdateIncidentStatus, type UpdateIncidentInput } from "../api";
import { useServiceUsers } from "../../service-users/api";
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

function remainingStatuses(current: IncidentStatus): IncidentStatus[] {
  return INCIDENT_STATUSES.slice(INCIDENT_STATUSES.indexOf(current) + 1);
}

function incidentToEditForm(incident: Incident): UpdateIncidentInput {
  return {
    type: incident.type,
    severity: incident.severity,
    description: incident.description,
    immediate_action: incident.immediate_action ?? "",
  };
}

export function IncidentsPage() {
  const [status, setStatus] = useState<IncidentStatus | "">("");
  const [severity, setSeverity] = useState<IncidentSeverity | "">("");
  const [page, setPage] = useState(1);

  const { data, isLoading } = useIncidents({
    status: status || undefined,
    severity: severity || undefined,
    page,
  });
  const { data: serviceUsers } = useServiceUsers(1);
  const createIncident = useCreateIncident();

  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [serviceUserId, setServiceUserId] = useState<number | "">("");
  const [type, setType] = useState<IncidentType>("fall");
  const [incidentSeverity, setIncidentSeverity] = useState<IncidentSeverity>("low");
  const [description, setDescription] = useState("");
  const [immediateAction, setImmediateAction] = useState("");
  const [createError, setCreateError] = useState<string | null>(null);

  const [editingIncident, setEditingIncident] = useState<Incident | null>(null);
  const [editForm, setEditForm] = useState<UpdateIncidentInput>({});
  const [editError, setEditError] = useState<string | null>(null);
  const updateIncident = useUpdateIncident(editingIncident?.id ?? 0);
  const updateStatus = useUpdateIncidentStatus();

  async function handleCreate(event: FormEvent) {
    event.preventDefault();
    setCreateError(null);
    try {
      await createIncident.mutateAsync({
        service_user_id: serviceUserId || undefined,
        type,
        severity: incidentSeverity,
        description,
        immediate_action: immediateAction || undefined,
      });
      setIsCreateOpen(false);
      setServiceUserId("");
      setType("fall");
      setIncidentSeverity("low");
      setDescription("");
      setImmediateAction("");
    } catch (err) {
      setCreateError(apiErrorMessage(err, "Could not report the incident. Please try again."));
    }
  }

  function openEdit(incident: Incident) {
    setEditingIncident(incident);
    setEditForm(incidentToEditForm(incident));
    setEditError(null);
  }

  async function handleEdit(event: FormEvent) {
    event.preventDefault();
    setEditError(null);
    try {
      await updateIncident.mutateAsync(editForm);
      setEditingIncident(null);
    } catch (err) {
      setEditError(apiErrorMessage(err, "Could not save this incident. Please try again."));
    }
  }

  const columns: Column<Incident>[] = [
    {
      key: "created_at",
      header: "Reported",
      render: (row) => new Date(row.created_at).toLocaleDateString(),
    },
    {
      key: "service_user",
      header: "Service User",
      render: (row) =>
        row.service_user_id ? (
          <Link
            to={`/service-users/${row.service_user_id}`}
            className="font-medium text-teal hover:text-teal/90"
          >
            {row.service_user_name ?? "—"}
          </Link>
        ) : (
          "Staff / other"
        ),
    },
    { key: "type", header: "Type", render: (row) => row.type.replaceAll("_", " ") },
    {
      key: "severity",
      header: "Severity",
      render: (row) => <StatusBadge label={row.severity} tone={SEVERITY_TONE[row.severity]} />,
    },
    {
      key: "status",
      header: "Status",
      render: (row) => <StatusBadge label={row.status.replaceAll("_", " ")} tone="info" />,
    },
    {
      key: "actions",
      header: "",
      className: "w-12 text-right",
      render: (row) => (
        <RowActionsMenu
          actions={[
            { label: "Edit", onClick: () => openEdit(row) },
            ...remainingStatuses(row.status).map((status) => ({
              label: `Mark as ${status.replaceAll("_", " ")}`,
              tone: status === "closed" ? ("danger" as const) : ("default" as const),
              onClick: () => updateStatus.mutate({ id: row.id, status }),
            })),
          ]}
        />
      ),
    },
  ];

  return (
    <div>
      <div className="mb-6 rounded-2xl border border-line bg-white px-5 py-4">
        <div className="flex items-start justify-between">
          <div>
            <h1 className="text-2xl font-extrabold tracking-tight text-ink">Incidents</h1>
            <p className="mt-1 text-sm text-inksoft">Organization-wide incident register.</p>
          </div>
          <Button onClick={() => setIsCreateOpen(true)}>Report Incident</Button>
        </div>
      </div>

      <FilterBar>
        <FormField label="Status" htmlFor="filter-status">
          <Select id="filter-status" value={status} onChange={(e) => setStatus(e.target.value as IncidentStatus | "")}>
            <option value="">All statuses</option>
            {INCIDENT_STATUSES.map((s) => (
              <option key={s} value={s}>
                {s.replaceAll("_", " ")}
              </option>
            ))}
          </Select>
        </FormField>
        <FormField label="Severity" htmlFor="filter-severity">
          <Select
            id="filter-severity"
            value={severity}
            onChange={(e) => setSeverity(e.target.value as IncidentSeverity | "")}
          >
            <option value="">All severities</option>
            {INCIDENT_SEVERITIES.map((s) => (
              <option key={s} value={s}>
                {s}
              </option>
            ))}
          </Select>
        </FormField>
      </FilterBar>

      <DataTable columns={columns} rows={data?.data ?? []} rowKey={(row) => row.id} isLoading={isLoading} />
      {data && (
        <Pagination currentPage={data.meta.current_page} lastPage={data.meta.last_page} onPageChange={setPage} />
      )}

      <Modal
        isOpen={isCreateOpen}
        onClose={() => {
          setIsCreateOpen(false);
          setCreateError(null);
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
          {createError && (
            <div className="mb-4">
              <Alert tone="danger">{createError}</Alert>
            </div>
          )}
          <FormField label="Service user (optional)" htmlFor="incident-service-user">
            <Select
              id="incident-service-user"
              value={serviceUserId}
              onChange={(e) => setServiceUserId(e.target.value ? Number(e.target.value) : "")}
            >
              <option value="">Staff / other — no service user</option>
              {(serviceUsers?.data ?? []).map((su) => (
                <option key={su.id} value={su.id}>
                  {su.first_name} {su.last_name}
                </option>
              ))}
            </Select>
          </FormField>
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
                value={incidentSeverity}
                onChange={(e) => setIncidentSeverity(e.target.value as IncidentSeverity)}
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
        isOpen={Boolean(editingIncident)}
        onClose={() => {
          setEditingIncident(null);
          setEditError(null);
        }}
        title="Edit Incident"
        footer={
          <>
            <Button variant="secondary" onClick={() => setEditingIncident(null)}>
              Cancel
            </Button>
            <Button form="edit-incident-form" type="submit" isLoading={updateIncident.isPending}>
              Save
            </Button>
          </>
        }
      >
        <form id="edit-incident-form" onSubmit={handleEdit}>
          {editError && (
            <div className="mb-4">
              <Alert tone="danger">{editError}</Alert>
            </div>
          )}
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <FormField label="Type" htmlFor="edit-incident-type">
              <Select
                id="edit-incident-type"
                value={editForm.type ?? "fall"}
                onChange={(e) => setEditForm({ ...editForm, type: e.target.value as IncidentType })}
              >
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
                value={editForm.severity ?? "low"}
                onChange={(e) => setEditForm({ ...editForm, severity: e.target.value as IncidentSeverity })}
              >
                {INCIDENT_SEVERITIES.map((s) => (
                  <option key={s} value={s}>
                    {s}
                  </option>
                ))}
              </Select>
            </FormField>
          </div>
          <FormField label="Description" htmlFor="edit-incident-description">
            <Textarea
              id="edit-incident-description"
              required
              value={editForm.description ?? ""}
              onChange={(e) => setEditForm({ ...editForm, description: e.target.value })}
            />
          </FormField>
          <FormField label="Immediate action taken" htmlFor="edit-incident-immediate-action">
            <Textarea
              id="edit-incident-immediate-action"
              value={editForm.immediate_action ?? ""}
              onChange={(e) => setEditForm({ ...editForm, immediate_action: e.target.value })}
            />
          </FormField>
        </form>
      </Modal>
    </div>
  );
}
