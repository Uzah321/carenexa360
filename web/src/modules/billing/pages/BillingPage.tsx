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
  Input,
  Modal,
  PageHeader,
  Select,
  StatusBadge,
  Tabs,
  Textarea,
  type Column,
} from "../../../design-system";
import { apiErrorMessage } from "../../../lib/api-error";
import { formatCurrency } from "../../../lib/currency";
import { useServiceUsers } from "../../service-users/api";
import {
  useCreateFunder,
  useFunders,
  useGenerateInvoice,
  useInvoices,
  useUpdateInvoice,
  type CreateFunderInput,
} from "../api";
import { FUNDER_TYPES, INVOICE_STATUSES, type Funder, type FunderType, type Invoice, type InvoiceStatus } from "../../../lib/types";

const NEXT_STATUS: Record<InvoiceStatus, InvoiceStatus | null> = {
  draft: "sent",
  sent: "paid",
  paid: null,
  overdue: "paid",
  cancelled: null,
};

const STATUS_TONE: Record<InvoiceStatus, "neutral" | "info" | "success" | "warning" | "danger"> = {
  draft: "neutral",
  sent: "info",
  paid: "success",
  overdue: "danger",
  cancelled: "neutral",
};

function InvoiceRowActions({ invoice }: { invoice: Invoice }) {
  const updateInvoice = useUpdateInvoice(invoice.id);
  const next = NEXT_STATUS[invoice.status];

  if (!next) return null;

  return (
    <button
      type="button"
      className="text-sm font-medium text-teal hover:text-teal/90"
      disabled={updateInvoice.isPending}
      onClick={() => updateInvoice.mutate({ status: next })}
    >
      Mark {next}
    </button>
  );
}

