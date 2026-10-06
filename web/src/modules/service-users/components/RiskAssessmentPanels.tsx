import type { ReactNode } from "react";
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  Checkbox,
  EmptyState,
  FormField,
  Input,
  RichTextEditor,
  RichTextView,
  RowActionsMenu,
  Select,
  StatusBadge,
  Textarea,
} from "../../../design-system";
import type { CarePlanRiskAssessmentInput } from "../../care-planning/api";
import {
  COMMON_MEDICATION_HAZARDS,
  LIKELIHOOD_LABELS,
  MEDICATION_SUPPORT_LABELS,
  PERSON_AT_RISK_LABELS,
  RISK_RATING_LABELS,
  RISK_RATING_TONE,
  RISK_TYPE_LABELS,
  SEVERITY_LABELS,
  riskRating,
  riskScore,
} from "../../care-planning/risk";
import {
  CARE_PLAN_AREAS,
  MEDICATION_SUPPORT_LEVELS,
  PERSONS_AT_RISK,
  RISK_TYPES,
  type CarePlanRiskAssessment,
  type MedicationRiskDetails,
  type MedicationSupportLevel,
  type RiskAssessmentType,
  type RiskType,
  type ServiceUser,
  type StaffMember,
} from "../../../lib/types";
import { todayIso } from "../../../lib/dates";

const SCALE = [1, 2, 3, 4, 5];

function areaLabel(area: string) {
  return area.replaceAll("_", " ");
}

export function RiskScoreBadge({ likelihood, severity }: { likelihood: number | null; severity: number | null }) {
  const score = riskScore(likelihood, severity);
  const rating = riskRating(score);
  if (!rating) return <StatusBadge label="Not scored" tone="neutral" />;
  return <StatusBadge label={`${RISK_RATING_LABELS[rating]} · ${score}`} tone={RISK_RATING_TONE[rating]} />;
}

const MATRIX_CELL_CLASSES = {
  low: "bg-limetint text-lime",
  medium: "bg-ambertint text-amber",
  high: "bg-coraltint text-coral",
  very_high: "bg-coral text-white",
} as const;

// Plots each assessment at its current position — residual (after controls)
// where scored, otherwise its initial score.
function RiskMatrix({ assessments }: { assessments: CarePlanRiskAssessment[] }) {
  const counts = new Map<string, number>();
  for (const ra of assessments) {
    const l = ra.residual_likelihood ?? ra.likelihood;
    const s = ra.residual_severity ?? ra.severity;
    if (l && s) counts.set(`${l}-${s}`, (counts.get(`${l}-${s}`) ?? 0) + 1);
  }

  return (
    <div className="overflow-x-auto">
      <table className="border-separate border-spacing-1 text-xs" aria-label="Risk matrix">
        <tbody>
          {[...SCALE].reverse().map((l) => (
            <tr key={l}>
              <th scope="row" className="pr-2 text-right font-medium text-inksoft">
                {LIKELIHOOD_LABELS[l]}
              </th>
              {SCALE.map((s) => {
                const rating = riskRating(l * s) ?? "low";
                const count = counts.get(`${l}-${s}`);
                return (
                  <td
                    key={s}
                    title={`Likelihood ${l} × Severity ${s} = ${l * s}`}
                    className={`h-9 w-12 rounded-md text-center font-semibold ${MATRIX_CELL_CLASSES[rating]} ${count ? "ring-2 ring-ink/60" : "opacity-70"}`}
                  >
                    {count ?? ""}
                  </td>
                );
              })}
            </tr>
          ))}
          <tr>
            <td />
            {SCALE.map((s) => (
              <th key={s} scope="col" className="pt-1 text-center font-medium text-inksoft">
                {SEVERITY_LABELS[s]}
              </th>
            ))}
          </tr>
        </tbody>
      </table>
      <p className="mt-1 text-xs text-inksoft">Rows: likelihood · Columns: severity · Numbers show how many risks sit in each cell after controls.</p>
    </div>
  );
}

