import { useState, type FormEvent } from "react";
import { Link, useNavigate } from "react-router-dom";
import {
  Alert,
  Button,
  ConfirmDialog,
  DataTable,
  FormField,
  Input,
  Modal,
  PageHeader,
  Pagination,
  RowActionsMenu,
  StatusBadge,
  type Column,
  type RowAction,
} from "../../../design-system";
import { useAuth } from "../../../lib/auth-context";
import { apiErrorMessage } from "../../../lib/api-error";
import { useCreateTenant, useTenants, useUpdateTenantStatus } from "../api";
import type { CreateTenantInput } from "../api";
import type { Tenant } from "../../../lib/types";

const STATUS_TONE: Record<Tenant["status"], "success" | "warning" | "neutral"> = {
  active: "success",
  trial: "warning",
  suspended: "neutral",
};

export function OrganizationsPage() {
  const navigate = useNavigate();
  const { user } = useAuth();
  const isPlatformAdmin = user?.tenant_id == null;
  const [page, setPage] = useState(1);
  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [statusTarget, setStatusTarget] = useState<Tenant | null>(null);
  const { data, isLoading } = useTenants(page);
  const createTenant = useCreateTenant();
  const updateStatus = useUpdateTenantStatus();

  const [form, setForm] = useState<CreateTenantInput>({
    name: "",
    slug: "",
    country: "",
    timezone: "UTC",
    currency: "GBP",
    locale: "en",
  });
  const [createError, setCreateError] = useState<string | null>(null);
  const [statusError, setStatusError] = useState<string | null>(null);

  async function handleCreate(event: FormEvent) {
    event.preventDefault();
    setCreateError(null);
    try {
      await createTenant.mutateAsync(form);
      setIsCreateOpen(false);
      setForm({ name: "", slug: "", country: "", timezone: "UTC", currency: "GBP", locale: "en" });
    } catch (err) {
      setCreateError(apiErrorMessage(err, "Could not create the organization. Please try again."));
    }
  }

  async function handleConfirmStatusChange() {
    if (!statusTarget) return;
    setStatusError(null);
    try {
      await updateStatus.mutateAsync({
        id: statusTarget.id,
        status: statusTarget.status === "suspended" ? "active" : "suspended",
      });
      setStatusTarget(null);
    } catch (err) {
      setStatusError(apiErrorMessage(err, "Could not update this organization's status. Please try again."));
    }
  }

  const columns: Column<Tenant>[] = [
    {
      key: "name",
      header: "Organization",
      render: (row) => (
        <Link to={`/organizations/${row.id}`} className="font-medium text-teal hover:text-teal/90">
          {row.name}
        </Link>
      ),
    },
    { key: "country", header: "Country", render: (row) => row.country },
    { key: "plan", header: "Plan", render: (row) => row.plan },
    {
      key: "status",
      header: "Status",
      render: (row) => <StatusBadge label={row.status} tone={STATUS_TONE[row.status]} />,
    },
    {
      key: "actions",
      header: "",
      className: "text-right",
      render: (row) => {
        const actions: RowAction[] = [
          { label: "View / Edit", onClick: () => navigate(`/organizations/${row.id}`) },
          {
            label: row.status === "suspended" ? "Reactivate" : "Suspend",
            onClick: () => setStatusTarget(row),
            tone: row.status === "suspended" ? "default" : "danger",
            hidden: !isPlatformAdmin,
          },
        ];
        return <RowActionsMenu actions={actions} label={`${row.name} actions`} />;
      },
    },
  ];

  return (
    <div>
      <PageHeader
        title="Organizations"
        description="Tenants operating on the platform."
        actions={<Button onClick={() => setIsCreateOpen(true)}>New Organization</Button>}
      />

      <DataTable columns={columns} rows={data?.data ?? []} rowKey={(row) => row.id} isLoading={isLoading} />

      {data && (
        <Pagination
          currentPage={data.meta.current_page}
          lastPage={data.meta.last_page}
          onPageChange={setPage}
        />
      )}

      <Modal
        isOpen={isCreateOpen}
        onClose={() => {
          setIsCreateOpen(false);
          setCreateError(null);
        }}
        title="New Organization"
        footer={
          <>
            <Button variant="secondary" onClick={() => setIsCreateOpen(false)}>
              Cancel
            </Button>
            <Button form="create-tenant-form" type="submit" isLoading={createTenant.isPending}>
              Create
            </Button>
          </>
        }
      >
        <form id="create-tenant-form" onSubmit={handleCreate}>
          {createError && (
            <div className="mb-4">
              <Alert tone="danger">{createError}</Alert>
            </div>
          )}
          <FormField label="Name" htmlFor="name">
            <Input
              id="name"
              required
              value={form.name}
              onChange={(e) => setForm({ ...form, name: e.target.value })}
            />
          </FormField>
          <FormField label="Slug" htmlFor="slug">
            <Input
              id="slug"
              required
              value={form.slug}
              onChange={(e) => setForm({ ...form, slug: e.target.value })}
            />
          </FormField>
          <FormField label="Country" htmlFor="country">
            <Input
              id="country"
              required
              value={form.country}
              onChange={(e) => setForm({ ...form, country: e.target.value })}
            />
          </FormField>
          <FormField label="Timezone" htmlFor="timezone">
            <Input
              id="timezone"
              required
              value={form.timezone}
              onChange={(e) => setForm({ ...form, timezone: e.target.value })}
            />
          </FormField>
          <FormField label="Currency" htmlFor="currency">
            <Input
              id="currency"
              required
              value={form.currency}
              onChange={(e) => setForm({ ...form, currency: e.target.value })}
            />
          </FormField>
          <FormField label="Locale" htmlFor="locale">
            <Input
              id="locale"
              required
              value={form.locale}
              onChange={(e) => setForm({ ...form, locale: e.target.value })}
            />
          </FormField>
        </form>
      </Modal>

      <ConfirmDialog
        isOpen={Boolean(statusTarget)}
        title={statusTarget?.status === "suspended" ? "Reactivate organization" : "Suspend organization"}
        message={
          statusTarget?.status === "suspended"
            ? `Restore access for "${statusTarget?.name}"? Their staff will be able to sign in again.`
            : `Suspend "${statusTarget?.name}"? Their staff will be unable to sign in until reactivated.`
        }
        confirmLabel={statusTarget?.status === "suspended" ? "Reactivate" : "Suspend"}
        tone={statusTarget?.status === "suspended" ? "default" : "danger"}
        isLoading={updateStatus.isPending}
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
