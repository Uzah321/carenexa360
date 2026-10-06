import { useState, type FormEvent } from "react";
import { ArrowRight } from "lucide-react";
import { Alert, Button, Card, CardBody, CardHeader, FormField, Input } from "../../../design-system";
import type { CarePathwaySettings } from "../defaults";
import { useSaveSettings } from "./useSaveSettings";

const FIELDS: { key: keyof CarePathwaySettings; stage: string; label: string; unit: string; min: number; max: number }[] = [
  { key: "assessment_within_days", stage: "Initial assessment", label: "Assess within", unit: "days of referral", min: 1, max: 90 },
  { key: "care_plan_within_days", stage: "Care plan agreed", label: "Agree a care plan within", unit: "days of referral", min: 1, max: 90 },
  { key: "first_review_within_weeks", stage: "First review", label: "First review within", unit: "weeks of the care plan", min: 1, max: 52 },
  { key: "review_interval_months", stage: "Regular reviews", label: "Then review every", unit: "months", min: 1, max: 24 },
  { key: "risk_review_interval_months", stage: "Risk reviews", label: "Review risks every", unit: "months", min: 1, max: 24 },
];

/** The timescales a client's care should move through, from referral to regular review. */
export function CarePathwayTab({ tenantId }: { tenantId: number }) {
  const settingsState = useSaveSettings(tenantId);

  if (settingsState.isLoading || !settingsState.tenant) {
    return (
      <Card>
        <CardBody>Loading…</CardBody>
      </Card>
    );
  }

  return <CarePathwayForm loaded={settingsState.tenant.settings.care_pathway} {...settingsState} />;
}

function CarePathwayForm({
  loaded,
  save,
  isSaving,
  saved,
  error,
}: { loaded: CarePathwaySettings } & Pick<ReturnType<typeof useSaveSettings>, "save" | "isSaving" | "saved" | "error">) {
  const [values, setValues] = useState<Record<string, string>>(() =>
    Object.fromEntries(FIELDS.map((f) => [f.key, String(loaded[f.key])])),
  );

  async function handleSave(event: FormEvent) {
    event.preventDefault();
    await save({ settings: { care_pathway: Object.fromEntries(FIELDS.map((f) => [f.key, Number(values[f.key])])) } });
  }

  return (
    <Card>
      <CardHeader>Care Pathway</CardHeader>
      <CardBody>
        <ol className="mb-6 flex flex-wrap items-center gap-2 text-xs font-medium text-inksoft">
          {["Referral", ...FIELDS.map((f) => f.stage)].map((stage, i) => (
            <li key={stage} className="flex items-center gap-2">
              {i > 0 && <ArrowRight className="h-3.5 w-3.5" aria-hidden />}
              <span className="rounded-full bg-tealtint px-2.5 py-1 text-teal">{stage}</span>
            </li>
          ))}
        </ol>
        <p className="mb-4 max-w-2xl text-sm text-inksoft">
          Each client is tracked against these timescales on their Overview tab and in the Care Pathway Progress report. New care plan
          sections and risk assessments get their review dates from them.
        </p>
        {saved && (
          <div className="mb-4">
            <Alert tone="success">Care pathway saved.</Alert>
          </div>
        )}
        {error && (
          <div className="mb-4">
            <Alert tone="danger">{error}</Alert>
          </div>
        )}
        <form onSubmit={handleSave} className="max-w-xl">
          {FIELDS.map((field) => (
            <FormField key={field.key} label={`${field.stage} — ${field.label} …`} htmlFor={`pathway-${field.key}`}>
              <div className="flex items-center gap-3">
                <Input
                  id={`pathway-${field.key}`}
                  type="number"
                  min={field.min}
                  max={field.max}
                  required
                  className="max-w-28"
                  value={values[field.key] ?? ""}
                  onChange={(e) => setValues({ ...values, [field.key]: e.target.value })}
                />
                <span className="text-sm text-inksoft">{field.unit}</span>
              </div>
            </FormField>
          ))}
          <Button type="submit" isLoading={isSaving}>
            Save changes
          </Button>
        </form>
      </CardBody>
    </Card>
  );
}