function Field({ label, value }: { label: string; value: ReactNode }) {
  if (value === null || value === undefined || value === "") return null;
  return (
    <div>
      <h4 className="mb-1 text-xs font-semibold uppercase tracking-wide text-inksoft">{label}</h4>
      <div className="whitespace-pre-line text-sm text-ink">{value}</div>
    </div>
  );
}

function yesNo(value: boolean | null | undefined) {
  if (value === null || value === undefined) return null;
  return value ? "Yes" : "No";
}

function RiskAssessmentCard({
  ra,
  canManage,
  onEdit,
  onRemove,
}: {
  ra: CarePlanRiskAssessment;
  canManage: boolean;
  onEdit: () => void;
  onRemove: () => void;
}) {
  const med = ra.medication_details;
  const overdue = ra.review_date && ra.review_date < todayIso();

  return (
    <Card className="print-avoid-break">
      <CardHeader>
        <div className="flex flex-wrap items-start justify-between gap-2">
          <div className="min-w-0">
            {med?.medication_name && <p className="text-xs font-semibold uppercase tracking-wide text-teal">{med.medication_name}</p>}
            <p className="font-display text-base font-bold text-ink">{ra.hazard}</p>
            <p className="text-xs text-inksoft">
              {[ra.risk_type ? RISK_TYPE_LABELS[ra.risk_type] : null, ra.area ? areaLabel(ra.area) : null].filter(Boolean).join(" · ")}
            </p>
          </div>
          <div className="flex shrink-0 items-center gap-2">
            <span className="text-xs text-inksoft">Initial</span>
            <RiskScoreBadge likelihood={ra.likelihood} severity={ra.severity} />
            <span className="text-xs text-inksoft">→ Residual</span>
            <RiskScoreBadge likelihood={ra.residual_likelihood} severity={ra.residual_severity} />
            {(ra.target_likelihood || ra.target_severity) && (
              <>
                <span className="text-xs text-inksoft">Target</span>
                <RiskScoreBadge likelihood={ra.target_likelihood} severity={ra.target_severity} />
              </>
            )}
            {canManage && (
              <RowActionsMenu
                label={`${ra.hazard} actions`}
                actions={[
                  { label: "Edit risk assessment", onClick: onEdit },
                  { label: "Remove risk assessment", onClick: onRemove, tone: "danger" as const },
                ]}
              />
            )}
          </div>
        </div>
      </CardHeader>
      <CardBody>
        {ra.details && (
          <div className="mb-5">
            <h4 className="mb-1 text-xs font-semibold uppercase tracking-wide text-inksoft">Risk details</h4>
            <RichTextView value={ra.details} />
          </div>
        )}
        <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
          <Field label="Risk triggers" value={ra.triggers} />
          <Field label="Who might be harmed" value={ra.persons_at_risk.map((p) => PERSON_AT_RISK_LABELS[p]).join(", ") || null} />
          <Field label="How they might be harmed" value={ra.harm_description} />
          {med && (
            <>
              <Field label="Dose / route / frequency" value={med.dose_route_frequency} />
              <Field label="Level of support" value={med.support_level ? MEDICATION_SUPPORT_LABELS[med.support_level] : null} />
              <Field label="Controlled drug" value={yesNo(med.controlled_drug)} />
              <Field label="PRN (as required)" value={yesNo(med.prn)} />
              <Field label="PRN protocol" value={med.prn ? med.prn_protocol : null} />
              <Field label="Capacity & consent" value={med.capacity_and_consent} />
              <Field label="Known allergies" value={med.known_allergies} />
              <Field label="Side effects to monitor" value={med.side_effects_to_monitor} />
              <Field label="Storage" value={med.storage} />
              <Field label="Ordering & collection" value={med.ordering_and_collection} />
              <Field label="Disposal" value={med.disposal} />
              <Field label="If a dose is missed, refused or an error occurs" value={med.error_response} />
            </>
          )}
          <Field label="Existing control measures" value={ra.existing_controls} />
          <Field label="Further action required" value={ra.further_actions} />
        </div>
        {ra.contingency_plan_required && (
          <div className="mt-5 rounded-xl border border-amber/30 bg-ambertint/40 p-3">
            <h4 className="mb-1 text-xs font-semibold uppercase tracking-wide text-amber">Safety / contingency plan</h4>
            {ra.contingency_plan ? <RichTextView value={ra.contingency_plan} /> : <p className="text-sm text-inksoft">Required — not yet written.</p>}
          </div>
        )}
        <div className="mt-5 grid grid-cols-2 gap-4 border-t border-line pt-4 sm:grid-cols-3">
          <div>
            <h4 className="mb-1 text-xs font-semibold uppercase tracking-wide text-inksoft">Action by</h4>
            <p className="text-sm text-ink">{ra.action_owner_name ?? "—"}</p>
          </div>
          <div>
            <h4 className="mb-1 text-xs font-semibold uppercase tracking-wide text-inksoft">Action due</h4>
            <p className="text-sm text-ink">{ra.action_due_date ?? "—"}</p>
          </div>
          <div>
            <h4 className="mb-1 text-xs font-semibold uppercase tracking-wide text-inksoft">Next review</h4>
            <p className={`text-sm ${overdue ? "font-semibold text-coral" : "text-ink"}`}>{ra.review_date ?? "—"}</p>
          </div>
        </div>
      </CardBody>
    </Card>
  );
}

