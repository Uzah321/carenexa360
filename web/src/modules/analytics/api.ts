import { useQuery } from "@tanstack/react-query";
import { apiClient } from "../../lib/api-client";
import type { ClientSnapshot, TodayResponse } from "../../lib/types";

export function useToday(date: string) {
  return useQuery({
    queryKey: ["today", date],
    queryFn: async () => {
      const { data } = await apiClient.get<TodayResponse>("/today", { params: { date } });
      return data;
    },
  });
}

export interface OperationsDashboardData {
  headline: {
    active_service_users: number;
    active_staff: number;
    visits_this_month: number;
    revenue_this_month: number;
    outstanding_invoices_total: number;
    open_incidents: number;
  };
  trends: {
    weeks: string[];
    visits: number[];
    revenue: number[];
    incidents: number[];
    rota_coverage_pct: (number | null)[];
  };
  risk: {
    open_incidents: number;
    non_compliant_requirements: number;
    training_expiring_soon: number;
    open_safeguarding_cases: number;
  };
  branches: {
    id: number;
    name: string;
    service_user_count: number;
    staff_count: number;
    visits_this_month: number;
    open_incidents: number;
  }[];
}

export function useOperationsDashboard() {
  return useQuery({
    queryKey: ["operations-dashboard"],
    queryFn: async () => {
      const { data } = await apiClient.get<OperationsDashboardData>("/operations-dashboard");
      return data;
    },
  });
}

export function useClientSnapshot(serviceUserId: number | null) {
  return useQuery({
    queryKey: ["today", "snapshot", serviceUserId],
    queryFn: async () => {
      const { data } = await apiClient.get<ClientSnapshot>(`/today/service-users/${serviceUserId}/snapshot`);
      return data;
    },
    enabled: Boolean(serviceUserId),
  });
}
