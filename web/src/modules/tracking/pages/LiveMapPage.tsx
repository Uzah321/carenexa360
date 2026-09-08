import { GoogleMap, InfoWindow, Marker, Polyline, useJsApiLoader } from "@react-google-maps/api";
import { useEffect, useMemo, useRef, useState } from "react";
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

// A colored circle with initials, same look as the old Leaflet DivIcon —
// Google's Marker takes an icon (image URL/Symbol), not arbitrary HTML, so
// this builds the same bubble as an inline SVG data URI instead. Cached per
// (color, label, focused) for the same reason the old DivIcon cache existed:
// a 20s data refresh must hand Marker back an icon it already has, or it
// treats it as changed and can misbehave on re-render.
const iconCache = new Map<string, google.maps.Icon>();

function carerIcon(color: string, label: string, focused: boolean): google.maps.Icon {
  const key = `${color}|${label}|${focused}`;
  const cached = iconCache.get(key);
  if (cached) return cached;

  const size = focused ? 40 : 32;
  const fontSize = focused ? 13 : 12;
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 ${size} ${size}">
    <circle cx="${size / 2}" cy="${size / 2}" r="${size / 2 - 1}" fill="${color}" stroke="white" stroke-width="2" />
    <text x="50%" y="50%" text-anchor="middle" dy=".35em" font-family="sans-serif" font-size="${fontSize}" font-weight="700" fill="white">${label}</text>
  </svg>`;

  const icon: google.maps.Icon = {
    url: `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(svg)}`,
    scaledSize: new google.maps.Size(size, size),
    anchor: new google.maps.Point(size / 2, size / 2),
  };
  iconCache.set(key, icon);
  return icon;
}

function formatTime(iso?: string) {
  return iso ? new Date(iso).toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }) : "";
}

/**
 * Where to draw a carer's marker: their latest live ping, or — while
 * they're checked in but haven't posted one yet (just checked in, GPS still
 * acquiring a fix) — where they checked in from, so they're never simply
 * missing from the map. Returns null only when neither exists.
 */
function carerPosition(carer: LiveMapCarer): { lat: number; lng: number; isLive: boolean } | null {
  const lastPoint = carer.trail.at(-1);
  if (lastPoint) return { lat: lastPoint.latitude, lng: lastPoint.longitude, isLive: true };
  if (carer.check_in_lat != null && carer.check_in_lng != null) {
    return { lat: carer.check_in_lat, lng: carer.check_in_lng, isLive: false };
  }
  return null;
}

const DEFAULT_CENTER = { lat: -17.8252, lng: 31.0335 };
const MAP_CONTAINER_STYLE = { height: "600px", width: "100%" };
const GOOGLE_MAPS_API_KEY = import.meta.env.VITE_GOOGLE_MAPS_API_KEY as string | undefined;

export function LiveMapPage() {
  const { user } = useAuth();
  const [branchId, setBranchId] = useState<number | null>(null);
  const { data: branches } = useBranches(user?.tenant_id ?? 0);
  const { data } = useLiveMap(branchId);
  const [searchParams] = useSearchParams();
  const [map, setMap] = useState<google.maps.Map | null>(null);
  const [openInfoWindowId, setOpenInfoWindowId] = useState<number | null>(null);
  const defaultCenterAppliedRef = useRef(false);

  const { isLoaded, loadError } = useJsApiLoader({
    id: "google-map-script",
    googleMapsApiKey: GOOGLE_MAPS_API_KEY ?? "",
  });

  const focusedCarerId = useMemo(() => {
    const raw = searchParams.get("carer");
    return raw ? Number(raw) : null;
  }, [searchParams]);

  // Every checked-in carer belongs on the map, even in the short window
  // before their first ping arrives (see carerPosition) — only a carer with
  // neither a live trail nor a check-in position drops off entirely.
  const carersOnMap: LiveMapCarer[] = (data?.carers ?? []).filter(
    (carer) => carer.trail.length > 0 || (carer.is_checked_in && carerPosition(carer) != null),
  );
  const focusedCarer = focusedCarerId != null ? carersOnMap.find((c) => c.user_id === focusedCarerId) : undefined;
  const focusedCarerUnavailable = focusedCarerId != null && Boolean(data) && !focusedCarer;

  // Whoever most recently checked in, among those we can currently place on
  // the map — the default the map centers on when nobody specific was asked
  // for.
  const mostRecentlyCheckedInCarer = useMemo(() => {
    if (!data) return undefined;
    const byRecency = [...data.checked_in.items].sort(
      (a, b) => new Date(b.checked_in_at ?? 0).getTime() - new Date(a.checked_in_at ?? 0).getTime(),
    );
    for (const person of byRecency) {
      const match = carersOnMap.find((c) => c.user_id === person.user_id);
      if (match) return match;
    }
    return undefined;
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [data]);

  // Flies to a specific carer's current position once their live data
  // arrives, and opens their info window — this is what makes "view their
  // location" from elsewhere in the app land somewhere useful.
  useEffect(() => {
    if (!map || !focusedCarer) return;
    const position = carerPosition(focusedCarer);
    if (!position) return;
    map.panTo(position);
    map.setZoom(16);
    setOpenInfoWindowId(focusedCarer.user_id);
  }, [map, focusedCarer]);

  // Sets the initial view once live data first arrives, when no specific
  // carer was requested via ?carer= — centers on whoever most recently
  // checked in, since they're the most likely reason someone opened this
  // page without already knowing who to look for. Unlike the focus effect
  // above, this only pans (keeps the current zoom) and only runs once.
  useEffect(() => {
    if (!map || defaultCenterAppliedRef.current || focusedCarerId != null || !mostRecentlyCheckedInCarer) return;
    const position = carerPosition(mostRecentlyCheckedInCarer);
    if (!position) return;
    map.setCenter(position);
    defaultCenterAppliedRef.current = true;
  }, [map, focusedCarerId, mostRecentlyCheckedInCarer]);

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

        {!GOOGLE_MAPS_API_KEY || loadError ? (
          <div
            style={MAP_CONTAINER_STYLE}
            className="flex items-center justify-center bg-paper text-sm text-inksoft"
          >
            {loadError
              ? "Couldn't load Google Maps — check the API key and its restrictions."
              : "Google Maps isn't configured (VITE_GOOGLE_MAPS_API_KEY is missing)."}
          </div>
        ) : !isLoaded ? (
          <div style={MAP_CONTAINER_STYLE} className="flex items-center justify-center bg-paper text-sm text-inksoft">
            Loading map…
          </div>
        ) : (
          <GoogleMap
            mapContainerStyle={MAP_CONTAINER_STYLE}
            center={DEFAULT_CENTER}
            zoom={13}
            onLoad={setMap}
            onUnmount={() => setMap(null)}
          >
            {carersOnMap.map((carer, index) => {
              const color = colorForCarer(index);
              const position = carerPosition(carer);
              const focused = carer.user_id === focusedCarerId;
              const path = (carer.route.length > 0 ? carer.route : carer.trail).map((p) => ({
                lat: p.latitude,
                lng: p.longitude,
              }));

              return (
                <div key={carer.user_id}>
                  {path.length > 1 && <Polyline path={path} options={{ strokeColor: color, strokeWeight: 4 }} />}
                  {position && (
                    <Marker
                      position={position}
                      icon={carerIcon(color, initials(carer.name), focused)}
                      opacity={position.isLive ? 1 : 0.6}
                      onClick={() => setOpenInfoWindowId(carer.user_id)}
                      zIndex={focused ? 1000 : undefined}
                    >
                      {openInfoWindowId === carer.user_id && (
                        <InfoWindow onCloseClick={() => setOpenInfoWindowId(null)}>
                          <div>
                            <strong>{carer.name}</strong>
                            <br />
                            {position.isLive
                              ? `Last seen ${formatTime(carer.last_ping_at ?? undefined)}`
                              : "Checked in — waiting for a location signal"}
                          </div>
                        </InfoWindow>
                      )}
                    </Marker>
                  )}
                </div>
              );
            })}
          </GoogleMap>
        )}
      </Card>

      <OpenShiftsCard />
    </div>
  );
}
