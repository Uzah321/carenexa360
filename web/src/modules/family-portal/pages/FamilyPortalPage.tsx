import { useState } from "react";
import { Card, CardBody, CardHeader, EmptyState, StatusBadge } from "../../../design-system";
import { downloadDocument } from "../../documents/api";
import { useFamilyServiceUserDetail, useFamilyServiceUsers } from "../api";
import type { ServiceUser, Visit, VisitStatus } from "../../../lib/types";
import { todayIso } from "../../../lib/dates";

const VISIT_STATUS_TONE: Record<VisitStatus, "success" | "warning" | "danger" | "neutral" | "info"> = {
  scheduled: "info",
  in_progress: "warning",
  completed: "success",
  missed: "danger",
  cancelled: "neutral",
};

function TodayCard({ visits }: { visits: Visit[] }) {
  const todaysVisits = visits.filter((visit) => visit.visit_date === todayIso());

  return (
    <Card className="border-teal/30 bg-tealtint/30">
      <CardHeader>Today</CardHeader>
      <CardBody>
        {todaysVisits.length === 0 ? (
          <p className="text-sm text-inksoft">No visit scheduled today.</p>
        ) : (
          <ul className="space-y-2">
            {todaysVisits.map((visit) => (
              <li
                key={visit.id}
                className="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-white px-4 py-3"
              >
                <div>
                  <p className="font-semibold text-ink">{visit.carer_name ?? "Carer not yet assigned"}</p>
                  <p className="text-sm text-inksoft">
                    {visit.start_time}–{visit.end_time}
                  </p>
                </div>
                <StatusBadge label={visit.status.replaceAll("_", " ")} tone={VISIT_STATUS_TONE[visit.status]} />
              </li>
            ))}
          </ul>
        )}
      </CardBody>
    </Card>
  );
}

function ServiceUserPicker({ serviceUsers, onSelect }: { serviceUsers: ServiceUser[]; onSelect: (id: number) => void }) {
  return (
    <div>
      <h1 className="mb-4 text-xl font-bold text-ink">Who would you like to view?</h1>
      <div className="space-y-3">
        {serviceUsers.map((su) => (
          <button
            key={su.id}
            type="button"
            onClick={() => onSelect(su.id)}
            className="block w-full rounded-xl border border-line bg-white px-5 py-4 text-left transition-colors duration-150 hover:border-teal hover:bg-tealtint"
          >
            <div className="font-semibold text-ink">
              {su.first_name} {su.last_name}
            </div>
            <div className="text-sm text-inksoft">{su.address ?? "No address on file"}</div>
          </button>
        ))}
      </div>
    </div>
  );
}

