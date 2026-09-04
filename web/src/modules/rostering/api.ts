import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiClient } from "../../lib/api-client";
import type { Paginated, Shift, ShiftType } from "../../lib/types";

export function useShifts(filters: { page?: number; user_id?: number; date?: string }) {
  return useQuery({
    queryKey: ["shifts", "list", filters],
    queryFn: async () => {
      const { data } = await apiClient.get<Paginated<Shift>>("/shifts", { params: filters });
      return data;
    },
  });
}

export interface CreateShiftInput {
  user_id: number;
  branch_id?: number | null;
  shift_date: string;
  start_time: string;
  end_time: string;
  shift_type?: ShiftType;
  notes?: string;
}

export function useCreateShift() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: CreateShiftInput) => {
      const { data } = await apiClient.post<{ data: Shift }>("/shifts", input);
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["shifts"] });
    },
  });
}
