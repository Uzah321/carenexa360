import type { ReactNode } from "react";

interface DrawerProps {
  isOpen: boolean;
  onClose: () => void;
  title: string;
  children: ReactNode;
}

export function Drawer({ isOpen, onClose, title, children }: DrawerProps) {
  if (!isOpen) return null;

  return (
    <div className="animate-fade-in fixed inset-0 z-50 flex justify-end bg-black/40">
      <div className="animate-slide-in-right h-full w-full max-w-md overflow-y-auto border-l border-line bg-white shadow-xl">
        <div className="flex items-center justify-between border-b border-line px-5 py-3">
          <h2 className="font-display text-base font-semibold text-ink">{title}</h2>
          <button
            type="button"
            onClick={onClose}
            aria-label="Close"
            className="text-inksoft transition-colors duration-150 hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal"
          >
            ✕
          </button>
        </div>
        <div className="px-5 py-4">{children}</div>
      </div>
    </div>
  );
}
