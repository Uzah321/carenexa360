import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiClient } from "../../lib/api-client";
import type { LeaveRequest, LeaveStatus, LeaveType, Paginated } from "../../lib/types";

export function useLeaveRequests(filters: { user_id?: number; status?: LeaveStatus } = {}) {
  return useQuery({
    queryKey: ["leave-requests", "list", filters],
    queryFn: async () => {
      const { data } = await apiClient.get<Paginated<LeaveRequest>>("/leave-requests", { params: filters });
      return data;
    },
  });
}

export interface CreateLeaveRequestInput {
  type: LeaveType;
  start_date: string;
  end_date: string;
  reason?: string;
}

export function useCreateLeaveRequest() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: CreateLeaveRequestInput) => {
      const { data } = await apiClient.post<{ data: LeaveRequest }>("/leave-requests", input);
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["leave-requests"] });
    },
  });
}

export function useUpdateLeaveRequest() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, status, notes }: { id: number; status: LeaveStatus; notes?: string }) => {
      const { data } = await apiClient.patch<{ data: LeaveRequest }>(`/leave-requests/${id}`, {
        status,
        notes,
      });
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["leave-requests"] });
    },
  });
}
