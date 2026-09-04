import { useQuery } from "@tanstack/react-query";
import { apiClient } from "../../lib/api-client";
import type { FamilyPortalDetail, ServiceUser } from "../../lib/types";

export function useFamilyServiceUsers() {
  return useQuery({
    queryKey: ["family-portal", "list"],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: ServiceUser[] }>("/family-portal");
      return data.data;
    },
  });
}

export function useFamilyServiceUserDetail(serviceUserId: number | null) {
  return useQuery({
    queryKey: ["family-portal", serviceUserId],
    queryFn: async () => {
      const { data } = await apiClient.get<FamilyPortalDetail>(`/family-portal/${serviceUserId}`);
      return data;
    },
    enabled: Boolean(serviceUserId),
  });
}
