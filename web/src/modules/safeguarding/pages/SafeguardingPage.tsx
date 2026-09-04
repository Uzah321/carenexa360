import { useState, type FormEvent } from "react";
import {
  Alert,
  Button,
  Card,
  CardBody,
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
import { useAuth } from "../../../lib/auth-context";
import { apiErrorMessage } from "../../../lib/api-error";
import { useServiceUsers } from "../../service-users/api";
import { useCreateSafeguardingCase, useSafeguardingCases, useUpdateSafeguardingCase } from "../api";
import {
  SAFEGUARDING_CASE_STATUSES,
  SAFEGUARDING_ROLES,
  type SafeguardingCase,
  type SafeguardingCaseStatus,
} from "../../../lib/types";

const STATUS_TONE: Record<SafeguardingCaseStatus, "info" | "warning" | "success" | "neutral"> = {
  reported: "info",
  investigating: "warning",
  actions_taken: "warning",
  closed: "success",
};

function SafeguardingCaseDetail({ safeguardingCase, onClose }: { safeguardingCase: SafeguardingCase; onClose: () => void }) {
  const updateCase = useUpdateSafeguardingCase(safeguardingCase.id);
  const [status, setStatus] = useState(safeguardingCase.status);
  const [investigationNotes, setInvestigationNotes] = useState(safeguardingCase.investigation_notes ?? "");
  const [actionsTaken, setActionsTaken] = useState(safeguardingCase.actions_taken ?? "");
  const [outcome, setOutcome] = useState(safeguardingCase.outcome ?? "");
  const [error, setError] = useState<string | null>(null);

  async function handleSave(event: FormEvent) {
    event.preventDefault();
    setError(null);
    try {
      await updateCase.mutateAsync({
        status,
        investigation_notes: investigationNotes || undefined,
        actions_taken: actionsTaken || undefined,
        outcome: outcome || undefined,
      });
      onClose();
    } catch (err) {
      setError(apiErrorMessage(err, "Could not save this case. Please try again."));
    }
  }

  return (
    <form onSubmit={handleSave}>
      {error && (
        <div className="mb-3">
          <Alert tone="danger">{error}</Alert>
        </div>
      )}
      <p className="mb-3 text-sm text-inksoft">
        <span className="font-medium text-ink">{safeguardingCase.concern_type}</span>
        {safeguardingCase.service_user_name && ` — ${safeguardingCase.service_user_name}`}
      </p>
      {safeguardingCase.immediate_risk && (
        <div className="mb-3">
          <Alert tone="danger">Flagged as an immediate risk.</Alert>
        </div>
      )}
      <FormField label="Status" htmlFor="sg-status">
        <Select id="sg-status" value={status} onChange={(e) => setStatus(e.target.value as SafeguardingCaseStatus)}>
          {SAFEGUARDING_CASE_STATUSES.map((s) => (
            <option key={s} value={s}>
              {s.replaceAll("_", " ")}
            </option>
          ))}
        </Select>
      </FormField>
      <FormField label="Investigation notes" htmlFor="sg-investigation-notes">
        <Textarea
          id="sg-investigation-notes"
          value={investigationNotes}
          onChange={(e) => setInvestigationNotes(e.target.value)}
        />
      </FormField>
      <FormField label="Actions taken" htmlFor="sg-actions-taken">
        <Textarea id="sg-actions-taken" value={actionsTaken} onChange={(e) => setActionsTaken(e.target.value)} />
      </FormField>
      <FormField label="Outcome" htmlFor="sg-outcome">
        <Textarea id="sg-outcome" value={outcome} onChange={(e) => setOutcome(e.target.value)} />
      </FormField>
      <Button type="submit" isLoading={updateCase.isPending}>
        Save
      </Button>
    </form>
  );
}

export function SafeguardingPage() {
  const { hasAnyRole } = useAuth();
  const isAuthorized = hasAnyRole(SAFEGUARDING_ROLES);

  // Hooks must run unconditionally on every render — the access check below
  // only gates what gets *returned*, not which hooks get called, since
  // React ties hook identity to call order.
  const { data, isLoading } = useSafeguardingCases();
  const { data: serviceUsers } = useServiceUsers(1);
  const createCase = useCreateSafeguardingCase();

  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [activeCase, setActiveCase] = useState<SafeguardingCase | null>(null);
  const [serviceUserId, setServiceUserId] = useState<number | "">("");
  const [victimName, setVictimName] = useState("");
  const [allegedPerpetrator, setAllegedPerpetrator] = useState("");
  const [concernType, setConcernType] = useState("");
  const [immediateRisk, setImmediateRisk] = useState(false);
  const [externalAgencies, setExternalAgencies] = useState("");
  const [createError, setCreateError] = useState<string | null>(null);

  if (!isAuthorized) {
    return (
      <Card>
        <CardBody>
          <Alert tone="danger">You don't have permission to view Safeguarding cases.</Alert>
        </CardBody>
      </Card>
    );
  }

  async function handleCreate(event: FormEvent) {
    event.preventDefault();
    setCreateError(null);
    try {
      await createCase.mutateAsync({
        service_user_id: serviceUserId || undefined,
        victim_name: victimName || undefined,
        alleged_perpetrator: allegedPerpetrator || undefined,
        concern_type: concernType,
        immediate_risk: immediateRisk,
        external_agencies_notified: externalAgencies || undefined,
      });
      setIsCreateOpen(false);
      setServiceUserId("");
      setVictimName("");
      setAllegedPerpetrator("");
      setConcernType("");
      setImmediateRisk(false);
      setExternalAgencies("");
    } catch (err) {
      setCreateError(apiErrorMessage(err, "Could not save this concern. Please try again."));
    }
  }

  const columns: Column<SafeguardingCase>[] = [
    { key: "created_at", header: "Reported", render: (row) => new Date(row.created_at).toLocaleDateString() },
    { key: "concern_type", header: "Concern", render: (row) => row.concern_type },
    {
      key: "subject",
      header: "Subject",
      render: (row) => row.service_user_name ?? row.victim_name ?? "—",
    },
    {
      key: "immediate_risk",
      header: "",
      render: (row) => (row.immediate_risk ? <StatusBadge label="Immediate risk" tone="danger" /> : null),
    },
    {
      key: "status",
      header: "Status",
      render: (row) => <StatusBadge label={row.status.replaceAll("_", " ")} tone={STATUS_TONE[row.status]} />,
    },
    {
      key: "actions",
      header: "",
      render: (row) => (
        <button
          type="button"
          className="text-sm font-medium text-teal hover:text-teal/90"
          onClick={() => setActiveCase(row)}
        >
          View
        </button>
      ),
    },
  ];

  return (
    <div>
      <div className="mb-6 rounded-2xl border border-line bg-white px-5 py-4">
        <div className="flex items-start justify-between">
          <div>
            <h1 className="text-2xl font-extrabold tracking-tight text-ink">Safeguarding</h1>
            <p className="mt-1 text-sm text-inksoft">
              Restricted to Organization Owners/Admins, Care Managers, Compliance Officers and Auditors.
            </p>
          </div>
          <Button onClick={() => setIsCreateOpen(true)}>Report Concern</Button>
        </div>
      </div>

      {!isLoading && (data?.data ?? []).length === 0 ? (
        <EmptyState message="No safeguarding concerns on record." />
      ) : (
        <DataTable columns={columns} rows={data?.data ?? []} rowKey={(row) => row.id} isLoading={isLoading} />
      )}

      <Modal
        isOpen={isCreateOpen}
        onClose={() => {
          setIsCreateOpen(false);
          setCreateError(null);
        }}
        title="Report Safeguarding Concern"
        footer={
          <>
            <Button variant="secondary" onClick={() => setIsCreateOpen(false)}>
              Cancel
            </Button>
            <Button form="new-safeguarding-form" type="submit" isLoading={createCase.isPending}>
              Save
            </Button>
          </>
        }
      >
        <form id="new-safeguarding-form" onSubmit={handleCreate}>
          {createError && (
            <div className="mb-4">
              <Alert tone="danger">{createError}</Alert>
            </div>
          )}
          <FormField label="Service user (optional)" htmlFor="sg-service-user">
            <Select
              id="sg-service-user"
              value={serviceUserId}
              onChange={(e) => setServiceUserId(e.target.value ? Number(e.target.value) : "")}
            >
              <option value="">Not a service user</option>
              {(serviceUsers?.data ?? []).map((su) => (
                <option key={su.id} value={su.id}>
                  {su.first_name} {su.last_name}
                </option>
              ))}
            </Select>
          </FormField>
          <FormField label="Victim name (if not a service user)" htmlFor="sg-victim-name">
            <Input id="sg-victim-name" value={victimName} onChange={(e) => setVictimName(e.target.value)} />
          </FormField>
          <FormField label="Concern type" htmlFor="sg-concern-type">
            <Input id="sg-concern-type" required value={concernType} onChange={(e) => setConcernType(e.target.value)} />
          </FormField>
          <FormField label="Alleged perpetrator" htmlFor="sg-alleged-perpetrator">
            <Textarea
              id="sg-alleged-perpetrator"
              value={allegedPerpetrator}
              onChange={(e) => setAllegedPerpetrator(e.target.value)}
            />
          </FormField>
          <FormField label="External agencies notified" htmlFor="sg-external-agencies">
            <Textarea
              id="sg-external-agencies"
              value={externalAgencies}
              onChange={(e) => setExternalAgencies(e.target.value)}
            />
          </FormField>
          <div className="mt-2">
            <Checkbox
              id="sg-immediate-risk"
              label="This is an immediate risk"
              checked={immediateRisk}
              onChange={(e) => setImmediateRisk(e.target.checked)}
            />
          </div>
        </form>
      </Modal>

      <Drawer isOpen={Boolean(activeCase)} onClose={() => setActiveCase(null)} title="Safeguarding Case">
        {activeCase && <SafeguardingCaseDetail safeguardingCase={activeCase} onClose={() => setActiveCase(null)} />}
      </Drawer>
    </div>
  );
}
