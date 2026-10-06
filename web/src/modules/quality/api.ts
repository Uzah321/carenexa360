import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiClient } from "../../lib/api-client";
import type { Complaint, ComplaintStatus, Paginated, SpotCheck, SpotCheckOutcome } from "../../lib/types";

export type ComplaintInput = Partial<Omit<Complaint, "id" | "service_user_name" | "assigned_to_name" | "is_overdue" | "created_at">>;
export type SpotCheckInput = Partial<Pick<SpotCheck, "staff_user_id" | "service_user_id" | "check_date" | "results" | "notes" | "actions_required" | "follow_up_date">>;

export function useComplaints(filters: { status?: ComplaintStatus; page?: number }) {
  return useQuery({
    queryKey: ["complaints", "list", filters],
    queryFn: async () => (await apiClient.get<Paginated<Complaint>>("/complaints", { params: filters })).data,
  });
}

export function useSaveComplaint() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, ...input }: ComplaintInput & { id?: number }) => {
      const { data } = id
        ? await apiClient.patch<{ data: Complaint }>(`/complaints/${id}`, input)
        : await apiClient.post<{ data: Complaint }>("/complaints", input);
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["complaints"] });
    },
  });
}

export function useSpotChecks(filters: { outcome?: SpotCheckOutcome; page?: number }) {
  return useQuery({
    queryKey: ["spot-checks", "list", filters],
    queryFn: async () => (await apiClient.get<Paginated<SpotCheck>>("/spot-checks", { params: filters })).data,
  });
}

export function useSaveSpotCheck() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, ...input }: SpotCheckInput & { id?: number }) => {
      const { data } = id
        ? await apiClient.patch<{ data: SpotCheck }>(`/spot-checks/${id}`, input)
        : await apiClient.post<{ data: SpotCheck }>("/spot-checks", input);
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["spot-checks"] });
    },
  });
}

export const label = (value: string | null | undefined) => (value ? value.charAt(0).toUpperCase() + value.slice(1).replaceAll("_", " ") : "—");
