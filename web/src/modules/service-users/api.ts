import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiClient } from "../../lib/api-client";
import type { Paginated, ServiceUser, ServiceUserContact, ServiceUserContactType } from "../../lib/types";

export interface ServiceUserInput {
  branch_id?: number | null;
  care_manager_id?: number | null;
  first_name: string;
  last_name: string;
  preferred_name?: string | null;
  date_of_birth?: string | null;
  gender?: string | null;
  language?: string | null;
  phone?: string | null;
  email?: string | null;
  address?: string | null;
  latitude?: number | null;
  longitude?: number | null;
  funding_source?: string | null;
  status?: string;
  allergies?: string[];
  diagnoses?: string[];
  medical_conditions?: string[];
  disabilities?: string[];
  mobility_notes?: string | null;
  communication_needs?: string | null;
  dietary_needs?: string | null;
  cultural_preferences?: string | null;
  religious_requirements?: string | null;
  behavioural_considerations?: string | null;
  preferred_routines?: string | null;
  capacity_consent_notes?: string | null;
}

export interface ServiceUserFilters {
  search?: string;
  status?: string;
  /** Defaults to the API's 15; raise it where a page needs the whole list at once. */
  perPage?: number;
}

export function useServiceUsers(page: number, filters: ServiceUserFilters = {}) {
  const search = filters.search?.trim() ?? "";
  const status = filters.status ?? "";
  const perPage = filters.perPage;

  return useQuery({
    // Namespaced with "list" so this never collides with useServiceUser's
    // ["service-users", id] cache key when page and id happen to match
    // (e.g. both 1) — TanStack Query treats identical key arrays as the
    // same cache entry regardless of what shape of data each hook expects.
    queryKey: ["service-users", "list", page, search, status, perPage],
    queryFn: async () => {
      const { data } = await apiClient.get<Paginated<ServiceUser>>("/service-users", {
        // Omitted rather than sent empty, so "no filter" stays a clean URL
        // and the API's defaults apply.
        params: {
          page,
          ...(search ? { search } : {}),
          ...(status ? { status } : {}),
          ...(perPage ? { per_page: perPage } : {}),
        },
      });
      return data;
    },
  });
}

export function useServiceUser(id: number) {
  return useQuery({
    queryKey: ["service-users", id],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: ServiceUser }>(`/service-users/${id}`);
      return data.data;
    },
    enabled: Boolean(id),
  });
}

export function useCreateServiceUser() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: ServiceUserInput) => {
      const { data } = await apiClient.post<{ data: ServiceUser }>("/service-users", input);
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["service-users"] });
    },
  });
}

export function useUpdateServiceUser(id: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: Partial<ServiceUserInput>) => {
      const { data } = await apiClient.patch<{ data: ServiceUser }>(`/service-users/${id}`, input);
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["service-users", id] });
    },
  });
}

export function useUpdateServiceUserStatus() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, status }: { id: number; status: string }) => {
      const { data } = await apiClient.patch<{ data: ServiceUser }>(`/service-users/${id}`, { status });
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["service-users"] });
    },
  });
}

export function useDeleteServiceUser() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id: number) => {
      await apiClient.delete(`/service-users/${id}`);
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["service-users"] });
    },
  });
}

export function useServiceUserContacts(serviceUserId: number) {
  return useQuery({
    queryKey: ["service-users", serviceUserId, "contacts"],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: ServiceUserContact[] }>(
        `/service-users/${serviceUserId}/contacts`,
      );
      return data.data;
    },
    enabled: Boolean(serviceUserId),
  });
}

export interface CreateContactInput {
  type: ServiceUserContactType;
  name: string;
  relationship?: string;
  phone?: string;
  email?: string;
  address?: string;
  notes?: string;
}

export function useCreateServiceUserContact(serviceUserId: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: CreateContactInput) => {
      const { data } = await apiClient.post<{ data: ServiceUserContact }>(
        `/service-users/${serviceUserId}/contacts`,
        input,
      );
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["service-users", serviceUserId, "contacts"] });
    },
  });
}

export function useDeleteServiceUserContact(serviceUserId: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (contactId: number) => {
      await apiClient.delete(`/service-users/${serviceUserId}/contacts/${contactId}`);
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["service-users", serviceUserId, "contacts"] });
    },
  });
}

export function useGrantPortalAccess(serviceUserId: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ contactId, email, password }: { contactId: number; email: string; password: string }) => {
      const { data } = await apiClient.post<{ data: ServiceUserContact }>(
        `/service-users/${serviceUserId}/contacts/${contactId}/grant-portal-access`,
        { email, password },
      );
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["service-users", serviceUserId, "contacts"] });
    },
  });
}
