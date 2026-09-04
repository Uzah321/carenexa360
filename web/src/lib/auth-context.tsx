import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useState,
  type ReactNode,
} from "react";
import { apiClient, ensureCsrfCookie } from "./api-client";
import type { User } from "./types";

interface AuthContextValue {
  user: User | null;
  isLoading: boolean;
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
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

  const login = useCallback(async (email: string, password: string) => {
    await ensureCsrfCookie();
    await apiClient.post("/auth/login", { email, password });
    await loadUser();
  }, [loadUser]);

  const logout = useCallback(async () => {
    await apiClient.post("/auth/logout");
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
    (roles: readonly string[]) => roles.some((role) => user?.roles.includes(role)) ?? false,
    [user],
  );

  return (
    <AuthContext.Provider
      value={{ user, isLoading, login, logout, hasPermission, hasRole, hasAnyRole }}
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
