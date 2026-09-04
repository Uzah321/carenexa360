import { useState } from "react";

export interface BarDatum {
  key: string;
  label: string;
  value: number;
  /** Only used in "status" mode — a design-token color name, e.g. "sky", "coral". */
  tone?: string;
}

const TONE_BAR_CLASS: Record<string, string> = {
  sky: "bg-sky",
  amber: "bg-amber",
  lime: "bg-lime",
  coral: "bg-coral",
  plum: "bg-plum",
  teal: "bg-teal",
  neutral: "bg-line",
};

const BAR_HEIGHT = 20;
const ROW_GAP = 10;

export function HorizontalBarChart({
  data,
  mode = "sequential",
  valueFormatter = (v: number) => v.toLocaleString(),
}: {
  data: BarDatum[];
  mode?: "sequential" | "status";
  valueFormatter?: (value: number) => string;
}) {
  const [hovered, setHovered] = useState<string | null>(null);
  const maxValue = Math.max(1, ...data.map((d) => d.value));

  if (data.every((d) => d.value === 0)) {
    return <p className="py-6 text-center text-sm text-inksoft">No data recorded for this period.</p>;
  }

  return (
    <div className="space-y-2.5" role="img" aria-label="Bar chart">
      {data.map((d) => {
        const widthPct = (d.value / maxValue) * 100;
        const barClass = mode === "status" ? (TONE_BAR_CLASS[d.tone ?? "neutral"] ?? "bg-teal") : "bg-teal";
        const isHovered = hovered === d.key;

        return (
          <div
            key={d.key}
            className="group relative"
            style={{ height: BAR_HEIGHT, marginBottom: ROW_GAP - 2 }}
            onMouseEnter={() => setHovered(d.key)}
            onMouseLeave={() => setHovered(null)}
            onFocus={() => setHovered(d.key)}
            onBlur={() => setHovered(null)}
            tabIndex={0}
          >
            <div className="mb-1 flex items-baseline justify-between text-xs">
              <span className="truncate pr-2 text-inksoft">{d.label}</span>
              <span className="shrink-0 font-semibold text-ink">{valueFormatter(d.value)}</span>
            </div>
            <div className="h-1.5 w-full rounded-full bg-paper">
              <div
                className={`h-full rounded-full transition-all duration-150 ${barClass} ${isHovered ? "brightness-95" : ""}`}
                style={{ width: `${widthPct}%` }}
              />
            </div>
            {isHovered && (
              <div className="absolute -top-7 left-0 z-10 rounded-lg border border-line bg-white px-2 py-1 text-xs shadow-lg">
                <span className="font-semibold text-ink">{valueFormatter(d.value)}</span>
                <span className="ml-1 text-inksoft">{d.label}</span>
              </div>
            )}
          </div>
        );
      })}
    </div>
  );
}
