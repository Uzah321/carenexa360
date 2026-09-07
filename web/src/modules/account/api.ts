import { useMutation } from "@tanstack/react-query";
import { apiClient } from "../../lib/api-client";

export interface TwoFactorSetup {
  secret: string;
  otpauth_url: string;
}

// The current user's mfa_enabled flag lives in AuthContext (from /auth/me),
// not a React Query cache — callers should follow a successful mutation
// here with useAuth().refreshUser() to pick up the change.

export function useStartTwoFactorSetup() {
  return useMutation({
    mutationFn: async () => {
      const { data } = await apiClient.post<TwoFactorSetup>("/account/two-factor");
      return data;
    },
  });
}

export function useConfirmTwoFactor() {
  return useMutation({
    mutationFn: async (code: string) => {
      await apiClient.post("/account/two-factor/confirm", { code });
    },
  });
}

export function useDisableTwoFactor() {
  return useMutation({
    mutationFn: async (currentPassword: string) => {
      await apiClient.delete("/account/two-factor", { data: { current_password: currentPassword } });
    },
  });
}
