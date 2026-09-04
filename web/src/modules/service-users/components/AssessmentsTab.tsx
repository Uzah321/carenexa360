import { useState, type FormEvent } from "react";
import {
  Alert,
  Button,
  Card,
  CardBody,
  CardHeader,
  DataTable,
  DynamicField,
  EmptyState,
  FormField,
  Modal,
  Select,
  StatusBadge,
  type Column,
} from "../../../design-system";
import { apiErrorMessage } from "../../../lib/api-error";
import {
  useAssessmentResponses,
  useAssessmentTemplates,
  useCreateAssessmentResponse,
} from "../../assessments/api";
import type { AssessmentResponse } from "../../../lib/types";

export function AssessmentsTab({ serviceUserId }: { serviceUserId: number }) {
  const { data: responses, isLoading } = useAssessmentResponses(serviceUserId);
  const { data: templates } = useAssessmentTemplates();
  const createResponse = useCreateAssessmentResponse(serviceUserId);

  const [isOpen, setIsOpen] = useState(false);
  const [templateId, setTemplateId] = useState<number | "">("");
  const [answers, setAnswers] = useState<Record<string, unknown>>({});
  const [error, setError] = useState<string | null>(null);

  const selectedTemplate = templates?.find((t) => t.id === templateId);

  async function handleCreate(event: FormEvent) {
    event.preventDefault();
    if (!templateId) return;
    setError(null);
    try {
      await createResponse.mutateAsync({
        assessment_template_id: templateId,
        answers,
        status: "completed",
      });
      setIsOpen(false);
      setTemplateId("");
      setAnswers({});
    } catch (err) {
      setError(apiErrorMessage(err, "Could not save this assessment. Please try again."));
    }
  }

  const columns: Column<AssessmentResponse>[] = [
    { key: "template", header: "Assessment", render: (row) => row.template_name ?? "—" },
    { key: "completed_by", header: "Completed By", render: (row) => row.completed_by_name ?? "—" },
    {
      key: "completed_at",
      header: "Completed",
      render: (row) => (row.completed_at ? new Date(row.completed_at).toLocaleDateString() : "—"),
    },
    {
      key: "status",
      header: "Status",
      render: (row) => (
        <StatusBadge label={row.status} tone={row.status === "completed" ? "success" : "warning"} />
      ),
    },
  ];

  return (
    <Card>
      <CardHeader>
        <div className="flex items-center justify-between">
          <span>Assessments</span>
          <Button variant="secondary" onClick={() => setIsOpen(true)}>
            New Assessment
          </Button>
        </div>
      </CardHeader>
      <CardBody>
        {!isLoading && (responses ?? []).length === 0 ? (
          <EmptyState message="No assessments completed yet." />
        ) : (
          <DataTable columns={columns} rows={responses ?? []} rowKey={(row) => row.id} isLoading={isLoading} />
        )}
      </CardBody>

      <Modal
        isOpen={isOpen}
        onClose={() => {
          setIsOpen(false);
          setError(null);
        }}
        title="New Assessment"
        footer={
          <>
            <Button variant="secondary" onClick={() => setIsOpen(false)}>
              Cancel
            </Button>
            <Button
              form="new-assessment-form"
              type="submit"
              isLoading={createResponse.isPending}
              disabled={!templateId}
            >
              Save
            </Button>
          </>
        }
      >
        <form id="new-assessment-form" onSubmit={handleCreate}>
          {error && (
            <div className="mb-4">
              <Alert tone="danger">{error}</Alert>
            </div>
          )}
          <FormField label="Template" htmlFor="template">
            <Select
              id="template"
              required
              value={templateId}
              onChange={(e) => {
                setTemplateId(Number(e.target.value));
                setAnswers({});
              }}
            >
              <option value="" disabled>
                Select a template
              </option>
              {(templates ?? []).map((template) => (
                <option key={template.id} value={template.id}>
                  {template.name}
                </option>
              ))}
            </Select>
          </FormField>

          {selectedTemplate?.fields.map((field) => (
            <DynamicField
              key={field.key}
              field={field}
              value={answers[field.key]}
              onChange={(value) => setAnswers((prev) => ({ ...prev, [field.key]: value }))}
            />
          ))}
        </form>
      </Modal>
    </Card>
  );
}
