import type { ReactNode } from "react";

type Tone = "success" | "warning" | "danger" | "info";

interface AlertProps {
  tone?: Tone;
  title?: string;
  children: ReactNode;
}

const TONE_CLASSES: Record<Tone, string> = {
  success: "bg-limetint text-lime ring-lime/25",
  warning: "bg-ambertint text-amber ring-amber/25",
  danger: "bg-coraltint text-coral ring-coral/25",
  info: "bg-skytint text-sky ring-sky/25",
};

export function Alert({ tone = "info", title, children }: AlertProps) {
  return (
    <div className={`rounded-xl p-4 text-sm ring-1 ring-inset ${TONE_CLASSES[tone]}`}>
      {title && <p className="mb-1 font-medium">{title}</p>}
      <div>{children}</div>
    </div>
  );
}