function InvoicesTab() {
  const [status, setStatus] = useState<InvoiceStatus | "">("");
  const { data, isLoading } = useInvoices({ status: status || undefined });
  const { data: serviceUsers } = useServiceUsers(1);
  const { data: funders } = useFunders();
  const generateInvoice = useGenerateInvoice();

  const [isGenerateOpen, setIsGenerateOpen] = useState(false);
  const [serviceUserId, setServiceUserId] = useState<number | "">("");
  const [funderId, setFunderId] = useState<number | "">("");
  const [periodStart, setPeriodStart] = useState("");
  const [periodEnd, setPeriodEnd] = useState("");
  const [hourlyRate, setHourlyRate] = useState("");
  const [generateError, setGenerateError] = useState<string | null>(null);

  async function handleGenerate(event: FormEvent) {
    event.preventDefault();
    if (!serviceUserId) return;
    setGenerateError(null);
    try {
      await generateInvoice.mutateAsync({
        service_user_id: serviceUserId,
        funder_id: funderId || undefined,
        period_start: periodStart,
        period_end: periodEnd,
        hourly_rate: Number(hourlyRate),
      });
      setIsGenerateOpen(false);
      setServiceUserId("");
      setFunderId("");
      setPeriodStart("");
      setPeriodEnd("");
      setHourlyRate("");
    } catch (err) {
      setGenerateError(apiErrorMessage(err, "Could not generate the invoice. Please try again."));
    }
  }

  const columns: Column<Invoice>[] = [
    { key: "invoice_number", header: "Invoice #", render: (row) => row.invoice_number ?? "—" },
    { key: "service_user", header: "Service User", render: (row) => row.service_user_name ?? "—" },
    { key: "funder", header: "Funder", render: (row) => row.funder_name ?? "Self-funded" },
    { key: "period", header: "Period", render: (row) => `${row.period_start} – ${row.period_end}` },
    { key: "total", header: "Total", render: (row) => formatCurrency(row.total, row.currency) },
    {
      key: "status",
      header: "Status",
      render: (row) => <StatusBadge label={row.status} tone={STATUS_TONE[row.status]} />,
    },
    { key: "actions", header: "", render: (row) => <InvoiceRowActions invoice={row} /> },
  ];

  return (
    <Card>
      <CardHeader>
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <span>Invoices</span>
            <Select
              value={status}
              onChange={(e) => setStatus(e.target.value as InvoiceStatus | "")}
              className="w-auto"
            >
              <option value="">All statuses</option>
              {INVOICE_STATUSES.map((s) => (
                <option key={s} value={s}>
                  {s}
                </option>
              ))}
            </Select>
          </div>
          <Button onClick={() => setIsGenerateOpen(true)}>Generate Invoice</Button>
        </div>
      </CardHeader>
      <CardBody>
        {!isLoading && (data?.data ?? []).length === 0 ? (
          <EmptyState message="No invoices yet." />
        ) : (
          <DataTable columns={columns} rows={data?.data ?? []} rowKey={(row) => row.id} isLoading={isLoading} />
        )}
      </CardBody>

      <Modal
        isOpen={isGenerateOpen}
        onClose={() => {
          setIsGenerateOpen(false);
          setGenerateError(null);
        }}
        title="Generate Invoice"
        footer={
          <>
            <Button variant="secondary" onClick={() => setIsGenerateOpen(false)}>
              Cancel
            </Button>
            <Button form="generate-invoice-form" type="submit" isLoading={generateInvoice.isPending}>
              Generate
            </Button>
          </>
        }
      >
        <form id="generate-invoice-form" onSubmit={handleGenerate}>
          {generateError && (
            <div className="mb-4">
              <Alert tone="danger">{generateError}</Alert>
            </div>
          )}
          <FormField label="Service user" htmlFor="invoice-service-user">
            <Select
              id="invoice-service-user"
              required
              value={serviceUserId}
              onChange={(e) => setServiceUserId(e.target.value ? Number(e.target.value) : "")}
            >
              <option value="" disabled>
                Select a service user
              </option>
              {(serviceUsers?.data ?? []).map((su) => (
                <option key={su.id} value={su.id}>
                  {su.first_name} {su.last_name}
                </option>
              ))}
            </Select>
          </FormField>
          <FormField label="Funder (optional)" htmlFor="invoice-funder">
            <Select
              id="invoice-funder"
              value={funderId}
              onChange={(e) => {
                const id = e.target.value ? Number(e.target.value) : "";
                setFunderId(id);
                const funder = (funders ?? []).find((f) => f.id === id);
                if (funder?.default_hourly_rate) setHourlyRate(funder.default_hourly_rate);
              }}
            >
              <option value="">Self-funded / no third-party funder</option>
              {(funders ?? []).map((f) => (
                <option key={f.id} value={f.id}>
                  {f.name}
                </option>
              ))}
            </Select>
          </FormField>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <FormField label="Period start" htmlFor="invoice-period-start">
              <Input
                id="invoice-period-start"
                type="date"
                required
                value={periodStart}
                onChange={(e) => setPeriodStart(e.target.value)}
              />
            </FormField>
            <FormField label="Period end" htmlFor="invoice-period-end">
              <Input
                id="invoice-period-end"
                type="date"
                required
                value={periodEnd}
                onChange={(e) => setPeriodEnd(e.target.value)}
              />
            </FormField>
          </div>
          <FormField label="Hourly rate" htmlFor="invoice-rate">
            <Input
              id="invoice-rate"
              type="number"
              step="0.01"
              min={0}
              required
              value={hourlyRate}
              onChange={(e) => setHourlyRate(e.target.value)}
            />
          </FormField>
        </form>
      </Modal>
    </Card>
  );
}

const EMPTY_FUNDER_FORM: CreateFunderInput = { name: "", type: "private" };

