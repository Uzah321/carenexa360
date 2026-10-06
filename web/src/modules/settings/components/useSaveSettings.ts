import { useState } from "react";
import { useAuth } from "../../../lib/auth-context";
import { apiErrorMessage } from "../../../lib/api-error";
import type { TenantSettings } from "../../../lib/types";
import { useTenant, useUpdateTenant } from "../../organization/api";

/**
 * Loads the organisation and saves settings, then refreshes the signed-in
 * user so the new values (carried on /auth/me) reach every page at once.
 */
export function useSaveSettings(tenantId: number) {
  const { refreshUser } = useAuth();
  const { data: tenant, isLoading } = useTenant(tenantId);
  const updateTenant = useUpdateTenant(tenantId);
  const [saved, setSaved] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function save(input: { settings?: TenantSettings; name?: string; country?: string; timezone?: string; currency?: string; locale?: string }) {
    setSaved(false);
    setError(null);
    try {
      await updateTenant.mutateAsync(input);
      await refreshUser();
      setSaved(true);
      return true;
    } catch (err) {
      setError(apiErrorMessage(err, "Something went wrong saving your changes. Please try again."));
      return false;
    }
  }

  return { tenant, isLoading, save, isSaving: updateTenant.isPending, saved, error };
}
