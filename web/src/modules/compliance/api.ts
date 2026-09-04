import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiClient } from "../../lib/api-client";
import type { CareDocument, ComplianceRequirement, ComplianceRequirementStatus } from "../../lib/types";

export function useComplianceRequirements(filters: { status?: ComplianceRequirementStatus } = {}) {
  return useQuery({
    queryKey: ["compliance-requirements", "list", filters],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: ComplianceRequirement[] }>("/compliance-requirements", {
        params: filters,
      });
      return data.data;
    },
  });
}

export interface CreateComplianceRequirementInput {
  name: string;
  category?: string;
  jurisdiction?: string;
  status?: ComplianceRequirementStatus;
  issued_date?: string;
  renewal_date?: string;
  reference_number?: string;
  responsible_user_id?: number | null;
  notes?: string;
}

export function useCreateComplianceRequirement() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: CreateComplianceRequirementInput) => {
      const { data } = await apiClient.post<{ data: ComplianceRequirement }>("/compliance-requirements", input);
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["compliance-requirements"] });
    },
  });
}

export function useUpdateComplianceRequirement(id: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: Partial<CreateComplianceRequirementInput>) => {
      const { data } = await apiClient.patch<{ data: ComplianceRequirement }>(`/compliance-requirements/${id}`, input);
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["compliance-requirements"] });
    },
  });
}

export function useDeleteComplianceRequirement() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id: number) => {
      await apiClient.delete(`/compliance-requirements/${id}`);
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["compliance-requirements"] });
    },
  });
}

export function useComplianceDocuments(requirementId: number | null) {
  return useQuery({
    queryKey: ["compliance-requirements", requirementId, "documents"],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: CareDocument[] }>(
        `/compliance-requirements/${requirementId}/documents`,
      );
      return data.data;
    },
    enabled: Boolean(requirementId),
  });
}

export function useUploadComplianceDocument(requirementId: number | null) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: { file: File; category?: string; expiry_date?: string }) => {
      const formData = new FormData();
      formData.append("file", input.file);
      if (input.category) formData.append("category", input.category);
      if (input.expiry_date) formData.append("expiry_date", input.expiry_date);

      const { data } = await apiClient.post<{ data: CareDocument }>(
        `/compliance-requirements/${requirementId}/documents`,
        formData,
        { headers: { "Content-Type": "multipart/form-data" } },
      );
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["compliance-requirements", requirementId, "documents"] });
    },
  });
}
