import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiClient } from "../../lib/api-client";
import type { TrainingCourse, TrainingRecord, TrainingRecordStatus } from "../../lib/types";

export function useTrainingCourses() {
  return useQuery({
    queryKey: ["training-courses", "list"],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: TrainingCourse[] }>("/training-courses");
      return data.data;
    },
  });
}

export interface CreateTrainingCourseInput {
  name: string;
  category?: string;
  description?: string;
  validity_period_months?: number;
  is_mandatory?: boolean;
}

export function useCreateTrainingCourse() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: CreateTrainingCourseInput) => {
      const { data } = await apiClient.post<{ data: TrainingCourse }>("/training-courses", input);
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["training-courses"] });
    },
  });
}

export function useTrainingRecords(filters: { user_id?: number; status?: TrainingRecordStatus } = {}) {
  return useQuery({
    queryKey: ["training-records", "list", filters],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: TrainingRecord[] }>("/training-records", { params: filters });
      return data.data;
    },
  });
}

export interface CreateTrainingRecordInput {
  user_id: number;
  training_course_id: number;
  completed_date: string;
  expiry_date?: string;
  notes?: string;
}

export function useCreateTrainingRecord() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: CreateTrainingRecordInput) => {
      const { data } = await apiClient.post<{ data: TrainingRecord }>("/training-records", input);
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["training-records"] });
    },
  });
}
