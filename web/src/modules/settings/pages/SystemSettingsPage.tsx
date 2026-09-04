import { useEffect, useState, type FormEvent } from "react";
import { Link } from "react-router-dom";
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
  PageHeader,
  RowActionsMenu,
  StatusBadge,
  Tabs,
  type Column,
  type RowAction,
  type TabItem,
} from "../../../design-system";
import { useAuth } from "../../../lib/auth-context";
import { apiErrorMessage } from "../../../lib/api-error";
import type { Branch } from "../../../lib/types";
import {
  useBranches,
  useCreateBranch,
  useTenant,
  useUpdateBranch,
  useUpdateBranchStatus,
  useUpdateTenant,
  type CreateBranchInput,
} from "../../organization/api";

const TABS: TabItem[] = [
  { key: "company", label: "Company Details" },
  { key: "locations", label: "Locations" },
  { key: "general", label: "General Settings" },
  { key: "checklist", label: "Assessment Checklist" },
  { key: "data", label: "Data Maintenance" },
  { key: "reference", label: "Reference Data" },
  { key: "pathway", label: "Care Pathway" },
];

function CompanyDetailsTab({ tenantId }: { tenantId: number }) {
  const { data: tenant, isLoading } = useTenant(tenantId);
  const updateTenant = useUpdateTenant(tenantId);
  const [form, setForm] = useState({ name: "", country: "", timezone: "", currency: "", locale: "" });
  const [saved, setSaved] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (tenant) {
      setForm({
        name: tenant.name,
        country: tenant.country,
        timezone: tenant.timezone,
        currency: tenant.currency,
        locale: tenant.locale,
      });
    }
  }, [tenant]);

  async function handleSave(event: FormEvent) {
    event.preventDefault();
    setSaved(false);
    setError(null);
    try {
      await updateTenant.mutateAsync(form);
      setSaved(true);
    } catch {
      setError("Something went wrong saving your changes. Please try again.");
    }
  }

  if (isLoading || !tenant) {
    return (
      <Card>
        <CardBody>Loading…</CardBody>
      </Card>
    );
  }

  return (
    <Card>
      <CardHeader>Company Details</CardHeader>
      <CardBody>
        {saved && (
          <div className="mb-4">
            <Alert tone="success">Company details saved.</Alert>
          </div>
        )}
        {error && (
          <div className="mb-4">
            <Alert tone="danger">{error}</Alert>
          </div>
        )}
        <form onSubmit={handleSave} className="max-w-lg">
          <FormField label="Organization name" htmlFor="company-name">
            <Input id="company-name" required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
          </FormField>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <FormField label="Country" htmlFor="company-country">
              <Input
                id="company-country"
                required
                value={form.country}
                onChange={(e) => setForm({ ...form, country: e.target.value })}
              />
            </FormField>
            <FormField label="Timezone" htmlFor="company-timezone">
              <Input
                id="company-timezone"
                required
                value={form.timezone}
                onChange={(e) => setForm({ ...form, timezone: e.target.value })}
              />
            </FormField>
          </div>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <FormField label="Currency" htmlFor="company-currency">
              <Input
                id="company-currency"
                required
                value={form.currency}
                onChange={(e) => setForm({ ...form, currency: e.target.value })}
              />
            </FormField>
            <FormField label="Locale" htmlFor="company-locale">
              <Input
                id="company-locale"
                required
                value={form.locale}
                onChange={(e) => setForm({ ...form, locale: e.target.value })}
              />
            </FormField>
          </div>
          <Button type="submit" isLoading={updateTenant.isPending}>
            Save changes
          </Button>
        </form>
      </CardBody>
    </Card>
  );
}

const EMPTY_BRANCH: CreateBranchInput = { name: "", country: "", region: "", address: "" };

const BRANCH_STATUS_TONE: Record<Branch["status"], "success" | "neutral"> = {
  active: "success",
  inactive: "neutral",
};

function BranchFormFields({
  form,
  setForm,
  idPrefix,
}: {
  form: CreateBranchInput;
  setForm: (form: CreateBranchInput) => void;
  idPrefix: string;
}) {
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
      <FormField label="Country" htmlFor={`${idPrefix}-country`}>
        <Input
          id={`${idPrefix}-country`}
          required
          value={form.country}
          onChange={(e) => setForm({ ...form, country: e.target.value })}
        />
      </FormField>
      <FormField label="Region" htmlFor={`${idPrefix}-region`}>
        <Input
          id={`${idPrefix}-region`}
          value={form.region ?? ""}
          onChange={(e) => setForm({ ...form, region: e.target.value })}
        />
      </FormField>
      <FormField label="Address" htmlFor={`${idPrefix}-address`}>
        <Input
          id={`${idPrefix}-address`}
          value={form.address ?? ""}
          onChange={(e) => setForm({ ...form, address: e.target.value })}
        />
      </FormField>
    </>
  );
}

