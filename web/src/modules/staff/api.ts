import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiClient } from "../../lib/api-client";
import type { Paginated, StaffMember } from "../../lib/types";

export function useStaff(page: number, perPage?: number) {
  return useQuery({
    queryKey: ["staff", "list", page, perPage],
    queryFn: async () => {
      const { data } = await apiClient.get<Paginated<StaffMember>>("/staff", {
        params: { page, per_page: perPage },
      });
      return data;
    },
  });
}

export interface CreateStaffInput {
  name: string;
  email: string;
  password: string;
  role: string;
  branch_id?: number | null;
  employee_number?: string;
  job_title?: string;
  employment_start_date?: string;
  skills?: string[];
}

export function useCreateStaff() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: CreateStaffInput) => {
      const { data } = await apiClient.post<{ data: StaffMember }>("/staff", input);
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["staff"] });
    },
  });
}

export interface UpdateStaffInput {
  branch_id?: number | null;
  employee_number?: string | null;
  job_title?: string | null;
  employment_start_date?: string | null;
  skills?: string[];
  employment_status?: StaffMember["employment_status"];
  hourly_rate?: number | null;
}

export function useUpdateStaffStatus() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, employment_status }: { id: number; employment_status: StaffMember["employment_status"] }) => {
      const { data } = await apiClient.patch<{ data: StaffMember }>(`/staff/${id}`, { employment_status });
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["staff"] });
    },
  });
}

export function useUpdateStaff(staffId: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: UpdateStaffInput) => {
      const { data } = await apiClient.patch<{ data: StaffMember }>(`/staff/${staffId}`, input);
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["staff"] });
    },
  });
}
