import { useQuery } from "@tanstack/react-query";
import { apiClient } from "../../lib/api-client";
import type { AuditLogDetail, AuditLogEntry, Paginated } from "../../lib/types";

export interface AuditLogFilters {
  page?: number;
  action?: string;
  record_type?: string;
  user_id?: number;
  from?: string;
  to?: string;
}

export function useAuditLog(filters: AuditLogFilters) {
  return useQuery({
    queryKey: ["audit-log", filters],
    queryFn: async () => {
      const params = Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== "" && v !== undefined));
      const { data } = await apiClient.get<Paginated<AuditLogEntry>>("/audit-log", { params });
      return data;
    },
  });
}

export function useAuditLogEntry(id: number | null) {
  return useQuery({
    queryKey: ["audit-log", "entry", id],
    queryFn: async () => (await apiClient.get<{ data: AuditLogDetail }>(`/audit-log/${id}`)).data.data,
    enabled: Boolean(id),
  });
}

export function useAuditRecordTypes() {
  return useQuery({
    queryKey: ["audit-log", "record-types"],
    queryFn: async () => (await apiClient.get<{ data: { value: string; label: string }[] }>("/audit-log/record-types")).data.data,
    staleTime: Infinity,
  });
}
