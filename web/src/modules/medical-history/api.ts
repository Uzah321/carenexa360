import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiClient } from "../../lib/api-client";
import type {
  AllergySeverity,
  AllergyType,
  MedicalHistoryCategory,
  MedicalHistoryEntry,
  MedicalHistoryStatus,
  ServiceUser,
} from "../../lib/types";

export interface MedicalHistoryEntryInput {
  category: MedicalHistoryCategory;
  name: string;
  status?: MedicalHistoryStatus | null;
  severity?: AllergySeverity | null;
  allergy_type?: AllergyType | null;
  reaction?: string | null;
  relationship?: string | null;
  started_on?: string | null;
  ended_on?: string | null;
  provider?: string | null;
  notes?: string | null;
}

export function useMedicalHistory(serviceUserId: number) {
  return useQuery({
    queryKey: ["service-users", serviceUserId, "medical-history"],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: MedicalHistoryEntry[] }>(
        `/service-users/${serviceUserId}/medical-history`,
      );
      return data.data;
    },
    enabled: Boolean(serviceUserId),
  });
}

// Every change also invalidates the client itself: the API rebuilds their
// allergy and condition summary lists (and may clear "no known allergies")
// from the history, so the Overview tab needs the fresh copy too.
function useInvalidateClient(serviceUserId: number) {
  const queryClient = useQueryClient();
  return () => queryClient.invalidateQueries({ queryKey: ["service-users", serviceUserId] });
}

export function useCreateMedicalHistoryEntry(serviceUserId: number) {
  const invalidate = useInvalidateClient(serviceUserId);
  return useMutation({
    mutationFn: async (input: MedicalHistoryEntryInput) => {
      const { data } = await apiClient.post<{ data: MedicalHistoryEntry }>(
        `/service-users/${serviceUserId}/medical-history`,
        input,
      );
      return data.data;
    },
    onSuccess: () => {
      void invalidate();
    },
  });
}

export function useUpdateMedicalHistoryEntry(serviceUserId: number) {
  const invalidate = useInvalidateClient(serviceUserId);
  return useMutation({
    mutationFn: async ({ id, ...input }: MedicalHistoryEntryInput & { id: number }) => {
      const { data } = await apiClient.patch<{ data: MedicalHistoryEntry }>(`/medical-history/${id}`, input);
      return data.data;
    },
    onSuccess: () => {
      void invalidate();
    },
  });
}

export function useDeleteMedicalHistoryEntry(serviceUserId: number) {
  const invalidate = useInvalidateClient(serviceUserId);
  return useMutation({
    mutationFn: async (id: number) => {
      await apiClient.delete(`/medical-history/${id}`);
    },
    onSuccess: () => {
      void invalidate();
    },
  });
}

/** Signs the history off as complete and up to date, stamped with who and when. */
export function useReviewMedicalHistory(serviceUserId: number) {
  const invalidate = useInvalidateClient(serviceUserId);
  return useMutation({
    mutationFn: async () => {
      const { data } = await apiClient.post<{ data: ServiceUser }>(`/service-users/${serviceUserId}/medical-history/review`);
      return data.data;
    },
    onSuccess: () => {
      void invalidate();
    },
  });
}