function FamilyServiceUserDetail({ serviceUserId, onBack }: { serviceUserId: number; onBack?: () => void }) {
  const { data, isLoading } = useFamilyServiceUserDetail(serviceUserId);

  if (isLoading || !data) {
    return (
      <Card>
        <CardBody>Loading…</CardBody>
      </Card>
    );
  }

  const { service_user: serviceUser, care_plan: carePlan, upcoming_visits: upcomingVisits, recent_visits: recentVisits, documents, incidents } = data;
  // Today's own visit(s) are already surfaced prominently in TodayCard —
  // this list is specifically for "what's coming after today".
  const futureVisits = upcomingVisits.filter((visit) => visit.visit_date !== todayIso());

  return (
    <div className="space-y-6">
      {onBack && (
        <button type="button" onClick={onBack} className="text-sm font-medium text-teal hover:text-teal/90">
          ← Back to list
        </button>
      )}

      <div>
        <h1 className="text-2xl font-extrabold tracking-tight text-ink">
          {serviceUser.first_name} {serviceUser.last_name}
        </h1>
        <p className="mt-1 text-sm text-inksoft">{serviceUser.address}</p>
      </div>

      <TodayCard visits={upcomingVisits} />

      <Card>
        <CardHeader>Future Visits</CardHeader>
        <CardBody>
          {futureVisits.length === 0 ? (
            <EmptyState message="No further visits scheduled yet." />
          ) : (
            <ul className="divide-y divide-line">
              {futureVisits.map((visit) => (
                <li key={visit.id} className="flex items-center justify-between py-2 text-sm">
                  <span>
                    {visit.visit_date} · {visit.start_time}–{visit.end_time}
                  </span>
                  <span className="text-inksoft">{visit.carer_name ?? "Unassigned"}</span>
                </li>
              ))}
            </ul>
          )}
        </CardBody>
      </Card>

      <Card>
        <CardHeader>Recent Visits</CardHeader>
        <CardBody>
          {recentVisits.length === 0 ? (
            <EmptyState message="No visits recorded yet." />
          ) : (
            <ul className="divide-y divide-line">
              {recentVisits.map((visit) => (
                <li key={visit.id} className="flex items-center justify-between py-2 text-sm">
                  <span>
                    {visit.visit_date} · {visit.start_time}–{visit.end_time}
                  </span>
                  <StatusBadge label={visit.status.replaceAll("_", " ")} tone={visit.status === "completed" ? "success" : "neutral"} />
                </li>
              ))}
            </ul>
          )}
        </CardBody>
      </Card>

      {carePlan && (
        <Card>
          <CardHeader>Care Plan Summary</CardHeader>
          <CardBody>
            <ul className="space-y-3">
              {carePlan.sections.map((section) => (
                <li key={section.id} className="border-b border-line pb-3 last:border-0">
                  <div className="text-sm font-semibold text-ink">{section.area.replaceAll("_", " ")}</div>
                  <div className="text-sm text-inksoft">{section.goal}</div>
                </li>
              ))}
            </ul>
          </CardBody>
        </Card>
      )}

      <Card>
        <CardHeader>Shared Documents</CardHeader>
        <CardBody>
          {documents.length === 0 ? (
            <EmptyState message="No documents have been shared yet." />
          ) : (
            <ul className="divide-y divide-line">
              {documents.map((doc) => (
                <li key={doc.id} className="flex items-center justify-between py-2 text-sm">
                  <span>{doc.original_filename}</span>
                  <button
                    type="button"
                    className="font-medium text-teal hover:text-teal/90"
                    onClick={() => downloadDocument(doc.id, doc.original_filename)}
                  >
                    Download
                  </button>
                </li>
              ))}
            </ul>
          )}
        </CardBody>
      </Card>

      <Card>
        <CardHeader>Incidents</CardHeader>
        <CardBody>
          {incidents.length === 0 ? (
            <EmptyState message="No incidents on record." />
          ) : (
            <ul className="space-y-3">
              {incidents.map((incident) => (
                <li key={incident.id} className="border-b border-line pb-3 last:border-0">
                  <div className="flex items-center justify-between">
                    <span className="text-sm font-semibold text-ink">{incident.type.replaceAll("_", " ")}</span>
                    <StatusBadge label={incident.severity} tone={incident.severity === "low" ? "neutral" : "warning"} />
                  </div>
                  <div className="text-sm text-inksoft">{incident.description}</div>
                </li>
              ))}
            </ul>
          )}
        </CardBody>
      </Card>
    </div>
  );
}

export function FamilyPortalPage() {
  const { data: serviceUsers, isLoading } = useFamilyServiceUsers();
  const [selectedId, setSelectedId] = useState<number | null>(null);

  if (isLoading) {
    return (
      <Card>
        <CardBody>Loading…</CardBody>
      </Card>
    );
  }

  if (!serviceUsers || serviceUsers.length === 0) {
    return (
      <EmptyState message="No linked profile yet. Please contact your care provider to set up portal access." />
    );
  }

  if (serviceUsers.length === 1) {
    return <FamilyServiceUserDetail serviceUserId={serviceUsers[0].id} />;
  }

  if (selectedId) {
    return <FamilyServiceUserDetail serviceUserId={selectedId} onBack={() => setSelectedId(null)} />;
  }

  return <ServiceUserPicker serviceUsers={serviceUsers} onSelect={setSelectedId} />;
}
