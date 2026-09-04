import { useState, type FormEvent } from "react";
import { Link } from "react-router-dom";
import {
  Alert,
  Button,
  ConfirmDialog,
  DataTable,
  FilterBar,
  FormField,
  Input,
  Modal,
  PageHeader,
  Pagination,
  RowActionsMenu,
  Select,
  StatusBadge,
  type Column,
} from "../../../design-system";
import { apiErrorMessage } from "../../../lib/api-error";
import {
  useCreateServiceUser,
  useDeleteServiceUser,
  useServiceUsers,
  useUpdateServiceUser,
  useUpdateServiceUserStatus,
  type ServiceUserInput,
} from "../api";
import type { ServiceUser } from "../../../lib/types";

const STATUS_TONE: Record<ServiceUser["status"], "success" | "warning" | "neutral"> = {
  active: "success",
  inactive: "neutral",
  discharged: "warning",
};

const EMPTY_FORM: ServiceUserInput = {
  first_name: "",
  last_name: "",
  preferred_name: "",
  date_of_birth: "",
};

function serviceUserToForm(serviceUser: ServiceUser): ServiceUserInput {
  return {
    first_name: serviceUser.first_name,
    last_name: serviceUser.last_name,
    preferred_name: serviceUser.preferred_name ?? "",
    date_of_birth: serviceUser.date_of_birth ?? "",
    gender: serviceUser.gender ?? "",
    phone: serviceUser.phone ?? "",
    address: serviceUser.address ?? "",
    funding_source: serviceUser.funding_source ?? "",
    status: serviceUser.status,
  };
}

