import { useState, type FormEvent } from "react";
import { Link } from "react-router-dom";
import {
  Alert,
  Button,
  Card,
  CardBody,
  CardHeader,
  EmptyState,
  FormField,
  Input,
  Modal,
  Select,
  StatTile,
  StatusBadge,
} from "../../../design-system";
import { apiErrorMessage } from "../../../lib/api-error";
import { useServiceUsers } from "../../service-users/api";
import { useStaff } from "../../staff/api";
import { useCreateVisit, type CreateVisitInput } from "../../visits/api";
import type { Visit, VisitStatus } from "../../../lib/types";
import { useClientSnapshot, useToday } from "../api";
import { todayIso } from "../../../lib/dates";

type DisplayStatus = "due" | "in_progress" | "late" | "done" | "missed" | "cancelled";

const STATUS_META: Record<DisplayStatus, { label: string; dot: string; card: string; badge: "info" | "warning" | "danger" | "success" | "neutral" }> = {
  due: { label: "Due", dot: "bg-sky", card: "bg-skytint", badge: "info" },
  in_progress: { label: "In progress", dot: "bg-amber", card: "bg-ambertint", badge: "warning" },
  late: { label: "Late", dot: "bg-coral", card: "bg-coraltint", badge: "danger" },
  done: { label: "Completed", dot: "bg-lime", card: "bg-limetint", badge: "success" },
  missed: { label: "Missed", dot: "bg-coral", card: "bg-coraltint", badge: "danger" },
  cancelled: { label: "Cancelled", dot: "bg-inksoft", card: "bg-paper", badge: "neutral" },
};

function deriveDisplayStatus(visit: Visit, isToday: boolean): DisplayStatus {
  const map: Record<VisitStatus, DisplayStatus> = {
    scheduled: "due",
    in_progress: "in_progress",
    completed: "done",
    missed: "missed",
    cancelled: "cancelled",
  };

  if (isToday && visit.status === "scheduled" && !visit.check_in_at) {
    const [hours, minutes] = visit.start_time.split(":").map(Number);
    const startAt = new Date();
    startAt.setHours(hours, minutes, 0, 0);
    if (new Date() > startAt) {
      return "late";
    }
  }

  return map[visit.status];
}

function Legend() {
  const items: DisplayStatus[] = ["due", "in_progress", "late", "done"];
  return (
    <div className="flex flex-wrap items-center gap-3 text-xs text-inksoft">
      {items.map((key) => (
        <span key={key} className="flex items-center gap-1.5">
          <span className={`h-2 w-2 rounded-full ${STATUS_META[key].dot}`} />
          {STATUS_META[key].label}
        </span>
      ))}
    </div>
  );
}

function VisitRow({ visit, isToday, isSelected, onSelect }: { visit: Visit; isToday: boolean; isSelected: boolean; onSelect: () => void }) {
  const displayStatus = deriveDisplayStatus(visit, isToday);
  const meta = STATUS_META[displayStatus];

  return (
    <button
      type="button"
      onClick={onSelect}
      className={`block w-full rounded-2xl border p-4 text-left transition-colors duration-150 ${meta.card} ${
        isSelected ? "border-teal/60 ring-2 ring-teal/20" : "border-transparent"
      }`}
    >
      <div className="flex items-start justify-between gap-3">
        <div>
          <div className="flex items-center gap-2">
            <span className={`h-2.5 w-2.5 rounded-full ${meta.dot}`} />
            <span className="text-sm text-inksoft">{visit.start_time}</span>
          </div>
          <div className="mt-1 font-semibold text-ink">
            {visit.service_user_name ?? "—"}
            {visit.carer_name && <span className="font-normal text-inksoft"> · {visit.carer_name}</span>}
          </div>
        </div>
        <StatusBadge label={meta.label} tone={meta.badge} />
      </div>
    </button>
  );
}

