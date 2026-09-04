import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiClient } from "../../lib/api-client";
import type { CarePlan, CarePlanArea, CarePlanRiskLevel } from "../../lib/types";

export function useCarePlans(serviceUserId: number) {
  return useQuery({
    queryKey: ["service-users", serviceUserId, "care-plans"],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: CarePlan[] }>(
        `/service-users/${serviceUserId}/care-plans`,
      );
      return data.data;
    },
    enabled: Boolean(serviceUserId),
  });
}

export function useCarePlan(id: number | null) {
  return useQuery({
    queryKey: ["care-plans", id],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: CarePlan }>(`/care-plans/${id}`);
      return data.data;
    },
    enabled: Boolean(id),
  });
}

export interface CarePlanSectionInput {
  area: CarePlanArea;
  identified_need: string;
  risk?: CarePlanRiskLevel | "";
  goal: string;
  intervention: string;
  equipment?: string;
  frequency?: string;
  responsible_staff_id?: number | null;
  start_date?: string;
  review_date?: string;
  status?: string;
  notes?: string;
}

export interface CreateCarePlanInput {
  effective_from: string;
  notes?: string;
  sections: CarePlanSectionInput[];
}

export function useCreateCarePlan(serviceUserId: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: CreateCarePlanInput) => {
      const { data } = await apiClient.post<{ data: CarePlan }>(
        `/service-users/${serviceUserId}/care-plans`,
        input,
      );
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["service-users", serviceUserId, "care-plans"] });
    },
  });
}