export function ServiceUsersPage() {
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("");
  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const { data, isLoading } = useServiceUsers(page, { search, status });

  // Changing a filter has to reset to page 1 — staying on page 3 of the old,
  // wider result set otherwise shows an empty table for a search that matched.
  function applyFilter(apply: () => void) {
    apply();
    setPage(1);
  }
  const createServiceUser = useCreateServiceUser();
  const [form, setForm] = useState<ServiceUserInput>(EMPTY_FORM);
  const [createError, setCreateError] = useState<string | null>(null);

  const [editingServiceUser, setEditingServiceUser] = useState<ServiceUser | null>(null);
  const [editForm, setEditForm] = useState<ServiceUserInput>(EMPTY_FORM);
  const [editError, setEditError] = useState<string | null>(null);
  const updateServiceUser = useUpdateServiceUser(editingServiceUser?.id ?? 0);

  const [archivingServiceUser, setArchivingServiceUser] = useState<ServiceUser | null>(null);
  const [archiveError, setArchiveError] = useState<string | null>(null);
  const deleteServiceUser = useDeleteServiceUser();
  const updateStatus = useUpdateServiceUserStatus();

  async function handleCreate(event: FormEvent) {
    event.preventDefault();
    setCreateError(null);
    try {
      await createServiceUser.mutateAsync(form);
      setIsCreateOpen(false);
      setForm(EMPTY_FORM);
    } catch (err) {
      setCreateError(apiErrorMessage(err, "Could not create this service user. Please try again."));
    }
  }

  function openEdit(serviceUser: ServiceUser) {
    setEditingServiceUser(serviceUser);
    setEditForm(serviceUserToForm(serviceUser));
    setEditError(null);
  }

  async function handleEdit(event: FormEvent) {
    event.preventDefault();
    setEditError(null);
    try {
      await updateServiceUser.mutateAsync(editForm);
      setEditingServiceUser(null);
    } catch (err) {
      setEditError(apiErrorMessage(err, "Could not save these changes. Please try again."));
    }
  }

  async function handleArchive() {
    if (!archivingServiceUser) return;
    setArchiveError(null);
    try {
      await deleteServiceUser.mutateAsync(archivingServiceUser.id);
      setArchivingServiceUser(null);
    } catch (err) {
      setArchiveError(apiErrorMessage(err, "Could not archive this service user. Please try again."));
    }
  }

  const columns: Column<ServiceUser>[] = [
    {
      key: "name",
      header: "Name",
      render: (row) => (
        <Link
          to={`/service-users/${row.id}`}
          className="font-medium text-teal hover:text-teal/90"
        >
          {row.first_name} {row.last_name}
        </Link>
      ),
    },
    {
      key: "dob",
      header: "Date of Birth",
      render: (row) => row.date_of_birth ?? "—",
    },
    { key: "funding_source", header: "Funding", render: (row) => row.funding_source ?? "—" },
    {
      key: "status",
      header: "Status",
      render: (row) => <StatusBadge label={row.status} tone={STATUS_TONE[row.status]} />,
    },
    {
      key: "actions",
      header: "",
      className: "w-12 text-right",
      render: (row) => (
        <RowActionsMenu
          actions={[
            { label: "Edit", onClick: () => openEdit(row) },
            {
              label: "Mark as active",
              onClick: () => updateStatus.mutate({ id: row.id, status: "active" }),
              hidden: row.status === "active",
            },
            {
              label: "Mark as discharged",
              onClick: () => updateStatus.mutate({ id: row.id, status: "discharged" }),
              hidden: row.status === "discharged",
            },
            { label: "Archive", tone: "danger", onClick: () => setArchivingServiceUser(row) },
          ]}
        />
      ),
    },
  ];

  return (
    <div>
      <PageHeader
        title="Service Users"
        description="People receiving care under your organization."
        actions={<Button onClick={() => setIsCreateOpen(true)}>New Service User</Button>}
      />

      <FilterBar>
        <FormField label="Search" htmlFor="filter-search">
          <Input
            id="filter-search"
            type="search"
            placeholder="Name"
            value={search}
            onChange={(e) => applyFilter(() => setSearch(e.target.value))}
          />
        </FormField>
        <FormField label="Status" htmlFor="filter-status">
          <Select
            id="filter-status"
            value={status}
            onChange={(e) => applyFilter(() => setStatus(e.target.value))}
          >
            <option value="">All statuses</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
            <option value="discharged">Discharged</option>
          </Select>
        </FormField>
      </FilterBar>

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
        title="New Service User"
        footer={
          <>
            <Button variant="secondary" onClick={() => setIsCreateOpen(false)}>
              Cancel
            </Button>
            <Button form="create-service-user-form" type="submit" isLoading={createServiceUser.isPending}>
              Create
            </Button>
          </>
        }
      >
        <form id="create-service-user-form" onSubmit={handleCreate}>
          {createError && (
            <div className="mb-4">
              <Alert tone="danger">{createError}</Alert>
            </div>
          )}
          <FormField label="First name" htmlFor="first_name">
            <Input
              id="first_name"
              required
              value={form.first_name}
              onChange={(e) => setForm({ ...form, first_name: e.target.value })}
            />
          </FormField>
          <FormField label="Last name" htmlFor="last_name">
            <Input
              id="last_name"
              required
              value={form.last_name}
              onChange={(e) => setForm({ ...form, last_name: e.target.value })}
            />
          </FormField>
          <FormField label="Preferred name" htmlFor="preferred_name">
            <Input
              id="preferred_name"
              value={form.preferred_name ?? ""}
              onChange={(e) => setForm({ ...form, preferred_name: e.target.value })}
            />
          </FormField>
          <FormField label="Date of birth" htmlFor="date_of_birth">
            <Input
              id="date_of_birth"
              type="date"
              value={form.date_of_birth ?? ""}
              onChange={(e) => setForm({ ...form, date_of_birth: e.target.value })}
            />
          </FormField>
        </form>
      </Modal>

      <Modal
        isOpen={Boolean(editingServiceUser)}
        onClose={() => {
          setEditingServiceUser(null);
          setEditError(null);
        }}
        title="Edit Service User"
        footer={
          <>
            <Button variant="secondary" onClick={() => setEditingServiceUser(null)}>
              Cancel
            </Button>
            <Button form="edit-service-user-form" type="submit" isLoading={updateServiceUser.isPending}>
              Save
            </Button>
          </>
        }
      >
        <form id="edit-service-user-form" onSubmit={handleEdit}>
          {editError && (
            <div className="mb-4">
              <Alert tone="danger">{editError}</Alert>
            </div>
          )}
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <FormField label="First name" htmlFor="edit-first-name">
              <Input
                id="edit-first-name"
                required
                value={editForm.first_name}
                onChange={(e) => setEditForm({ ...editForm, first_name: e.target.value })}
              />
            </FormField>
            <FormField label="Last name" htmlFor="edit-last-name">
              <Input
                id="edit-last-name"
                required
                value={editForm.last_name}
                onChange={(e) => setEditForm({ ...editForm, last_name: e.target.value })}
              />
            </FormField>
          </div>
          <FormField label="Preferred name" htmlFor="edit-preferred-name">
            <Input
              id="edit-preferred-name"
              value={editForm.preferred_name ?? ""}
              onChange={(e) => setEditForm({ ...editForm, preferred_name: e.target.value })}
            />
          </FormField>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <FormField label="Date of birth" htmlFor="edit-dob">
              <Input
                id="edit-dob"
                type="date"
                value={editForm.date_of_birth ?? ""}
                onChange={(e) => setEditForm({ ...editForm, date_of_birth: e.target.value })}
              />
            </FormField>
            <FormField label="Gender" htmlFor="edit-gender">
              <Input
                id="edit-gender"
                value={editForm.gender ?? ""}
                onChange={(e) => setEditForm({ ...editForm, gender: e.target.value })}
              />
            </FormField>
          </div>
          <FormField label="Phone" htmlFor="edit-phone">
            <Input
              id="edit-phone"
              value={editForm.phone ?? ""}
              onChange={(e) => setEditForm({ ...editForm, phone: e.target.value })}
            />
          </FormField>
          <FormField label="Address" htmlFor="edit-address">
            <Input
              id="edit-address"
              value={editForm.address ?? ""}
              onChange={(e) => setEditForm({ ...editForm, address: e.target.value })}
            />
          </FormField>
          <FormField label="Funding source" htmlFor="edit-funding-source">
            <Input
              id="edit-funding-source"
              value={editForm.funding_source ?? ""}
              onChange={(e) => setEditForm({ ...editForm, funding_source: e.target.value })}
            />
          </FormField>
          <FormField label="Status" htmlFor="edit-status">
            <Select
              id="edit-status"
              value={editForm.status}
              onChange={(e) => setEditForm({ ...editForm, status: e.target.value })}
            >
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
              <option value="discharged">Discharged</option>
            </Select>
          </FormField>
        </form>
      </Modal>

      <ConfirmDialog
        isOpen={Boolean(archivingServiceUser)}
        title="Archive service user"
        message={`Archive ${archivingServiceUser?.first_name} ${archivingServiceUser?.last_name}? They'll be hidden from active lists but their records are preserved.`}
        confirmLabel="Archive"
        tone="danger"
        isLoading={deleteServiceUser.isPending}
        error={archiveError}
        onConfirm={handleArchive}
        onCancel={() => {
          setArchivingServiceUser(null);
          setArchiveError(null);
        }}
      />
    </div>
  );
}
