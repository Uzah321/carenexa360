import { useQuery } from "@tanstack/react-query";
import { apiClient } from "../../lib/api-client";

export interface GeneratedReportColumn {
  key: string;
  label: string;
}

export interface GeneratedReport {
  key: string;
  title: string;
  generated_at: string;
  filters: { from: string; to: string; branch_id: number | null };
  columns: GeneratedReportColumn[];
  rows: Record<string, string | number>[];
}

export function useGenerateReport(reportKey: string | null, from: string, to: string, branchId: number | null) {
  return useQuery({
    queryKey: ["reports", "generate", reportKey, from, to, branchId],
    queryFn: async () => {
      const { data } = await apiClient.get<GeneratedReport>("/reports/generate", {
        params: { key: reportKey, from, to, branch_id: branchId ?? undefined },
      });
      return data;
    },
    enabled: Boolean(reportKey),
  });
}
