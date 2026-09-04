import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiClient } from "../../lib/api-client";
import type { Branch, Department, Paginated, Tenant, TenantSettings } from "../../lib/types";

export function useTenants(page: number) {
  return useQuery({
    // Namespaced with "list" so this never collides with useTenant's
    // ["tenants", id] cache key when page and id happen to match (e.g. both 1).
    queryKey: ["tenants", "list", page],
    queryFn: async () => {
      const { data } = await apiClient.get<Paginated<Tenant>>("/organizations/tenants", {
        params: { page },
      });
      return data;
    },
  });
}

export function useTenant(tenantId: number) {
  return useQuery({
    queryKey: ["tenants", tenantId],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: Tenant }>(`/organizations/tenants/${tenantId}`);
      return data.data;
    },
    enabled: Boolean(tenantId),
  });
}

export interface CreateTenantInput {
  name: string;
  slug: string;
  country: string;
  timezone: string;
  currency: string;
  locale: string;
}

export function useCreateTenant() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: CreateTenantInput) => {
      const { data } = await apiClient.post<{ data: Tenant }>("/organizations/tenants", input);
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["tenants"] });
    },
  });
}

export interface UpdateTenantInput {
  name?: string;
  country?: string;
  timezone?: string;
  currency?: string;
  locale?: string;
  settings?: TenantSettings;
}

export function useUpdateTenant(tenantId: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: UpdateTenantInput) => {
      const { data } = await apiClient.patch<{ data: Tenant }>(`/organizations/tenants/${tenantId}`, input);
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["tenants", tenantId] });
    },
  });
}

export function useUpdateTenantStatus() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, status }: { id: number; status: Tenant["status"] }) => {
      const { data } = await apiClient.patch<{ data: Tenant }>(`/organizations/tenants/${id}/status`, { status });
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["tenants"] });
    },
  });
}

export function useBranches(tenantId: number) {
  return useQuery({
    queryKey: ["tenants", tenantId, "branches"],
    queryFn: async () => {
      const { data } = await apiClient.get<Paginated<Branch>>(
        `/organizations/tenants/${tenantId}/branches`,
      );
      return data;
    },
    enabled: Boolean(tenantId),
  });
}

export interface CreateBranchInput {
  name: string;
  country: string;
  region?: string;
  address?: string;
}

export function useCreateBranch(tenantId: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: CreateBranchInput) => {
      const { data } = await apiClient.post<{ data: Branch }>(
        `/organizations/tenants/${tenantId}/branches`,
        input,
      );
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["tenants", tenantId, "branches"] });
    },
  });
}

export interface UpdateBranchInput {
  name?: string;
  country?: string;
  region?: string;
  address?: string;
}

export function useUpdateBranch(tenantId: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, ...input }: UpdateBranchInput & { id: number }) => {
      const { data } = await apiClient.patch<{ data: Branch }>(
        `/organizations/tenants/${tenantId}/branches/${id}`,
        input,
      );
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["tenants", tenantId, "branches"] });
    },
  });
}

export function useUpdateBranchStatus(tenantId: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, status }: { id: number; status: Branch["status"] }) => {
      const { data } = await apiClient.patch<{ data: Branch }>(
        `/organizations/tenants/${tenantId}/branches/${id}/status`,
        { status },
      );
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["tenants", tenantId, "branches"] });
    },
  });
}

export function useDepartments(tenantId: number) {
  return useQuery({
    queryKey: ["tenants", tenantId, "departments"],
    queryFn: async () => {
      const { data } = await apiClient.get<Paginated<Department>>(
        `/organizations/tenants/${tenantId}/departments`,
      );
      return data;
    },
    enabled: Boolean(tenantId),
  });
}

export interface CreateDepartmentInput {
  branch_id: number;
  name: string;
}

export function useCreateDepartment(tenantId: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: CreateDepartmentInput) => {
      const { data } = await apiClient.post<{ data: Department }>(
        `/organizations/tenants/${tenantId}/departments`,
        input,
      );
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["tenants", tenantId, "departments"] });
    },
  });
}
