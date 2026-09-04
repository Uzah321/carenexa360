import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiClient } from "../../lib/api-client";
import type { Funder, FunderType, Invoice, InvoiceStatus, Paginated } from "../../lib/types";

export function useFunders() {
  return useQuery({
    queryKey: ["funders", "list"],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: Funder[] }>("/funders");
      return data.data;
    },
  });
}

export interface CreateFunderInput {
  name: string;
  type: FunderType;
  contact_name?: string;
  phone?: string;
  email?: string;
  address?: string;
  default_hourly_rate?: number;
  notes?: string;
}

export function useCreateFunder() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: CreateFunderInput) => {
      const { data } = await apiClient.post<{ data: Funder }>("/funders", input);
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["funders"] });
    },
  });
}

export interface InvoiceFilters {
  service_user_id?: number;
  funder_id?: number;
  status?: InvoiceStatus;
  page?: number;
}

export function useInvoices(filters: InvoiceFilters = {}) {
  return useQuery({
    queryKey: ["invoices", "list", filters],
    queryFn: async () => {
      const { data } = await apiClient.get<Paginated<Invoice>>("/invoices", { params: filters });
      return data;
    },
  });
}

export interface GenerateInvoiceInput {
  service_user_id: number;
  funder_id?: number | null;
  period_start: string;
  period_end: string;
  hourly_rate: number;
  due_date?: string;
  notes?: string;
}

export function useGenerateInvoice() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: GenerateInvoiceInput) => {
      const { data } = await apiClient.post<{ data: Invoice }>("/invoices/generate", input);
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["invoices"] });
    },
  });
}

export function useUpdateInvoice(invoiceId: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: { status?: InvoiceStatus; due_date?: string; notes?: string }) => {
      const { data } = await apiClient.patch<{ data: Invoice }>(`/invoices/${invoiceId}`, input);
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["invoices"] });
    },
  });
}
