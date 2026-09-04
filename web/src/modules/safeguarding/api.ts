import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiClient } from "../../lib/api-client";
import type { Paginated, SafeguardingCase, SafeguardingCaseStatus } from "../../lib/types";

export function useSafeguardingCases(status?: SafeguardingCaseStatus) {
  return useQuery({
    queryKey: ["safeguarding-cases", "list", status ?? "all"],
    queryFn: async () => {
      const { data } = await apiClient.get<Paginated<SafeguardingCase>>("/safeguarding-cases", {
        params: status ? { status } : undefined,
      });
      return data;
    },
  });
}

export interface CreateSafeguardingCaseInput {
  service_user_id?: number | null;
  victim_name?: string;
  alleged_perpetrator?: string;
  concern_type: string;
  immediate_risk: boolean;
  external_agencies_notified?: string;
  confidential_notes?: string;
}

export function useCreateSafeguardingCase() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: CreateSafeguardingCaseInput) => {
      const { data } = await apiClient.post<{ data: SafeguardingCase }>("/safeguarding-cases", input);
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["safeguarding-cases"] });
    },
  });
}

export interface UpdateSafeguardingCaseInput {
  status?: SafeguardingCaseStatus;
  investigation_notes?: string;
  actions_taken?: string;
  outcome?: string;
  external_agencies_notified?: string;
  confidential_notes?: string;
}

export function useUpdateSafeguardingCase(caseId: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: UpdateSafeguardingCaseInput) => {
      const { data } = await apiClient.patch<{ data: SafeguardingCase }>(
        `/safeguarding-cases/${caseId}`,
        input,
      );
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["safeguarding-cases"] });
    },
  });
}
