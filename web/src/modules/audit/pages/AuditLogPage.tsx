import { useState } from "react";
import { DataTable, PageHeader, Pagination, type Column } from "../../../design-system";
import { useAuditLog } from "../api";
import type { AuditLogEntry } from "../../../lib/types";

function formatEntity(type: string) {
  const parts = type.split("\\");
  return parts[parts.length - 1];
}

export function AuditLogPage() {
  const [page, setPage] = useState(1);
  const { data, isLoading } = useAuditLog(page);

  const columns: Column<AuditLogEntry>[] = [
    {
      key: "created_at",
      header: "When",
      render: (row) => new Date(row.created_at).toLocaleString(),
    },
    { key: "user_name", header: "Who", render: (row) => row.user_name ?? "System" },
    { key: "action", header: "Action", render: (row) => row.action },
    {
      key: "entity",
      header: "Record",
      render: (row) => `${formatEntity(row.auditable_type)} #${row.auditable_id}`,
    },
    { key: "ip_address", header: "IP", render: (row) => row.ip_address ?? "—" },
  ];

  return (
    <div>
      <PageHeader title="Audit Log" description="Every important action taken on this tenant." />
      <DataTable columns={columns} rows={data?.data ?? []} rowKey={(row) => row.id} isLoading={isLoading} />
      {data && (
        <Pagination
          currentPage={data.meta.current_page}
          lastPage={data.meta.last_page}
          onPageChange={setPage}
        />
      )}
    </div>
  );
}
