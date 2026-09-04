import { useQuery } from "@tanstack/react-query";
import { apiClient } from "../../lib/api-client";
import type { AuditLogEntry, Paginated } from "../../lib/types";

export function useAuditLog(page: number) {
  return useQuery({
    queryKey: ["audit-log", page],
    queryFn: async () => {
      const { data } = await apiClient.get<Paginated<AuditLogEntry>>("/audit-log", {
        params: { page },
      });
      return data;
    },
  });
}
