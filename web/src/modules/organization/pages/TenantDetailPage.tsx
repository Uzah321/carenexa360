import { useState, type FormEvent } from "react";
import { useParams } from "react-router-dom";
import {
  Alert,
  Button,
  DataTable,
  FormField,
  Input,
  Modal,
  PageHeader,
  Select,
  Tabs,
  type Column,
} from "../../../design-system";
import { apiErrorMessage } from "../../../lib/api-error";
import {
  useBranches,
  useCreateBranch,
  useCreateDepartment,
  useDepartments,
  useTenant,
  type CreateBranchInput,
} from "../api";
import type { Branch, Department } from "../../../lib/types";

export function TenantDetailPage() {
  const { tenantId } = useParams<{ tenantId: string }>();
  const id = Number(tenantId);
  const [tab, setTab] = useState("branches");

  const { data: tenant } = useTenant(id);

  return (
    <div>
      <PageHeader
        title={tenant?.name ?? "Organization"}
        breadcrumbs={[
          { label: "Organizations", to: "/organizations" },
          { label: tenant?.name ?? "…" },
        ]}
      />
      <Tabs
        items={[
          { key: "branches", label: "Branches" },
          { key: "departments", label: "Departments" },
        ]}
        activeKey={tab}
        onChange={setTab}
      />
      <div className="mt-4">
        {tab === "branches" && <BranchesTab tenantId={id} />}
        {tab === "departments" && <DepartmentsTab tenantId={id} />}
      </div>
    </div>
  );
}

function BranchesTab({ tenantId }: { tenantId: number }) {
  const { data, isLoading } = useBranches(tenantId);
  const createBranch = useCreateBranch(tenantId);
  const [isOpen, setIsOpen] = useState(false);
  const [form, setForm] = useState<CreateBranchInput>({ name: "", country: "", region: "" });
  const [error, setError] = useState<string | null>(null);

  async function handleCreate(event: FormEvent) {
    event.preventDefault();
    setError(null);
    try {
      await createBranch.mutateAsync(form);
      setIsOpen(false);
      setForm({ name: "", country: "", region: "" });
    } catch (err) {
      setError(apiErrorMessage(err, "Could not create the branch. Please try again."));
    }
  }

  const columns: Column<Branch>[] = [
    { key: "name", header: "Branch", render: (row) => row.name },
    { key: "country", header: "Country", render: (row) => row.country },
    { key: "region", header: "Region", render: (row) => row.region ?? "—" },
  ];

  return (
    <div>
      <div className="mb-3 flex justify-end">
        <Button onClick={() => setIsOpen(true)}>New Branch</Button>
      </div>
      <DataTable
        columns={columns}
        rows={data?.data ?? []}
        rowKey={(row) => row.id}
        isLoading={isLoading}
        emptyMessage="No branches yet."
      />
      <Modal
        isOpen={isOpen}
        onClose={() => {
          setIsOpen(false);
          setError(null);
        }}
        title="New Branch"
        footer={
          <>
            <Button variant="secondary" onClick={() => setIsOpen(false)}>
              Cancel
            </Button>
            <Button form="create-branch-form" type="submit" isLoading={createBranch.isPending}>
              Create
            </Button>
          </>
        }
      >
        <form id="create-branch-form" onSubmit={handleCreate}>
          {error && (
            <div className="mb-4">
              <Alert tone="danger">{error}</Alert>
            </div>
          )}
          <FormField label="Name" htmlFor="branch-name">
            <Input
              id="branch-name"
              required
              value={form.name}
              onChange={(e) => setForm({ ...form, name: e.target.value })}
            />
          </FormField>
          <FormField label="Country" htmlFor="branch-country">
            <Input
              id="branch-country"
              required
              value={form.country}
              onChange={(e) => setForm({ ...form, country: e.target.value })}
            />
          </FormField>
          <FormField label="Region" htmlFor="branch-region">
            <Input
              id="branch-region"
              value={form.region}
              onChange={(e) => setForm({ ...form, region: e.target.value })}
            />
          </FormField>
        </form>
      </Modal>
    </div>
  );
}

function DepartmentsTab({ tenantId }: { tenantId: number }) {
  const { data: departments, isLoading } = useDepartments(tenantId);
  const { data: branches } = useBranches(tenantId);
  const createDepartment = useCreateDepartment(tenantId);
  const [isOpen, setIsOpen] = useState(false);
  const [name, setName] = useState("");
  const [branchId, setBranchId] = useState<number | "">("");
  const [error, setError] = useState<string | null>(null);

  async function handleCreate(event: FormEvent) {
    event.preventDefault();
    if (!branchId) return;
    setError(null);
    try {
      await createDepartment.mutateAsync({ branch_id: branchId, name });
      setIsOpen(false);
      setName("");
      setBranchId("");
    } catch (err) {
      setError(apiErrorMessage(err, "Could not create the department. Please try again."));
    }
  }

  const branchNameById = new Map((branches?.data ?? []).map((b) => [b.id, b.name]));

  const columns: Column<Department>[] = [
    { key: "name", header: "Department", render: (row) => row.name },
    { key: "branch", header: "Branch", render: (row) => branchNameById.get(row.branch_id) ?? row.branch_id },
  ];

  return (
    <div>
      <div className="mb-3 flex justify-end">
        <Button onClick={() => setIsOpen(true)}>New Department</Button>
      </div>
      <DataTable
        columns={columns}
        rows={departments?.data ?? []}
        rowKey={(row) => row.id}
        isLoading={isLoading}
        emptyMessage="No departments yet."
      />
      <Modal
        isOpen={isOpen}
        onClose={() => {
          setIsOpen(false);
          setError(null);
        }}
        title="New Department"
        footer={
          <>
            <Button variant="secondary" onClick={() => setIsOpen(false)}>
              Cancel
            </Button>
            <Button form="create-department-form" type="submit" isLoading={createDepartment.isPending}>
              Create
            </Button>
          </>
        }
      >
        <form id="create-department-form" onSubmit={handleCreate}>
          {error && (
            <div className="mb-4">
              <Alert tone="danger">{error}</Alert>
            </div>
          )}
          <FormField label="Branch" htmlFor="dept-branch">
            <Select
              id="dept-branch"
              required
              value={branchId}
              onChange={(e) => setBranchId(Number(e.target.value))}
            >
              <option value="" disabled>
                Select a branch
              </option>
              {(branches?.data ?? []).map((branch) => (
                <option key={branch.id} value={branch.id}>
                  {branch.name}
                </option>
              ))}
            </Select>
          </FormField>
          <FormField label="Name" htmlFor="dept-name">
            <Input id="dept-name" required value={name} onChange={(e) => setName(e.target.value)} />
          </FormField>
        </form>
      </Modal>
    </div>
  );
}
