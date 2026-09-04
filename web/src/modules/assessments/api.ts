import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiClient } from "../../lib/api-client";
import type { AssessmentField, AssessmentResponse, AssessmentTemplate } from "../../lib/types";

export function useAssessmentTemplates() {
  return useQuery({
    queryKey: ["assessment-templates"],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: AssessmentTemplate[] }>("/assessment-templates");
      return data.data;
    },
  });
}

export interface CreateTemplateInput {
  name: string;
  category?: string;
  description?: string;
  fields: AssessmentField[];
}

export function useCreateAssessmentTemplate() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: CreateTemplateInput) => {
      const { data } = await apiClient.post<{ data: AssessmentTemplate }>(
        "/assessment-templates",
        input,
      );
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["assessment-templates"] });
    },
  });
}

export function useAssessmentResponses(serviceUserId: number) {
  return useQuery({
    queryKey: ["service-users", serviceUserId, "assessment-responses"],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: AssessmentResponse[] }>(
        `/service-users/${serviceUserId}/assessment-responses`,
      );
      return data.data;
    },
    enabled: Boolean(serviceUserId),
  });
}

export interface CreateResponseInput {
  assessment_template_id: number;
  answers: Record<string, unknown>;
  status?: "draft" | "completed";
}

export function useCreateAssessmentResponse(serviceUserId: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: CreateResponseInput) => {
      const { data } = await apiClient.post<{ data: AssessmentResponse }>(
        `/service-users/${serviceUserId}/assessment-responses`,
        input,
      );
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({
        queryKey: ["service-users", serviceUserId, "assessment-responses"],
      });
    },
  });
}