function EditBranchModal({
  tenantId,
  branch,
  onClose,
}: {
  tenantId: number;
  branch: Branch;
  onClose: () => void;
}) {
  const updateBranch = useUpdateBranch(tenantId);
  const [form, setForm] = useState<CreateBranchInput>({
    name: branch.name,
    country: branch.country,
    region: branch.region ?? "",
    address: branch.address ?? "",
  });
  const [error, setError] = useState<string | null>(null);

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setError(null);
    try {
      await updateBranch.mutateAsync({ id: branch.id, ...form });
      onClose();
    } catch (err) {
      setError(apiErrorMessage(err, "Could not save this location. Please try again."));
    }
  }

  return (
    <Modal
      isOpen
      onClose={onClose}
      title="Edit Location"
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button form="edit-branch-form" type="submit" isLoading={updateBranch.isPending}>
            Save
          </Button>
        </>
      }
    >
      <form id="edit-branch-form" onSubmit={handleSubmit}>
        {error && (
          <div className="mb-4">
            <Alert tone="danger">{error}</Alert>
          </div>
        )}
        <BranchFormFields form={form} setForm={setForm} idPrefix="edit-branch" />
      </form>
    </Modal>
  );
}

function LocationsTab({ tenantId }: { tenantId: number }) {
  const { data: branches, isLoading } = useBranches(tenantId);
  const createBranch = useCreateBranch(tenantId);
  const updateBranchStatus = useUpdateBranchStatus(tenantId);
  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [form, setForm] = useState<CreateBranchInput>(EMPTY_BRANCH);
  const [createError, setCreateError] = useState<string | null>(null);
  const [editTarget, setEditTarget] = useState<Branch | null>(null);
  const [statusTarget, setStatusTarget] = useState<Branch | null>(null);
  const [statusError, setStatusError] = useState<string | null>(null);

  async function handleCreate(event: FormEvent) {
    event.preventDefault();
    setCreateError(null);
    try {
      await createBranch.mutateAsync(form);
      setIsCreateOpen(false);
      setForm(EMPTY_BRANCH);
    } catch (err) {
      setCreateError(apiErrorMessage(err, "Could not create this location. Please try again."));
    }
  }

  async function handleConfirmStatusChange() {
    if (!statusTarget) return;
    setStatusError(null);
    try {
      await updateBranchStatus.mutateAsync({
        id: statusTarget.id,
        status: statusTarget.status === "active" ? "inactive" : "active",
      });
      setStatusTarget(null);
    } catch (err) {
      setStatusError(apiErrorMessage(err, "Could not update this location's status. Please try again."));
    }
  }

  const columns: Column<Branch>[] = [
    { key: "name", header: "Name", render: (row) => row.name },
    { key: "country", header: "Country", render: (row) => row.country },
    { key: "region", header: "Region", render: (row) => row.region ?? "—" },
    { key: "address", header: "Address", render: (row) => row.address ?? "—" },
    {
      key: "status",
      header: "Status",
      render: (row) => <StatusBadge label={row.status} tone={BRANCH_STATUS_TONE[row.status]} />,
    },
    {
      key: "actions",
      header: "",
      className: "text-right",
      render: (row) => {
        const actions: RowAction[] = [
          { label: "Edit", onClick: () => setEditTarget(row) },
          {
            label: row.status === "active" ? "Deactivate" : "Activate",
            onClick: () => setStatusTarget(row),
            tone: row.status === "active" ? "danger" : "default",
          },
        ];
        return <RowActionsMenu actions={actions} label={`${row.name} actions`} />;
      },
    },
  ];

  return (
    <div>
      <div className="mb-4 flex items-center justify-between">
        <p className="text-sm text-inksoft">The branches / sites your organization operates from.</p>
        <Button onClick={() => setIsCreateOpen(true)}>New Location</Button>
      </div>
      <DataTable columns={columns} rows={branches?.data ?? []} rowKey={(row) => row.id} isLoading={isLoading} />

      <Modal
        isOpen={isCreateOpen}
        onClose={() => {
          setIsCreateOpen(false);
          setCreateError(null);
        }}
        title="New Location"
        footer={
          <>
            <Button variant="secondary" onClick={() => setIsCreateOpen(false)}>
              Cancel
            </Button>
            <Button form="new-branch-form" type="submit" isLoading={createBranch.isPending}>
              Create
            </Button>
          </>
        }
      >
        <form id="new-branch-form" onSubmit={handleCreate}>
          {createError && (
            <div className="mb-4">
              <Alert tone="danger">{createError}</Alert>
            </div>
          )}
          <BranchFormFields form={form} setForm={setForm} idPrefix="branch" />
        </form>
      </Modal>

      {editTarget && (
        <EditBranchModal tenantId={tenantId} branch={editTarget} onClose={() => setEditTarget(null)} />
      )}

      <ConfirmDialog
        isOpen={Boolean(statusTarget)}
        title={statusTarget?.status === "active" ? "Deactivate location" : "Activate location"}
        message={
          statusTarget?.status === "active"
            ? `Mark "${statusTarget?.name}" as inactive? It will be flagged as no longer in active use.`
            : `Mark "${statusTarget?.name}" as active again?`
        }
        confirmLabel={statusTarget?.status === "active" ? "Deactivate" : "Activate"}
        tone={statusTarget?.status === "active" ? "danger" : "default"}
        isLoading={updateBranchStatus.isPending}
        error={statusError}
        onConfirm={handleConfirmStatusChange}
        onCancel={() => {
          setStatusTarget(null);
          setStatusError(null);
        }}
      />
    </div>
  );
}

