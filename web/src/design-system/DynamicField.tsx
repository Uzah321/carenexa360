import type { AssessmentField } from "../lib/types";
import { Checkbox } from "./form/Checkbox";
import { FormField } from "./form/FormField";
import { Input } from "./form/Input";
import { Select } from "./form/Select";
import { Textarea } from "./form/Textarea";

interface DynamicFieldProps {
  field: AssessmentField;
  value: unknown;
  onChange: (value: unknown) => void;
}

export function DynamicField({ field, value, onChange }: DynamicFieldProps) {
  const id = `field-${field.key}`;

  if (field.type === "checkbox") {
    return (
      <div className="mb-4">
        <Checkbox
          id={id}
          label={field.label}
          checked={Boolean(value)}
          onChange={(e) => onChange(e.target.checked)}
        />
      </div>
    );
  }

  return (
    <FormField label={field.label} htmlFor={id}>
      {field.type === "textarea" && (
        <Textarea
          id={id}
          required={field.required}
          value={(value as string) ?? ""}
          onChange={(e) => onChange(e.target.value)}
        />
      )}
      {field.type === "select" && (
        <Select
          id={id}
          required={field.required}
          value={(value as string) ?? ""}
          onChange={(e) => onChange(e.target.value)}
        >
          <option value="" disabled>
            Select…
          </option>
          {(field.options ?? []).map((option) => (
            <option key={option} value={option}>
              {option}
            </option>
          ))}
        </Select>
      )}
      {(field.type === "text" || field.type === "date") && (
        <Input
          id={id}
          type={field.type}
          required={field.required}
          value={(value as string) ?? ""}
          onChange={(e) => onChange(e.target.value)}
        />
      )}
      {(field.type === "number" || field.type === "score") && (
        <Input
          id={id}
          type="number"
          required={field.required}
          value={(value as number | string) ?? ""}
          onChange={(e) => onChange(e.target.valueAsNumber)}
        />
      )}
    </FormField>
  );
}
