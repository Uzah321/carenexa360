type Tone = "success" | "warning" | "danger" | "neutral" | "info";

interface StatusBadgeProps {
  label: string;
  tone?: Tone;
}

const TONE_CLASSES: Record<Tone, string> = {
  success: "bg-limetint text-lime ring-lime/25",
  warning: "bg-ambertint text-amber ring-amber/25",
  danger: "bg-coraltint text-coral ring-coral/25",
  neutral: "bg-paper text-inksoft ring-line",
  info: "bg-skytint text-sky ring-sky/25",
};

export function StatusBadge({ label, tone = "neutral" }: StatusBadgeProps) {
  return (
    <span
      className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset ${TONE_CLASSES[tone]}`}
    >
      {label}
    </span>
  );
}