function ClientPanel({ serviceUserId }: { serviceUserId: number }) {
  const { data, isLoading } = useClientSnapshot(serviceUserId);

  if (isLoading || !data) {
    return (
      <Card>
        <CardBody>Loading…</CardBody>
      </Card>
    );
  }

  const { service_user: serviceUser, care_plan_sections: sections, medications } = data;
  const initials = `${serviceUser.first_name[0] ?? ""}${serviceUser.last_name[0] ?? ""}`.toUpperCase();
  const riskTags = [...(serviceUser.medical_conditions ?? []), ...(serviceUser.disabilities ?? [])];

  return (
    <Card>
      <CardBody>
        <div className="flex items-center gap-3">
          <span className="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-tealtint text-lg font-bold text-teal">
            {initials}
          </span>
          <div>
            <div className="font-display text-lg font-bold text-ink">
              {serviceUser.first_name} {serviceUser.last_name}
            </div>
            <div className="text-sm text-inksoft">{serviceUser.address ?? "No address on file"}</div>
          </div>
        </div>

        {riskTags.length > 0 && (
          <div className="mt-3 flex flex-wrap gap-1.5">
            {riskTags.map((tag) => (
              <StatusBadge key={tag} label={tag} tone="warning" />
            ))}
          </div>
        )}

        <div className="mt-5">
          <h3 className="mb-2 text-xs font-semibold uppercase tracking-wide text-inksoft">Care plan tasks</h3>
          {sections.length === 0 ? (
            <p className="text-sm text-inksoft">No active care plan.</p>
          ) : (
            <ul className="space-y-2">
              {sections.map((section) => (
                <li key={section.id} className="flex items-center gap-2 text-sm">
                  {section.status === "met" ? (
                    <span className="flex h-4 w-4 items-center justify-center rounded-full bg-lime text-[10px] font-bold text-white">✓</span>
                  ) : section.status === "ongoing" ? (
                    <span className="h-4 w-4 rounded-full border-2 border-amber" />
                  ) : (
                    <span className="h-4 w-4 rounded-full border-2 border-line" />
                  )}
                  <span className={section.status === "met" ? "text-inksoft line-through" : "text-ink"}>
                    {section.goal}
                  </span>
                </li>
              ))}
            </ul>
          )}
        </div>

        <div className="mt-5">
          <h3 className="mb-2 text-xs font-semibold uppercase tracking-wide text-inksoft">Medication · MAR</h3>
          {medications.length === 0 ? (
            <p className="text-sm text-inksoft">No active medications.</p>
          ) : (
            <ul className="space-y-2">
              {medications.map((medication) => (
                <li key={medication.id} className="flex items-center justify-between text-sm">
                  <span className="text-ink">
                    {medication.name} {medication.dose}
                  </span>
                  {medication.latest_administration ? (
                    <StatusBadge
                      label={medication.latest_administration.status.replaceAll("_", " ")}
                      tone={medication.latest_administration.status === "administered" ? "success" : "warning"}
                    />
                  ) : (
                    <StatusBadge label="not recorded" tone="neutral" />
                  )}
                </li>
              ))}
            </ul>
          )}
        </div>

        <Link to={`/service-users/${serviceUser.id}`}>
          <Button className="mt-6 w-full">Open full care plan</Button>
        </Link>
      </CardBody>
    </Card>
  );
}

const EMPTY_VISIT_FORM = {
  service_user_id: 0,
  carer_id: null as number | null,
  start_time: "",
  end_time: "",
};

