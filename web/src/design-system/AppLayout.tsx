import {
  ChevronDown,
  ChevronLeft,
  ChevronRight,
  CreditCard,
  FileBarChart,
  HeartPulse,
  Home,
  LogOut,
  Menu,
  Settings,
  Users,
  X,
  type LucideIcon,
} from "lucide-react";
import { useEffect, useMemo, useState, type ReactNode } from "react";
import { NavLink, useLocation } from "react-router-dom";
import { useAuth } from "../lib/auth-context";
import {
  ADMINISTRATION_ROLES,
  ANALYTICS_ROLES,
  AUDIT_ROLES,
  COMPLIANCE_ROLES,
  FINANCE_ROLES,
  PAYROLL_ROLES,
  PLATFORM_ADMIN_ROLES,
  REPORT_ROLES,
  ROSTERING_ROLES,
  SAFEGUARDING_ROLES,
  STAFF_ROLES,
  TRACKING_ROLES,
} from "../lib/types";
import { Logo } from "./Logo";

interface NavItem {
  to: string;
  label: string;
  roles?: readonly string[];
}

interface NavGroup {
  key: string;
  label: string;
  icon: LucideIcon;
  items: NavItem[];
}

const NAV_GROUPS: NavGroup[] = [
  {
    key: "overview",
    label: "Overview",
    icon: Home,
    items: [
      { to: "/operations-dashboard", label: "Operations Dashboard", roles: ANALYTICS_ROLES },
      { to: "/today", label: "Today", roles: ANALYTICS_ROLES },
      { to: "/live-map", label: "Live Map", roles: TRACKING_ROLES },
      { to: "/my-day", label: "My Day" },
      { to: "/announcements", label: "Announcements" },
    ],
  },
  {
    key: "care-management",
    label: "Care Management",
    icon: HeartPulse,
    items: [
      { to: "/service-users", label: "Service Users" },
      { to: "/schedule", label: "Schedule", roles: ROSTERING_ROLES },
      { to: "/visits", label: "Visits" },
      { to: "/incidents", label: "Incidents" },
      { to: "/safeguarding", label: "Safeguarding", roles: SAFEGUARDING_ROLES },
      { to: "/assessment-templates", label: "Assessment Templates" },
    ],
  },
  {
    key: "workforce",
    label: "Workforce",
    icon: Users,
    items: [
      { to: "/staff", label: "Staff", roles: STAFF_ROLES },
      { to: "/rostering", label: "Rostering", roles: ROSTERING_ROLES },
      { to: "/leave", label: "Leave" },
      { to: "/training", label: "Training" },
      { to: "/payroll", label: "Payroll", roles: PAYROLL_ROLES },
    ],
  },
  {
    key: "reports",
    label: "Reports",
    icon: FileBarChart,
    items: [
      { to: "/reports", label: "Reports", roles: REPORT_ROLES },
      { to: "/audit-log", label: "Audit Log", roles: AUDIT_ROLES },
    ],
  },
  {
    key: "finance",
    label: "Finance",
    icon: CreditCard,
    items: [{ to: "/billing", label: "Billing", roles: FINANCE_ROLES }],
  },
  {
    key: "administration",
    label: "Administration",
    icon: Settings,
    items: [
      { to: "/organizations", label: "Organizations", roles: PLATFORM_ADMIN_ROLES },
      { to: "/compliance", label: "Compliance", roles: COMPLIANCE_ROLES },
      { to: "/settings", label: "System Settings", roles: ADMINISTRATION_ROLES },
      { to: "/roles-permissions", label: "User Roles & Permissions", roles: ADMINISTRATION_ROLES },
    ],
  },
];

const EXPANDED_GROUP_STORAGE_KEY = "carenexa360.sidebar.expandedGroup";
const COLLAPSED_STORAGE_KEY = "carenexa360.sidebar.collapsed";

interface FlyoutAnchor {
  groupKey: string;
  top: number;
  left: number;
}

function isItemActive(pathname: string, to: string) {
  return pathname === to || pathname.startsWith(`${to}/`);
}

