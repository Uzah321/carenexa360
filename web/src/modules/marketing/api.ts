import { useMutation } from "@tanstack/react-query";
import { apiClient, ensureCsrfCookie } from "../../lib/api-client";

export interface CreateDemoRequestInput {
  name: string;
  email: string;
  organization_name: string;
  phone?: string;
  message?: string;
}

export interface CreateDemoRequestResult {
  demo_login: { email: string; password: string; url: string };
}

export function useCreateDemoRequest() {
  return useMutation({
    mutationFn: async (input: CreateDemoRequestInput) => {
      await ensureCsrfCookie();
      const { data } = await apiClient.post<CreateDemoRequestResult>("/demo-requests", input);
      return data;
    },
  });
}
