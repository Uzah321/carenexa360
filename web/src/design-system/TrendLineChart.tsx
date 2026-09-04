import { useState } from "react";

export interface TrendPoint {
  label: string;
  value: number | null;
}

const WIDTH = 560;
const HEIGHT = 160;
const PADDING = { top: 12, right: 12, bottom: 24, left: 12 };

const TONE_STROKE: Record<string, string> = {
  teal: "var(--teal)",
  sky: "var(--sky)",
  coral: "var(--coral)",
  lime: "var(--lime)",
  amber: "var(--amber)",
};

export function TrendLineChart({
  data,
  tone = "teal",
  valueFormatter = (v: number) => v.toLocaleString(),
}: {
  data: TrendPoint[];
  tone?: "teal" | "sky" | "coral" | "lime" | "amber";
  valueFormatter?: (value: number) => string;
}) {
  const [hoverIndex, setHoverIndex] = useState<number | null>(null);
  const values = data.map((d) => d.value).filter((v): v is number => v !== null);

  if (values.length === 0) {
    return <p className="py-6 text-center text-sm text-inksoft">No data recorded for this period.</p>;
  }

  const maxValue = Math.max(1, ...values);
  const minValue = Math.min(0, ...values);
  const range = maxValue - minValue || 1;
  const innerWidth = WIDTH - PADDING.left - PADDING.right;
  const innerHeight = HEIGHT - PADDING.top - PADDING.bottom;
  const stepX = data.length > 1 ? innerWidth / (data.length - 1) : 0;
  const stroke = TONE_STROKE[tone];

  const points = data.map((d, i) => ({
    x: PADDING.left + i * stepX,
    y: d.value === null ? null : PADDING.top + innerHeight - ((d.value - minValue) / range) * innerHeight,
    value: d.value,
    label: d.label,
  }));

  const linePath = points
    .filter((p) => p.y !== null)
    .map((p, i) => `${i === 0 ? "M" : "L"} ${p.x} ${p.y}`)
    .join(" ");

  const areaPath =
    points.filter((p) => p.y !== null).length > 0
      ? `${linePath} L ${points[points.length - 1].x} ${PADDING.top + innerHeight} L ${points[0].x} ${PADDING.top + innerHeight} Z`
      : "";

  const hovered = hoverIndex !== null ? points[hoverIndex] : null;

  return (
    <div className="relative">
      <svg
        viewBox={`0 0 ${WIDTH} ${HEIGHT}`}
        className="w-full"
        style={{ height: HEIGHT }}
        onMouseLeave={() => setHoverIndex(null)}
      >
        <line
          x1={PADDING.left}
          y1={PADDING.top + innerHeight}
          x2={WIDTH - PADDING.right}
          y2={PADDING.top + innerHeight}
          stroke="var(--line)"
          strokeWidth={1}
        />
        {areaPath && <path d={areaPath} fill={stroke} opacity={0.1} />}
        {linePath && <path d={linePath} fill="none" stroke={stroke} strokeWidth={2} strokeLinejoin="round" strokeLinecap="round" />}
        {points.map(
          (p, i) =>
            p.y !== null && (
              <circle
                key={i}
                cx={p.x}
                cy={p.y}
                r={hoverIndex === i ? 5 : 4}
                fill="white"
                stroke={stroke}
                strokeWidth={2}
              />
            ),
        )}
        {points.map((p, i) => (
          <rect
            key={`hit-${i}`}
            x={p.x - stepX / 2}
            y={0}
            width={stepX || WIDTH}
            height={HEIGHT}
            fill="transparent"
            tabIndex={0}
            onMouseEnter={() => setHoverIndex(i)}
            onFocus={() => setHoverIndex(i)}
          />
        ))}
        {points
          .filter((_, i) => i === 0 || i === points.length - 1)
          .map((p, i) => (
            <text
              key={i}
              x={p.x}
              y={HEIGHT - 6}
              textAnchor={i === 0 ? "start" : "end"}
              className="fill-inksoft text-[10px]"
            >
              {p.label}
            </text>
          ))}
      </svg>
      {hovered && hovered.value !== null && (
        <div
          className="pointer-events-none absolute -translate-x-1/2 -translate-y-full rounded-lg border border-line bg-white px-2 py-1 text-xs shadow-lg"
          style={{ left: `${(hovered.x / WIDTH) * 100}%`, top: `${(hovered.y! / HEIGHT) * 100 - 2}%` }}
        >
          <span className="font-semibold text-ink">{valueFormatter(hovered.value)}</span>
          <span className="ml-1 text-inksoft">{hovered.label}</span>
        </div>
      )}
    </div>
  );
}
