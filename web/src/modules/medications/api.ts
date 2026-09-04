import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiClient } from "../../lib/api-client";
import type { Medication, MedicationAdministration, MedicationAdministrationStatus } from "../../lib/types";

export function useMedications(serviceUserId: number) {
  return useQuery({
    queryKey: ["service-users", serviceUserId, "medications"],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: Medication[] }>(
        `/service-users/${serviceUserId}/medications`,
      );
      return data.data;
    },
    enabled: Boolean(serviceUserId),
  });
}

export interface CreateMedicationInput {
  name: string;
  strength?: string;
  form?: string;
  dose: string;
  route: string;
  frequency: string;
  schedule?: string[];
  start_date: string;
  end_date?: string;
  prescriber?: string;
  pharmacy?: string;
  instructions?: string;
  is_prn?: boolean;
  prn_instructions?: string;
  is_controlled_drug?: boolean;
}

export function useCreateMedication(serviceUserId: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: CreateMedicationInput) => {
      const { data } = await apiClient.post<{ data: Medication }>(
        `/service-users/${serviceUserId}/medications`,
        input,
      );
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["service-users", serviceUserId, "medications"] });
    },
  });
}

export function useMedicationAdministrations(medicationId: number | null) {
  return useQuery({
    queryKey: ["medications", medicationId, "administrations"],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: MedicationAdministration[] }>(
        `/medications/${medicationId}/administrations`,
      );
      return data.data;
    },
    enabled: Boolean(medicationId),
  });
}

export interface RecordAdministrationInput {
  status: MedicationAdministrationStatus;
  administered_at?: string;
  witness_id?: number | null;
  notes?: string;
}

export function useRecordAdministration(medicationId: number | null) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: RecordAdministrationInput) => {
      const { data } = await apiClient.post<{ data: MedicationAdministration }>(
        `/medications/${medicationId}/administrations`,
        input,
      );
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["medications", medicationId, "administrations"] });
    },
  });
}