export function AppLayout({ children }: { children: ReactNode }) {
  const { user, logout, hasAnyRole } = useAuth();
  const { pathname } = useLocation();

  const visibleGroups = useMemo(
    () =>
      NAV_GROUPS.map((group) => ({
        ...group,
        items: group.items.filter((item) => !item.roles || hasAnyRole(item.roles)),
      })).filter((group) => group.items.length > 0),
    [hasAnyRole],
  );

  const activeGroupKey = useMemo(
    () => visibleGroups.find((group) => group.items.some((item) => isItemActive(pathname, item.to)))?.key ?? null,
    [visibleGroups, pathname],
  );

  const [expandedKey, setExpandedKey] = useState<string | null>(() => {
    if (activeGroupKey) return activeGroupKey;
    try {
      return localStorage.getItem(EXPANDED_GROUP_STORAGE_KEY);
    } catch {
      return null;
    }
  });

  useEffect(() => {
    if (activeGroupKey && activeGroupKey !== expandedKey) {
      setExpandedKey(activeGroupKey);
    }
    // Only re-sync when the active route's group changes, not on every
    // manual toggle — otherwise a manual expand of a non-active group
    // would immediately get overwritten by this effect.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [activeGroupKey]);

  useEffect(() => {
    try {
      if (expandedKey) {
        localStorage.setItem(EXPANDED_GROUP_STORAGE_KEY, expandedKey);
      } else {
        localStorage.removeItem(EXPANDED_GROUP_STORAGE_KEY);
      }
    } catch {
      // Private browsing / storage disabled — expansion just won't persist.
    }
  }, [expandedKey]);

  const [isCollapsed, setIsCollapsed] = useState<boolean>(() => {
    try {
      return localStorage.getItem(COLLAPSED_STORAGE_KEY) === "1";
    } catch {
      return false;
    }
  });

  useEffect(() => {
    try {
      localStorage.setItem(COLLAPSED_STORAGE_KEY, isCollapsed ? "1" : "0");
    } catch {
      // Private browsing / storage disabled — collapsed state just won't persist.
    }
  }, [isCollapsed]);

  // A collapsed group's sub-items surface as a flyout instead of an inline
  // list. Positioned via `fixed` (computed from the trigger's own rect,
  // not CSS) so it always escapes the nav's scroll clipping regardless of
  // where the trigger sits in the scrolled list.
  const [flyout, setFlyout] = useState<FlyoutAnchor | null>(null);

  useEffect(() => {
    setFlyout(null);
  }, [pathname]);

  useEffect(() => {
    if (!flyout) return;
    function onKeyDown(e: KeyboardEvent) {
      if (e.key === "Escape") setFlyout(null);
    }
    window.addEventListener("keydown", onKeyDown);
    return () => window.removeEventListener("keydown", onKeyDown);
  }, [flyout]);

  // Below the `lg` breakpoint the persistent sidebar doesn't render at all —
  // this slide-in drawer is the only way to navigate on mobile/tablet.
  const [isMobileNavOpen, setIsMobileNavOpen] = useState(false);

  useEffect(() => {
    setIsMobileNavOpen(false);
  }, [pathname]);

  useEffect(() => {
    if (!isMobileNavOpen) return;
    function onKeyDown(e: KeyboardEvent) {
      if (e.key === "Escape") setIsMobileNavOpen(false);
    }
    window.addEventListener("keydown", onKeyDown);
    return () => window.removeEventListener("keydown", onKeyDown);
  }, [isMobileNavOpen]);

  const initials = user?.name
    ? user.name
        .split(" ")
        .map((part) => part[0])
        .slice(0, 2)
        .join("")
        .toUpperCase()
    : "";

  // Shared between the desktop sidebar's expanded accordion and the mobile
  // drawer — both are just the group list rendered in different chrome.
  function renderAccordionGroup(group: NavGroup) {
    const Icon = group.icon;
    const isExpanded = group.key === expandedKey;
    return (
      <div key={group.key}>
        <button
          type="button"
          onClick={() => setExpandedKey((prev) => (prev === group.key ? null : group.key))}
          aria-expanded={isExpanded}
          className="flex w-full items-center gap-2.5 rounded-2xl px-3 py-2.5 text-left font-semibold text-ink transition-colors duration-150 hover:bg-paper focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal focus-visible:ring-offset-1"
        >
          <Icon className="h-4 w-4 shrink-0 text-inksoft" aria-hidden />
          <span className="flex-1">{group.label}</span>
          <ChevronDown
            className={`h-4 w-4 shrink-0 text-inksoft transition-transform duration-150 ${
              isExpanded ? "" : "-rotate-90"
            }`}
            aria-hidden
          />
        </button>
        {isExpanded && (
          <div className="mt-0.5 mb-1 flex flex-col gap-0.5 pl-9">
            {group.items.map((item) => (
              <NavLink
                key={item.to}
                to={item.to}
                className={({ isActive }) =>
                  `rounded-2xl px-3 py-2 text-left font-medium transition-colors duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal focus-visible:ring-offset-1 ${
                    isActive ? "bg-tealtint font-semibold text-teal" : "text-inksoft hover:bg-paper hover:text-ink"
                  }`
                }
              >
                {item.label}
              </NavLink>
            ))}
          </div>
        )}
      </div>
    );
  }

  function renderProfileBlock(compact: boolean) {
    if (compact) {
      return (
        <>
          <span
            title={user?.name}
            className="flex size-10 shrink-0 items-center justify-center rounded-full bg-tealtint text-sm font-semibold text-teal"
          >
            {initials}
          </span>
          <button
            type="button"
            onClick={() => void logout()}
            title="Sign out"
            aria-label="Sign out"
            className="flex h-8 w-8 items-center justify-center rounded-full text-teal transition-colors duration-150 hover:bg-tealtint focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal"
          >
            <LogOut className="h-4 w-4" aria-hidden />
          </button>
        </>
      );
    }
    return (
      <>
        <div className="flex items-center gap-3">
          <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-tealtint text-sm font-semibold text-teal">
            {initials}
          </span>
          <div className="min-w-0">
            <p className="truncate text-sm font-semibold text-ink">{user?.name}</p>
            <p className="truncate font-mono text-[10px] text-inksoft">{user?.roles[0] ?? user?.email}</p>
          </div>
        </div>
        <button
          type="button"
          onClick={() => void logout()}
          className="mt-3 text-sm font-medium text-teal transition-colors duration-150 hover:text-teal/80 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal"
        >
          Sign out
        </button>
      </>
    );
  }

  return (
    <div className="flex h-screen overflow-hidden bg-paper print:block print:h-auto print:overflow-visible">
      <aside
        className={`hidden shrink-0 flex-col border-r border-line bg-white/70 p-4 transition-[width] duration-200 lg:flex print:hidden ${
          isCollapsed ? "w-[76px]" : "w-[248px]"
        }`}
      >
        <div className={`flex items-center pt-1 pb-6 ${isCollapsed ? "flex-col gap-2 px-0" : "justify-between px-2"}`}>
          <Logo compact={isCollapsed} />
          <button
            type="button"
            onClick={() => {
              setIsCollapsed((v) => !v);
              setFlyout(null);
            }}
            aria-label={isCollapsed ? "Expand sidebar" : "Collapse sidebar"}
            title={isCollapsed ? "Expand sidebar" : "Collapse sidebar"}
            className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-inksoft transition-colors duration-150 hover:bg-paper hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal"
          >
            {isCollapsed ? <ChevronRight className="h-4 w-4" aria-hidden /> : <ChevronLeft className="h-4 w-4" aria-hidden />}
          </button>
        </div>
        <nav className="flex-1 space-y-1 overflow-y-auto text-sm">
          {visibleGroups.map((group) => {
            const Icon = group.icon;

            if (isCollapsed) {
              const isActiveGroup = group.key === activeGroupKey;
              const isFlyoutOpen = flyout?.groupKey === group.key;
              return (
                <button
                  key={group.key}
                  type="button"
                  onClick={(e) => {
                    if (isFlyoutOpen) {
                      setFlyout(null);
                      return;
                    }
                    const rect = e.currentTarget.getBoundingClientRect();
                    setFlyout({ groupKey: group.key, top: rect.top, left: rect.right + 8 });
                  }}
                  aria-expanded={isFlyoutOpen}
                  aria-label={group.label}
                  title={group.label}
                  className={`mx-auto flex h-10 w-10 items-center justify-center rounded-2xl transition-colors duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal focus-visible:ring-offset-1 ${
                    isActiveGroup ? "bg-tealtint text-teal" : "text-inksoft hover:bg-paper hover:text-ink"
                  }`}
                >
                  <Icon className="h-5 w-5" aria-hidden />
                </button>
              );
            }

            return renderAccordionGroup(group);
          })}
        </nav>
        <div className={`mt-4 rounded-2xl bg-paper ring-1 ring-black/5 ${isCollapsed ? "flex flex-col items-center gap-2 p-2" : "p-3"}`}>
          {renderProfileBlock(isCollapsed)}
        </div>
      </aside>

      {/* Mobile top bar — the only nav entry point below `lg`, where the persistent sidebar is hidden */}
      <div className="fixed inset-x-0 top-0 z-30 flex h-14 items-center justify-between border-b border-line bg-white/95 px-3 backdrop-blur lg:hidden print:hidden">
        <button
          type="button"
          onClick={() => setIsMobileNavOpen(true)}
          aria-label="Open menu"
          className="flex h-9 w-9 items-center justify-center rounded-full text-inksoft transition-colors duration-150 hover:bg-paper hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal"
        >
          <Menu className="h-5 w-5" aria-hidden />
        </button>
        <Logo compact />
        <span className="w-9" aria-hidden />
      </div>

      {isMobileNavOpen && (
        <>
          <div className="fixed inset-0 z-40 bg-ink/30 lg:hidden" onClick={() => setIsMobileNavOpen(false)} />
          <aside className="fixed inset-y-0 left-0 z-50 flex w-[280px] max-w-[85vw] flex-col overflow-y-auto bg-white p-4 shadow-xl lg:hidden">
            <div className="flex items-center justify-between px-2 pt-1 pb-6">
              <Logo />
              <button
                type="button"
                onClick={() => setIsMobileNavOpen(false)}
                aria-label="Close menu"
                className="flex h-8 w-8 items-center justify-center rounded-full text-inksoft transition-colors duration-150 hover:bg-paper hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal"
              >
                <X className="h-4 w-4" aria-hidden />
              </button>
            </div>
            <nav className="flex-1 space-y-1 text-sm">{visibleGroups.map((group) => renderAccordionGroup(group))}</nav>
            <div className="mt-4 rounded-2xl bg-paper p-3 ring-1 ring-black/5">{renderProfileBlock(false)}</div>
          </aside>
        </>
      )}

      {flyout &&
        (() => {
          const group = visibleGroups.find((g) => g.key === flyout.groupKey);
          if (!group) return null;
          return (
            <>
              <div className="fixed inset-0 z-40" onClick={() => setFlyout(null)} />
              <div
                className="fixed z-50 w-56 rounded-2xl border border-line bg-white p-2 shadow-lg"
                style={{ top: flyout.top, left: flyout.left }}
              >
                <p className="px-2 py-1 text-xs font-semibold tracking-wide text-inksoft uppercase">{group.label}</p>
                <div className="flex flex-col gap-0.5">
                  {group.items.map((item) => (
                    <NavLink
                      key={item.to}
                      to={item.to}
                      className={({ isActive }) =>
                        `rounded-xl px-3 py-2 text-left font-medium transition-colors duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal focus-visible:ring-offset-1 ${
                          isActive ? "bg-tealtint font-semibold text-teal" : "text-inksoft hover:bg-paper hover:text-ink"
                        }`
                      }
                    >
                      {item.label}
                    </NavLink>
                  ))}
                </div>
              </div>
            </>
          );
        })()}

      <main className="flex-1 overflow-y-auto pt-14 lg:pt-0 print:overflow-visible print:pt-0">
        <div className="mx-auto max-w-6xl px-4 py-6 sm:px-6 sm:py-8 print:max-w-none print:px-0 print:py-0">{children}</div>
      </main>
    </div>
  );
}
