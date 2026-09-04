import type { ReactNode } from "react";
import { Navigate } from "react-router-dom";
import { getDefaultRouteFor, useAuth } from "../lib/auth-context";
import { AppLayout } from "../design-system/AppLayout";
import { FamilyPortalLayout } from "../modules/family-portal/layouts/FamilyPortalLayout";
import { LandingPage } from "../modules/marketing/pages/LandingPage";

/**
 * `roles`, when given, blocks direct URL navigation for anyone without one
 * of those roles — the nav link being hidden only stops in-app clicks, not
 * someone typing the path straight in.
 */
export function ProtectedRoute({
  children,
  roles,
}: {
  children: ReactNode;
  roles?: readonly string[];
}) {
  const { user, isLoading, hasAnyRole } = useAuth();

  if (isLoading) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-paper text-sm text-inksoft">
        Loading…
      </div>
    );
  }

  if (!user) {
    return <Navigate to="/login" replace />;
  }

  // A Family Member has no business anywhere in the staff console, not just on
  // the role-gated pages. The API already 403s every staff endpoint for them,
  // so no data leaks — but without this they still get the full staff shell
  // (sidebar, "New Service User", "Report Incident") on any ungated route they
  // type in, which reads as an internal tool they were never meant to see.
  if (user.roles.includes("Family Member")) {
    return <Navigate to={getDefaultRouteFor(user)} replace />;
  }

  if (roles && !hasAnyRole(roles)) {
    return <Navigate to={getDefaultRouteFor(user)} replace />;
  }

  return <AppLayout>{children}</AppLayout>;
}

/**
 * A Family Member gets a deliberately separate, simplified experience — no
 * staff sidebar/nav — rather than the full AppLayout with restricted nav.
 */
export function FamilyPortalRoute({ children }: { children: ReactNode }) {
  const { user, isLoading } = useAuth();

  if (isLoading) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-paper text-sm text-inksoft">
        Loading…
      </div>
    );
  }

  if (!user) {
    return <Navigate to="/login" replace />;
  }

  return <FamilyPortalLayout>{children}</FamilyPortalLayout>;
}

/**
 * Tenant-scoped users can't view /organizations (platform-admin only, 403s),
 * so route "/" and unknown paths to a role-appropriate landing page instead
 * of a hardcoded one.
 */
export function DefaultRedirect() {
  const { user, isLoading } = useAuth();

  if (isLoading) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-paper text-sm text-inksoft">
        Loading…
      </div>
    );
  }

  if (!user) {
    return <Navigate to="/login" replace />;
  }

  return <Navigate to={getDefaultRouteFor(user)} replace />;
}

/**
 * "/" specifically: a logged-out visitor gets the public marketing page
 * rather than being bounced straight to /login, while a logged-in user
 * still lands on their normal role-based page.
 */
export function HomeRoute() {
  const { user, isLoading } = useAuth();

  if (isLoading) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-paper text-sm text-inksoft">
        Loading…
      </div>
    );
  }

  if (user) {
    return <Navigate to={getDefaultRouteFor(user)} replace />;
  }

  return <LandingPage />;
}
