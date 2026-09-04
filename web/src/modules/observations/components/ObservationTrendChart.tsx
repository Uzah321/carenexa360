import { useMemo, useState } from "react";
import type { Observation, ObservationType } from "../../../lib/types";

// Chart tokens, following the dataviz skill's palette structure substituted
// with this app's teal/paper brand (see design-system/index.ts for the same
// teal token used across primary actions). Blood pressure is the only
// two-series case (systolic/diastolic), so it's the only chart that needs a
// second categorical slot + legend; every other observation type is a single
// series, which per the skill needs no legend box.
const SERIES_1 = "#0d9488"; // teal — systolic / the single-series line
const SERIES_2 = "#d97706"; // amber — diastolic
const GRIDLINE = "#e1e0d9";
const AXIS_TEXT = "#898781";
const SURFACE = "#ffffff";
const ALERT_CRITICAL = "#d03b3b";
const ALERT_WARNING = "#fab219";

interface Point {
  x: number;
  recordedAt: string;
  primary: number;
  secondary?: number;
  alertSeverity?: "warning" | "critical";
}

function extractSeries(observations: Observation[], type: ObservationType): Point[] {
  return observations
    .filter((o) => o.type === type)
    .map((o) => ({
      x: new Date(o.recorded_at).getTime(),
      recordedAt: o.recorded_at,
      primary: type === "blood_pressure" ? Number(o.value.systolic) : Number(o.value.value),
      secondary: type === "blood_pressure" ? Number(o.value.diastolic) : undefined,
      alertSeverity: o.alerts?.[0]?.severity,
    }))
    .filter((p) => Number.isFinite(p.primary))
    .sort((a, b) => a.x - b.x);
}

const WIDTH = 640;
const HEIGHT = 220;
const PADDING = { top: 16, right: 16, bottom: 24, left: 36 };