function FundersTab() {
  const { data: funders, isLoading } = useFunders();
  const createFunder = useCreateFunder();
  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [form, setForm] = useState<CreateFunderInput>(EMPTY_FUNDER_FORM);
  const [createError, setCreateError] = useState<string | null>(null);

  async function handleCreate(event: FormEvent) {
    event.preventDefault();
    setCreateError(null);
    try {
      await createFunder.mutateAsync(form);
      setIsCreateOpen(false);
      setForm(EMPTY_FUNDER_FORM);
    } catch (err) {
      setCreateError(apiErrorMessage(err, "Could not save the funder. Please try again."));
    }
  }

  const columns: Column<Funder>[] = [
    { key: "name", header: "Name", render: (row) => row.name },
    { key: "type", header: "Type", render: (row) => row.type.replaceAll("_", " ") },
    { key: "contact_name", header: "Contact", render: (row) => row.contact_name ?? "—" },
    { key: "rate", header: "Default Rate", render: (row) => formatCurrency(row.default_hourly_rate) },
    {
      key: "status",
      header: "Status",
      render: (row) => <StatusBadge label={row.status} tone={row.status === "active" ? "success" : "neutral"} />,
    },
  ];

  return (
    <Card>
      <CardHeader>
        <div className="flex items-center justify-between">
          <span>Funders</span>
          <Button variant="secondary" onClick={() => setIsCreateOpen(true)}>
            Add Funder
          </Button>
        </div>
      </CardHeader>
      <CardBody>
        {!isLoading && (funders ?? []).length === 0 ? (
          <EmptyState message="No funders added yet." />
        ) : (
          <DataTable columns={columns} rows={funders ?? []} rowKey={(row) => row.id} isLoading={isLoading} />
        )}
      </CardBody>

      <Modal
        isOpen={isCreateOpen}
        onClose={() => {
          setIsCreateOpen(false);
          setCreateError(null);
        }}
        title="Add Funder"
        footer={
          <>
            <Button variant="secondary" onClick={() => setIsCreateOpen(false)}>
              Cancel
            </Button>
            <Button form="new-funder-form" type="submit" isLoading={createFunder.isPending}>
              Save
            </Button>
          </>
        }
      >
        <form id="new-funder-form" onSubmit={handleCreate}>
          {createError && (
            <div className="mb-4">
              <Alert tone="danger">{createError}</Alert>
            </div>
          )}
          <FormField label="Name" htmlFor="funder-name">
            <Input
              id="funder-name"
              required
              value={form.name}
              onChange={(e) => setForm({ ...form, name: e.target.value })}
            />
          </FormField>
          <FormField label="Type" htmlFor="funder-type">
            <Select
              id="funder-type"
              value={form.type}
              onChange={(e) => setForm({ ...form, type: e.target.value as FunderType })}
            >
              {FUNDER_TYPES.map((type) => (
                <option key={type} value={type}>
                  {type.replaceAll("_", " ")}
                </option>
              ))}
            </Select>
          </FormField>
          <FormField label="Contact name" htmlFor="funder-contact">
            <Input
              id="funder-contact"
              value={form.contact_name ?? ""}
              onChange={(e) => setForm({ ...form, contact_name: e.target.value })}
            />
          </FormField>
          <FormField label="Default hourly rate" htmlFor="funder-rate">
            <Input
              id="funder-rate"
              type="number"
              step="0.01"
              min={0}
              value={form.default_hourly_rate ?? ""}
              onChange={(e) => setForm({ ...form, default_hourly_rate: Number(e.target.value) })}
            />
          </FormField>
          <FormField label="Notes" htmlFor="funder-notes">
            <Textarea
              id="funder-notes"
              value={form.notes ?? ""}
              onChange={(e) => setForm({ ...form, notes: e.target.value })}
            />
          </FormField>
        </form>
      </Modal>
    </Card>
  );
}

export function BillingPage() {
  const [tab, setTab] = useState("invoices");

  return (
    <div>
      <PageHeader title="Billing" description="Invoices and funders for care delivered." />
      <Tabs
        items={[
          { key: "invoices", label: "Invoices" },
          { key: "funders", label: "Funders" },
        ]}
        activeKey={tab}
        onChange={setTab}
      />
      <div className="mt-4">
        {tab === "invoices" && <InvoicesTab />}
        {tab === "funders" && <FundersTab />}
      </div>
    </div>
  );
}
