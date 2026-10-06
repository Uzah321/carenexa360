import type { ReactNode } from "react";
import { Info } from "lucide-react";
import { Button, Card, CardBody, CardHeader, EmptyState, Input, RichTextEditor, RichTextView, StatusBadge } from "../../../design-system";
import type { HomeCarePlan, HomeCarePlanNeed, HomeCarePlanNeedArea } from "../../../lib/types";
import {
  ABOUT_ME_TEMPLATE,
  COGNITION_PROMPTS,
  COGNITION_TEMPLATE,
  END_OF_LIFE_AREAS,
  GOALS_PROMPTS,
  GOALS_TEMPLATE,
  NEED_AREAS,
  SUMMARIES,
  consentLabel,
  homeCarePlanHasContent,
  type NeedAreaConfig,
} from "../../care-planning/homeCarePlan";

// ---- Shared bits ----------------------------------------------------------

function FieldLabel({ htmlFor, label, hint }: { htmlFor: string; label: string; hint?: string }) {
  return (
    <label htmlFor={htmlFor} className="mb-1 flex items-center gap-1.5 text-sm font-medium text-ink">
      {label}
      {hint && (
        <span title={hint} className="text-sky" aria-label={hint}>
          <Info className="h-3.5 w-3.5" aria-hidden />
        </span>
      )}
    </label>
  );
}

/** Text input with "optional prompts" — suggested phrases staff can pick or type over. */
function PromptInput({ id, value, prompts, onChange }: { id: string; value: string; prompts: string[]; onChange: (v: string) => void }) {
  return (
    <>
      <Input id={id} list={`${id}-prompts`} placeholder="Optional prompts" value={value} onChange={(e) => onChange(e.target.value)} />
      <datalist id={`${id}-prompts`}>
        {prompts.map((p) => (
          <option key={p} value={p} />
        ))}
      </datalist>
    </>
  );
}

/** Yes / No, with neither selected meaning consent hasn't been recorded. */
function ConsentToggle({
  id,
  label,
  value,
  onChange,
}: {
  id: string;
  label: string;
  value: boolean | null;
  onChange: (v: boolean | null) => void;
}) {
  const option = (choice: boolean, text: string) => {
    const selected = value === choice;
    return (
      <button
        type="button"
        role="radio"
        aria-checked={selected}
        onClick={() => onChange(selected ? null : choice)}
        className={`px-3 py-1 text-xs font-medium transition-colors duration-150 ${
          selected ? (choice ? "bg-lime text-white" : "bg-coral text-white") : "bg-white text-inksoft hover:bg-paper"
        }`}
      >
        {text}
      </button>
    );
  };

  return (
    <div className="mb-5 flex flex-wrap items-center justify-between gap-2">
      <span id={id} className="text-sm text-ink">
        {label}
      </span>
      <div role="radiogroup" aria-labelledby={id} className="flex overflow-hidden rounded-full border border-line">
        {option(true, "Yes")}
        {option(false, "No")}
      </div>
    </div>
  );
}

function ConsentBadge({ consented }: { consented: boolean | null | undefined }) {
  return (
    <StatusBadge label={consentLabel(consented)} tone={consented === true ? "success" : consented === false ? "danger" : "neutral"} />
  );
}

// ---- Form -------------------------------------------------------------------

