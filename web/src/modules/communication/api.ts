import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiClient } from "../../lib/api-client";
import type { Announcement } from "../../lib/types";

export function useAnnouncements() {
  return useQuery({
    queryKey: ["announcements", "list"],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: Announcement[] }>("/announcements");
      return data.data;
    },
  });
}

export interface CreateAnnouncementInput {
  branch_id?: number | null;
  title: string;
  body: string;
  pinned?: boolean;
}

export function useCreateAnnouncement() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: CreateAnnouncementInput) => {
      const { data } = await apiClient.post<{ data: Announcement }>("/announcements", input);
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["announcements"] });
    },
  });
}
