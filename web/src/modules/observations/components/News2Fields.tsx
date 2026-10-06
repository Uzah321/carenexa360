import { Checkbox, FormField, Input, Select, StatusBadge } from "../../../design-system";
import type { News2Assessment, News2Parameter, ObservationType } from "../../../lib/types";
import { DEFAULT_UNITS, type ObservationDraft } from "../observationDraft";
import {
  CONSCIOUSNESS_LABELS,
  CONSCIOUSNESS_LEVELS,
  RISK_LABELS,
  directionLabel,
  riskTone,
  scoreTone,
} from "../news2";

function NumberField({
  id,
  label,
  draftKey,
  draft,
  onChange,
  step = "1",
}: {
  id: string;
  label: string;
  draftKey: string;
  draft: ObservationDraft;
  onChange: (patch: ObservationDraft) => void;
  step?: string;
}) {
  return (
    <FormField label={label} htmlFor={id}>
      <Input
        id={id}
        type="number"
        step={step}
        inputMode="decimal"
        required
        value={String(draft[draftKey] ?? "")}
        onChange={(e) => onChange({ [draftKey]: e.target.value })}
      />
    </FormField>
  );
}

function Spo2ScaleField({ idPrefix, draft, onChange }: { idPrefix: string; draft: ObservationDraft; onChange: (patch: ObservationDraft) => void }) {
  return (
    <FormField label="SpO₂ scale" htmlFor={`${idPrefix}-scale`}>
      <Select id={`${idPrefix}-scale`} value={String(draft.spo2_scale ?? "1")} onChange={(e) => onChange({ spo2_scale: e.target.value })}>
        <option value="1">Scale 1 — standard</option>
        <option value="2">Scale 2 — target 88–92% (clinician-prescribed, e.g. COPD)</option>
      </Select>
    </FormField>
  );
}

/** The value inputs for one observation type. */
export function ObservationValueFields({
  type,
  idPrefix,
  draft,
  onChange,
}: {
  type: ObservationType;
  idPrefix: string;
  draft: ObservationDraft;
  onChange: (patch: ObservationDraft) => void;
}) {
  if (type === "blood_pressure") {
    return (
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <NumberField id={`${idPrefix}-systolic`} label="Systolic (mmHg)" draftKey="systolic" draft={draft} onChange={onChange} />
        <NumberField id={`${idPrefix}-diastolic`} label="Diastolic (mmHg)" draftKey="diastolic" draft={draft} onChange={onChange} />
      </div>
    );
  }

  if (type === "oxygen_saturation") {
    return (
      <>
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <NumberField id={`${idPrefix}-value`} label="SpO₂ (%)" draftKey="value" draft={draft} onChange={onChange} />
          <Spo2ScaleField idPrefix={idPrefix} draft={draft} onChange={onChange} />
        </div>
        <div className="mb-4">
          <Checkbox
            id={`${idPrefix}-oxygen`}
            label="On supplemental oxygen"
            checked={draft.on_oxygen === true}
            onChange={(e) => onChange({ on_oxygen: e.target.checked })}
          />
        </div>
      </>
    );
  }

  if (type === "news2") {
    return (
      <>
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <NumberField id={`${idPrefix}-rr`} label="Respiration rate (per min)" draftKey="respiration_rate" draft={draft} onChange={onChange} />
          <NumberField id={`${idPrefix}-spo2`} label="SpO₂ (%)" draftKey="spo2" draft={draft} onChange={onChange} />
          <Spo2ScaleField idPrefix={idPrefix} draft={draft} onChange={onChange} />
          <FormField label="Air or oxygen?" htmlFor={`${idPrefix}-air`}>
            <Select
              id={`${idPrefix}-air`}
              value={draft.on_oxygen === true ? "oxygen" : "air"}
              onChange={(e) => onChange({ on_oxygen: e.target.value === "oxygen" })}
            >
              <option value="air">Air</option>
              <option value="oxygen">Oxygen</option>
            </Select>
          </FormField>
          <NumberField id={`${idPrefix}-sbp`} label="Systolic BP (mmHg)" draftKey="systolic" draft={draft} onChange={onChange} />
          <NumberField id={`${idPrefix}-pulse`} label="Pulse (per min)" draftKey="pulse" draft={draft} onChange={onChange} />
          <FormField label="Consciousness (ACVPU)" htmlFor={`${idPrefix}-acvpu`}>
            <Select
              id={`${idPrefix}-acvpu`}
              required
              value={String(draft.consciousness ?? "alert")}
              onChange={(e) => onChange({ consciousness: e.target.value })}
            >
              {CONSCIOUSNESS_LEVELS.map((level) => (
                <option key={level} value={level}>
                  {CONSCIOUSNESS_LABELS[level]}
                </option>
              ))}
            </Select>
          </FormField>
          <NumberField id={`${idPrefix}-temp`} label="Temperature (°C)" draftKey="temperature" draft={draft} onChange={onChange} step="0.1" />
        </div>
      </>
    );
  }

  return (
    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
      <NumberField
        id={`${idPrefix}-value`}
        label="Value"
        draftKey="value"
        draft={draft}
        onChange={onChange}
        step={type === "temperature" ? "0.1" : "any"}
      />
      <FormField label="Unit" htmlFor={`${idPrefix}-unit`}>
        <Input
          id={`${idPrefix}-unit`}
          placeholder={DEFAULT_UNITS[type] ?? ""}
          value={String(draft.unit ?? "")}
          onChange={(e) => onChange({ unit: e.target.value })}
        />
      </FormField>
    </div>
  );
}

