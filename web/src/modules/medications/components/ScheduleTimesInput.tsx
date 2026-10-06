import { X } from "lucide-react";
import { Input } from "../../../design-system";

interface ScheduleTimesInputProps {
  id: string;
  value: string[];
  onChange: (times: string[]) => void;
}

/** The times of day a dose is due ("08:00", "19:30"), kept sorted. */
export function ScheduleTimesInput({ id, value, onChange }: ScheduleTimesInputProps) {
  const update = (times: string[]) => onChange([...times].sort());

  return (
    <div className="space-y-2">
      {value.map((time, index) => (
        <div key={index} className="flex items-center gap-2">
          <Input
            id={index === 0 ? id : `${id}-${index}`}
            type="time"
            required
            value={time}
            onChange={(e) => onChange(value.map((t, i) => (i === index ? e.target.value : t)))}
            onBlur={() => update(value)}
            aria-label={`Dose time ${index + 1}`}
          />
          <button
            type="button"
            className="rounded p-1 text-inksoft hover:text-coral"
            onClick={() => update(value.filter((_, i) => i !== index))}
            aria-label={`Remove dose time ${time || index + 1}`}
          >
            <X className="h-4 w-4" />
          </button>
        </div>
      ))}
      <button
        type="button"
        className="text-sm font-medium text-teal hover:text-teal/90"
        onClick={() => onChange([...value, ""])}
      >
        + Add dose time
      </button>
    </div>
  );
}