export function RiskAssessmentList({
  type,
  assessments,
  canManage,
  onAdd,
  onEdit,
  onRemove,
  children,
}: {
  type: RiskAssessmentType;
  assessments: CarePlanRiskAssessment[];
  canManage: boolean;
  onAdd: () => void;
  onEdit: (ra: CarePlanRiskAssessment) => void;
  onRemove: (ra: CarePlanRiskAssessment) => void;
  children?: ReactNode;
}) {
  const ofType = assessments
    .filter((ra) => ra.type === type)
    .sort((a, b) => (b.residual_risk_score ?? b.risk_score ?? 0) - (a.residual_risk_score ?? a.risk_score ?? 0));
  const noun = type === "medication" ? "medication risk assessment" : "risk assessment";

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <p className="text-sm text-inksoft">
          {ofType.length} {noun}
          {ofType.length === 1 ? "" : "s"} on this version, highest current risk first.
        </p>
        {canManage && (
          <Button variant="secondary" onClick={onAdd}>
            Add {noun}
          </Button>
        )}
      </div>

      {ofType.length > 0 && (
        <Card>
          <CardBody>
            <RiskMatrix assessments={ofType} />
          </CardBody>
        </Card>
      )}

      {ofType.length === 0 ? (
        <EmptyState message={`No ${noun}s have been recorded on this care plan.`} />
      ) : (
        ofType.map((ra) => (
          <RiskAssessmentCard key={ra.id} ra={ra} canManage={canManage} onEdit={() => onEdit(ra)} onRemove={() => onRemove(ra)} />
        ))
      )}

      {children}
    </div>
  );
}

function ScoreSelect({
  id,
  label,
  value,
  labels,
  onChange,
}: {
  id: string;
  label: string;
  value: number | null;
  labels: Record<number, string>;
  onChange: (value: number | null) => void;
}) {
  return (
    <FormField label={label} htmlFor={id}>
      <Select id={id} value={value ?? ""} onChange={(e) => onChange(e.target.value ? Number(e.target.value) : null)}>
        <option value="">Not scored</option>
        {SCALE.map((n) => (
          <option key={n} value={n}>
            {n} — {labels[n]}
          </option>
        ))}
      </Select>
    </FormField>
  );
}

