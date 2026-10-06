// Mirrors Observation::WOUND_STAGES / WOUND_APPEARANCES / WOUND_EXUDATE.
export const WOUND_STAGE_LABELS: Record<string, string> = {
  category_1: "Category 1",
  category_2: "Category 2",
  category_3: "Category 3",
  category_4: "Category 4",
  unstageable: "Unstageable",
  deep_tissue_injury: "Deep tissue injury",
  not_pressure_ulcer: "Not a pressure ulcer",
};

export const WOUND_APPEARANCE_LABELS: Record<string, string> = {
  epithelialising: "Epithelialising",
  granulating: "Granulating",
  sloughy: "Sloughy",
  necrotic: "Necrotic",
  infected: "Infected",
  healed: "Healed",
};

export const WOUND_EXUDATE_LABELS: Record<string, string> = {
  none: "None",
  low: "Low",
  moderate: "Moderate",
  high: "High",
};
