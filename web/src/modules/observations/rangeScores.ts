import type { News2Direction, News2Parameter } from "../../lib/types";

// Mirrors api/app/Modules/Observations/Support/RangeScores.php — 0–3 scores
// for measurements NEWS2 doesn't cover. These are never added to the NEWS2
// total. Keep the bands in step with the PHP.

type Scored = { score: number; direction: News2Direction } | null;

function toNumber(value: unknown): number | null {
  if (value === null || value === undefined || value === "") return null;
  const n = Number(value);
  return Number.isFinite(n) ? n : null;
}

/** Normal range 60–120 mmHg. */
export function scoreDiastolic(raw: unknown): Scored {
  const value = toNumber(raw);
  if (value === null) return null;
  if (value <= 40) return { score: 3, direction: "low" };
  if (value < 50) return { score: 2, direction: "low" };
  if (value < 60) return { score: 1, direction: "low" };
  if (value <= 120) return { score: 0, direction: "normal" };
  return { score: 3, direction: "high" };
}

/** Normal range 70–250 mg/dL. */
export function scoreBloodGlucose(raw: unknown): Scored {
  const value = toNumber(raw);
  if (value === null) return null;
  if (value < 54) return { score: 3, direction: "low" };
  if (value < 70) return { score: 2, direction: "low" };
  if (value <= 250) return { score: 0, direction: "normal" };
  if (value <= 300) return { score: 1, direction: "high" };
  if (value <= 400) return { score: 2, direction: "high" };
  return { score: 3, direction: "high" };
}

export function rangeScores(type: string, value: Record<string, unknown>): News2Parameter[] {
  const entries: [string, Scored, unknown, string, string][] =
    type === "blood_pressure"
      ? [["diastolic", scoreDiastolic(value.diastolic), value.diastolic, "Diastolic blood pressure", "mmHg"]]
      : type === "blood_glucose"
        ? [["blood_glucose", scoreBloodGlucose(value.value), value.value, "Blood glucose", "mg/dL"]]
        : [];

  return entries
    .filter((e): e is [string, NonNullable<Scored>, unknown, string, string] => e[1] !== null)
    .map(([parameter, scored, reading, label, unit]) => ({ parameter, label, reading: `${reading} ${unit}`, ...scored }));
}
