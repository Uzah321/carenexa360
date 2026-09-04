import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiClient } from "../../lib/api-client";
import type { Paginated, RouteStop, Visit, VisitPriority } from "../../lib/types";

export interface VisitFilters {
  page?: number;
  per_page?: number;
  carer_id?: number;
  service_user_id?: number;
  date?: string;
  status?: string;
}

export function useVisits(filters: VisitFilters) {
  return useQuery({
    queryKey: ["visits", "list", filters],
    queryFn: async () => {
      const { data } = await apiClient.get<Paginated<Visit>>("/visits", { params: filters });
      return data;
    },
  });
}

export function useVisit(id: number | null) {
  return useQuery({
    queryKey: ["visits", id],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: Visit }>(`/visits/${id}`);
      return data.data;
    },
    enabled: Boolean(id),
  });
}

export interface CreateVisitInput {
  service_user_id: number;
  carer_id?: number | null;
  visit_date: string;
  start_time: string;
  end_time: string;
  care_tasks?: string[];
  medication_tasks?: boolean;
  required_skills?: string[];
  priority?: VisitPriority;
  notes?: string;
  recurrence?: {
    weekdays: number[];
    until: string;
  };
}

interface MutationResult<T> {
  data: T;
  warnings: string[];
}

export function useCreateVisit() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: CreateVisitInput) => {
      const { data } = await apiClient.post<MutationResult<Visit | Visit[]>>("/visits", input);
      return data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["visits"] });
    },
  });
}

export interface UpdateVisitInput {
  carer_id?: number | null;
  visit_date?: string;
  start_time?: string;
  end_time?: string;
  care_tasks?: string[];
  completed_care_tasks?: string[];
  medication_tasks?: boolean;
  medication_tasks_completed?: boolean;
  required_skills?: string[];
  priority?: VisitPriority;
  status?: string;
  notes?: string;
}

export function useUpdateVisit(visitId: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: UpdateVisitInput) => {
      const { data } = await apiClient.patch<{ data: Visit }>(`/visits/${visitId}`, input);
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["visits"] });
    },
  });
}

export function useUpdateVisitStatus() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, status }: { id: number; status: string }) => {
      const { data } = await apiClient.patch<{ data: Visit }>(`/visits/${id}`, { status });
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["visits"] });
    },
  });
}

export function useRescheduleVisit() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({
      id,
      ...input
    }: {
      id: number;
      carer_id?: number | null;
      start_time?: string;
      end_time?: string;
    }) => {
      const { data } = await apiClient.patch<{ data: Visit }>(`/visits/${id}`, input);
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["visits"] });
    },
  });
}

export function useCheckIn(visitId: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: { latitude: number; longitude: number; accuracy?: number; override_reason?: string }) => {
      const { data } = await apiClient.post<{ data: Visit }>(`/visits/${visitId}/check-in`, input);
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["visits"] });
    },
  });
}

export function useCheckOut(visitId: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: { latitude: number; longitude: number; accuracy?: number; override_reason?: string }) => {
      const { data } = await apiClient.post<{ data: Visit }>(`/visits/${visitId}/check-out`, input);
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["visits"] });
    },
  });
}

export function useRoute(carerId: number | null, date: string) {
  return useQuery({
    queryKey: ["visits", "route", carerId, date],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: Visit[]; stops: RouteStop[] }>("/visits/route", {
        params: { carer_id: carerId, date },
      });
      return data;
    },
    enabled: Boolean(carerId && date),
  });
}
