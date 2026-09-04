import { Route, Routes } from "react-router-dom";
import { Providers } from "./Providers";
import { DefaultRedirect, FamilyPortalRoute, HomeRoute, ProtectedRoute } from "./ProtectedRoute";
import { LoginPage } from "../modules/identity/pages/LoginPage";
import { OrganizationsPage } from "../modules/organization/pages/OrganizationsPage";
import { TenantDetailPage } from "../modules/organization/pages/TenantDetailPage";
import { AuditLogPage } from "../modules/audit/pages/AuditLogPage";
import { ServiceUsersPage } from "../modules/service-users/pages/ServiceUsersPage";
import { ServiceUserDetailPage } from "../modules/service-users/pages/ServiceUserDetailPage";
import { AssessmentTemplatesPage } from "../modules/assessments/pages/AssessmentTemplatesPage";
import { StaffPage } from "../modules/staff/pages/StaffPage";
import { VisitsPage } from "../modules/visits/pages/VisitsPage";
import { SchedulePage } from "../modules/visits/pages/SchedulePage";
import { VisitDetailPage } from "../modules/visits/pages/VisitDetailPage";
import { RoutePage } from "../modules/visits/pages/RoutePage";
import { RosteringPage } from "../modules/rostering/pages/RosteringPage";
import { IncidentsPage } from "../modules/incidents/pages/IncidentsPage";
import { SafeguardingPage } from "../modules/safeguarding/pages/SafeguardingPage";
import { BillingPage } from "../modules/billing/pages/BillingPage";
import { PayrollPage } from "../modules/payroll/pages/PayrollPage";
import { LeavePage } from "../modules/hr/pages/LeavePage";
import { TrainingPage } from "../modules/training/pages/TrainingPage";
import { AnnouncementsPage } from "../modules/communication/pages/AnnouncementsPage";
import { MyDayPage } from "../modules/visits/pages/MyDayPage";
import { FamilyPortalPage } from "../modules/family-portal/pages/FamilyPortalPage";
import { TodayPage } from "../modules/analytics/pages/TodayPage";
import { OperationsDashboardPage } from "../modules/analytics/pages/OperationsDashboardPage";
import { LiveMapPage } from "../modules/tracking/pages/LiveMapPage";
import { CompliancePage } from "../modules/compliance/pages/CompliancePage";
import { ReportsPage } from "../modules/reports/pages/ReportsPage";
import { SystemSettingsPage } from "../modules/settings/pages/SystemSettingsPage";
import { UserRolesPermissionsPage } from "../modules/settings/pages/UserRolesPermissionsPage";
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

