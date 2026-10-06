import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiClient } from "../../lib/api-client";
import type {
  CarePlan,
  CarePlanArea,
  CarePlanRiskLevel,
  HomeCarePlan,
  MedicationRiskDetails,
  PersonAtRisk,
  RiskAssessmentType,
  RiskType,
} from "../../lib/types";

export function useCarePlans(serviceUserId: number) {
  return useQuery({
    queryKey: ["service-users", serviceUserId, "care-plans"],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: CarePlan[] }>(
        `/service-users/${serviceUserId}/care-plans`,
      );
      return data.data;
    },
    enabled: Boolean(serviceUserId),
  });
}

export function useCarePlan(id: number | null) {
  return useQuery({
    queryKey: ["care-plans", id],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: CarePlan }>(`/care-plans/${id}`);
      return data.data;
    },
    enabled: Boolean(id),
  });
}

export interface CarePlanSectionInput {
  area: CarePlanArea;
  identified_need: string;
  risk?: CarePlanRiskLevel | "";
  goal: string;
  intervention: string;
  equipment?: string;
  frequency?: string;
  responsible_staff_id?: number | null;
  start_date?: string;
  review_date?: string;
  status?: string;
  notes?: string;
}

export interface CarePlanRiskAssessmentInput {
  type: RiskAssessmentType;
  area: CarePlanArea | "";
  risk_type: RiskType | "";
  hazard: string;
  details: string;
  triggers: string;
  persons_at_risk: PersonAtRisk[];
  harm_description: string;
  likelihood: number | null;
  severity: number | null;
  existing_controls: string;
  further_actions: string;
  residual_likelihood: number | null;
  residual_severity: number | null;
  target_likelihood: number | null;
  target_severity: number | null;
  contingency_plan_required: boolean;
  contingency_plan: string;
  action_owner_id: number | null;
  action_due_date: string;
  review_date: string;
  medication_details: MedicationRiskDetails | null;
}

type BlankableRiskField =
  | "area"
  | "risk_type"
  | "details"
  | "triggers"
  | "harm_description"
  | "existing_controls"
  | "further_actions"
  | "contingency_plan"
  | "action_due_date"
  | "review_date";

// What's actually sent — form blanks ("") become nulls, see normalizeRiskAssessmentInput.
export type CarePlanRiskAssessmentPayload = Omit<CarePlanRiskAssessmentInput, BlankableRiskField> & {
  [K in BlankableRiskField]: Exclude<CarePlanRiskAssessmentInput[K], ""> | null;
};

export interface CreateCarePlanInput {
  effective_from: string;
  notes?: string;
  home_care_plan?: HomeCarePlan | null;
  sections: CarePlanSectionInput[];
  risk_assessments?: CarePlanRiskAssessmentPayload[];
}

export function useCreateCarePlan(serviceUserId: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: CreateCarePlanInput) => {
      const { data } = await apiClient.post<{ data: CarePlan }>(
        `/service-users/${serviceUserId}/care-plans`,
        input,
      );
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["service-users", serviceUserId, "care-plans"] });
      void queryClient.invalidateQueries({ queryKey: ["service-users", serviceUserId, "care-pathway"] });
    },
  });
}

export interface CarePathwayStage {
  key: string;
  label: string;
  /** done = on time; done_late; due = not yet, by `due`; overdue; waiting = can't be scheduled yet. */
  status: "done" | "done_late" | "due" | "overdue" | "waiting";
  due: string | null;
  done: string | null;
}

/** Where the client is on the care pathway (System Settings → Care Pathway). */
export function useCarePathway(serviceUserId: number) {
  return useQuery({
    queryKey: ["service-users", serviceUserId, "care-pathway"],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: { stages: CarePathwayStage[]; overdue: number } }>(
        `/service-users/${serviceUserId}/care-pathway`,
      );
      return data.data;
    },
    enabled: Boolean(serviceUserId),
  });
}
