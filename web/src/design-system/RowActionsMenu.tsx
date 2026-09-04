import { MoreVertical } from "lucide-react";
import { useEffect, useRef, useState } from "react";

export interface RowAction {
  label: string;
  onClick: () => void;
  tone?: "default" | "danger";
  disabled?: boolean;
  hidden?: boolean;
}

export function RowActionsMenu({ actions, label = "Actions" }: { actions: RowAction[]; label?: string }) {
  const [isOpen, setIsOpen] = useState(false);
  const containerRef = useRef<HTMLDivElement>(null);
  const visibleActions = actions.filter((action) => !action.hidden);

  useEffect(() => {
    if (!isOpen) return;

    function handlePointerDown(event: PointerEvent) {
      if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    }
    function handleKeyDown(event: KeyboardEvent) {
      if (event.key === "Escape") setIsOpen(false);
    }

    document.addEventListener("pointerdown", handlePointerDown);
    document.addEventListener("keydown", handleKeyDown);
    return () => {
      document.removeEventListener("pointerdown", handlePointerDown);
      document.removeEventListener("keydown", handleKeyDown);
    };
  }, [isOpen]);

  if (visibleActions.length === 0) {
    return null;
  }

  return (
    <div ref={containerRef} className="relative inline-block text-left">
      <button
        type="button"
        aria-label={label}
        aria-haspopup="menu"
        aria-expanded={isOpen}
        onClick={() => setIsOpen((prev) => !prev)}
        className="flex h-8 w-8 items-center justify-center rounded-full text-inksoft transition-colors duration-150 hover:bg-paper hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal"
      >
        <MoreVertical className="h-4 w-4" aria-hidden />
      </button>

      {isOpen && (
        <div
          role="menu"
          className="animate-fade-in absolute right-0 z-20 mt-1 w-48 overflow-hidden rounded-xl border border-line bg-white py-1 shadow-lg"
        >
          {visibleActions.map((action) => (
            <button
              key={action.label}
              type="button"
              role="menuitem"
              disabled={action.disabled}
              onClick={() => {
                setIsOpen(false);
                action.onClick();
              }}
              className={`block w-full px-3 py-2 text-left text-sm transition-colors duration-150 disabled:cursor-not-allowed disabled:opacity-50 ${
                action.tone === "danger" ? "text-coral hover:bg-coraltint" : "text-ink hover:bg-paper"
              }`}
            >
              {action.label}
            </button>
          ))}
        </div>
      )}
    </div>
  );
}