export function HomeCarePlanFormFields({ value, onChange }: { value: HomeCarePlan; onChange: (next: HomeCarePlan) => void }) {
  const set = (patch: Partial<HomeCarePlan>) => onChange({ ...value, ...patch });
  const need = (area: HomeCarePlanNeedArea): HomeCarePlanNeed => value.needs?.[area] ?? { details: null, consented: null };
  const setNeed = (area: HomeCarePlanNeedArea, patch: Partial<HomeCarePlanNeed>) =>
    set({ needs: { ...value.needs, [area]: { ...need(area), ...patch } } });

  const needFields = (config: NeedAreaConfig) => (
    <div key={config.key} className="mb-2">
      <FieldLabel htmlFor={`hcp-need-${config.key}`} label={config.label} />
      <div className="mb-4">
        <RichTextEditor
          id={`hcp-need-${config.key}`}
          aria-label={config.label}
          value={need(config.key).details ?? ""}
          template={config.template}
          onChange={(details) => setNeed(config.key, { details })}
        />
      </div>
      {config.consentLabel && (
        <ConsentToggle
          id={`hcp-consent-${config.key}`}
          label={config.consentLabel}
          value={need(config.key).consented}
          onChange={(consented) => setNeed(config.key, { consented })}
        />
      )}
    </div>
  );

  return (
    <>
      <h3 className="mb-3 text-sm font-semibold text-teal">Home Care Plan</h3>

      <FieldLabel htmlFor="hcp-about-me" label="About me" />
      <div className="mb-5">
        <RichTextEditor id="hcp-about-me" aria-label="About me" value={value.about_me ?? ""} template={ABOUT_ME_TEMPLATE} onChange={(about_me) => set({ about_me })} />
      </div>

      <FieldLabel
        htmlFor="hcp-desired-outcomes"
        label="What are the client's desired goals and outcomes?"
        hint="What the client wants from their care, in their own words where possible."
      />
      <div className="mb-3">
        <PromptInput id="hcp-desired-outcomes" value={value.desired_outcomes ?? ""} prompts={GOALS_PROMPTS} onChange={(desired_outcomes) => set({ desired_outcomes })} />
      </div>
      <FieldLabel htmlFor="hcp-goals" label="Detailed goals and outcomes" />
      <div className="mb-5">
        <RichTextEditor
          id="hcp-goals"
          aria-label="Detailed goals and outcomes"
          value={value.goals_and_outcomes ?? ""}
          template={GOALS_TEMPLATE}
          onChange={(goals_and_outcomes) => set({ goals_and_outcomes })}
        />
      </div>

      <FieldLabel
        htmlFor="hcp-cognition-summary"
        label="Does the client have cognitive impairment?"
        hint="Include any diagnosis, how it affects daily life, and mental capacity for care decisions."
      />
      <div className="mb-3">
        <PromptInput
          id="hcp-cognition-summary"
          value={value.cognitive_impairment_summary ?? ""}
          prompts={COGNITION_PROMPTS}
          onChange={(cognitive_impairment_summary) => set({ cognitive_impairment_summary })}
        />
      </div>
      <div className="mb-5">
        <RichTextEditor
          id="hcp-cognition"
          aria-label="Cognitive impairment details"
          value={value.cognitive_impairment ?? ""}
          template={COGNITION_TEMPLATE}
          onChange={(cognitive_impairment) => set({ cognitive_impairment })}
        />
      </div>

      {NEED_AREAS.map(needFields)}

      <h3 className="mb-3 mt-6 text-sm font-semibold text-teal">Summaries</h3>
      {SUMMARIES.map((summary) => (
        <div key={summary.key} className="mb-3">
          <FieldLabel htmlFor={`hcp-summary-${summary.key}`} label={summary.label} hint={summary.hint} />
          <PromptInput
            id={`hcp-summary-${summary.key}`}
            value={value.summaries?.[summary.key] ?? ""}
            prompts={summary.prompts}
            onChange={(text) => set({ summaries: { ...value.summaries, [summary.key]: text } })}
          />
        </div>
      ))}

      <h3 className="mb-3 mt-6 text-sm font-semibold text-teal">Advance care and final days</h3>
      {END_OF_LIFE_AREAS.map(needFields)}
    </>
  );
}

// ---- Read view -------------------------------------------------------------

function Block({ title, children, aside }: { title: string; children: ReactNode; aside?: ReactNode }) {
  return (
    <div className="border-b border-line py-4 last:border-b-0">
      <div className="mb-1.5 flex flex-wrap items-center justify-between gap-2">
        <h4 className="text-xs font-semibold uppercase tracking-wide text-inksoft">{title}</h4>
        {aside}
      </div>
      {children}
    </div>
  );
}

