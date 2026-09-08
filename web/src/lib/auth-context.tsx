import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useState,
  type ReactNode,
} from "react";
import { apiClient, ensureCsrfCookie, setSessionExpiredHandler } from "./api-client";
import { queryClient } from "./query-client";
import type { User } from "./types";

export interface RegisterInput {
  organization_name: string;
  country: string;
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
}

interface AuthContextValue {
  user: User | null;
  isLoading: boolean;
  /** Resolves to true when the account has 2FA enabled — the caller still owes a code via submitTwoFactorCode before the session is actually established. */
  login: (email: string, password: string) => Promise<{ twoFactorRequired: boolean }>;
  submitTwoFactorCode: (code: string) => Promise<void>;
  register: (input: RegisterInput) => Promise<void>;
  logout: () => Promise<void>;
  /** Re-fetches /auth/me — call after anything that changes the current user's own record outside a page-level refetch, e.g. enabling/disabling 2FA. */
  refreshUser: () => Promise<void>;
  hasPermission: (permission: string) => boolean;
  hasRole: (role: string) => boolean;
  hasAnyRole: (roles: readonly string[]) => boolean;
}

const AuthContext = createContext<AuthContextValue | undefined>(undefined);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  const loadUser = useCallback(async () => {
    try {
      const { data } = await apiClient.get<{ data: User }>("/auth/me");
      setUser(data.data);
    } catch {
      setUser(null);
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    loadUser();
  }, [loadUser]);

  useEffect(() => {
    // A cached query answers "whose data is this" purely by queryKey, not by
    // which user fetched it — so anything already in cache (duty period,
    // visits, whatever) would otherwise keep rendering under the next
    // person's session on this tab until each query happened to go stale
    // and refetch on its own. Wiping the cache at the account boundary is
    // what makes "logged in as" actually mean something between sessions.
    setSessionExpiredHandler(() => {
      queryClient.clear();
      setUser(null);
    });
    return () => setSessionExpiredHandler(() => {});
  }, []);

  const login = useCallback(
    async (email: string, password: string) => {
      await ensureCsrfCookie();
      const { data } = await apiClient.post<{ two_factor_required?: boolean }>("/auth/login", {
        email,
        password,
      });
      if (data?.two_factor_required) {
        return { twoFactorRequired: true };
      }
      await loadUser();
      return { twoFactorRequired: false };
    },
    [loadUser],
  );

  const submitTwoFactorCode = useCallback(
    async (code: string) => {
      await apiClient.post("/auth/two-factor-challenge", { code });
      await loadUser();
    },
    [loadUser],
  );

  const register = useCallback(async (input: RegisterInput) => {
    await ensureCsrfCookie();
    await apiClient.post("/auth/register", input);
    await loadUser();
  }, [loadUser]);

  const logout = useCallback(async () => {
    await apiClient.post("/auth/logout");
    queryClient.clear();
    setUser(null);
  }, []);

  const hasPermission = useCallback(
    (permission: string) => user?.permissions.includes(permission) ?? false,
    [user],
  );

  const hasRole = useCallback(
    (role: string) => user?.roles.includes(role) ?? false,
    [user],
  );

  const hasAnyRole = useCallback(
    // A platform admin (tenant_id === null) has no restrictions anywhere in
    // the app — mirrors the same short-circuit on the User model backend
    // side, so nav visibility and route guards match what the API actually
    // allows instead of hiding things a platform admin can already do.
    (roles: readonly string[]) =>
      user ? user.tenant_id === null || roles.some((role) => user.roles.includes(role)) : false,
    [user],
  );

  return (
    <AuthContext.Provider
      value={{
        user,
        isLoading,
        login,
        submitTwoFactorCode,
        register,
        logout,
        refreshUser: loadUser,
        hasPermission,
        hasRole,
        hasAnyRole,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error("useAuth must be used within an AuthProvider");
  }
  return context;
}

/**
 * The single source of truth for "where should this user land" — used by
 * both LoginPage (right after signing in) and DefaultRedirect (on "/" and
 * unknown paths). Keeping this logic in one place is deliberate: it used to
 * be duplicated, and a Family Member role added to one copy but not the
 * other sent family logins into the staff console instead of the portal.
 */
const OWNER_ADMIN_ROLES = ["Organization Owner", "Organization Admin"];
const CARER_ROLES = ["Carer / Support Worker", "Senior Carer"];

export function getDefaultRouteFor(user: User): string {
  if (user.roles.includes("Family Member")) {
    return "/family-portal";
  }

  if (!user.tenant_id) {
    return "/organizations";
  }

  if (user.roles.some((role) => OWNER_ADMIN_ROLES.includes(role))) {
    return "/operations-dashboard";
  }

  if (user.roles.some((role) => CARER_ROLES.includes(role))) {
    return "/my-day";
  }

  return "/service-users";
}
