import { useEffect, useMemo, useRef, useState } from "react";
import { Alert, Card, FormField, Input, PageHeader, Select } from "../../../design-system";
import { useAuth } from "../../../lib/auth-context";
import { deliversVisits } from "../../../lib/types";
import type { Visit, VisitStatus } from "../../../lib/types";
import { useBranches } from "../../organization/api";
import { useStaff } from "../../staff/api";
import { useServiceUsers } from "../../service-users/api";
import { AssignTaskModal } from "../components/AssignTaskModal";
import { EditVisitModal } from "../components/EditVisitModal";
import { useRescheduleVisit, useVisits } from "../api";
import { addDays, todayIso } from "../../../lib/dates";

const HOURS = Array.from({ length: 24 }, (_, i) => i);
const MINUTES_PER_DAY = 24 * 60;
const PX_PER_MINUTE = 1; // 1 hour column = 60px — keeps the minutes-to-pixels math trivial.
const NAME_COLUMN_WIDTH = 200;
const ROW_HEIGHT = 60;
const HEADER_HEIGHT = 36;
const SNAP_MINUTES = 15; // drag/resize snaps to quarter-hour increments
const CLICK_THRESHOLD_PX = 5; // pointer movement below this counts as a click, not a drag

const STATUS_STYLE: Record<VisitStatus, { bg: string; text: string; border: string }> = {
  scheduled: { bg: "bg-skytint", text: "text-sky", border: "border-sky" },
  in_progress: { bg: "bg-ambertint", text: "text-amber", border: "border-amber" },
  completed: { bg: "bg-limetint", text: "text-lime", border: "border-lime" },
  missed: { bg: "bg-coraltint", text: "text-coral", border: "border-coral" },
  cancelled: { bg: "bg-paper", text: "text-inksoft", border: "border-line" },
};

const LEGEND: { status: VisitStatus; label: string }[] = [
  { status: "scheduled", label: "Scheduled" },
  { status: "in_progress", label: "In progress" },
  { status: "completed", label: "Completed" },
  { status: "missed", label: "Missed" },
  { status: "cancelled", label: "Cancelled" },
];

function timeToMinutes(time: string): number {
  const [h, m] = time.split(":").map(Number);
  return h * 60 + m;
}

function minutesToTimeString(minutes: number): string {
  const clamped = Math.max(0, Math.min(MINUTES_PER_DAY - 1, Math.round(minutes)));
  const h = Math.floor(clamped / 60);
  const m = clamped % 60;
  return `${String(h).padStart(2, "0")}:${String(m).padStart(2, "0")}`;
}

interface CarerDragInfo {
  userId: number;
  name: string;
  x: number;
  y: number;
  moved: boolean;
}

interface DragInfo {
  visit: Visit;
  mode: "move" | "resize";
  pointerStartX: number;
  pointerStartY: number;
  originalStart: number;
  originalEnd: number;
  originalRowIndex: number;
  currentStart: number;
  currentEnd: number;
  currentRowIndex: number;
  moved: boolean;
}

