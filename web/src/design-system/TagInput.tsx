import { useState, type KeyboardEvent } from "react";
import { Select } from "./form/Select";
import { StatusBadge } from "./StatusBadge";

interface TagInputProps {
  id?: string;
  value: string[];
  onChange: (tags: string[]) => void;
  placeholder?: string;
  /** Common values offered in a dropdown above the input — picking one adds
   * it straight away. Free typing below still works exactly the same, so
   * anything not on the list is never blocked. */
  suggestions?: string[];
}

export function TagInput({ id, value, onChange, placeholder, suggestions }: TagInputProps) {
  const [draft, setDraft] = useState("");

  function addTag(tag: string) {
    if (tag && !value.includes(tag)) {
      onChange([...value, tag]);
    }
  }

  function commitDraft() {
    addTag(draft.trim());
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

  const remainingSuggestions = suggestions?.filter((s) => !value.includes(s)) ?? [];

  return (
    <div>
      {suggestions && (
        <Select
          value=""
          onChange={(e) => addTag(e.target.value)}
          className="mb-1.5"
          disabled={remainingSuggestions.length === 0}
        >
          <option value="">
            {remainingSuggestions.length > 0 ? "+ Add a common task…" : "All common tasks added"}
          </option>
          {remainingSuggestions.map((s) => (
            <option key={s} value={s}>
              {s}
            </option>
          ))}
        </Select>
      )}
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
    </div>
  );
}
