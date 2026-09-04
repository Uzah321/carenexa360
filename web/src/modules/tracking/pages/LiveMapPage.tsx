import { DivIcon } from "leaflet";
import { Fragment, useMemo, useState } from "react";
import { MapContainer, Marker, Polyline, Popup, TileLayer } from "react-leaflet";
import { Card, PageHeader, Select } from "../../../design-system";
import { useAuth } from "../../../lib/auth-context";
import { useBranches } from "../../organization/api";
import { useLiveMap, type LiveMapCarer } from "../api";
import { OpenShiftsCard } from "../components/OpenShiftsCard";

const CARER_COLORS = ["#00b4a3", "#398fde", "#f66d62", "#eea83a", "#a162de", "#80bc4e"];

function colorForCarer(index: number) {
  return CARER_COLORS[index % CARER_COLORS.length];
}

function initials(name: string) {
  return name
    .split(" ")
    .map((part) => part[0])
    .slice(0, 2)
    .join("")
    .toUpperCase();
}

function carerIcon(color: string, label: string) {
  return new DivIcon({
    html: `<div style="background:${color};color:white;border-radius:9999px;width:32px;height:32px;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;border:2px solid white;box-shadow:0 2px 6px rgba(0,0,0,0.35);">${label}</div>`,
    className: "",
    iconSize: [32, 32],
    iconAnchor: [16, 16],
  });
}

function formatTime(iso?: string) {
  return iso ? new Date(iso).toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }) : "";
}

const DEFAULT_CENTER: [number, number] = [-17.8252, 31.0335];

export function LiveMapPage() {
  const { user } = useAuth();
  const [branchId, setBranchId] = useState<number | null>(null);
  const { data: branches } = useBranches(user?.tenant_id ?? 0);
  const { data } = useLiveMap(branchId);

  const carersWithTrail: LiveMapCarer[] = (data?.carers ?? []).filter((carer) => carer.trail.length > 0);

  const center = useMemo<[number, number]>(() => {
    const lastPoint = carersWithTrail[0]?.trail.at(-1);
    return lastPoint ? [lastPoint.latitude, lastPoint.longitude] : DEFAULT_CENTER;
    // Only computed once, from whatever the first live payload looks like —
    // MapContainer's `center` prop only sets the initial view, so this
    // deliberately doesn't recompute on every 20s refetch (that would jerk
    // the map around under the user while they're looking at it).
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  return (
    <div>
      <PageHeader
        title="Live Map"
        description="Where your carers are right now, and the route they've taken between visits today."
      />

      <Card className="relative overflow-hidden p-0">
        <div className="absolute left-3 top-3 z-[1000] w-56">
          <Select
            value={branchId ?? ""}
            onChange={(e) => setBranchId(e.target.value ? Number(e.target.value) : null)}
            className="bg-white shadow-md"
          >
            <option value="">All Branches</option>
            {(branches?.data ?? []).map((branch) => (
              <option key={branch.id} value={branch.id}>
                {branch.name}
              </option>
            ))}
          </Select>
        </div>

        <div className="absolute right-3 top-3 z-[1000] w-72 rounded-2xl border border-line bg-white p-3 shadow-lg">
          <div className="mb-1 flex items-center justify-between text-sm font-semibold text-ink">
            <span>Checked in</span>
            <span className="rounded-full bg-limetint px-2 py-0.5 text-xs text-lime">
              {data?.checked_in.count ?? 0}
            </span>
          </div>
          <ul className="mb-3 space-y-1">
            {(data?.checked_in.items ?? []).map((person) => (
              <li key={person.user_id} className="flex items-center justify-between text-xs">
                <span className="font-medium text-ink">{person.name}</span>
                <span className="text-inksoft">{formatTime(person.checked_in_at)}</span>
              </li>
            ))}
            {(data?.checked_in.items ?? []).length === 0 && (
              <li className="text-xs text-inksoft">No one currently checked in.</li>
            )}
          </ul>

          <div className="mb-1 flex items-center justify-between text-sm font-semibold text-ink">
            <span>Completed today</span>
            <span className="rounded-full bg-paper px-2 py-0.5 text-xs text-inksoft">
              {data?.checked_out.count ?? 0}
            </span>
          </div>
          <ul className="space-y-1">
            {(data?.checked_out.items ?? []).map((person) => (
              <li key={person.user_id} className="flex items-center justify-between text-xs">
                <span className="font-medium text-ink">{person.name}</span>
                <span className="text-inksoft">{formatTime(person.checked_out_at)}</span>
              </li>
            ))}
            {(data?.checked_out.items ?? []).length === 0 && (
              <li className="text-xs text-inksoft">None yet.</li>
            )}
          </ul>
        </div>

        <MapContainer center={center} zoom={13} style={{ height: "600px", width: "100%" }}>
          <TileLayer
            attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
          />
          {carersWithTrail.map((carer, index) => {
            const color = colorForCarer(index);
            const lastPoint = carer.trail.at(-1);
            return (
              <Fragment key={carer.user_id}>
                <Polyline positions={carer.trail.map((p) => [p.latitude, p.longitude])} color={color} weight={4} />
                {lastPoint && (
                  <Marker
                    position={[lastPoint.latitude, lastPoint.longitude]}
                    icon={carerIcon(color, initials(carer.name))}
                  >
                    <Popup>
                      <strong>{carer.name}</strong>
                      <br />
                      Last seen {formatTime(carer.last_ping_at ?? undefined)}
                    </Popup>
                  </Marker>
                )}
              </Fragment>
            );
          })}
        </MapContainer>
      </Card>

      <OpenShiftsCard />
    </div>
  );
}
