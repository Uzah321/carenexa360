import type { ReactNode } from "react";
import { EmptyState } from "./EmptyState";

export interface Column<T> {
  key: string;
  header: string;
  render: (row: T) => ReactNode;
  className?: string;
}

interface DataTableProps<T> {
  columns: Column<T>[];
  rows: T[];
  rowKey: (row: T) => string | number;
  isLoading?: boolean;
  emptyMessage?: string;
}

export function DataTable<T>({
  columns,
  rows,
  rowKey,
  isLoading,
  emptyMessage = "No records found.",
}: DataTableProps<T>) {
  // Defensive against a backend response that omits an expected relation
  // (e.g. Laravel's whenLoaded() silently drops the key when a controller
  // forgets to eager-load it) — rows should always be an array per the type,
  // but a server-side gap shouldn't crash the whole page.
  const safeRows = rows ?? [];

  if (!isLoading && safeRows.length === 0) {
    return <EmptyState message={emptyMessage} />;
  }

  return (
    <div className="overflow-x-auto rounded-2xl border border-line bg-white">
      <table className="min-w-full divide-y divide-line">
        <thead className="bg-paper">
          <tr>
            {columns.map((col) => (
              <th
                key={col.key}
                className="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-inksoft"
              >
                {col.header}
              </th>
            ))}
          </tr>
        </thead>
        <tbody className="divide-y divide-line bg-white">
          {isLoading
            ? Array.from({ length: 3 }).map((_, i) => (
                <tr key={`skeleton-${i}`}>
                  {columns.map((col) => (
                    <td key={col.key} className="px-4 py-3">
                      <div className="h-4 w-24 animate-pulse rounded-full bg-line" />
                    </td>
                  ))}
                </tr>
              ))
            : safeRows.map((row) => (
                <tr key={rowKey(row)} className="transition-colors duration-150 hover:bg-paper">
                  {columns.map((col) => (
                    <td key={col.key} className={`px-4 py-3 text-sm text-ink ${col.className ?? ""}`}>
                      {col.render(row)}
                    </td>
                  ))}
                </tr>
              ))}
        </tbody>
      </table>
    </div>
  );
}
