import { useState, type FormEvent, type ReactNode } from "react";
import { Alert, Button, Card, CardBody, CardHeader, FormField, Input } from "../../../design-system";
import type { EffectiveTenantSettings } from "../defaults";
import { useSaveSettings } from "./useSaveSettings";

type NumericKey = Exclude<keyof EffectiveTenantSettings, "care_pathway" | "reference_data" | "session_timeout_minutes">;

interface FieldDef {
  key: NumericKey | "session_timeout_minutes";
  label: string;
  help: string;
  min: number;
  max: number;
  step?: string;
  optional?: boolean;
}

const GROUPS: { title: string; fields: FieldDef[] }[] = [
  {
    title: "Visits & GPS",
    fields: [
      {
        key: "geofence_radius_meters",
        label: "Check-in geofence radius (metres)",
        help: "How far a carer's GPS position may be from the client's address at check-in/out before an override reason is required. Also used by the GPS verification reports.",
        min: 10,
        max: 2000,
      },
      {
        key: "late_arrival_minutes",
        label: "Late arrival grace (minutes)",
        help: "How long after the start time a visit check-in or shift clock-in still counts as on time. Used by Late Visits, Staff Attendance and the Service Quality Indicators.",
        min: 0,
        max: 120,
      },
    ],
  },
  {
    title: "Workforce",
    fields: [
      {
        key: "overtime_weekly_hours",
        label: "Overtime threshold (hours a week)",
        help: "Hours worked beyond this in a week show on the Overtime report; the Overtime Risk report warns within 10% of it.",
        min: 1,
        max: 100,
        step: "0.5",
      },
      {
        key: "mileage_rate_per_mile",
        label: "Mileage reimbursement rate (per mile)",
        help: "In your organisation's currency. 0.45 is the HMRC approved rate. Used by the Mileage Reimbursement report.",
        min: 0,
        max: 10,
        step: "0.01",
      },
      {
        key: "training_expiry_warning_days",
        label: "Training expiry warning (days)",
        help: "How long before a certificate expires it's flagged as expiring soon on Today, the Operations Dashboard and the training and background-check reports.",
        min: 1,
        max: 180,
      },
    ],
  },
  {
    title: "Medication",
    fields: [
      {
        key: "medication_window_minutes",
        label: "Dose timing window (minutes)",
        help: "A dose given further than this either side of its due time counts as late on the Late Medication report.",
        min: 5,
        max: 240,
      },
      {
        key: "stock_reorder_days",
        label: "Low stock warning (days of stock left)",
        help: "A stock-tracked medication is flagged to reorder when it will run out within this many days, as well as at its own reorder level.",
        min: 1,
        max: 90,
      },
    ],
  },
  {
    title: "Complaints",
    fields: [
      {
        key: "complaint_response_days",
        label: "Complaint response time (days)",
        help: "A new complaint's response is due this many days after it's received (UK practice is 20 working days). Overdue complaints are flagged.",
        min: 1,
        max: 120,
      },
    ],
  },
  {
    title: "Security",
    fields: [
      {
        key: "session_timeout_minutes",
        label: "Session timeout (minutes)",
        help: "How long staff can be idle before they're signed out automatically. Leave blank for no timeout.",
        min: 5,
        max: 1440,
        optional: true,
      },
    ],
  },
];

function Group({ title, children }: { title: string; children: ReactNode }) {
  return (
    <fieldset className="mb-6">
      <legend className="mb-3 text-sm font-semibold text-teal">{title}</legend>
      {children}
    </fieldset>
  );
}

function initialValues(settings: EffectiveTenantSettings): Record<string, string> {
  const values: Record<string, string> = {};
  for (const group of GROUPS) {
    for (const field of group.fields) {
      const value = settings[field.key];
      values[field.key] = value === null || value === undefined ? "" : String(value);
    }
  }
  return values;
}

export function GeneralSettingsTab({ tenantId }: { tenantId: number }) {
  const settingsState = useSaveSettings(tenantId);

  if (settingsState.isLoading || !settingsState.tenant) {
    return (
      <Card>
        <CardBody>Loading…</CardBody>
      </Card>
    );
  }

  return <GeneralSettingsForm settings={settingsState.tenant.settings} {...settingsState} />;
}

function GeneralSettingsForm({
  settings: loaded,
  save,
  isSaving,
  saved,
  error,
}: { settings: EffectiveTenantSettings } & Pick<ReturnType<typeof useSaveSettings>, "save" | "isSaving" | "saved" | "error">) {
  const [values, setValues] = useState<Record<string, string>>(() => initialValues(loaded));

  async function handleSave(event: FormEvent) {
    event.preventDefault();
    const settings: Record<string, number | null> = {};
    for (const group of GROUPS) {
      for (const field of group.fields) {
        const raw = values[field.key] ?? "";
        settings[field.key] = raw === "" && field.optional ? null : Number(raw);
      }
    }
    await save({ settings });
  }

  return (
    <Card>
      <CardHeader>General Settings</CardHeader>
      <CardBody>
        {saved && (
          <div className="mb-4">
            <Alert tone="success">Settings saved — they take effect immediately across the app.</Alert>
          </div>
        )}
        {error && (
          <div className="mb-4">
            <Alert tone="danger">{error}</Alert>
          </div>
        )}
        <form onSubmit={handleSave} className="max-w-2xl">
          {GROUPS.map((group) => (
            <Group key={group.title} title={group.title}>
              {group.fields.map((field) => (
                <div key={field.key}>
                  <FormField label={field.label} htmlFor={`setting-${field.key}`}>
                    <Input
                      id={`setting-${field.key}`}
                      type="number"
                      min={field.min}
                      max={field.max}
                      step={field.step ?? "1"}
                      required={!field.optional}
                      placeholder={field.optional ? "Not set" : undefined}
                      value={values[field.key] ?? ""}
                      onChange={(e) => setValues({ ...values, [field.key]: e.target.value })}
                    />
                  </FormField>
                  <p className="-mt-3 mb-4 text-xs text-inksoft">{field.help}</p>
                </div>
              ))}
            </Group>
          ))}
          <Button type="submit" isLoading={isSaving}>
            Save changes
          </Button>
        </form>
      </CardBody>
    </Card>
  );
}
