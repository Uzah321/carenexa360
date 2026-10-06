import type { ObservationType } from "../../lib/types";

// Form state for any observation type. Numbers stay strings while editing
// so a half-typed value ("38.") doesn't get coerced.
export type ObservationDraft = Record<string, string | boolean>;

export const DEFAULT_UNITS: Partial<Record<ObservationType, string>> = {
  pulse: "bpm",
  temperature: "°C",
  oxygen_saturation: "%",
  respiratory_rate: "breaths/min",
  blood_glucose: "mg/dL",
};

const NUMERIC_KEYS = ["value", "systolic", "diastolic", "respiration_rate", "spo2", "pulse", "temperature", "spo2_scale", "length_cm", "width_cm", "depth_cm"];

export function draftToValue(type: ObservationType, draft: ObservationDraft): Record<string, number | string | boolean> {
  const keys =
    type === "blood_pressure"
      ? ["systolic", "diastolic"]
      : type === "oxygen_saturation"
        ? ["value", "on_oxygen", "spo2_scale"]
        : type === "news2"
          ? ["respiration_rate", "spo2", "spo2_scale", "on_oxygen", "systolic", "pulse", "consciousness", "temperature"]
          : type === "wound"
            ? ["site", "length_cm", "width_cm", "depth_cm", "stage", "appearance", "exudate"]
            : ["value"];

  return Object.fromEntries(
    keys
      .filter((k) => draft[k] !== undefined && draft[k] !== "")
      .map((k) => [k, NUMERIC_KEYS.includes(k) ? Number(draft[k]) : draft[k]]),
  );
}

export function valueToDraft(value: Record<string, number | string | boolean>): ObservationDraft {
  return Object.fromEntries(Object.entries(value).map(([k, v]) => [k, typeof v === "boolean" ? v : String(v)]));
}
