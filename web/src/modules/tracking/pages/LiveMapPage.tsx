import { DivIcon, type Marker as LeafletMarker } from "leaflet";
import { Fragment, useEffect, useMemo, useRef, useState } from "react";
import { MapContainer, Marker, Polyline, Popup, TileLayer, useMap } from "react-leaflet";
import { useSearchParams } from "react-router-dom";
import { Alert, Card, PageHeader, Select } from "../../../design-system";
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

// Cached per (color, label, focused) so a 20s data refresh doesn't hand
// react-leaflet a brand-new DivIcon identity for markers that haven't
// actually changed — that was making it call marker.setIcon() on every poll,
// which re-binds the marker's popup and silently closes one just opened.
const iconCache = new Map<string, DivIcon>();

function carerIcon(color: string, label: string, focused: boolean) {
  const key = `${color}|${label}|${focused}`;
  const cached = iconCache.get(key);
  if (cached) return cached;

  const size = focused ? 40 : 32;
  const shadow = focused
    ? `0 0 0 4px ${color}55, 0 2px 8px rgba(0,0,0,0.4)`
    : "0 2px 6px rgba(0,0,0,0.35)";
  const icon = new DivIcon({
    html: `<div style="background:${color};color:white;border-radius:9999px;width:${size}px;height:${size}px;display:flex;align-items:center;justify-content:center;font-size:${
      focused ? 13 : 12
    }px;font-weight:700;border:2px solid white;box-shadow:${shadow};">${label}</div>`,
    className: "",
    iconSize: [size, size],
    iconAnchor: [size / 2, size / 2],
  });
  iconCache.set(key, icon);
  return icon;
}

function formatTime(iso?: string) {
  return iso ? new Date(iso).toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }) : "";
}

const DEFAULT_CENTER: [number, number] = [-17.8252, 31.0335];

/** Flies the map to a specific carer's last-known point once their live data
 * arrives, and opens their marker popup — this is what makes "view their
 * location" from elsewhere in the app land somewhere useful. */
function FocusCarer({
  carer,
  markerRefs,
}: {
  carer: LiveMapCarer | undefined;
  markerRefs: React.MutableRefObject<Map<number, LeafletMarker>>;
}) {
  const map = useMap();

  useEffect(() => {
    if (!carer) return;
    const lastPoint = carer.trail.at(-1);
    if (!lastPoint) return;
    map.flyTo([lastPoint.latitude, lastPoint.longitude], 16, { duration: 1 });
    markerRefs.current.get(carer.user_id)?.openPopup();
  }, [carer, map, markerRefs]);

  return null;
}

/**
 * Sets the initial view once live data first arrives, when no specific
 * carer was requested via ?carer= — centers on whoever most recently
 * checked in, since they're the most likely reason someone opened this
 * page without already knowing who to look for. Unlike FocusCarer this
 * only pans (keeps the current zoom, no popup) — landing here "cold"
 * should still show the surrounding area, not zoom in tight on one marker.
 */
function DefaultCenter({ carer }: { carer: LiveMapCarer | undefined }) {
  const map = useMap();
  const appliedRef = useRef(false);

  useEffect(() => {
    if (appliedRef.current || !carer) return;
    const lastPoint = carer.trail.at(-1);
    if (!lastPoint) return;
    map.setView([lastPoint.latitude, lastPoint.longitude], map.getZoom());
    appliedRef.current = true;
  }, [carer, map]);

  return null;
}

export function LiveMapPage() {
  const { user } = useAuth();
  const [branchId, setBranchId] = useState<number | null>(null);
  const { data: branches } = useBranches(user?.tenant_id ?? 0);
  const { data } = useLiveMap(branchId);
  const [searchParams] = useSearchParams();
  const markerRefs = useRef(new Map<number, LeafletMarker>());

  const focusedCarerId = useMemo(() => {
    const raw = searchParams.get("carer");
    return raw ? Number(raw) : null;
  }, [searchParams]);

  const carersWithTrail: LiveMapCarer[] = (data?.carers ?? []).filter((carer) => carer.trail.length > 0);
  const focusedCarer = focusedCarerId != null ? carersWithTrail.find((c) => c.user_id === focusedCarerId) : undefined;
  const focusedCarerUnavailable = focusedCarerId != null && Boolean(data) && !focusedCarer;

  // Whoever most recently checked in, among those we actually have a
  // location for — the default the map centers on when nobody specific was
  // asked for.
  const mostRecentlyCheckedInCarer = useMemo(() => {
    if (!data) return undefined;
    const byRecency = [...data.checked_in.items].sort(
      (a, b) => new Date(b.checked_in_at ?? 0).getTime() - new Date(a.checked_in_at ?? 0).getTime(),
    );
    for (const person of byRecency) {
      const match = carersWithTrail.find((c) => c.user_id === person.user_id);
      if (match) return match;
    }
    return undefined;
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [data]);

  return (
    <div>
      <PageHeader
        title="Live Map"
        description="Where your carers are right now, and the route they've taken between visits today."
      />

      {focusedCarerUnavailable && (
        <div className="mb-4">
          <Alert tone="warning">
            That carer isn't currently showing a live location — they may not be checked in right now.
          </Alert>
        </div>
      )}

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
              <li
                key={person.user_id}
                className={`flex items-center justify-between rounded-lg px-1 text-xs ${
                  person.user_id === focusedCarerId ? "bg-limetint" : ""
                }`}
              >
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

        <MapContainer center={DEFAULT_CENTER} zoom={13} style={{ height: "600px", width: "100%" }}>
          <TileLayer
            attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
          />
          {carersWithTrail.map((carer, index) => {
            const color = colorForCarer(index);
            const lastPoint = carer.trail.at(-1);
            const focused = carer.user_id === focusedCarerId;
            return (
              <Fragment key={carer.user_id}>
                <Polyline positions={carer.trail.map((p) => [p.latitude, p.longitude])} color={color} weight={4} />
                {lastPoint && (
                  <Marker
                    ref={(instance) => {
                      if (instance) markerRefs.current.set(carer.user_id, instance);
                      else markerRefs.current.delete(carer.user_id);
                    }}
                    position={[lastPoint.latitude, lastPoint.longitude]}
                    icon={carerIcon(color, initials(carer.name), focused)}
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
          {focusedCarerId != null ? (
            <FocusCarer carer={focusedCarer} markerRefs={markerRefs} />
          ) : (
            <DefaultCenter carer={mostRecentlyCheckedInCarer} />
          )}
        </MapContainer>
      </Card>

      <OpenShiftsCard />
    </div>
  );
}
