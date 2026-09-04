import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiClient } from "../../lib/api-client";
import type { Paginated, PayPeriod, Payslip } from "../../lib/types";

export function usePayPeriods(page: number) {
  return useQuery({
    queryKey: ["pay-periods", "list", page],
    queryFn: async () => {
      const { data } = await apiClient.get<Paginated<PayPeriod>>("/pay-periods", { params: { page } });
      return data;
    },
  });
}

export function usePayPeriod(id: number | null) {
  return useQuery({
    queryKey: ["pay-periods", id],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: PayPeriod }>(`/pay-periods/${id}`);
      return data.data;
    },
    enabled: Boolean(id),
  });
}

export interface CreatePayPeriodInput {
  start_date: string;
  end_date: string;
  notes?: string;
}

export function useCreatePayPeriod() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: CreatePayPeriodInput) => {
      const { data } = await apiClient.post<{ data: PayPeriod }>("/pay-periods", input);
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["pay-periods"] });
    },
  });
}

export function useGeneratePayslips(payPeriodId: number | null) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async () => {
      const { data } = await apiClient.post<{ data: PayPeriod }>(`/pay-periods/${payPeriodId}/generate-payslips`);
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["pay-periods"] });
    },
  });
}

// A payroll-role viewer's unfiltered index() call returns everyone's
// payslips — this always passes the viewer's own id so it self-limits
// regardless of role (the backend only auto-scopes to "own" for non-admins).
export function useMyPayslips(userId: number | undefined) {
  return useQuery({
    queryKey: ["payslips", "list", "mine", userId],
    queryFn: async () => {
      const { data } = await apiClient.get<Paginated<Payslip>>("/payslips", { params: { user_id: userId } });
      return data;
    },
    enabled: Boolean(userId),
  });
}

export function useUpdatePayslip(payslipId: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: { status?: string; deductions?: number }) => {
      const { data } = await apiClient.patch<{ data: Payslip }>(`/payslips/${payslipId}`, input);
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["pay-periods"] });
      void queryClient.invalidateQueries({ queryKey: ["payslips"] });
    },
  });
}
