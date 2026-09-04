import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiClient } from "../../lib/api-client";
import type { Incident, IncidentSeverity, IncidentStatus, IncidentType, Paginated } from "../../lib/types";

export interface IncidentFilters {
  service_user_id?: number;
  status?: IncidentStatus;
  severity?: IncidentSeverity;
  page?: number;
}

export function useIncidents(filters: IncidentFilters = {}) {
  return useQuery({
    queryKey: ["incidents", "list", filters],
    queryFn: async () => {
      const { data } = await apiClient.get<Paginated<Incident>>("/incidents", { params: filters });
      return data;
    },
  });
}

export interface CreateIncidentInput {
  service_user_id?: number | null;
  type: IncidentType;
  severity: IncidentSeverity;
  description: string;
  immediate_action?: string;
  assigned_to?: number | null;
}

export function useCreateIncident() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: CreateIncidentInput) => {
      const { data } = await apiClient.post<{ data: Incident }>("/incidents", input);
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["incidents"] });
    },
  });
}

export interface UpdateIncidentInput {
  type?: IncidentType;
  severity?: IncidentSeverity;
  description?: string;
  immediate_action?: string;
  status?: IncidentStatus;
  assigned_to?: number | null;
  investigation_notes?: string;
  corrective_actions?: string;
}

export function useUpdateIncidentStatus() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, status }: { id: number; status: IncidentStatus }) => {
      const { data } = await apiClient.patch<{ data: Incident }>(`/incidents/${id}`, { status });
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["incidents"] });
    },
  });
}

export function useUpdateIncident(incidentId: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: UpdateIncidentInput) => {
      const { data } = await apiClient.patch<{ data: Incident }>(`/incidents/${incidentId}`, input);
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["incidents"] });
    },
  });
}