function Routing() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route
        path="/organizations"
        element={
          <ProtectedRoute roles={PLATFORM_ADMIN_ROLES}>
            <OrganizationsPage />
          </ProtectedRoute>
        }
      />
      <Route
        path="/organizations/:tenantId"
        element={
          <ProtectedRoute roles={PLATFORM_ADMIN_ROLES}>
            <TenantDetailPage />
          </ProtectedRoute>
        }
      />
      <Route
        path="/audit-log"
        element={
          <ProtectedRoute roles={AUDIT_ROLES}>
            <AuditLogPage />
          </ProtectedRoute>
        }
      />
      <Route
        path="/service-users"
        element={
          <ProtectedRoute>
            <ServiceUsersPage />
          </ProtectedRoute>
        }
      />
      <Route
        path="/service-users/:serviceUserId"
        element={
          <ProtectedRoute>
            <ServiceUserDetailPage />
          </ProtectedRoute>
        }
      />
      <Route
        path="/assessment-templates"
        element={
          <ProtectedRoute>
            <AssessmentTemplatesPage />
          </ProtectedRoute>
        }
      />
      <Route
        path="/staff"
        element={
          <ProtectedRoute roles={STAFF_ROLES}>
            <StaffPage />
          </ProtectedRoute>
        }
      />
      <Route
        path="/visits/route"
        element={
          <ProtectedRoute>
            <RoutePage />
          </ProtectedRoute>
        }
      />
      <Route
        path="/visits/:visitId"
        element={
          <ProtectedRoute>
            <VisitDetailPage />
          </ProtectedRoute>
        }
      />
      <Route
        path="/visits"
        element={
          <ProtectedRoute>
            <VisitsPage />
          </ProtectedRoute>
        }
      />
      <Route
        path="/schedule"
        element={
          <ProtectedRoute roles={ROSTERING_ROLES}>
            <SchedulePage />
          </ProtectedRoute>
        }
      />
      <Route
        path="/rostering"
        element={
          <ProtectedRoute roles={ROSTERING_ROLES}>
            <RosteringPage />
          </ProtectedRoute>
        }
      />
      <Route
        path="/incidents"
        element={
          <ProtectedRoute>
            <IncidentsPage />
          </ProtectedRoute>
        }
      />
      <Route
        path="/safeguarding"
        element={
          <ProtectedRoute roles={SAFEGUARDING_ROLES}>
            <SafeguardingPage />
          </ProtectedRoute>
        }
      />
      <Route
        path="/billing"
        element={
          <ProtectedRoute roles={FINANCE_ROLES}>
            <BillingPage />
          </ProtectedRoute>
        }
      />
      <Route
        path="/payroll"
        element={
          <ProtectedRoute roles={PAYROLL_ROLES}>
            <PayrollPage />
          </ProtectedRoute>
        }
      />
      <Route
        path="/leave"
        element={
          <ProtectedRoute>
            <LeavePage />
          </ProtectedRoute>
        }
      />
      <Route
        path="/training"
        element={
          <ProtectedRoute>
            <TrainingPage />
          </ProtectedRoute>
        }
      />
      <Route
        path="/announcements"
        element={
          <ProtectedRoute>
            <AnnouncementsPage />
          </ProtectedRoute>
        }
      />
      <Route
        path="/my-day"
        element={
          <ProtectedRoute>
            <MyDayPage />
          </ProtectedRoute>
        }
      />
      <Route
        path="/family-portal"
        element={
          <FamilyPortalRoute>
            <FamilyPortalPage />
          </FamilyPortalRoute>
        }
      />
      <Route
        path="/today"
        element={
          <ProtectedRoute roles={ANALYTICS_ROLES}>
            <TodayPage />
          </ProtectedRoute>
        }
      />
      <Route
        path="/operations-dashboard"
        element={
          <ProtectedRoute roles={ANALYTICS_ROLES}>
            <OperationsDashboardPage />
          </ProtectedRoute>
        }
      />
      <Route
        path="/live-map"
        element={
          <ProtectedRoute roles={TRACKING_ROLES}>
            <LiveMapPage />
          </ProtectedRoute>
        }
      />
      <Route
        path="/compliance"
        element={
          <ProtectedRoute roles={COMPLIANCE_ROLES}>
            <CompliancePage />
          </ProtectedRoute>
        }
      />
      <Route
        path="/reports"
        element={
          <ProtectedRoute roles={REPORT_ROLES}>
            <ReportsPage />
          </ProtectedRoute>
        }
      />
      <Route
        path="/settings"
        element={
          <ProtectedRoute roles={ADMINISTRATION_ROLES}>
            <SystemSettingsPage />
          </ProtectedRoute>
        }
      />
      <Route
        path="/roles-permissions"
        element={
          <ProtectedRoute roles={ADMINISTRATION_ROLES}>
            <UserRolesPermissionsPage />
          </ProtectedRoute>
        }
      />
      <Route path="/" element={<HomeRoute />} />
      <Route path="*" element={<DefaultRedirect />} />
    </Routes>
  );
}

export function AppRouter() {
  return (
    <Providers>
      <Routing />
    </Providers>
  );
}
