import type { ReactNode } from "react";

export function FilterBar({ children }: { children: ReactNode }) {
  return (
    <div className="mb-4 flex flex-wrap items-center gap-3 rounded-2xl border border-line bg-white p-3">
      {children}
    </div>
  );
}
