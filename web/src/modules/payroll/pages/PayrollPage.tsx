import { useState, type FormEvent } from "react";
import {
  Alert,
  Button,
  Card,
  CardBody,
  CardHeader,
  DataTable,
  Drawer,
  EmptyState,
  FormField,
  Input,
  Modal,
  PageHeader,
  Pagination,
  StatusBadge,
  type Column,
} from "../../../design-system";
import { apiErrorMessage } from "../../../lib/api-error";
import { formatCurrency } from "../../../lib/currency";
import {
  useCreatePayPeriod,
  useGeneratePayslips,
  useMyPayslips,
  usePayPeriod,
  usePayPeriods,
  type CreatePayPeriodInput,
} from "../api";
import { useAuth } from "../../../lib/auth-context";
import type { PayPeriod, Payslip, PayslipStatus } from "../../../lib/types";

const STATUS_TONE: Record<PayslipStatus, "neutral" | "warning" | "success"> = {
  draft: "neutral",
  finalized: "warning",
  paid: "success",
};

function PayPeriodDrawer({ payPeriodId }: { payPeriodId: number }) {
  const { data: payPeriod, isLoading } = usePayPeriod(payPeriodId);
  const generatePayslips = useGeneratePayslips(payPeriodId);
  const [generateError, setGenerateError] = useState<string | null>(null);

  async function handleGenerate() {
    setGenerateError(null);
    try {
      await generatePayslips.mutateAsync();
    } catch (err) {
      setGenerateError(apiErrorMessage(err, "Could not generate payslips. Please try again."));
    }
  }

  const columns: Column<Payslip>[] = [
    { key: "user", header: "Staff", render: (row) => row.user_name ?? "—" },
    { key: "hours", header: "Hours", render: (row) => row.regular_hours },
    { key: "gross", header: "Gross", render: (row) => formatCurrency(row.gross_pay) },
    { key: "deductions", header: "Deductions", render: (row) => formatCurrency(row.deductions) },
    { key: "net", header: "Net", render: (row) => formatCurrency(row.net_pay) },
    {
      key: "status",
      header: "Status",
      render: (row) => <StatusBadge label={row.status} tone={STATUS_TONE[row.status]} />,
    },
  ];

  return (
    <div>
      <p className="mb-4 text-sm text-inksoft">
        {payPeriod?.start_date} – {payPeriod?.end_date}
      </p>
      {generateError && (
        <div className="mb-4">
          <Alert tone="danger">{generateError}</Alert>
        </div>
      )}
      <div className="mb-4">
        <Button onClick={handleGenerate} isLoading={generatePayslips.isPending}>
          Generate Payslips
        </Button>
      </div>
      {!isLoading && (payPeriod?.payslips ?? []).length === 0 ? (
        <EmptyState message="No payslips generated yet." />
      ) : (
        <DataTable
          columns={columns}
          rows={payPeriod?.payslips ?? []}
          rowKey={(row) => row.id}
          isLoading={isLoading}
        />
      )}
    </div>
  );
}

const EMPTY_FORM: CreatePayPeriodInput = { start_date: "", end_date: "" };

export function PayrollPage() {
  const [page, setPage] = useState(1);
  const { data, isLoading } = usePayPeriods(page);
  const createPayPeriod = useCreatePayPeriod();
  const { user } = useAuth();
  const { data: myPayslips } = useMyPayslips(user?.id);

  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [form, setForm] = useState<CreatePayPeriodInput>(EMPTY_FORM);
  const [activePayPeriodId, setActivePayPeriodId] = useState<number | null>(null);
  const [createError, setCreateError] = useState<string | null>(null);

  async function handleCreate(event: FormEvent) {
    event.preventDefault();
    setCreateError(null);
    try {
      await createPayPeriod.mutateAsync(form);
      setIsCreateOpen(false);
      setForm(EMPTY_FORM);
    } catch (err) {
      setCreateError(apiErrorMessage(err, "Could not create the pay period. Please try again."));
    }
  }

  const columns: Column<PayPeriod>[] = [
    { key: "period", header: "Period", render: (row) => `${row.start_date} – ${row.end_date}` },
    { key: "notes", header: "Notes", render: (row) => row.notes ?? "—" },
    {
      key: "actions",
      header: "",
      render: (row) => (
        <button
          type="button"
          className="text-sm font-medium text-teal hover:text-teal/90"
          onClick={() => setActivePayPeriodId(row.id)}
        >
          View Payslips
        </button>
      ),
    },
  ];

  const myColumns: Column<Payslip>[] = [
    { key: "period", header: "Period", render: (row) => `${row.pay_period_start} – ${row.pay_period_end}` },
    { key: "hours", header: "Hours", render: (row) => row.regular_hours },
    { key: "net", header: "Net Pay", render: (row) => formatCurrency(row.net_pay) },
    {
      key: "status",
      header: "Status",
      render: (row) => <StatusBadge label={row.status} tone={STATUS_TONE[row.status]} />,
    },
  ];

  return (
    <div>
      <PageHeader
        title="Payroll"
        description="Pay periods and staff payslips."
        actions={<Button onClick={() => setIsCreateOpen(true)}>New Pay Period</Button>}
      />

      <Card>
        <CardHeader>Pay Periods</CardHeader>
        <CardBody>
          {!isLoading && (data?.data ?? []).length === 0 ? (
            <EmptyState message="No pay periods yet." />
          ) : (
            <DataTable columns={columns} rows={data?.data ?? []} rowKey={(row) => row.id} isLoading={isLoading} />
          )}
        </CardBody>
      </Card>
      {data && (
        <Pagination currentPage={data.meta.current_page} lastPage={data.meta.last_page} onPageChange={setPage} />
      )}

      <div className="mt-6">
        <Card>
          <CardHeader>My Payslips</CardHeader>
          <CardBody>
            {(myPayslips?.data ?? []).length === 0 ? (
              <EmptyState message="No payslips yet." />
            ) : (
              <DataTable columns={myColumns} rows={myPayslips?.data ?? []} rowKey={(row) => row.id} />
            )}
          </CardBody>
        </Card>
      </div>

      <Modal
        isOpen={isCreateOpen}
        onClose={() => {
          setIsCreateOpen(false);
          setCreateError(null);
        }}
        title="New Pay Period"
        footer={
          <>
            <Button variant="secondary" onClick={() => setIsCreateOpen(false)}>
              Cancel
            </Button>
            <Button form="new-pay-period-form" type="submit" isLoading={createPayPeriod.isPending}>
              Create
            </Button>
          </>
        }
      >
        <form id="new-pay-period-form" onSubmit={handleCreate}>
          {createError && (
            <div className="mb-4">
              <Alert tone="danger">{createError}</Alert>
            </div>
          )}
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <FormField label="Start date" htmlFor="pay-period-start">
              <Input
                id="pay-period-start"
                type="date"
                required
                value={form.start_date}
                onChange={(e) => setForm({ ...form, start_date: e.target.value })}
              />
            </FormField>
            <FormField label="End date" htmlFor="pay-period-end">
              <Input
                id="pay-period-end"
                type="date"
                required
                value={form.end_date}
                onChange={(e) => setForm({ ...form, end_date: e.target.value })}
              />
            </FormField>
          </div>
        </form>
      </Modal>

      <Drawer
        isOpen={activePayPeriodId !== null}
        onClose={() => setActivePayPeriodId(null)}
        title="Pay Period"
      >
        {activePayPeriodId !== null && <PayPeriodDrawer payPeriodId={activePayPeriodId} />}
      </Drawer>
    </div>
  );
}
