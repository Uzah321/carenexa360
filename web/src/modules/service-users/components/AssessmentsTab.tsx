import { useState, type FormEvent } from "react";
import {
  Alert,
  Button,
  Card,
  CardBody,
  CardHeader,
  ConfirmDialog,
  DataTable,
  DynamicField,
  EmptyState,
  FormField,
  Modal,
  RowActionsMenu,
  Select,
  StatusBadge,
  type Column,
  type RowAction,
} from "../../../design-system";
import { apiErrorMessage } from "../../../lib/api-error";
import {
  useArchiveAssessmentResponse,
  useAssessmentResponses,
  useAssessmentTemplates,
  useCreateAssessmentResponse,
  useUpdateAssessmentResponse,
} from "../../assessments/api";
import type { AssessmentResponse } from "../../../lib/types";

function fieldLabel(templates: ReturnType<typeof useAssessmentTemplates>["data"], response: AssessmentResponse, key: string) {
  const template = templates?.find((t) => t.id === response.assessment_template_id);
  return template?.fields.find((f) => f.key === key)?.label ?? key;
}

export function AssessmentsTab({ serviceUserId }: { serviceUserId: number }) {
  const { data: responses, isLoading } = useAssessmentResponses(serviceUserId);
  const { data: templates } = useAssessmentTemplates();
  const createResponse = useCreateAssessmentResponse(serviceUserId);
  const updateResponse = useUpdateAssessmentResponse(serviceUserId);
  const archiveResponse = useArchiveAssessmentResponse(serviceUserId);

  const [isOpen, setIsOpen] = useState(false);
  const [templateId, setTemplateId] = useState<number | "">("");
  const [answers, setAnswers] = useState<Record<string, unknown>>({});
  const [error, setError] = useState<string | null>(null);

  const [viewingResponse, setViewingResponse] = useState<AssessmentResponse | null>(null);
  const [editingResponse, setEditingResponse] = useState<AssessmentResponse | null>(null);
  const [editAnswers, setEditAnswers] = useState<Record<string, unknown>>({});
  const [editError, setEditError] = useState<string | null>(null);
  const [archiveTarget, setArchiveTarget] = useState<AssessmentResponse | null>(null);
  const [archiveError, setArchiveError] = useState<string | null>(null);

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

  function openEdit(response: AssessmentResponse) {
    setEditingResponse(response);
    setEditAnswers(response.answers);
    setEditError(null);
  }

  async function handleSaveEdit(event: FormEvent) {
    event.preventDefault();
    if (!editingResponse) return;
    setEditError(null);
    try {
      await updateResponse.mutateAsync({ id: editingResponse.id, answers: editAnswers });
      setEditingResponse(null);
    } catch (err) {
      setEditError(apiErrorMessage(err, "Could not save this assessment. Please try again."));
    }
  }

  async function handleConfirmArchive() {
    if (!archiveTarget) return;
    setArchiveError(null);
    try {
      await archiveResponse.mutateAsync({ id: archiveTarget.id, archived: !archiveTarget.archived_at });
      setArchiveTarget(null);
    } catch (err) {
      setArchiveError(apiErrorMessage(err, "Could not update this assessment. Please try again."));
    }
  }

  const editingTemplate = templates?.find((t) => t.id === editingResponse?.assessment_template_id);

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
        <div className="flex items-center gap-1.5">
          <StatusBadge label={row.status} tone={row.status === "completed" ? "success" : "warning"} />
          {row.archived_at && <StatusBadge label="Archived" tone="neutral" />}
        </div>
      ),
    },
    {
      key: "actions",
      header: "",
      className: "text-right",
      render: (row) => {
        const actions: RowAction[] = [
          { label: "View", onClick: () => setViewingResponse(row) },
          { label: "Edit", onClick: () => openEdit(row) },
          {
            label: row.archived_at ? "Restore" : "Archive",
            onClick: () => setArchiveTarget(row),
            tone: row.archived_at ? "default" : "danger",
          },
        ];
        return <RowActionsMenu actions={actions} label={`${row.template_name ?? "Assessment"} actions`} />;
      },
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

      <Modal
        isOpen={Boolean(editingResponse)}
        onClose={() => {
          setEditingResponse(null);
          setEditError(null);
        }}
        title={`Edit — ${editingResponse?.template_name ?? "Assessment"}`}
        footer={
          <>
            <Button variant="secondary" onClick={() => setEditingResponse(null)}>
              Cancel
            </Button>
            <Button form="edit-assessment-form" type="submit" isLoading={updateResponse.isPending}>
              Save
            </Button>
          </>
        }
      >
        <form id="edit-assessment-form" onSubmit={handleSaveEdit}>
          {editError && (
            <div className="mb-4">
              <Alert tone="danger">{editError}</Alert>
            </div>
          )}
          {editingTemplate?.fields.map((field) => (
            <DynamicField
              key={field.key}
              field={field}
              value={editAnswers[field.key]}
              onChange={(value) => setEditAnswers((prev) => ({ ...prev, [field.key]: value }))}
            />
          ))}
          {!editingTemplate && (
            <p className="text-sm text-inksoft">
              This assessment's template is no longer available, so its individual fields can't be edited here.
            </p>
          )}
        </form>
      </Modal>

      <Modal
        isOpen={Boolean(viewingResponse)}
        onClose={() => setViewingResponse(null)}
        title={viewingResponse?.template_name ?? "Assessment"}
        footer={
          <Button variant="secondary" onClick={() => setViewingResponse(null)}>
            Close
          </Button>
        }
      >
        {viewingResponse && (
          <div className="space-y-3">
            <dl>
              <div className="flex justify-between border-b border-line py-2 text-sm">
                <dt className="text-inksoft">Status</dt>
                <dd className="font-medium text-ink">{viewingResponse.status}</dd>
              </div>
              <div className="flex justify-between border-b border-line py-2 text-sm">
                <dt className="text-inksoft">Completed by</dt>
                <dd className="font-medium text-ink">{viewingResponse.completed_by_name ?? "—"}</dd>
              </div>
              <div className="flex justify-between border-b border-line py-2 text-sm last:border-0">
                <dt className="text-inksoft">Completed at</dt>
                <dd className="font-medium text-ink">
                  {viewingResponse.completed_at ? new Date(viewingResponse.completed_at).toLocaleString() : "—"}
                </dd>
              </div>
            </dl>
            <div>
              <h4 className="mb-2 text-xs font-semibold uppercase tracking-wide text-inksoft">Answers</h4>
              <dl>
                {Object.entries(viewingResponse.answers).map(([key, value]) => (
                  <div key={key} className="flex justify-between gap-4 border-b border-line py-2 text-sm last:border-0">
                    <dt className="text-inksoft">{fieldLabel(templates, viewingResponse, key)}</dt>
                    <dd className="text-right font-medium text-ink">{String(value ?? "—")}</dd>
                  </div>
                ))}
              </dl>
            </div>
          </div>
        )}
      </Modal>

      <ConfirmDialog
        isOpen={Boolean(archiveTarget)}
        title={archiveTarget?.archived_at ? "Restore assessment" : "Archive assessment"}
        message={
          archiveTarget?.archived_at
            ? `Restore this "${archiveTarget?.template_name ?? "assessment"}" response to the active list?`
            : `Archive this "${archiveTarget?.template_name ?? "assessment"}" response? It's kept for the record but hidden from the active list.`
        }
        confirmLabel={archiveTarget?.archived_at ? "Restore" : "Archive"}
        tone={archiveTarget?.archived_at ? "default" : "danger"}
        isLoading={archiveResponse.isPending}
        error={archiveError}
        onConfirm={handleConfirmArchive}
        onCancel={() => {
          setArchiveTarget(null);
          setArchiveError(null);
        }}
      />
    </Card>
  );
}
