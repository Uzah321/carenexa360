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
  Modal,
  Select,
  StatusBadge,
  Textarea,
  type Column,
} from "../../../design-system";
import { apiErrorMessage } from "../../../lib/api-error";
import { useCreateIncident, useIncidents } from "../../incidents/api";
import { INCIDENT_SEVERITIES, INCIDENT_TYPES, type Incident, type IncidentSeverity, type IncidentType } from "../../../lib/types";

const SEVERITY_TONE: Record<IncidentSeverity, "neutral" | "warning" | "danger"> = {
  low: "neutral",
  medium: "warning",
  high: "danger",
  critical: "danger",
};

export function IncidentsTab({ serviceUserId }: { serviceUserId: number }) {
  const { data, isLoading } = useIncidents({ service_user_id: serviceUserId });
  const createIncident = useCreateIncident();

  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [type, setType] = useState<IncidentType>("fall");
  const [severity, setSeverity] = useState<IncidentSeverity>("low");
  const [description, setDescription] = useState("");
  const [immediateAction, setImmediateAction] = useState("");
  const [error, setError] = useState<string | null>(null);

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
      render: (row) => <StatusBadge label={row.status.replaceAll("_", " ")} tone="info" />,
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
    </Card>
  );
}
