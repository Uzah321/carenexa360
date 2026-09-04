export type StatTileTone = "amber" | "coral" | "sky" | "lime" | "teal" | "plum";

const TONE_CLASSES: Record<StatTileTone, string> = {
  amber: "bg-ambertint text-amber",
  coral: "bg-coraltint text-coral",
  sky: "bg-skytint text-sky",
  lime: "bg-limetint text-lime",
  teal: "bg-tealtint text-teal",
  plum: "bg-plum/10 text-plum",
};

export function StatTile({
  label,
  value,
  description,
  tone,
}: {
  label: string;
  value: string;
  description?: string;
  tone: StatTileTone;
}) {
  return (
    <div className={`rounded-2xl p-4 ${TONE_CLASSES[tone]}`}>
      <div className="text-xs font-semibold uppercase tracking-wide">{label}</div>
      <div className="mt-1 text-3xl font-extrabold">{value}</div>
      {description && <div className="mt-1 text-xs opacity-80">{description}</div>}
    </div>
  );
}
