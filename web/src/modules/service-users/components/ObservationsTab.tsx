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
  Select,
  Textarea,
  type Column,
} from "../../../design-system";
import { apiErrorMessage } from "../../../lib/api-error";
import {
  useAcknowledgeAlert,
  useClinicalAlerts,
  useCreateObservation,
  useObservations,
} from "../../observations/api";
import { ObservationTrendChart } from "../../observations/components/ObservationTrendChart";
import { OBSERVATION_TYPES, type Observation, type ObservationType } from "../../../lib/types";

function labelFor(type: ObservationType): string {
  return type.replaceAll("_", " ");
}

export function ObservationsTab({ serviceUserId }: { serviceUserId: number }) {
  const [selectedType, setSelectedType] = useState<ObservationType>("blood_pressure");
  const { data: observations, isLoading } = useObservations(serviceUserId);
  const { data: alerts } = useClinicalAlerts(serviceUserId);
  const acknowledgeAlert = useAcknowledgeAlert(serviceUserId);
  const createObservation = useCreateObservation(serviceUserId);

  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [formType, setFormType] = useState<ObservationType>("blood_pressure");
  const [systolic, setSystolic] = useState("");
  const [diastolic, setDiastolic] = useState("");
  const [value, setValue] = useState("");
  const [unit, setUnit] = useState("");
  const [notes, setNotes] = useState("");
  const [error, setError] = useState<string | null>(null);

  const activeAlerts = (alerts ?? []).filter((a) => !a.acknowledged_at);
  const readingsForType = (observations ?? []).filter((o) => o.type === selectedType);

  function resetForm() {
    setFormType("blood_pressure");
    setSystolic("");
    setDiastolic("");
    setValue("");
    setUnit("");
    setNotes("");
  }

  async function handleCreate(event: FormEvent) {
    event.preventDefault();
    setError(null);
    try {
      await createObservation.mutateAsync({
        type: formType,
        value:
          formType === "blood_pressure"
            ? { systolic: Number(systolic), diastolic: Number(diastolic) }
            : { value: Number(value) },
        unit: unit || undefined,
        notes: notes || undefined,
      });
      setIsCreateOpen(false);
      resetForm();
    } catch (err) {
      setError(apiErrorMessage(err, "Could not save this observation. Please try again."));
    }
  }

  const columns: Column<Observation>[] = [
    {
      key: "recorded_at",
      header: "Recorded",
      render: (row) => new Date(row.recorded_at).toLocaleString(),
    },
    { key: "type", header: "Type", render: (row) => labelFor(row.type) },
    {
      key: "value",
      header: "Reading",
      render: (row) =>
        row.type === "blood_pressure"
          ? `${row.value.systolic}/${row.value.diastolic} mmHg`
          : `${row.value.value}${row.unit ? ` ${row.unit}` : ""}`,
    },
    { key: "recorded_by", header: "Recorded By", render: (row) => row.recorded_by_name ?? "—" },
    {
      key: "alert",
      header: "",
      render: (row) =>
        (row.alerts ?? []).length > 0 ? (
          <span className="text-xs font-medium text-red-600">Breach flagged</span>
        ) : null,
    },
  ];

  return (
    <Card>
      <CardHeader>
        <div className="flex items-center justify-between">
          <span>Observations</span>
          <Button variant="secondary" onClick={() => setIsCreateOpen(true)}>
            Record Observation
          </Button>
        </div>
      </CardHeader>
      <CardBody>
        {activeAlerts.length > 0 && (
          <div className="mb-4 space-y-2">
            {activeAlerts.map((alert) => (
              <Alert key={alert.id} tone={alert.severity === "critical" ? "danger" : "warning"}>
                <div className="flex items-center justify-between gap-3">
                  <span>{alert.message}</span>
                  <button
                    type="button"
                    className="shrink-0 text-xs font-medium underline"
                    onClick={() => acknowledgeAlert.mutate(alert.id)}
                  >
                    Acknowledge
                  </button>
                </div>
              </Alert>
            ))}
          </div>
        )}

        <div className="mb-4 flex items-center gap-3">
          <FormField label="Trend for" htmlFor="observation-trend-type">
            <Select
              id="observation-trend-type"
              value={selectedType}
              onChange={(e) => setSelectedType(e.target.value as ObservationType)}
            >
              {OBSERVATION_TYPES.map((type) => (
                <option key={type} value={type}>
                  {labelFor(type)}
                </option>
              ))}
            </Select>
          </FormField>
        </div>
        <div className="mb-6 rounded-xl border border-line p-4">
          <ObservationTrendChart
            observations={readingsForType}
            type={selectedType}
            unit={readingsForType[0]?.unit ?? null}
          />
        </div>

        {!isLoading && (observations ?? []).length === 0 ? (
          <EmptyState message="No observations recorded yet." />
        ) : (
          <DataTable columns={columns} rows={observations ?? []} rowKey={(row) => row.id} isLoading={isLoading} />
        )}
      </CardBody>

      <Modal
        isOpen={isCreateOpen}
        onClose={() => {
          setIsCreateOpen(false);
          setError(null);
        }}
        title="Record Observation"
        footer={
          <>
            <Button variant="secondary" onClick={() => setIsCreateOpen(false)}>
              Cancel
            </Button>
            <Button form="new-observation-form" type="submit" isLoading={createObservation.isPending}>
              Save
            </Button>
          </>
        }
      >
        <form id="new-observation-form" onSubmit={handleCreate}>
          {error && (
            <div className="mb-4">
              <Alert tone="danger">{error}</Alert>
            </div>
          )}
          <FormField label="Type" htmlFor="observation-type">
            <Select
              id="observation-type"
              value={formType}
              onChange={(e) => setFormType(e.target.value as ObservationType)}
            >
              {OBSERVATION_TYPES.map((type) => (
                <option key={type} value={type}>
                  {labelFor(type)}
                </option>
              ))}
            </Select>
          </FormField>

          {formType === "blood_pressure" ? (
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <FormField label="Systolic" htmlFor="observation-systolic">
                <Input
                  id="observation-systolic"
                  type="number"
                  required
                  value={systolic}
                  onChange={(e) => setSystolic(e.target.value)}
                />
              </FormField>
              <FormField label="Diastolic" htmlFor="observation-diastolic">
                <Input
                  id="observation-diastolic"
                  type="number"
                  required
                  value={diastolic}
                  onChange={(e) => setDiastolic(e.target.value)}
                />
              </FormField>
            </div>
          ) : (
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <FormField label="Value" htmlFor="observation-value">
                <Input
                  id="observation-value"
                  type="number"
                  required
                  value={value}
                  onChange={(e) => setValue(e.target.value)}
                />
              </FormField>
              <FormField label="Unit" htmlFor="observation-unit">
                <Input id="observation-unit" value={unit} onChange={(e) => setUnit(e.target.value)} />
              </FormField>
            </div>
          )}

          <FormField label="Notes" htmlFor="observation-notes">
            <Textarea id="observation-notes" value={notes} onChange={(e) => setNotes(e.target.value)} />
          </FormField>
        </form>
      </Modal>
    </Card>
  );
}
