import type { News2Assessment, News2Direction, News2Parameter, News2Risk } from "../../lib/types";

// Mirrors api/app/Modules/Observations/Support/News2.php so the recording
// form can show the score live, before saving. The API remains the source of
// truth — saved readings display the `news2` block the API returns.

type Band = [upper: number | null, score: number, direction: News2Direction];

const BANDS: Record<"respiration_rate" | "spo2_scale_1" | "systolic" | "pulse" | "temperature", Band[]> = {
  respiration_rate: [[8, 3, "low"], [11, 1, "low"], [20, 0, "normal"], [24, 2, "high"], [null, 3, "high"]],
  spo2_scale_1: [[91, 3, "low"], [93, 2, "low"], [95, 1, "low"], [null, 0, "normal"]],
  systolic: [[90, 3, "low"], [100, 2, "low"], [110, 1, "low"], [219, 0, "normal"], [null, 3, "high"]],
  pulse: [[40, 3, "low"], [50, 1, "low"], [90, 0, "normal"], [110, 1, "high"], [130, 2, "high"], [null, 3, "high"]],
  temperature: [[35.0, 3, "low"], [36.0, 1, "low"], [38.0, 0, "normal"], [39.0, 1, "high"], [null, 2, "high"]],
};

const LABELS: Record<string, [string, string]> = {
  respiration_rate: ["Respiration rate", "breaths/min"],
  spo2: ["Oxygen saturation", "%"],
  air_or_oxygen: ["Supplemental oxygen", ""],
  systolic: ["Systolic blood pressure", "mmHg"],
  pulse: ["Pulse", "bpm"],
  consciousness: ["Consciousness", ""],
  temperature: ["Temperature", "°C"],
};

export const CONSCIOUSNESS_LEVELS = ["alert", "new_confusion", "voice", "pain", "unresponsive"] as const;
export type ConsciousnessLevel = (typeof CONSCIOUSNESS_LEVELS)[number];

export const CONSCIOUSNESS_LABELS: Record<ConsciousnessLevel, string> = {
  alert: "Alert",
  new_confusion: "New confusion",
  voice: "Responds to voice",
  pain: "Responds to pain",
  unresponsive: "Unresponsive",
};

export const RISK_LABELS: Record<News2Risk, string> = {
  none: "No concern",
  low: "Low risk",
  low_medium: "Low-medium risk",
  medium: "Medium risk",
  high: "High risk",
};

export const RESPONSES: Record<News2Risk, string> = {
  none: "No concerns — continue routine monitoring.",
  low: "Low risk — inform the senior carer / nurse, who should decide whether monitoring needs to increase.",
  low_medium: "Low-medium risk — a single parameter is in the red zone. Seek urgent clinical advice (GP or NHS 111).",
  medium: "Medium risk — urgent clinical review needed. Contact the GP / NHS 111 urgently and monitor at least hourly.",
  high: "High risk — emergency response. Call 999.",
};

type Scored = { score: number; direction: News2Direction } | null;

function toNumber(value: unknown): number | null {
  if (value === null || value === undefined || value === "") return null;
  const n = Number(value);
  return Number.isFinite(n) ? n : null;
}

function scoreBand(parameter: keyof typeof BANDS, raw: unknown): Scored {
  const value = toNumber(raw);
  if (value === null) return null;
  for (const [upper, score, direction] of BANDS[parameter]) {
    if (upper === null || value <= upper) return { score, direction };
  }
  return null;
}

function scoreSpo2(raw: unknown, scale: number, onOxygen: boolean): Scored {
  const value = toNumber(raw);
  if (value === null) return null;
  if (scale !== 2) return scoreBand("spo2_scale_1", value);
  if (value <= 83) return { score: 3, direction: "low" };
  if (value <= 85) return { score: 2, direction: "low" };
  if (value <= 87) return { score: 1, direction: "low" };
  if (value <= 92 || !onOxygen) return { score: 0, direction: "normal" };
  if (value <= 94) return { score: 1, direction: "high" };
  if (value <= 96) return { score: 2, direction: "high" };
  return { score: 3, direction: "high" };
}