function GeneralSettingsTab({ tenantId }: { tenantId: number }) {
  const { data: tenant, isLoading } = useTenant(tenantId);
  const updateTenant = useUpdateTenant(tenantId);
  const [geofence, setGeofence] = useState("100");
  const [trainingWindow, setTrainingWindow] = useState("30");
  const [saved, setSaved] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (tenant) {
      setGeofence(String(tenant.settings.geofence_radius_meters ?? 100));
      setTrainingWindow(String(tenant.settings.training_expiry_warning_days ?? 30));
    }
  }, [tenant]);

  async function handleSave(event: FormEvent) {
    event.preventDefault();
    setSaved(false);
    setError(null);
    try {
      await updateTenant.mutateAsync({
        settings: {
          geofence_radius_meters: Number(geofence),
          training_expiry_warning_days: Number(trainingWindow),
        },
      });
      setSaved(true);
    } catch {
      setError("Something went wrong saving your changes. Please try again.");
    }
  }

  if (isLoading || !tenant) {
    return (
      <Card>
        <CardBody>Loading…</CardBody>
      </Card>
    );
  }

  return (
    <Card>
      <CardHeader>General Settings</CardHeader>
      <CardBody>
        {saved && (
          <div className="mb-4">
            <Alert tone="success">Settings saved — these take effect immediately across the app.</Alert>
          </div>
        )}
        {error && (
          <div className="mb-4">
            <Alert tone="danger">{error}</Alert>
          </div>
        )}
        <form onSubmit={handleSave} className="max-w-lg">
          <FormField label="Visit check-in geofence radius (meters)" htmlFor="geofence-radius">
            <Input
              id="geofence-radius"
              type="number"
              min={10}
              max={2000}
              required
              value={geofence}
              onChange={(e) => setGeofence(e.target.value)}
            />
          </FormField>
          <p className="-mt-3 mb-4 text-xs text-inksoft">
            How far a carer's GPS position may be from a service user's address at check-in/check-out before an
            override reason is required.
          </p>
          <FormField label="Training expiry warning window (days)" htmlFor="training-window">
            <Input
              id="training-window"
              type="number"
              min={1}
              max={180}
              required
              value={trainingWindow}
              onChange={(e) => setTrainingWindow(e.target.value)}
            />
          </FormField>
          <p className="-mt-3 mb-4 text-xs text-inksoft">
            How many days before a training certificate expires it's flagged as "expiring soon" on Today, Reports,
            and the Operations Dashboard.
          </p>
          <Button type="submit" isLoading={updateTenant.isPending}>
            Save changes
          </Button>
        </form>
      </CardBody>
    </Card>
  );
}

function ComingSoonTab({ label }: { label: string }) {
  return <EmptyState message={`${label} is coming soon.`} />;
}

export function SystemSettingsPage() {
  const { user } = useAuth();
  const [activeTab, setActiveTab] = useState("company");
  const tenantId = user?.tenant_id ?? 0;

  return (
    <div>
      <PageHeader title="System Settings" description="Organization-wide preferences, branding, and integrations." />

      <div className="mb-4">
        <Tabs items={TABS} activeKey={activeTab} onChange={setActiveTab} />
      </div>

      {activeTab === "company" && <CompanyDetailsTab tenantId={tenantId} />}
      {activeTab === "locations" && <LocationsTab tenantId={tenantId} />}
      {activeTab === "general" && <GeneralSettingsTab tenantId={tenantId} />}
      {activeTab === "checklist" && (
        <Card>
          <CardBody>
            <p className="mb-3 text-sm text-inksoft">
              Initial assessment checklists are managed as Assessment Templates, alongside every other assessment
              form used across the organization.
            </p>
            <Link to="/assessment-templates">
              <Button>Open Assessment Templates</Button>
            </Link>
          </CardBody>
        </Card>
      )}
      {activeTab === "data" && <ComingSoonTab label="Data Maintenance" />}
      {activeTab === "reference" && <ComingSoonTab label="Reference Data Management" />}
      {activeTab === "pathway" && <ComingSoonTab label="Care Pathway" />}
    </div>
  );
}
