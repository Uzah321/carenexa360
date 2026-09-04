import { useState } from "react";
import { useParams } from "react-router-dom";
import { Card, CardBody, PageHeader, StatusBadge, Tabs } from "../../../design-system";
import { useServiceUser } from "../api";
import { OverviewTab } from "../components/OverviewTab";
import { CarePlanTab } from "../components/CarePlanTab";
import { AssessmentsTab } from "../components/AssessmentsTab";
import { DocumentsTab } from "../components/DocumentsTab";
import { MedicationsTab } from "../components/MedicationsTab";
import { ObservationsTab } from "../components/ObservationsTab";
import { IncidentsTab } from "../components/IncidentsTab";

const STATUS_TONE = {
  active: "success",
  inactive: "neutral",
  discharged: "warning",
} as const;

export function ServiceUserDetailPage() {
  const { serviceUserId } = useParams<{ serviceUserId: string }>();
  const id = Number(serviceUserId);
  const [tab, setTab] = useState("overview");

  const { data: serviceUser, isLoading } = useServiceUser(id);

  if (isLoading || !serviceUser) {
    return (
      <Card>
        <CardBody>Loading…</CardBody>
      </Card>
    );
  }

  return (
    <div>
      <PageHeader
        title={`${serviceUser.first_name} ${serviceUser.last_name}`}
        breadcrumbs={[
          { label: "Service Users", to: "/service-users" },
          { label: `${serviceUser.first_name} ${serviceUser.last_name}` },
        ]}
        actions={<StatusBadge label={serviceUser.status} tone={STATUS_TONE[serviceUser.status]} />}
      />
      <Tabs
        items={[
          { key: "overview", label: "Overview" },
          { key: "care-plan", label: "Care Plan" },
          { key: "assessments", label: "Assessments" },
          { key: "medications", label: "Medications" },
          { key: "observations", label: "Observations" },
          { key: "incidents", label: "Incidents" },
          { key: "documents", label: "Documents" },
        ]}
        activeKey={tab}
        onChange={setTab}
      />
      <div className="mt-4">
        {tab === "overview" && <OverviewTab serviceUser={serviceUser} />}
        {tab === "care-plan" && <CarePlanTab serviceUserId={id} />}
        {tab === "assessments" && <AssessmentsTab serviceUserId={id} />}
        {tab === "medications" && <MedicationsTab serviceUserId={id} />}
        {tab === "observations" && <ObservationsTab serviceUserId={id} />}
        {tab === "incidents" && <IncidentsTab serviceUserId={id} />}
        {tab === "documents" && <DocumentsTab serviceUserId={id} />}
      </div>
    </div>
  );
}