function NeedBlock({ config, plan }: { config: NeedAreaConfig; plan: HomeCarePlan }) {
  const need = plan.needs?.[config.key];
  if (!need || (!need.details && need.consented === null)) return null;
  return (
    <Block title={config.title} aside={config.consentLabel ? <ConsentBadge consented={need.consented} /> : undefined}>
      {need.details ? <RichTextView value={need.details} /> : <p className="text-sm text-inksoft">—</p>}
    </Block>
  );
}

export function HomeCarePlanView({
  plan,
  riskCount,
  canManage,
  onEdit,
  onViewRisks,
}: {
  plan: HomeCarePlan | null;
  riskCount: number;
  canManage: boolean;
  onEdit: () => void;
  onViewRisks: () => void;
}) {
  const hasContent = homeCarePlanHasContent(plan);
  const summaries = SUMMARIES.filter((s) => plan?.summaries?.[s.key]);

  return (
    <Card>
      <CardHeader>
        <div className="flex items-center justify-between">
          <span className="font-display text-base font-bold text-ink">Home Care Plan</span>
          {canManage && (
            <Button variant="secondary" onClick={onEdit}>
              {hasContent ? "Edit" : "Start Home Care Plan"}
            </Button>
          )}
        </div>
      </CardHeader>
      <CardBody>
        {!hasContent || !plan ? (
          <EmptyState message="No home care plan has been written for this version yet." />
        ) : (
          <>
            {summaries.length > 0 && (
              <dl className="mb-2 grid grid-cols-1 gap-x-6 gap-y-2 rounded-xl bg-paper p-4 sm:grid-cols-2">
                {summaries.map((s) => (
                  <div key={s.key}>
                    <dt className="text-xs text-inksoft first-letter:uppercase">{s.label.replace(/^Client's /, "").replace(/ summary$/, "")}</dt>
                    <dd className="text-sm font-medium text-ink">{plan.summaries?.[s.key]}</dd>
                  </div>
                ))}
              </dl>
            )}

            {plan.about_me && (
              <Block title="About me">
                <RichTextView value={plan.about_me} />
              </Block>
            )}
            {(plan.desired_outcomes || plan.goals_and_outcomes) && (
              <Block title="Desired goals and outcomes">
                {plan.desired_outcomes && <p className="mb-1 text-sm font-medium text-ink">{plan.desired_outcomes}</p>}
                <RichTextView value={plan.goals_and_outcomes} />
              </Block>
            )}
            {(plan.cognitive_impairment_summary || plan.cognitive_impairment) && (
              <Block title="Cognitive impairment">
                {plan.cognitive_impairment_summary && <p className="mb-1 text-sm font-medium text-ink">{plan.cognitive_impairment_summary}</p>}
                <RichTextView value={plan.cognitive_impairment} />
              </Block>
            )}
            {NEED_AREAS.map((config) => (
              <NeedBlock key={config.key} config={config} plan={plan} />
            ))}
            {END_OF_LIFE_AREAS.map((config) => (
              <NeedBlock key={config.key} config={config} plan={plan} />
            ))}
          </>
        )}

        <div className="mt-4 flex flex-wrap items-center justify-between gap-2 rounded-xl border border-line px-4 py-3 text-sm">
          <span className="text-ink">Risk assessment</span>
          <div className="flex items-center gap-3">
            <StatusBadge label={riskCount === 0 ? "None" : `${riskCount} recorded`} tone={riskCount === 0 ? "neutral" : "info"} />
            <button type="button" className="font-medium text-teal hover:text-teal/90" onClick={onViewRisks}>
              {riskCount === 0 ? "Add a risk" : "View risks"}
            </button>
          </div>
        </div>
      </CardBody>
    </Card>
  );
}