export function TodayPage() {
  const [date, setDate] = useState(todayIso());
  const [selectedVisit, setSelectedVisit] = useState<Visit | null>(null);
  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [form, setForm] = useState(EMPTY_VISIT_FORM);
  const [createError, setCreateError] = useState<string | null>(null);

  const { data, isLoading } = useToday(date);
  const { data: serviceUsers } = useServiceUsers(1);
  const { data: staff } = useStaff(1);
  const createVisit = useCreateVisit();

  const isToday = date === todayIso();

  async function handleCreate(event: FormEvent) {
    event.preventDefault();
    setCreateError(null);
    const input: CreateVisitInput = {
      service_user_id: form.service_user_id,
      carer_id: form.carer_id,
      visit_date: date,
      start_time: form.start_time,
      end_time: form.end_time,
    };
    try {
      await createVisit.mutateAsync(input);
      setIsCreateOpen(false);
      setForm(EMPTY_VISIT_FORM);
    } catch (err) {
      setCreateError(apiErrorMessage(err, "Could not create the visit. Please try again."));
    }
  }

  const dateLabel = new Date(`${date}T00:00:00`).toLocaleDateString(undefined, {
    weekday: "long",
    day: "numeric",
    month: "long",
    year: "numeric",
  });

  return (
    <div>
      <div className="mb-6 flex flex-wrap items-start justify-between gap-4 rounded-2xl border border-line bg-white px-5 py-4">
        <div>
          <p className="font-mono text-xs uppercase tracking-wide text-inksoft">{dateLabel}</p>
          <h1 className="mt-1 font-display text-3xl font-bold tracking-tight text-ink">Today's visit timeline</h1>
        </div>
        <div className="flex items-center gap-2">
          <Input type="date" value={date} onChange={(e) => setDate(e.target.value)} className="w-auto rounded-full" />
          <Button onClick={() => setIsCreateOpen(true)}>New visit</Button>
        </div>
      </div>

      {!isLoading && data && (
        <div className="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
          <StatTile
            label="Training expiry"
            value={String(data.stats.training_expiring_soon.count)}
            description={
              data.stats.training_expiring_soon.soonest_expiry_date
                ? `need refresher by ${data.stats.training_expiring_soon.soonest_expiry_date}`
                : "none due soon"
            }
            tone="amber"
          />
          <StatTile
            label="Missed visits"
            value={String(data.stats.missed_visits_this_week)}
            description="this week"
            tone="coral"
          />
          <StatTile label="Incidents" value={String(data.stats.open_incidents)} description="open, non-serious" tone="sky" />
          <StatTile
            label="MAR accuracy"
            value={data.stats.mar_accuracy_pct !== null ? `${data.stats.mar_accuracy_pct}%` : "—"}
            description="rolling 30 days"
            tone="lime"
          />
          <StatTile
            label="Rota coverage"
            value={data.stats.rota_coverage_pct !== null ? `${data.stats.rota_coverage_pct}%` : "—"}
            description="of visits this week"
            tone="teal"
          />
        </div>
      )}

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-5">
        <div className="lg:col-span-3">
          <Card>
            <CardHeader>
              <div className="flex items-center justify-between">
                <span>Visits</span>
                <Legend />
              </div>
            </CardHeader>
            <CardBody>
              {!isLoading && (data?.visits ?? []).length === 0 ? (
                <EmptyState message="No visits scheduled for this date." />
              ) : (
                <div className="space-y-3">
                  {(data?.visits ?? []).map((visit) => (
                    <VisitRow
                      key={visit.id}
                      visit={visit}
                      isToday={isToday}
                      isSelected={selectedVisit?.id === visit.id}
                      onSelect={() => setSelectedVisit(visit)}
                    />
                  ))}
                </div>
              )}
            </CardBody>
          </Card>
        </div>

        <div className="lg:col-span-2">
          {selectedVisit ? (
            <ClientPanel serviceUserId={selectedVisit.service_user_id} />
          ) : (
            <EmptyState message="Select a visit to see the client's details." />
          )}
        </div>
      </div>

      <Modal
        isOpen={isCreateOpen}
        onClose={() => {
          setIsCreateOpen(false);
          setCreateError(null);
        }}
        title="New Visit"
        footer={
          <>
            <Button variant="secondary" onClick={() => setIsCreateOpen(false)}>
              Cancel
            </Button>
            <Button form="new-today-visit-form" type="submit" isLoading={createVisit.isPending}>
              Create
            </Button>
          </>
        }
      >
        <form id="new-today-visit-form" onSubmit={handleCreate}>
          {createError && (
            <div className="mb-4">
              <Alert tone="danger">{createError}</Alert>
            </div>
          )}
          <FormField label="Service user" htmlFor="today-visit-service-user">
            <Select
              id="today-visit-service-user"
              required
              value={form.service_user_id || ""}
              onChange={(e) => setForm({ ...form, service_user_id: Number(e.target.value) })}
            >
              <option value="" disabled>
                Select a service user
              </option>
              {(serviceUsers?.data ?? []).map((su) => (
                <option key={su.id} value={su.id}>
                  {su.first_name} {su.last_name}
                </option>
              ))}
            </Select>
          </FormField>
          <FormField label="Carer" htmlFor="today-visit-carer">
            <Select
              id="today-visit-carer"
              value={form.carer_id ?? ""}
              onChange={(e) => setForm({ ...form, carer_id: e.target.value ? Number(e.target.value) : null })}
            >
              <option value="">Unassigned</option>
              {(staff?.data ?? []).map((s) => (
                <option key={s.id} value={s.user_id}>
                  {s.name}
                </option>
              ))}
            </Select>
          </FormField>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <FormField label="Start time" htmlFor="today-visit-start">
              <Input
                id="today-visit-start"
                type="time"
                required
                value={form.start_time}
                onChange={(e) => setForm({ ...form, start_time: e.target.value })}
              />
            </FormField>
            <FormField label="End time" htmlFor="today-visit-end">
              <Input
                id="today-visit-end"
                type="time"
                required
                value={form.end_time}
                onChange={(e) => setForm({ ...form, end_time: e.target.value })}
              />
            </FormField>
          </div>
        </form>
      </Modal>
    </div>
  );
}
