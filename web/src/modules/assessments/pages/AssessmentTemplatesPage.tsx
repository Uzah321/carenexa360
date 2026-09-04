import { useState, type FormEvent } from "react";
import {
  Alert,
  Button,
  Card,
  CardBody,
  CardHeader,
  Checkbox,
  DataTable,
  EmptyState,
  FormField,
  Input,
  PageHeader,
  Select,
  type Column,
} from "../../../design-system";
import { apiErrorMessage } from "../../../lib/api-error";
import { useAssessmentTemplates, useCreateAssessmentTemplate } from "../api";
import { ASSESSMENT_FIELD_TYPES, type AssessmentField, type AssessmentTemplate } from "../../../lib/types";

const EMPTY_FIELD: AssessmentField = { key: "", label: "", type: "text", required: false };

export function AssessmentTemplatesPage() {
  const { data: templates, isLoading } = useAssessmentTemplates();
  const createTemplate = useCreateAssessmentTemplate();

  const [name, setName] = useState("");
  const [category, setCategory] = useState("");
  const [description, setDescription] = useState("");
  const [fields, setFields] = useState<AssessmentField[]>([{ ...EMPTY_FIELD }]);
  const [error, setError] = useState<string | null>(null);

  function updateField(index: number, patch: Partial<AssessmentField>) {
    setFields((prev) => prev.map((f, i) => (i === index ? { ...f, ...patch } : f)));
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setError(null);
    try {
      await createTemplate.mutateAsync({
        name,
        category: category || undefined,
        description: description || undefined,
        fields,
      });
      setName("");
      setCategory("");
      setDescription("");
      setFields([{ ...EMPTY_FIELD }]);
    } catch (err) {
      setError(apiErrorMessage(err, "Could not save the template. Please try again."));
    }
  }

  const columns: Column<AssessmentTemplate>[] = [
    { key: "name", header: "Name", render: (row) => row.name },
    { key: "category", header: "Category", render: (row) => row.category ?? "—" },
    { key: "fields", header: "Fields", render: (row) => row.fields.length },
  ];

  return (
    <div>
      <PageHeader title="Assessment Templates" description="Configurable assessment forms for your organization." />

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_1.2fr]">
        <Card>
          <CardHeader>Existing Templates</CardHeader>
          <CardBody>
            {!isLoading && (templates ?? []).length === 0 ? (
              <EmptyState message="No assessment templates yet." />
            ) : (
              <DataTable columns={columns} rows={templates ?? []} rowKey={(row) => row.id} isLoading={isLoading} />
            )}
          </CardBody>
        </Card>

        <Card>
          <CardHeader>New Template</CardHeader>
          <CardBody>
            <form onSubmit={handleSubmit}>
              {error && (
                <div className="mb-4">
                  <Alert tone="danger">{error}</Alert>
                </div>
              )}
              <FormField label="Name" htmlFor="template-name">
                <Input id="template-name" required value={name} onChange={(e) => setName(e.target.value)} />
              </FormField>
              <FormField label="Category" htmlFor="template-category">
                <Input
                  id="template-category"
                  value={category}
                  onChange={(e) => setCategory(e.target.value)}
                  placeholder="e.g. Falls Risk"
                />
              </FormField>
              <FormField label="Description" htmlFor="template-description">
                <Input
                  id="template-description"
                  value={description}
                  onChange={(e) => setDescription(e.target.value)}
                />
              </FormField>

              <div className="mb-2 mt-4 text-sm font-semibold text-ink">Fields</div>
              {fields.map((field, index) => (
                <div key={index} className="mb-3 grid grid-cols-12 items-end gap-2 rounded-xl border border-line p-2">
                  <div className="col-span-3">
                    <FormField label="Key" htmlFor={`key-${index}`}>
                      <Input
                        id={`key-${index}`}
                        required
                        value={field.key}
                        onChange={(e) => updateField(index, { key: e.target.value })}
                      />
                    </FormField>
                  </div>
                  <div className="col-span-4">
                    <FormField label="Label" htmlFor={`label-${index}`}>
                      <Input
                        id={`label-${index}`}
                        required
                        value={field.label}
                        onChange={(e) => updateField(index, { label: e.target.value })}
                      />
                    </FormField>
                  </div>
                  <div className="col-span-3">
                    <FormField label="Type" htmlFor={`type-${index}`}>
                      <Select
                        id={`type-${index}`}
                        value={field.type}
                        onChange={(e) =>
                          updateField(index, { type: e.target.value as AssessmentField["type"] })
                        }
                      >
                        {ASSESSMENT_FIELD_TYPES.map((type) => (
                          <option key={type} value={type}>
                            {type}
                          </option>
                        ))}
                      </Select>
                    </FormField>
                  </div>
                  <div className="col-span-2 mb-4 flex items-center justify-between">
                    <Checkbox
                      id={`required-${index}`}
                      label="Required"
                      checked={Boolean(field.required)}
                      onChange={(e) => updateField(index, { required: e.target.checked })}
                    />
                  </div>
                  {fields.length > 1 && (
                    <div className="col-span-12">
                      <button
                        type="button"
                        className="text-sm text-red-600 hover:text-red-500"
                        onClick={() => setFields((prev) => prev.filter((_, i) => i !== index))}
                      >
                        Remove field
                      </button>
                    </div>
                  )}
                </div>
              ))}
              <Button
                type="button"
                variant="secondary"
                className="mb-4"
                onClick={() => setFields((prev) => [...prev, { ...EMPTY_FIELD }])}
              >
                Add field
              </Button>

              <div className="flex justify-end">
                <Button type="submit" isLoading={createTemplate.isPending}>
                  Save Template
                </Button>
              </div>
            </form>
          </CardBody>
        </Card>
      </div>
    </div>
  );
}