export function RiskAssessmentFormFields({
  value,
  onChange,
  staff,
  serviceUser,
}: {
  value: CarePlanRiskAssessmentInput;
  onChange: (patch: Partial<CarePlanRiskAssessmentInput>) => void;
  staff: StaffMember[] | undefined;
  serviceUser?: ServiceUser;
}) {
  const isMedication = value.type === "medication";
  const med: MedicationRiskDetails = value.medication_details ?? {};
  const setMed = (patch: Partial<MedicationRiskDetails>) => onChange({ medication_details: { ...med, ...patch } });

  function togglePerson(person: (typeof PERSONS_AT_RISK)[number], checked: boolean) {
    onChange({
      persons_at_risk: checked ? [...value.persons_at_risk, person] : value.persons_at_risk.filter((p) => p !== person),
    });
  }

  return (
    <>
      {serviceUser && (
        <p className="mb-4 text-base font-semibold text-ink">
          {serviceUser.first_name} {serviceUser.last_name}{" "}
          <span className="text-sm font-normal text-inksoft">
            ({[serviceUser.date_of_birth, serviceUser.nhs_number ? `NHS ${serviceUser.nhs_number}` : null].filter(Boolean).join(" / ") || "no DOB or NHS number recorded"})
          </span>
        </p>
      )}
      {isMedication && (
        <>
          <h3 className="mb-2 text-sm font-semibold text-ink">Medication</h3>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <FormField label="Medication name" htmlFor="ra-med-name">
              <Input id="ra-med-name" value={med.medication_name ?? ""} onChange={(e) => setMed({ medication_name: e.target.value })} />
            </FormField>
            <FormField label="Dose / route / frequency" htmlFor="ra-med-dose">
              <Input
                id="ra-med-dose"
                placeholder="e.g. 5mg oral, once daily at 6pm"
                value={med.dose_route_frequency ?? ""}
                onChange={(e) => setMed({ dose_route_frequency: e.target.value })}
              />
            </FormField>
          </div>
          <FormField label="Level of support needed" htmlFor="ra-med-support">
            <Select
              id="ra-med-support"
              value={med.support_level ?? ""}
              onChange={(e) => setMed({ support_level: (e.target.value || null) as MedicationSupportLevel | null })}
            >
              <option value="">Not specified</option>
              {MEDICATION_SUPPORT_LEVELS.map((level) => (
                <option key={level} value={level}>
                  {MEDICATION_SUPPORT_LABELS[level]}
                </option>
              ))}
            </Select>
          </FormField>
          <div className="mb-4 flex flex-wrap gap-6">
            <Checkbox
              id="ra-med-cd"
              label="Controlled drug"
              checked={Boolean(med.controlled_drug)}
              onChange={(e) => setMed({ controlled_drug: e.target.checked })}
            />
            <Checkbox id="ra-med-prn" label="PRN (as required)" checked={Boolean(med.prn)} onChange={(e) => setMed({ prn: e.target.checked })} />
          </div>
          {med.prn && (
            <FormField label="PRN protocol (when to give, max dose, minimum interval)" htmlFor="ra-med-prn-protocol">
              <Textarea id="ra-med-prn-protocol" value={med.prn_protocol ?? ""} onChange={(e) => setMed({ prn_protocol: e.target.value })} />
            </FormField>
          )}
          <FormField label="Mental capacity & consent to support" htmlFor="ra-med-capacity">
            <Textarea
              id="ra-med-capacity"
              value={med.capacity_and_consent ?? ""}
              onChange={(e) => setMed({ capacity_and_consent: e.target.value })}
            />
          </FormField>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <FormField label="Known allergies" htmlFor="ra-med-allergies">
              <Input id="ra-med-allergies" value={med.known_allergies ?? ""} onChange={(e) => setMed({ known_allergies: e.target.value })} />
            </FormField>
            <FormField label="Side effects to monitor" htmlFor="ra-med-side-effects">
              <Input
                id="ra-med-side-effects"
                value={med.side_effects_to_monitor ?? ""}
                onChange={(e) => setMed({ side_effects_to_monitor: e.target.value })}
              />
            </FormField>
            <FormField label="Storage arrangements" htmlFor="ra-med-storage">
              <Input id="ra-med-storage" value={med.storage ?? ""} onChange={(e) => setMed({ storage: e.target.value })} />
            </FormField>
            <FormField label="Who orders & collects" htmlFor="ra-med-ordering">
              <Input
                id="ra-med-ordering"
                value={med.ordering_and_collection ?? ""}
                onChange={(e) => setMed({ ordering_and_collection: e.target.value })}
              />
            </FormField>
          </div>
          <FormField label="Disposal of unused / expired medication" htmlFor="ra-med-disposal">
            <Input id="ra-med-disposal" value={med.disposal ?? ""} onChange={(e) => setMed({ disposal: e.target.value })} />
          </FormField>
          <FormField label="What to do if a dose is missed, refused or an error occurs" htmlFor="ra-med-error">
            <Textarea id="ra-med-error" value={med.error_response ?? ""} onChange={(e) => setMed({ error_response: e.target.value })} />
          </FormField>
          <h3 className="mb-2 mt-6 text-sm font-semibold text-ink">Risk</h3>
        </>
      )}

      {!isMedication && (
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <FormField label="Risk type *" htmlFor="ra-risk-type">
            <Select id="ra-risk-type" required value={value.risk_type} onChange={(e) => onChange({ risk_type: e.target.value as RiskType | "" })}>
              <option value="" disabled>
                Select risk type
              </option>
              {RISK_TYPES.map((t) => (
                <option key={t} value={t}>
                  {RISK_TYPE_LABELS[t]}
                </option>
              ))}
            </Select>
          </FormField>
          <FormField label="Care plan area" htmlFor="ra-area">
            <Select id="ra-area" value={value.area} onChange={(e) => onChange({ area: e.target.value as CarePlanRiskAssessmentInput["area"] })}>
              <option value="">General / not area-specific</option>
              {CARE_PLAN_AREAS.map((area) => (
                <option key={area} value={area}>
                  {areaLabel(area)}
                </option>
              ))}
            </Select>
          </FormField>
        </div>
      )}

      <FormField label="Risk name — what could go wrong? *" htmlFor="ra-hazard">
        <Input
          id="ra-hazard"
          required
          placeholder="Name of the risk"
          list={isMedication ? "ra-medication-hazards" : undefined}
          value={value.hazard}
          onChange={(e) => onChange({ hazard: e.target.value })}
        />
        {isMedication && (
          <datalist id="ra-medication-hazards">
            {COMMON_MEDICATION_HAZARDS.map((h) => (
              <option key={h} value={h} />
            ))}
          </datalist>
        )}
      </FormField>

      <FormField label="Risk details" htmlFor="ra-details">
        <RichTextEditor
          id="ra-details"
          aria-label="Risk details"
          value={value.details}
          template="<p><b>What the risk is:</b> </p><p><b>History / previous incidents:</b> </p><p><b>Current situation:</b> </p>"
          onChange={(details) => onChange({ details })}
        />
      </FormField>

      <FormField label="Risk triggers" htmlFor="ra-triggers">
        <Textarea
          id="ra-triggers"
          rows={2}
          placeholder="What makes this risk more likely — times, situations, signs to watch for"
          value={value.triggers}
          onChange={(e) => onChange({ triggers: e.target.value })}
        />
      </FormField>

      <fieldset className="mb-4">
        <legend className="mb-2 text-sm font-medium text-ink">Who might be harmed?</legend>
        <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
          {PERSONS_AT_RISK.map((person) => (
            <Checkbox
              key={person}
              id={`ra-person-${person}`}
              label={PERSON_AT_RISK_LABELS[person]}
              checked={value.persons_at_risk.includes(person)}
              onChange={(e) => togglePerson(person, e.target.checked)}
            />
          ))}
        </div>
      </fieldset>

      <FormField label="How might they be harmed?" htmlFor="ra-harm">
        <Textarea id="ra-harm" value={value.harm_description} onChange={(e) => onChange({ harm_description: e.target.value })} />
      </FormField>

      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <ScoreSelect id="ra-likelihood" label="Likelihood (before controls)" value={value.likelihood} labels={LIKELIHOOD_LABELS} onChange={(likelihood) => onChange({ likelihood })} />
        <ScoreSelect id="ra-severity" label="Severity (before controls)" value={value.severity} labels={SEVERITY_LABELS} onChange={(severity) => onChange({ severity })} />
      </div>
      <p className="-mt-2 mb-4 text-xs text-inksoft">
        Initial risk: <RiskScoreBadge likelihood={value.likelihood} severity={value.severity} />
      </p>

      <FormField label="Existing control measures" htmlFor="ra-controls">
        <Textarea id="ra-controls" rows={3} value={value.existing_controls} onChange={(e) => onChange({ existing_controls: e.target.value })} />
      </FormField>
      <FormField label="Further action required to reduce the risk" htmlFor="ra-further">
        <Textarea id="ra-further" rows={3} value={value.further_actions} onChange={(e) => onChange({ further_actions: e.target.value })} />
      </FormField>

      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <ScoreSelect
          id="ra-residual-likelihood"
          label="Likelihood (after controls)"
          value={value.residual_likelihood}
          labels={LIKELIHOOD_LABELS}
          onChange={(residual_likelihood) => onChange({ residual_likelihood })}
        />
        <ScoreSelect
          id="ra-residual-severity"
          label="Severity (after controls)"
          value={value.residual_severity}
          labels={SEVERITY_LABELS}
          onChange={(residual_severity) => onChange({ residual_severity })}
        />
      </div>
      <p className="-mt-2 mb-4 text-xs text-inksoft">
        Residual risk: <RiskScoreBadge likelihood={value.residual_likelihood} severity={value.residual_severity} />
      </p>

      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <ScoreSelect
          id="ra-target-likelihood"
          label="Target likelihood"
          value={value.target_likelihood}
          labels={LIKELIHOOD_LABELS}
          onChange={(target_likelihood) => onChange({ target_likelihood })}
        />
        <ScoreSelect
          id="ra-target-severity"
          label="Target severity"
          value={value.target_severity}
          labels={SEVERITY_LABELS}
          onChange={(target_severity) => onChange({ target_severity })}
        />
      </div>
      <p className="-mt-2 mb-4 text-xs text-inksoft">
        Target risk — the level this plan is aiming for: <RiskScoreBadge likelihood={value.target_likelihood} severity={value.target_severity} />
      </p>

      <div className="mb-4">
        <Checkbox
          id="ra-contingency-required"
          label="A safety / contingency plan is required for this risk"
          checked={value.contingency_plan_required}
          onChange={(e) => onChange({ contingency_plan_required: e.target.checked })}
        />
      </div>
      {value.contingency_plan_required && (
        <FormField label="Safety / contingency plan" htmlFor="ra-contingency">
          <RichTextEditor
            id="ra-contingency"
            aria-label="Safety / contingency plan"
            value={value.contingency_plan}
            template="<p><b>If this happens:</b> </p><p><b>Immediate actions:</b> </p><p><b>Who to contact:</b> </p><p><b>Out of hours:</b> </p>"
            onChange={(contingency_plan) => onChange({ contingency_plan })}
          />
        </FormField>
      )}

      <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <FormField label="Action by" htmlFor="ra-owner">
          <Select
            id="ra-owner"
            value={value.action_owner_id ?? ""}
            onChange={(e) => onChange({ action_owner_id: e.target.value ? Number(e.target.value) : null })}
          >
            <option value="">Unassigned</option>
            {(staff ?? []).map((s) => (
              <option key={s.id} value={s.user_id}>
                {s.name}
              </option>
            ))}
          </Select>
        </FormField>
        <FormField label="Action due" htmlFor="ra-due">
          <Input id="ra-due" type="date" value={value.action_due_date} onChange={(e) => onChange({ action_due_date: e.target.value })} />
        </FormField>
        <FormField label="Review date" htmlFor="ra-review">
          <Input id="ra-review" type="date" value={value.review_date} onChange={(e) => onChange({ review_date: e.target.value })} />
        </FormField>
      </div>
    </>
  );
}
