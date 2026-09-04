import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiClient } from "../../lib/api-client";
import type { ClinicalAlert, Observation, ObservationType } from "../../lib/types";

export function useObservations(serviceUserId: number, type?: ObservationType) {
  return useQuery({
    queryKey: ["service-users", serviceUserId, "observations", type ?? "all"],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: Observation[] }>(
        `/service-users/${serviceUserId}/observations`,
        { params: type ? { type } : undefined },
      );
      return data.data;
    },
    enabled: Boolean(serviceUserId),
  });
}

export interface CreateObservationInput {
  type: ObservationType;
  value: Record<string, number | string>;
  unit?: string;
  recorded_at?: string;
  notes?: string;
}

export function useCreateObservation(serviceUserId: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: CreateObservationInput) => {
      const { data } = await apiClient.post<{ data: Observation }>(
        `/service-users/${serviceUserId}/observations`,
        input,
      );
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["service-users", serviceUserId, "observations"] });
      void queryClient.invalidateQueries({ queryKey: ["service-users", serviceUserId, "clinical-alerts"] });
    },
  });
}

export function useClinicalAlerts(serviceUserId: number) {
  return useQuery({
    queryKey: ["service-users", serviceUserId, "clinical-alerts"],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: ClinicalAlert[] }>(
        `/service-users/${serviceUserId}/clinical-alerts`,
      );
      return data.data;
    },
    enabled: Boolean(serviceUserId),
  });
}

export function useAcknowledgeAlert(serviceUserId: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (alertId: number) => {
      const { data } = await apiClient.post<{ data: ClinicalAlert }>(`/clinical-alerts/${alertId}/acknowledge`);
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["service-users", serviceUserId, "clinical-alerts"] });
    },
  });
}