export function ObservationTrendChart({
  observations,
  type,
  unit,
}: {
  observations: Observation[];
  type: ObservationType;
  unit: string | null;
}) {
  const [hoverIndex, setHoverIndex] = useState<number | null>(null);

  const points = useMemo(() => extractSeries(observations, type), [observations, type]);
  const isBloodPressure = type === "blood_pressure";

  if (points.length < 2) {
    return (
      <p className="py-6 text-center text-sm text-inksoft">
        Record at least two readings of this type to see a trend.
      </p>
    );
  }

  const xMin = points[0].x;
  const xMax = points[points.length - 1].x;
  const allValues = points.flatMap((p) => [p.primary, ...(p.secondary !== undefined ? [p.secondary] : [])]);
  const yMin = Math.min(...allValues);
  const yMax = Math.max(...allValues);
  const yPad = (yMax - yMin || 1) * 0.15;
  const yDomainMin = yMin - yPad;
  const yDomainMax = yMax + yPad;

  const plotWidth = WIDTH - PADDING.left - PADDING.right;
  const plotHeight = HEIGHT - PADDING.top - PADDING.bottom;

  const scaleX = (x: number) =>
    PADDING.left + (xMax === xMin ? plotWidth / 2 : ((x - xMin) / (xMax - xMin)) * plotWidth);
  const scaleY = (y: number) =>
    PADDING.top + plotHeight - ((y - yDomainMin) / (yDomainMax - yDomainMin)) * plotHeight;

  const linePath = (key: "primary" | "secondary") =>
    points
      .filter((p) => p[key] !== undefined)
      .map((p, i) => `${i === 0 ? "M" : "L"} ${scaleX(p.x)} ${scaleY(p[key] as number)}`)
      .join(" ");

  const yTicks = 4;
  const gridValues = Array.from({ length: yTicks + 1 }, (_, i) => yDomainMin + ((yDomainMax - yDomainMin) / yTicks) * i);

  function handleMove(event: React.MouseEvent<SVGSVGElement>) {
    const rect = event.currentTarget.getBoundingClientRect();
    const relativeX = ((event.clientX - rect.left) / rect.width) * WIDTH;
    let nearest = 0;
    let nearestDistance = Infinity;
    points.forEach((p, i) => {
      const distance = Math.abs(scaleX(p.x) - relativeX);
      if (distance < nearestDistance) {
        nearestDistance = distance;
        nearest = i;
      }
    });
    setHoverIndex(nearest);
  }

  const hovered = hoverIndex !== null ? points[hoverIndex] : null;

  return (
    <div className="relative">
      {isBloodPressure && (
        <div className="mb-2 flex items-center gap-4 text-xs text-inksoft">
          <span className="flex items-center gap-1.5">
            <span className="inline-block h-2 w-2" style={{ background: SERIES_1 }} />
            Systolic
          </span>
          <span className="flex items-center gap-1.5">
            <span className="inline-block h-2 w-2" style={{ background: SERIES_2 }} />
            Diastolic
          </span>
        </div>
      )}
      <svg
        viewBox={`0 0 ${WIDTH} ${HEIGHT}`}
        className="w-full"
        onMouseMove={handleMove}
        onMouseLeave={() => setHoverIndex(null)}
      >
        {gridValues.map((value) => (
          <g key={value}>
            <line
              x1={PADDING.left}
              x2={WIDTH - PADDING.right}
              y1={scaleY(value)}
              y2={scaleY(value)}
              stroke={GRIDLINE}
              strokeWidth={1}
            />
            <text x={PADDING.left - 8} y={scaleY(value)} textAnchor="end" dominantBaseline="middle" fontSize={10} fill={AXIS_TEXT}>
              {Math.round(value)}
            </text>
          </g>
        ))}

        <path d={linePath("primary")} fill="none" stroke={SERIES_1} strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" />
        {isBloodPressure && (
          <path d={linePath("secondary")} fill="none" stroke={SERIES_2} strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" />
        )}

        {points.map((p, i) => (
          <g key={i}>
            <circle cx={scaleX(p.x)} cy={scaleY(p.primary)} r={4} fill={SERIES_1} stroke={SURFACE} strokeWidth={2} />
            {isBloodPressure && p.secondary !== undefined && (
              <circle cx={scaleX(p.x)} cy={scaleY(p.secondary)} r={4} fill={SERIES_2} stroke={SURFACE} strokeWidth={2} />
            )}
            {p.alertSeverity && (
              <circle
                cx={scaleX(p.x)}
                cy={scaleY(p.primary)}
                r={7}
                fill="none"
                stroke={p.alertSeverity === "critical" ? ALERT_CRITICAL : ALERT_WARNING}
                strokeWidth={2}
              />
            )}
          </g>
        ))}

        {hovered && (
          <line
            x1={scaleX(hovered.x)}
            x2={scaleX(hovered.x)}
            y1={PADDING.top}
            y2={HEIGHT - PADDING.bottom}
            stroke={AXIS_TEXT}
            strokeWidth={1}
            strokeDasharray="3 3"
          />
        )}
      </svg>

      {hovered && (
        <div
          className="pointer-events-none absolute top-0 rounded-lg border border-line bg-white px-2.5 py-1.5 text-xs text-ink shadow-md"
          style={{
            left: `${Math.min(85, Math.max(5, (scaleX(hovered.x) / WIDTH) * 100))}%`,
          }}
        >
          <div className="font-medium text-ink">{new Date(hovered.recordedAt).toLocaleString()}</div>
          {isBloodPressure ? (
            <div>
              {hovered.primary}/{hovered.secondary} mmHg
            </div>
          ) : (
            <div>
              {hovered.primary} {unit ?? ""}
            </div>
          )}
          {hovered.alertSeverity && (
            <div style={{ color: hovered.alertSeverity === "critical" ? ALERT_CRITICAL : "#92620a" }}>
              {hovered.alertSeverity} threshold breach
            </div>
          )}
        </div>
      )}
    </div>
  );
}
