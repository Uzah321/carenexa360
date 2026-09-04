import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiClient } from "../../lib/api-client";
import type { UserRoleAssignment } from "../../lib/types";

export function useUserRoles() {
  return useQuery({
    queryKey: ["user-roles"],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: UserRoleAssignment[] }>("/user-roles");
      return data.data;
    },
  });
}

export function useUpdateUserRole() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, role }: { id: number; role: string }) => {
      const { data } = await apiClient.patch<{ data: UserRoleAssignment }>(`/user-roles/${id}`, { role });
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["user-roles"] });
    },
  });
}

export interface CreateUserInput {
  name: string;
  email: string;
  password: string;
  role: string;
}

export function useCreateUserRole() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: CreateUserInput) => {
      const { data } = await apiClient.post<{ data: UserRoleAssignment }>("/user-roles", input);
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["user-roles"] });
    },
  });
}
