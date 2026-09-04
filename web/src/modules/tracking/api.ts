import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiClient } from "../../lib/api-client";

export interface PostLocationInput {
  latitude: number;
  longitude: number;
  accuracy?: number | null;
  visit_id?: number | null;
}

export function usePostLocation() {
  return useMutation({
    mutationFn: async (input: PostLocationInput) => {
      await apiClient.post("/carer-locations", input);
    },
  });
}

export interface LiveMapPing {
  latitude: number;
  longitude: number;
  recorded_at: string;
}

export interface LiveMapCarer {
  user_id: number;
  name: string;
  is_checked_in: boolean;
  last_ping_at: string | null;
  trail: LiveMapPing[];
}

export interface LiveMapPerson {
  user_id: number;
  name: string;
  service_user_name: string | null;
  checked_in_at?: string;
  checked_out_at?: string;
}

export interface LiveMapResponse {
  date: string;
  checked_in: { count: number; items: LiveMapPerson[] };
  checked_out: { count: number; items: LiveMapPerson[] };
  carers: LiveMapCarer[];
}

export function useLiveMap(branchId: number | null) {
  return useQuery({
    queryKey: ["carer-locations", "live", branchId],
    queryFn: async () => {
      const { data } = await apiClient.get<LiveMapResponse>("/carer-locations/live", {
        params: branchId ? { branch_id: branchId } : undefined,
      });
      return data;
    },
    refetchInterval: 20000,
  });
}

export interface DutyPeriod {
  id: number;
  user_id: number;
  carer_name?: string | null;
  started_at: string;
  ended_at: string | null;
  is_active: boolean;
  /** Set only when a manager closed the shift instead of the carer. */
  close_reason?: string | null;
  closed_by_name?: string | null;
}

export function useCurrentDutyPeriod() {
  return useQuery({
    queryKey: ["duty-periods", "current"],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: DutyPeriod | null }>("/duty-periods/current");
      return data.data;
    },
  });
}

export interface CheckInPayload {
  latitude: number;
  longitude: number;
  accuracy?: number | null;
}

export function useCheckInDuty() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: CheckInPayload) => {
      const { data } = await apiClient.post<{ data: DutyPeriod }>("/duty-periods", input);
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["duty-periods", "current"] });
    },
  });
}

export function useCheckOutDuty() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, ...input }: CheckInPayload & { id: number }) => {
      const { data } = await apiClient.post<{ data: DutyPeriod }>(`/duty-periods/${id}/check-out`, input);
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["duty-periods", "current"] });
    },
  });
}

/** Shifts still open right now — a manager's view for chasing forgotten check-outs. */
export function useOpenDutyPeriods(enabled = true) {
  return useQuery({
    queryKey: ["duty-periods", "open"],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: DutyPeriod[] }>("/duty-periods/open");
      return data.data;
    },
    enabled,
    refetchInterval: 60000,
  });
}

/**
 * A manager ending a shift the carer never checked out of. The reason is
 * mandatory server-side and stored on the record, so this can never close a
 * shift silently.
 */
export function useForceCloseDutyPeriod() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, reason }: { id: number; reason: string }) => {
      const { data } = await apiClient.post<{ data: DutyPeriod }>(
        `/duty-periods/${id}/force-close`,
        { reason },
      );
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["duty-periods"] });
      void queryClient.invalidateQueries({ queryKey: ["carer-locations"] });
    },
  });
}