function ScoreRow({ p }: { p: News2Parameter }) {
  return (
    <li className="flex items-center justify-between gap-3 py-1.5 text-sm">
      <span className="text-ink">
        {p.label} <span className="text-inksoft">— {p.reading}</span>
      </span>
      <StatusBadge label={`${directionLabel(p.direction)} · ${p.score}`} tone={scoreTone(p.score)} />
    </li>
  );
}

/**
 * Compact badge for a table row: "High · NEWS2 2" for a single reading (or
 * "Low · score 2" when the worst score is a non-NEWS2 one like glucose), and
 * "NEWS2 7 · High risk" for a full set.
 */
export function News2Badge({
  assessment,
  rangeScores = [],
  isSet,
}: {
  assessment: News2Assessment | null | undefined;
  rangeScores?: News2Parameter[];
  isSet: boolean;
}) {
  if (isSet && assessment) {
    return <StatusBadge label={`NEWS2 ${assessment.total} · ${RISK_LABELS[assessment.risk]}`} tone={riskTone(assessment.risk)} />;
  }

  const scored = [
    ...(assessment?.parameters ?? []).map((p) => ({ ...p, scale: "NEWS2" })),
    ...rangeScores.map((p) => ({ ...p, scale: "score" })),
  ];
  if (scored.length === 0) return null;

  const worst = scored.reduce((a, b) => (b.score > a.score ? b : a));
  return <StatusBadge label={`${directionLabel(worst.direction)} · ${worst.scale} ${worst.score}`} tone={scoreTone(worst.score)} />;
}

/** Per-parameter breakdown plus the escalation guidance. */
export function News2Summary({
  assessment,
  rangeScores = [],
  isSet,
}: {
  assessment: News2Assessment | null;
  rangeScores?: News2Parameter[];
  isSet: boolean;
}) {
  if (!assessment && rangeScores.length === 0) return null;

  const worstRange = Math.max(0, ...rangeScores.map((p) => p.score));

  return (
    <div className="mb-4 rounded-xl border border-line bg-paper p-3">
      {assessment && (
        <>
          <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
            <span className="text-sm font-semibold text-ink">{isSet ? `NEWS2 score: ${assessment.total}` : "NEWS2 assessment"}</span>
            {isSet && <StatusBadge label={RISK_LABELS[assessment.risk]} tone={riskTone(assessment.risk)} />}
          </div>
          <ul className="divide-y divide-line">
            {assessment.parameters.map((p) => (
              <ScoreRow key={p.parameter} p={p} />
            ))}
          </ul>
          {(isSet || assessment.total > 0) && <p className="mt-2 text-xs text-inksoft">{assessment.response}</p>}
        </>
      )}
      {rangeScores.length > 0 && (
        <>
          <p className={`text-sm font-semibold text-ink ${assessment ? "mt-3" : "mb-2"}`}>
            {assessment ? "Not part of NEWS2" : "Range assessment"}
          </p>
          <ul className="divide-y divide-line">
            {rangeScores.map((p) => (
              <ScoreRow key={p.parameter} p={p} />
            ))}
          </ul>
          {worstRange > 0 && (
            <p className="mt-2 text-xs text-inksoft">
              {worstRange >= 3
                ? "Red zone — seek urgent clinical advice (GP or NHS 111), or call 999 if the person is unwell."
                : "Outside the normal range — inform the senior carer / nurse."}
            </p>
          )}
        </>
      )}
    </div>
  );
}
