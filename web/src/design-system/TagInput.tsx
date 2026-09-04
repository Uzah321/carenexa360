import { useState, type KeyboardEvent } from "react";
import { StatusBadge } from "./StatusBadge";

interface TagInputProps {
  id?: string;
  value: string[];
  onChange: (tags: string[]) => void;
  placeholder?: string;
}

export function TagInput({ id, value, onChange, placeholder }: TagInputProps) {
  const [draft, setDraft] = useState("");

  function commitDraft() {
    const tag = draft.trim();
    if (tag && !value.includes(tag)) {
      onChange([...value, tag]);
    }
    setDraft("");
  }

  function handleKeyDown(event: KeyboardEvent<HTMLInputElement>) {
    if (event.key === "Enter" || event.key === ",") {
      event.preventDefault();
      commitDraft();
    } else if (event.key === "Backspace" && draft === "" && value.length > 0) {
      onChange(value.slice(0, -1));
    }
  }

  return (
    <div className="flex flex-wrap items-center gap-1.5 rounded-lg border border-line px-2 py-1.5 transition-colors duration-150 focus-within:border-teal focus-within:ring-1 focus-within:ring-teal">
      {value.map((tag) => (
        <span key={tag} className="inline-flex items-center gap-1">
          <StatusBadge label={tag} tone="info" />
          <button
            type="button"
            className="text-inksoft hover:text-ink"
            onClick={() => onChange(value.filter((t) => t !== tag))}
            aria-label={`Remove ${tag}`}
          >
            ✕
          </button>
        </span>
      ))}
      <input
        id={id}
        type="text"
        value={draft}
        onChange={(e) => setDraft(e.target.value)}
        onKeyDown={handleKeyDown}
        onBlur={commitDraft}
        placeholder={value.length === 0 ? placeholder : undefined}
        className="min-w-[8rem] flex-1 border-0 py-0.5 text-sm outline-none focus:ring-0"
      />
    </div>
  );
}