export function SchedulePage() {
  const { user } = useAuth();
  const [date, setDate] = useState(todayIso);
  // Two ways to read the same day: "client" asks whether everyone's care is
  // covered, "carer" asks whether anyone's round is overloaded.
  const [view, setView] = useState<"client" | "carer">("client");
  const [clientSearch, setClientSearch] = useState("");
  const [branchId, setBranchId] = useState<number | null>(null);
  const [editingVisit, setEditingVisit] = useState<Visit | null>(null);
  const [assigningCarer, setAssigningCarer] = useState<{ user_id: number; name: string } | null>(null);
  // Set when a carer is dropped on empty space in a client's row.
  const [assignDraft, setAssignDraft] = useState<{
    carer: { user_id: number; name: string };
    serviceUserId: number;
    startTime: string;
  } | null>(null);
  const [dragError, setDragError] = useState<string | null>(null);
  const [dragPreview, setDragPreview] = useState<DragInfo | null>(null);

  // Source of truth inside the pointer handlers below (avoids stale closures
  // in the mount-once effect). `dragPreview` state is a snapshot of it kept
  // in sync purely so the render below has something reactive to read —
  // render must never read the ref directly.
  const dragRef = useRef<DragInfo | null>(null);

  // Dragging a *carer* onto a visit is a separate gesture from dragging a visit
  // around, so it gets its own ref/state pair rather than overloading DragInfo.
  const carerDragRef = useRef<CarerDragInfo | null>(null);
  const [carerDrag, setCarerDrag] = useState<CarerDragInfo | null>(null);
  // A drag that ends back on the chip still fires a click; this stops that
  // click from also opening the Assign modal.
  const suppressChipClickRef = useRef(false);

  const { data: branches } = useBranches(user?.tenant_id ?? 0);
  const { data: staff } = useStaff(1, 100);
  const { data: visitsData } = useVisits({ date, per_page: 100 });
  const { data: serviceUsersData } = useServiceUsers(1, { status: "active", perPage: 200 });
  const rescheduleVisit = useRescheduleVisit();

  // Memoized so the `?? []` fallback doesn't hand every dependent useMemo a
  // brand-new array identity on each render and defeat their caching.
  const visits = useMemo(() => visitsData?.data ?? [], [visitsData]);

  // Anyone already holding a visit today keeps a row even if their role isn't a
  // visit-delivering one, so a scheduled visit can never become invisible.
  const carerIdsWithVisitsToday = useMemo(
    () => new Set(visits.map((v) => v.carer_id).filter((id): id is number => id !== null)),
    [visits],
  );

  const carers = useMemo(
    () =>
      (staff?.data ?? [])
        .filter((s) => s.employment_status !== "inactive")
        .filter((s) => !branchId || s.branch_id === branchId)
        .filter((s) => deliversVisits(s.roles) || carerIdsWithVisitsToday.has(s.user_id))
        .sort((a, b) => a.name.localeCompare(b.name)),
    [staff, branchId, carerIdsWithVisitsToday],
  );
  const visitsByCarer = useMemo(() => {
    const map = new Map<number | "unassigned", Visit[]>();
    for (const visit of visits) {
      const key = visit.carer_id ?? "unassigned";
      map.set(key, [...(map.get(key) ?? []), visit]);
    }
    return map;
  }, [visits]);

  const clients = useMemo(
    () =>
      (serviceUsersData?.data ?? [])
        .filter((su) => !branchId || su.branch_id === branchId)
        .sort((a, b) => `${a.last_name} ${a.first_name}`.localeCompare(`${b.last_name} ${b.first_name}`)),
    [serviceUsersData, branchId],
  );

  const visibleClients = useMemo(() => {
    const q = clientSearch.trim().toLowerCase();
    if (!q) return clients;
    return clients.filter((c) => `${c.first_name} ${c.last_name}`.toLowerCase().includes(q));
  }, [clients, clientSearch]);

  const visitsByClient = useMemo(() => {
    const map = new Map<number, Visit[]>();
    for (const visit of visits) {
      map.set(visit.service_user_id, [...(map.get(visit.service_user_id) ?? []), visit]);
    }
    return map;
  }, [visits]);

  // How many visits each carer already holds today — shown on their chip so you
  // can see who is filling up before handing them another one.
  const loadByCarer = useMemo(() => {
    const map = new Map<number, number>();
    for (const visit of visits) {
      if (visit.carer_id === null) continue;
      if (visit.status === "cancelled") continue;
      map.set(visit.carer_id, (map.get(visit.carer_id) ?? 0) + 1);
    }
    return map;
  }, [visits]);

  // The pool keeps every carer all day — a carer does several visits, so they
  // have to stay draggable after the first one.
  const carerPool = useMemo(
    () =>
      (staff?.data ?? [])
        .filter((s) => s.employment_status !== "inactive")
        .filter((s) => !branchId || s.branch_id === branchId)
        .filter((s) => deliversVisits(s.roles))
        .sort((a, b) => a.name.localeCompare(b.name)),
    [staff, branchId],
  );

  const unassignedVisits = visitsByCarer.get("unassigned") ?? [];
  // Only people who actually deliver visits are worth offering an assignment to.
  const carersWithoutVisits = useMemo(
    () =>
      carers.filter(
        (carer) => deliversVisits(carer.roles) && (visitsByCarer.get(carer.user_id) ?? []).length === 0,
      ),
    [carers, visitsByCarer],
  );
  const isToday = date === todayIso();
  const nowMinutes = new Date().getHours() * 60 + new Date().getMinutes();

  const timelineWidth = MINUTES_PER_DAY * PX_PER_MINUTE;

  // Row 0 = the always-present "Unassigned" row, rows 1..n = carers in display order.
  // Visit.carer_id references users.id, which is `user_id` here — a staff
  // profile's own `id` is a different primary key and must never be used to
  // match a visit to a row.
  const rowTargets = useMemo<(number | null)[]>(() => [null, ...carers.map((c) => c.user_id)], [carers]);

  // Keep "live" refs so the mount-once pointer effect below always sees fresh
  // data without needing to re-subscribe its window listeners on every render.
  const rowTargetsRef = useRef(rowTargets);
  const rescheduleRef = useRef(rescheduleVisit);
  const viewRef = useRef(view);
  useEffect(() => {
    rowTargetsRef.current = rowTargets;
    rescheduleRef.current = rescheduleVisit;
    viewRef.current = view;
  });

  function hasConflict(carerId: number | null, start: number, end: number, excludeVisitId: number): boolean {
    if (carerId === null) return false;
    const rowVisits = visitsByCarer.get(carerId) ?? [];
    return rowVisits.some((v) => {
      if (v.id === excludeVisitId) return false;
      const vs = timeToMinutes(v.start_time);
      const ve = timeToMinutes(v.end_time);
      return start < ve && end > vs;
    });
  }

  function startDrag(e: React.PointerEvent, visit: Visit, mode: "move" | "resize", rowIndex: number) {
    e.stopPropagation();
    e.preventDefault();
    e.currentTarget.setPointerCapture(e.pointerId);
    const start = timeToMinutes(visit.start_time);
    const end = timeToMinutes(visit.end_time);
    dragRef.current = {
      visit,
      mode,
      pointerStartX: e.clientX,
      pointerStartY: e.clientY,
      originalStart: start,
      originalEnd: end,
      originalRowIndex: rowIndex,
      currentStart: start,
      currentEnd: end,
      currentRowIndex: rowIndex,
      moved: false,
    };
    setDragPreview({ ...dragRef.current });
  }

  function startCarerDrag(e: React.PointerEvent, carer: { user_id: number; name: string }) {
    e.preventDefault();
    carerDragRef.current = {
      userId: carer.user_id,
      name: carer.name,
      x: e.clientX,
      y: e.clientY,
      moved: false,
    };
    setCarerDrag({ ...carerDragRef.current });
  }

  // Drop a carer onto any visit block to hand that visit to them. Hit-testing
  // with elementFromPoint rather than per-block pointer handlers keeps the drop
  // target working no matter which row the visit is sitting in.
  useEffect(() => {
    function onCarerMove(e: PointerEvent) {
      const d = carerDragRef.current;
      if (!d) return;
      if (Math.abs(e.clientX - d.x) > CLICK_THRESHOLD_PX || Math.abs(e.clientY - d.y) > CLICK_THRESHOLD_PX) {
        d.moved = true;
      }
      d.x = e.clientX;
      d.y = e.clientY;
      setCarerDrag({ ...d });
    }

    function onCarerUp(e: PointerEvent) {
      const d = carerDragRef.current;
      if (!d) return;
      carerDragRef.current = null;
      setCarerDrag(null);

      // Barely moved — let it be a click, which opens the Assign modal instead.
      if (!d.moved) return;
      suppressChipClickRef.current = true;

      const under = document.elementFromPoint(e.clientX, e.clientY);
      const block = under?.closest("[data-visit-id]");

      if (!block) {
        // Dropped on open space in a client's row — there is no visit to take
        // over, so offer to create one for that client at the hour dropped on.
        const lane = under?.closest("[data-service-user-id]");
        if (!lane) return;
        const rect = lane.getBoundingClientRect();
        const minutes = Math.round((e.clientX - rect.left) / PX_PER_MINUTE / 60) * 60;
        setAssignDraft({
          carer: { user_id: d.userId, name: d.name },
          serviceUserId: Number(lane.getAttribute("data-service-user-id")),
          startTime: minutesToTimeString(Math.max(0, Math.min(23 * 60, minutes))),
        });
        return;
      }

      const visitId = Number(block.getAttribute("data-visit-id"));
      const currentCarerId = block.getAttribute("data-carer-id");
      if (!visitId || Number(currentCarerId) === d.userId) return;

      setDragError(null);
      rescheduleRef.current.mutate(
        { id: visitId, carer_id: d.userId },
        {
          onError: () => {
            setDragError(
              `Couldn't give that visit to ${d.name} — it clashes with another visit or shift they already have.`,
            );
          },
        },
      );
    }

    window.addEventListener("pointermove", onCarerMove);
    window.addEventListener("pointerup", onCarerUp);
    return () => {
      window.removeEventListener("pointermove", onCarerMove);
      window.removeEventListener("pointerup", onCarerUp);
    };
  }, []);

  useEffect(() => {
    function onMove(e: PointerEvent) {
      const d = dragRef.current;
      if (!d) return;
      const dx = e.clientX - d.pointerStartX;
      const dy = e.clientY - d.pointerStartY;
      if (Math.abs(dx) > CLICK_THRESHOLD_PX || Math.abs(dy) > CLICK_THRESHOLD_PX) {
        d.moved = true;
      }

      if (d.mode === "move") {
        const duration = d.originalEnd - d.originalStart;
        const rawStart = d.originalStart + dx / PX_PER_MINUTE;
        const snapped = Math.round(rawStart / SNAP_MINUTES) * SNAP_MINUTES;
        d.currentStart = Math.max(0, Math.min(MINUTES_PER_DAY - duration, snapped));
        d.currentEnd = d.currentStart + duration;

        // Rows only mean "carer" in the carer view. In the client view a row is
        // a person receiving care, and dragging a visit onto a different client
        // would silently move someone else's care — so the row is pinned there.
        if (viewRef.current === "carer") {
          const rowTargets = rowTargetsRef.current;
          const rowDelta = Math.round(dy / ROW_HEIGHT);
          d.currentRowIndex = Math.max(0, Math.min(rowTargets.length - 1, d.originalRowIndex + rowDelta));
        }
      } else {
        const rawEnd = d.originalEnd + dx / PX_PER_MINUTE;
        const snapped = Math.round(rawEnd / SNAP_MINUTES) * SNAP_MINUTES;
        d.currentEnd = Math.max(d.originalStart + SNAP_MINUTES, Math.min(MINUTES_PER_DAY, snapped));
      }

      setDragPreview({ ...d });
    }

    function onUp() {
      const d = dragRef.current;
      if (!d) return;
      dragRef.current = null;

      if (!d.moved) {
        // No meaningful movement — treat it as a click, same as before drag existed.
        setDragPreview(null);
        setEditingVisit(d.visit);
        return;
      }

      const rowTargets = rowTargetsRef.current;
      const patch =
        d.mode === "resize"
          ? { end_time: minutesToTimeString(d.currentEnd) }
          : {
              start_time: minutesToTimeString(d.currentStart),
              end_time: minutesToTimeString(d.currentEnd),
              // Only the carer view can reassign by dragging; see onMove above.
              ...(viewRef.current === "carer"
                ? { carer_id: rowTargets[d.currentRowIndex] ?? null }
                : {}),
            };

      setDragPreview(null);
      setDragError(null);
      rescheduleRef.current.mutate(
        { id: d.visit.id, ...patch },
        {
          onError: () => {
            setDragError("Couldn't reschedule that visit — the new slot conflicts with another visit for this carer.");
          },
        },
      );
    }

    window.addEventListener("pointermove", onMove);
    window.addEventListener("pointerup", onUp);
    return () => {
      window.removeEventListener("pointermove", onMove);
      window.removeEventListener("pointerup", onUp);
    };
  }, []);

  useEffect(() => {
    if (!dragError) return;
    const timer = setTimeout(() => setDragError(null), 5000);
    return () => clearTimeout(timer);
  }, [dragError]);

  const draggingVisitId = dragPreview?.visit.id ?? null;

  function renderBlock(visit: Visit, rowIndex: number) {
    const start = timeToMinutes(visit.start_time);
    const end = timeToMinutes(visit.end_time);
    const style = STATUS_STYLE[visit.status];

    return (
      <div
        key={visit.id}
        role="button"
        tabIndex={0}
        data-visit-id={visit.id}
        data-carer-id={visit.carer_id ?? ""}
        onPointerDown={(e) => startDrag(e, visit, "move", rowIndex)}
        onKeyDown={(e) => {
          if (e.key === "Enter" || e.key === " ") {
            e.preventDefault();
            setEditingVisit(visit);
          }
        }}
        title={`${visit.service_user_name ?? "—"} · ${visit.start_time}–${visit.end_time} · ${visit.status.replaceAll("_", " ")} · drag to reschedule`}
        className={`group absolute top-1/2 flex -translate-y-1/2 cursor-grab items-center overflow-hidden rounded-lg border px-2 text-left text-xs font-medium shadow-sm transition-colors duration-150 hover:brightness-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal active:cursor-grabbing ${style.bg} ${style.text} ${style.border} ${
          visit.status === "cancelled" ? "border-dashed line-through decoration-1" : ""
        } ${carerDrag ? "ring-2 ring-teal/50" : ""}`}
        style={{
          left: start * PX_PER_MINUTE,
          width: Math.max(24, (end - start) * PX_PER_MINUTE),
          height: ROW_HEIGHT - 16,
          touchAction: "none",
        }}
      >
        <span className="truncate">{visit.service_user_name ?? "—"}</span>
        <div
          onPointerDown={(e) => startDrag(e, visit, "resize", rowIndex)}
          className="absolute right-0 top-0 h-full w-2 cursor-ew-resize bg-current opacity-0 group-hover:opacity-30"
          style={{ touchAction: "none" }}
        />
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title="Schedule"
        description={
          view === "client"
            ? "Every client's care for the day. Drag a carer from the Unassigned row onto a client to give them the work — drop on an existing visit to take it over, or on open time to book a new one."
            : "Every carer's visits for the day, laid out on a timeline. Drag a visit to reschedule it, drag its right edge to change its duration, or drag a carer from the panel below onto a visit to hand it to them."
        }
        actions={
          <div className="flex items-center gap-2">
            <button
              type="button"
              onClick={() => setDate((d) => addDays(d, -1))}
              className="flex h-9 w-9 items-center justify-center rounded-full text-inksoft transition-colors duration-150 hover:bg-paper hover:text-ink"
              aria-label="Previous day"
            >
              ‹
            </button>
            <input
              type="date"
              value={date}
              onChange={(e) => setDate(e.target.value)}
              className="rounded-lg border border-line px-3 py-2 text-sm shadow-sm"
            />
            <button
              type="button"
              onClick={() => setDate((d) => addDays(d, 1))}
              className="flex h-9 w-9 items-center justify-center rounded-full text-inksoft transition-colors duration-150 hover:bg-paper hover:text-ink"
              aria-label="Next day"
            >
              ›
            </button>
            {!isToday && (
              <button
                type="button"
                onClick={() => setDate(todayIso())}
                className="rounded-full bg-tealtint px-3 py-1.5 text-xs font-semibold text-teal transition-colors duration-150 hover:bg-tealtint/80"
              >
                Today
              </button>
            )}
            <div className="flex shrink-0 rounded-full border border-line bg-white p-0.5 shadow-sm">
              {(["client", "carer"] as const).map((mode) => (
                <button
                  key={mode}
                  type="button"
                  onClick={() => setView(mode)}
                  aria-pressed={view === mode}
                  className={`whitespace-nowrap rounded-full px-3 py-1.5 text-xs font-semibold transition-colors duration-150 ${
                    view === mode ? "bg-teal text-white" : "text-inksoft hover:text-ink"
                  }`}
                >
                  By {mode}
                </button>
              ))}
            </div>
            <Select
              value={branchId ?? ""}
              onChange={(e) => setBranchId(e.target.value ? Number(e.target.value) : null)}
              className="w-44"
            >
              <option value="">All Branches</option>
              {(branches?.data ?? []).map((branch) => (
                <option key={branch.id} value={branch.id}>
                  {branch.name}
                </option>
              ))}
            </Select>
          </div>
        }
      />

      {dragError && (
        <div className="mb-4">
          <Alert tone="danger">{dragError}</Alert>
        </div>
      )}

      <div className="mb-4 flex flex-wrap items-center gap-4 rounded-2xl border border-line bg-white px-4 py-2.5">
        {LEGEND.map(({ status, label }) => (
          <span key={status} className="flex items-center gap-1.5 text-xs text-inksoft">
            <span className={`h-2.5 w-2.5 rounded-full ${STATUS_STYLE[status].bg} border ${STATUS_STYLE[status].border}`} />
            {label}
          </span>
        ))}
      </div>

      {view === "carer" && carersWithoutVisits.length > 0 && (
        <div className="mb-4 rounded-2xl border border-amber/30 bg-ambertint/30 px-4 py-3">
          <p className="mb-1 text-sm font-medium text-ink">
            {carersWithoutVisits.length} carer{carersWithoutVisits.length === 1 ? "" : "s"} with no visits on {date}
          </p>
          <p className="mb-2 text-xs text-inksoft">
            Drag one onto a visit to hand it to them, or click to book a new visit.
          </p>
          <div className="flex flex-wrap gap-2">
            {carersWithoutVisits.map((carer) => (
              <button
                key={carer.id}
                type="button"
                onPointerDown={(e) => startCarerDrag(e, carer)}
                onClick={() => {
                  // A drag that happened to finish on the chip also fires a click —
                  // don't let it open the modal on top of the assignment just made.
                  if (suppressChipClickRef.current) {
                    suppressChipClickRef.current = false;
                    return;
                  }
                  setAssigningCarer({ user_id: carer.user_id, name: carer.name });
                }}
                style={{ touchAction: "none" }}
                className={`flex cursor-grab items-center gap-1.5 rounded-full border bg-white py-1 pl-3 pr-2 text-xs font-medium text-ink shadow-sm transition-colors duration-150 hover:border-teal hover:text-teal active:cursor-grabbing ${
                  carerDrag?.userId === carer.user_id ? "border-teal opacity-40" : "border-line"
                }`}
              >
                {carer.name}
                <span className="rounded-full bg-tealtint px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-teal">
                  Assign
                </span>
              </button>
            ))}
          </div>
        </div>
      )}

      <Card className="overflow-hidden p-0">
        <div className="overflow-x-auto">
          <div className="relative" style={{ width: NAME_COLUMN_WIDTH + timelineWidth, minWidth: "100%" }}>
            {/* Hour header */}
            <div
              className="sticky top-0 z-20 flex border-b border-line bg-white"
              style={{ height: HEADER_HEIGHT }}
            >
              <div
                className="sticky left-0 z-20 shrink-0 border-r border-line bg-white"
                style={{ width: NAME_COLUMN_WIDTH }}
              />
              <div className="relative" style={{ width: timelineWidth }}>
                {HOURS.map((hour) => (
                  <div
                    key={hour}
                    className="absolute top-0 flex h-full items-center border-l border-line px-1.5 text-xs text-inksoft"
                    style={{ left: hour * 60 * PX_PER_MINUTE, width: 60 }}
                  >
                    {String(hour).padStart(2, "0")}:00
                  </div>
                ))}
              </div>
            </div>

            {view === "carer" && (<>
            {/* Unassigned row — always shown as a stable drop target for un-assigning a visit */}
            <div className="flex border-b border-line bg-ambertint/20">
              <div
                className="sticky left-0 z-10 flex shrink-0 items-center border-r border-line bg-ambertint/40 px-3 text-sm font-medium text-ink"
                style={{ width: NAME_COLUMN_WIDTH, height: ROW_HEIGHT }}
              >
                Unassigned
              </div>
              <div className="relative" style={{ width: timelineWidth, height: ROW_HEIGHT }}>
                {HOURS.map((hour) => (
                  <div
                    key={hour}
                    className="absolute top-0 h-full border-l border-line"
                    style={{ left: hour * 60 * PX_PER_MINUTE }}
                  />
                ))}
                {unassignedVisits
                  .filter((v) => v.id !== draggingVisitId)
                  .map((v) => renderBlock(v, 0))}
              </div>
            </div>

            {/* Carer rows */}
            {carers.map((carer, index) => (
              <div key={carer.id} className="flex border-b border-line last:border-b-0">
                <div
                  className="sticky left-0 z-10 flex shrink-0 items-center border-r border-line bg-white px-3 text-sm font-medium text-ink"
                  style={{ width: NAME_COLUMN_WIDTH, height: ROW_HEIGHT }}
                >
                  <span className="truncate">{carer.name}</span>
                </div>
                <div className="relative" style={{ width: timelineWidth, height: ROW_HEIGHT }}>
                  {HOURS.map((hour) => (
                    <div
                      key={hour}
                      className="absolute top-0 h-full border-l border-line"
                      style={{ left: hour * 60 * PX_PER_MINUTE }}
                    />
                  ))}
                  {isToday && (
                    <div
                      className="absolute top-0 z-10 h-full w-px bg-coral"
                      style={{ left: nowMinutes * PX_PER_MINUTE }}
                    />
                  )}
                  {(visitsByCarer.get(carer.user_id) ?? [])
                    .filter((v) => v.id !== draggingVisitId)
                    .map((v) => renderBlock(v, index + 1))}
                </div>
              </div>
            ))}

            {carers.length === 0 && (
              <div className="flex items-center justify-center py-12 text-sm text-inksoft">
                No carers to show for this branch.
              </div>
            )}
            </>)}

            {view === "client" && (<>
            {/* Client search — filters the rows below without touching who is in
                the Unassigned pool or what's already scheduled. */}
            <div className="flex items-center gap-3 border-b border-line bg-white px-3 py-2.5">
              <div style={{ width: NAME_COLUMN_WIDTH - 12 }}>
                <FormField label="Search clients" htmlFor="schedule-client-search">
                  <Input
                    id="schedule-client-search"
                    type="search"
                    placeholder="Name"
                    value={clientSearch}
                    onChange={(e) => setClientSearch(e.target.value)}
                  />
                </FormField>
              </div>
              {clientSearch && (
                <span className="mt-5 text-xs text-inksoft">
                  {visibleClients.length} of {clients.length} client{clients.length === 1 ? "" : "s"}
                </span>
              )}
            </div>

            {/* The carer pool. Every carer stays here all day — a carer covers
                several clients, so they have to remain draggable after the first. */}
            <div className="flex border-b-2 border-amber/40 bg-ambertint/20">
              <div
                className="sticky left-0 z-10 flex shrink-0 items-start border-r border-line bg-ambertint/40 px-3 py-3 text-sm font-medium text-ink"
                style={{ width: NAME_COLUMN_WIDTH }}
              >
                Unassigned
              </div>
              <div className="flex flex-wrap items-start gap-2 p-3" style={{ width: timelineWidth }}>
                {carerPool.map((carer) => {
                  const load = loadByCarer.get(carer.user_id) ?? 0;
                  return (
                    <button
                      key={carer.id}
                      type="button"
                      onPointerDown={(e) => startCarerDrag(e, carer)}
                      onClick={() => {
                        if (suppressChipClickRef.current) {
                          suppressChipClickRef.current = false;
                          return;
                        }
                        setAssigningCarer({ user_id: carer.user_id, name: carer.name });
                      }}
                      style={{ touchAction: "none" }}
                      title={`${carer.name} — ${load} visit${load === 1 ? "" : "s"} today. Drag onto a client to give them work, or click to book a visit.`}
                      className={`flex cursor-grab items-center gap-1.5 rounded-full border bg-white py-1 pl-3 pr-2 text-xs font-medium text-ink shadow-sm transition-colors duration-150 hover:border-teal hover:text-teal active:cursor-grabbing ${
                        carerDrag?.userId === carer.user_id ? "border-teal opacity-40" : "border-line"
                      }`}
                    >
                      {carer.name}
                      <span
                        className={`rounded-full px-1.5 py-0.5 text-[10px] font-semibold tabular-nums ${
                          load === 0 ? "bg-paper text-inksoft" : "bg-tealtint text-teal"
                        }`}
                      >
                        {load}
                      </span>
                    </button>
                  );
                })}
                {carerPool.length === 0 && (
                  <span className="text-xs text-inksoft">No carers available for this branch.</span>
                )}
              </div>
            </div>

            {/* Client rows */}
            {visibleClients.map((client, index) => {
              const clientVisits = visitsByClient.get(client.id) ?? [];
              const uncovered = clientVisits.length === 0;
              return (
                <div key={client.id} className="flex border-b border-line last:border-b-0">
                  <div
                    className={`sticky left-0 z-10 flex shrink-0 items-center justify-between gap-2 border-r border-line px-3 text-sm font-medium text-ink ${
                      uncovered ? "bg-coraltint/30" : "bg-white"
                    }`}
                    style={{ width: NAME_COLUMN_WIDTH, height: ROW_HEIGHT }}
                  >
                    <span className="truncate">
                      {client.first_name} {client.last_name}
                    </span>
                    {uncovered && (
                      <span className="shrink-0 rounded-full bg-coraltint px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-coral">
                        No care
                      </span>
                    )}
                  </div>
                  <div
                    className="relative"
                    data-service-user-id={client.id}
                    style={{ width: timelineWidth, height: ROW_HEIGHT }}
                  >
                    {HOURS.map((hour) => (
                      <div
                        key={hour}
                        className="absolute top-0 h-full border-l border-line"
                        style={{ left: hour * 60 * PX_PER_MINUTE }}
                      />
                    ))}
                    {isToday && (
                      <div
                        className="absolute top-0 z-10 h-full w-px bg-coral"
                        style={{ left: nowMinutes * PX_PER_MINUTE }}
                      />
                    )}
                    {clientVisits
                      .filter((v) => v.id !== draggingVisitId)
                      .map((v) => renderBlock(v, index + 1))}
                  </div>
                </div>
              );
            })}

            {clients.length === 0 && (
              <div className="flex items-center justify-center py-12 text-sm text-inksoft">
                No clients to show for this branch.
              </div>
            )}
            {clients.length > 0 && visibleClients.length === 0 && (
              <div className="flex items-center justify-center py-12 text-sm text-inksoft">
                No clients match &ldquo;{clientSearch}&rdquo;.
              </div>
            )}
            </>)}

            {/* Floating drag preview — rendered above everything so it can move across rows freely */}
            {dragPreview &&
              (() => {
                const d = dragPreview;
                const targetCarerId = rowTargets[d.currentRowIndex] ?? null;
                const style = STATUS_STYLE[d.visit.status];
                const conflict = d.moved && d.mode === "move" && hasConflict(targetCarerId, d.currentStart, d.currentEnd, d.visit.id);

                return (
                  <div
                    className={`pointer-events-none absolute z-30 flex items-center overflow-hidden rounded-lg border-2 px-2 text-left text-xs font-semibold shadow-lg ${
                      conflict ? "border-coral bg-coraltint text-coral" : `${style.bg} ${style.text} ${style.border}`
                    }`}
                    style={{
                      top: HEADER_HEIGHT + d.currentRowIndex * ROW_HEIGHT + 8,
                      left: NAME_COLUMN_WIDTH + d.currentStart * PX_PER_MINUTE,
                      width: Math.max(24, (d.currentEnd - d.currentStart) * PX_PER_MINUTE),
                      height: ROW_HEIGHT - 16,
                    }}
                  >
                    <span className="truncate">
                      {d.visit.service_user_name ?? "—"} · {minutesToTimeString(d.currentStart)}–
                      {minutesToTimeString(d.currentEnd)}
                      {conflict ? " ⚠" : ""}
                    </span>
                  </div>
                );
              })()}
          </div>
        </div>
      </Card>

      <EditVisitModal visit={editingVisit} onClose={() => setEditingVisit(null)} />
      {carerDrag?.moved && (
        <div
          className="pointer-events-none fixed z-[100] flex items-center gap-1.5 rounded-full border border-teal bg-white py-1 pl-3 pr-2 text-xs font-medium text-ink shadow-lg"
          style={{ left: carerDrag.x + 12, top: carerDrag.y + 12 }}
        >
          {carerDrag.name}
          <span className="rounded-full bg-tealtint px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-teal">
            Drop on a visit
          </span>
        </div>
      )}

      <AssignTaskModal carer={assigningCarer} date={date} onClose={() => setAssigningCarer(null)} />
      <AssignTaskModal
        carer={assignDraft?.carer ?? null}
        date={date}
        serviceUserId={assignDraft?.serviceUserId}
        startTime={assignDraft?.startTime}
        onClose={() => setAssignDraft(null)}
      />
    </div>
  );
}
