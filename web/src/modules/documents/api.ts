import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiClient } from "../../lib/api-client";
import type { CareDocument } from "../../lib/types";

export function useServiceUserDocuments(serviceUserId: number) {
  return useQuery({
    queryKey: ["service-users", serviceUserId, "documents"],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: CareDocument[] }>(
        `/service-users/${serviceUserId}/documents`,
      );
      return data.data;
    },
    enabled: Boolean(serviceUserId),
  });
}

export interface UploadDocumentInput {
  file: File;
  category?: string;
  expiry_date?: string;
}

export function useUploadDocument(serviceUserId: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: UploadDocumentInput) => {
      const formData = new FormData();
      formData.append("file", input.file);
      if (input.category) formData.append("category", input.category);
      if (input.expiry_date) formData.append("expiry_date", input.expiry_date);

      const { data } = await apiClient.post<{ data: CareDocument }>(
        `/service-users/${serviceUserId}/documents`,
        formData,
        { headers: { "Content-Type": "multipart/form-data" } },
      );
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["service-users", serviceUserId, "documents"] });
    },
  });
}

export function useStaffDocuments(staffId: number) {
  return useQuery({
    queryKey: ["staff", staffId, "documents"],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: CareDocument[] }>(`/staff/${staffId}/documents`);
      return data.data;
    },
    enabled: Boolean(staffId),
  });
}

export function useUploadStaffDocument(staffId: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: UploadDocumentInput) => {
      const formData = new FormData();
      formData.append("file", input.file);
      if (input.category) formData.append("category", input.category);
      if (input.expiry_date) formData.append("expiry_date", input.expiry_date);

      const { data } = await apiClient.post<{ data: CareDocument }>(
        `/staff/${staffId}/documents`,
        formData,
        { headers: { "Content-Type": "multipart/form-data" } },
      );
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["staff", staffId, "documents"] });
    },
  });
}

export async function downloadDocument(documentId: number, filename: string) {
  const response = await apiClient.get(`/documents/${documentId}/download`, {
    responseType: "blob",
  });
  const url = window.URL.createObjectURL(response.data as Blob);
  const link = window.document.createElement("a");
  link.href = url;
  link.download = filename;
  link.click();
  window.URL.revokeObjectURL(url);
}