function scoreConsciousness(level: unknown): Scored {
  if (!CONSCIOUSNESS_LEVELS.includes(level as ConsciousnessLevel)) return null;
  return level === "alert" ? { score: 0, direction: "normal" } : { score: 3, direction: "abnormal" };
}

function scoreOxygen(onOxygen: boolean): Scored {
  return onOxygen ? { score: 2, direction: "abnormal" } : { score: 0, direction: "normal" };
}

function isTrue(value: unknown): boolean {
  return value === true || value === 1 || value === "1" || value === "true";
}

function formatReading(parameter: string, reading: unknown, unit: string, scale: number): string {
  if (parameter === "air_or_oxygen") return reading ? "On oxygen" : "Air";
  if (parameter === "consciousness") return CONSCIOUSNESS_LABELS[reading as ConsciousnessLevel] ?? String(reading);
  if (parameter === "spo2") return `${reading}% (Scale ${scale})`;
  return `${reading} ${unit}`.trim();
}

export function assessNews2(type: string, value: Record<string, unknown>): News2Assessment | null {
  const onOxygen = isTrue(value.on_oxygen);
  const scale = Number(value.spo2_scale ?? 1) || 1;

  let entries: [string, Scored, unknown][] = [];
  switch (type) {
    case "respiratory_rate":
      entries = [["respiration_rate", scoreBand("respiration_rate", value.value), value.value]];
      break;
    case "oxygen_saturation":
      entries = [["spo2", scoreSpo2(value.value, scale, onOxygen), value.value]];
      if ("on_oxygen" in value) entries.push(["air_or_oxygen", scoreOxygen(onOxygen), onOxygen]);
      break;
    case "blood_pressure":
      entries = [["systolic", scoreBand("systolic", value.systolic), value.systolic]];
      break;
    case "pulse":
      entries = [["pulse", scoreBand("pulse", value.value), value.value]];
      break;
    case "temperature":
      entries = [["temperature", scoreBand("temperature", value.value), value.value]];
      break;
    case "news2":
      entries = [
        ["respiration_rate", scoreBand("respiration_rate", value.respiration_rate), value.respiration_rate],
        ["spo2", scoreSpo2(value.spo2, scale, onOxygen), value.spo2],
        ["air_or_oxygen", scoreOxygen(onOxygen), onOxygen],
        ["systolic", scoreBand("systolic", value.systolic), value.systolic],
        ["pulse", scoreBand("pulse", value.pulse), value.pulse],
        ["consciousness", scoreConsciousness(value.consciousness), value.consciousness],
        ["temperature", scoreBand("temperature", value.temperature), value.temperature],
      ];
      break;
    default:
      return null;
  }

  const parameters: News2Parameter[] = entries
    .filter((e): e is [string, NonNullable<Scored>, unknown] => e[1] !== null)
    .map(([parameter, scored, reading]) => {
      const [label, unit] = LABELS[parameter];
      return { parameter, label, reading: formatReading(parameter, reading, unit, scale), ...scored };
    });

  if (parameters.length === 0) return null;

  const total = parameters.reduce((sum, p) => sum + p.score, 0);
  const singleThree = parameters.some((p) => p.score === 3);
  const risk: News2Risk = total >= 7 ? "high" : total >= 5 ? "medium" : singleThree ? "low_medium" : total >= 1 ? "low" : "none";

  return { total, risk, single_parameter_3: singleThree, response: RESPONSES[risk], parameters };
}

/** "High", "Low" or "Normal" — the headline for a single-parameter reading. */
export function directionLabel(direction: News2Direction): string {
  return { low: "Low", high: "High", normal: "Normal", abnormal: "Abnormal" }[direction];
}

export function scoreTone(score: number): "success" | "warning" | "danger" {
  if (score >= 3) return "danger";
  if (score >= 1) return "warning";
  return "success";
}

export function riskTone(risk: News2Risk): "success" | "warning" | "danger" {
  if (risk === "medium" || risk === "high") return "danger";
  if (risk === "none") return "success";
  return "warning";
}
