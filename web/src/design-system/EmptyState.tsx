import type { ReactNode } from "react";

interface EmptyStateProps {
  message: string;
  action?: ReactNode;
}

export function EmptyState({ message, action }: EmptyStateProps) {
  return (
    <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-line bg-white py-12 text-center">
      <p className="text-sm text-inksoft">{message}</p>
      {action && <div className="mt-3">{action}</div>}
    </div>
  );
}
