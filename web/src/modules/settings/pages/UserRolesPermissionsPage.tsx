import { useState, type FormEvent } from "react";
import {
  Alert,
  Button,
  Card,
  CardBody,
  DataTable,
  FormField,
  Input,
  Modal,
  PageHeader,
  RowActionsMenu,
  Select,
  StatusBadge,
  type Column,
  type RowAction,
} from "../../../design-system";
import { useAuth } from "../../../lib/auth-context";
import { apiErrorMessage } from "../../../lib/api-error";
import { ADMINISTRATION_ROLES, STAFF_ASSIGNABLE_ROLES, type UserRoleAssignment } from "../../../lib/types";
import { useCreateUserRole, useUpdateUserRole, useUserRoles, type CreateUserInput } from "../../identity/api";

const EMPTY_NEW_USER: CreateUserInput = { name: "", email: "", password: "", role: STAFF_ASSIGNABLE_ROLES[0] };

function NewUserModal({ onClose }: { onClose: () => void }) {
  const createUser = useCreateUserRole();
  const [form, setForm] = useState<CreateUserInput>(EMPTY_NEW_USER);
  const [error, setError] = useState<string | null>(null);

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setError(null);
    try {
      await createUser.mutateAsync(form);
      onClose();
    } catch (err) {
      setError(apiErrorMessage(err, "Could not create this user. Please try again."));
    }
  }

  return (
    <Modal
      isOpen
      onClose={onClose}
      title="New User"
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button form="new-user-form" type="submit" isLoading={createUser.isPending}>
            Create
          </Button>
        </>
      }
    >
      <form id="new-user-form" onSubmit={handleSubmit}>
        {error && (
          <div className="mb-4">
            <Alert tone="danger">{error}</Alert>
          </div>
        )}
        <FormField label="Name" htmlFor="new-user-name">
          <Input
            id="new-user-name"
            required
            value={form.name}
            onChange={(e) => setForm({ ...form, name: e.target.value })}
          />
        </FormField>
        <FormField label="Email" htmlFor="new-user-email">
          <Input
            id="new-user-email"
            type="email"
            required
            value={form.email}
            onChange={(e) => setForm({ ...form, email: e.target.value })}
          />
        </FormField>
        <FormField label="Temporary password" htmlFor="new-user-password">
          <Input
            id="new-user-password"
            type="text"
            required
            minLength={8}
            value={form.password}
            onChange={(e) => setForm({ ...form, password: e.target.value })}
          />
        </FormField>
        <FormField label="Role" htmlFor="new-user-role">
          <Select
            id="new-user-role"
            value={form.role}
            onChange={(e) => setForm({ ...form, role: e.target.value })}
          >
            {STAFF_ASSIGNABLE_ROLES.map((r) => (
              <option key={r} value={r}>
                {r}
              </option>
            ))}
          </Select>
        </FormField>
      </form>
    </Modal>
  );
}

function ChangeRoleModal({
  target,
  onClose,
}: {
  target: UserRoleAssignment;
  onClose: () => void;
}) {
  const updateRole = useUpdateUserRole();
  const [role, setRole] = useState(target.role ?? STAFF_ASSIGNABLE_ROLES[0]);
  const [error, setError] = useState<string | null>(null);

  async function handleSubmit() {
    setError(null);
    try {
      await updateRole.mutateAsync({ id: target.id, role });
      onClose();
    } catch (err) {
      setError(apiErrorMessage(err, "Could not change this role. Please try again."));
    }
  }

  return (
    <Modal
      isOpen
      onClose={onClose}
      title={`Change role — ${target.name}`}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button onClick={handleSubmit} isLoading={updateRole.isPending}>
            Save
          </Button>
        </>
      }
    >
      {error && (
        <div className="mb-4">
          <Alert tone="danger">{error}</Alert>
        </div>
      )}
      <FormField label="Role" htmlFor="change-role-select">
        <Select id="change-role-select" value={role} onChange={(e) => setRole(e.target.value)}>
          {STAFF_ASSIGNABLE_ROLES.map((r) => (
            <option key={r} value={r}>
              {r}
            </option>
          ))}
        </Select>
      </FormField>
    </Modal>
  );
}

export function UserRolesPermissionsPage() {
  const { hasAnyRole } = useAuth();
  const isAuthorized = hasAnyRole(ADMINISTRATION_ROLES);
  const { data: users, isLoading } = useUserRoles();
  const [editTarget, setEditTarget] = useState<UserRoleAssignment | null>(null);
  const [isCreateOpen, setIsCreateOpen] = useState(false);

  if (!isAuthorized) {
    return (
      <Card>
        <CardBody>
          <Alert tone="danger">You don't have permission to manage user roles.</Alert>
        </CardBody>
      </Card>
    );
  }

  const columns: Column<UserRoleAssignment>[] = [
    { key: "name", header: "Name", render: (row) => row.name },
    { key: "email", header: "Email", render: (row) => row.email },
    { key: "job_title", header: "Job Title", render: (row) => row.job_title ?? "—" },
    {
      key: "role",
      header: "Role",
      render: (row) => (row.role ? <StatusBadge label={row.role} tone="neutral" /> : "—"),
    },
    {
      key: "actions",
      header: "",
      className: "text-right",
      render: (row) => {
        const actions: RowAction[] = [{ label: "Change role", onClick: () => setEditTarget(row) }];
        return <RowActionsMenu actions={actions} label={`${row.name} actions`} />;
      },
    },
  ];

  return (
    <div>
      <PageHeader
        title="User Roles & Permissions"
        description="Every staff member's role determines what they can see and do across the system."
        actions={<Button onClick={() => setIsCreateOpen(true)}>New User</Button>}
      />

      <DataTable columns={columns} rows={users ?? []} rowKey={(row) => row.id} isLoading={isLoading} />

      {editTarget && <ChangeRoleModal target={editTarget} onClose={() => setEditTarget(null)} />}
      {isCreateOpen && <NewUserModal onClose={() => setIsCreateOpen(false)} />}
    </div>
  );
}
