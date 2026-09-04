import type { ReactNode } from "react";
import { Logo } from "../../../design-system/Logo";
import { useAuth } from "../../../lib/auth-context";

export function FamilyPortalLayout({ children }: { children: ReactNode }) {
  const { user, logout } = useAuth();

  return (
    <div className="min-h-screen bg-paper">
      <header className="border-b border-line bg-white">
        <div className="mx-auto flex max-w-3xl items-center justify-between px-4 py-4 sm:px-6">
          <Logo compact />
          <div className="flex items-center gap-3 text-sm">
            <span className="hidden text-inksoft sm:inline">{user?.name}</span>
            <button
              type="button"
              onClick={() => void logout()}
              className="font-medium text-teal hover:text-teal/90"
            >
              Sign out
            </button>
          </div>
        </div>
      </header>
      <main className="mx-auto max-w-3xl px-4 py-6 sm:px-6 sm:py-8">{children}</main>
    </div>
  );
}
